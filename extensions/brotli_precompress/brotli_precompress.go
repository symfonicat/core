package brotliprecompress

import (
	"io"
	"mime"
	"net/http"
	"os"
	"path"
	"path/filepath"
	"strconv"
	"strings"
	"sync"

	"github.com/andybalholm/brotli"
	"github.com/caddyserver/caddy/v2"
	"github.com/caddyserver/caddy/v2/caddyconfig/httpcaddyfile"
	"github.com/caddyserver/caddy/v2/modules/caddyhttp"
)

func init() {
	caddy.RegisterModule(BrotliPrecompress{})
	httpcaddyfile.RegisterHandlerDirective("brotli_precompress", parseCaddyfile)
}

type BrotliPrecompress struct {
	Root       string   `json:"root,omitempty"`
	DiskDir    string   `json:"disk_dir,omitempty"`
	Quality    int      `json:"quality,omitempty"`
	MinSize    int64    `json:"min_size,omitempty"`
	Extensions []string `json:"extensions,omitempty"`

	precompressMu      sync.Mutex
	precompressed      bool
	precompressionPath string
}

func (BrotliPrecompress) CaddyModule() caddy.ModuleInfo {
	return caddy.ModuleInfo{
		ID:  "http.handlers.brotli_precompress",
		New: func() caddy.Module { return new(BrotliPrecompress) },
	}
}

func (b *BrotliPrecompress) Provision(_ caddy.Context) error {
	if b.Root == "" {
		b.Root = "/symfonicat/public"
	}

	if b.DiskDir == "" {
		b.DiskDir = "build"
	}

	if b.Quality == 0 {
		b.Quality = 6
	}

	if b.MinSize == 0 {
		b.MinSize = 1024
	}

	if len(b.Extensions) == 0 {
		b.Extensions = []string{
			".js",
			".css",
			".svg",
			".json",
			".html",
			".xml",
			".wasm",
		}
	}

	return b.ensurePrecompressed()
}

func (b *BrotliPrecompress) ServeHTTP(w http.ResponseWriter, r *http.Request, next caddyhttp.Handler) error {
	if !strings.Contains(r.Header.Get("Accept-Encoding"), "br") {
		return next.ServeHTTP(w, r)
	}

	if b.Root == "" {
		return next.ServeHTTP(w, r)
	}

	if err := b.ensurePrecompressed(); err != nil {
		return next.ServeHTTP(w, r)
	}

	cleanPath := path.Clean(r.URL.Path)
	sourcePath := filepath.Join(b.Root, filepath.FromSlash(strings.TrimPrefix(cleanPath, "/")))
	brotliPath := sourcePath + ".br"

	if b.shouldCompress(sourcePath, brotliPath) {
		if err := b.compressFile(sourcePath, brotliPath); err != nil {
			return next.ServeHTTP(w, r)
		}
	}

	w.Header().Set("X-Symfonicat-Brotli", "1")
	if err := b.serveBrotliFile(w, r, sourcePath, brotliPath); err == nil {
		return nil
	}

	return next.ServeHTTP(w, r)
}

func (b *BrotliPrecompress) ensurePrecompressed() error {
	buildDir := filepath.Join(b.Root, b.DiskDir)

	b.precompressMu.Lock()
	defer b.precompressMu.Unlock()

	if b.precompressed && b.precompressionPath == buildDir {
		return nil
	}

	if _, err := os.Stat(buildDir); err != nil {
		if os.IsNotExist(err) {
			return nil
		}

		return err
	}

	if err := b.precompressTree(buildDir); err != nil {
		return err
	}

	b.precompressed = true
	b.precompressionPath = buildDir

	return nil
}

func (b BrotliPrecompress) serveBrotliFile(w http.ResponseWriter, r *http.Request, sourcePath string, brotliPath string) error {
	compressed, err := os.Open(brotliPath)
	if err != nil {
		return err
	}
	defer compressed.Close()

	stat, err := compressed.Stat()
	if err != nil {
		return err
	}

	if contentType := mime.TypeByExtension(filepath.Ext(sourcePath)); contentType != "" {
		w.Header().Set("Content-Type", contentType)
	}
	w.Header().Set("Content-Encoding", "br")
	w.Header().Set("Content-Length", strconv.FormatInt(stat.Size(), 10))
	w.Header().Add("Vary", "Accept-Encoding")

	if r.Method == http.MethodHead {
		return nil
	}

	if _, err := io.Copy(w, compressed); err != nil {
		return err
	}

	return nil
}

func (b BrotliPrecompress) precompressTree(root string) error {
	return filepath.WalkDir(root, func(filePath string, d os.DirEntry, err error) error {
		if err != nil {
			return err
		}

		if d.IsDir() {
			return nil
		}

		if strings.HasSuffix(filePath, ".br") {
			return nil
		}

		if !b.extensionAllowed(filepath.Ext(filePath)) {
			return nil
		}

		return b.precompressFileIfNeeded(filePath)
	})
}

func (b BrotliPrecompress) precompressFileIfNeeded(sourcePath string) error {
	sourceInfo, err := os.Stat(sourcePath)
	if err != nil || sourceInfo.IsDir() {
		return err
	}

	if sourceInfo.Size() < b.MinSize {
		return nil
	}

	brotliPath := sourcePath + ".br"
	brotliInfo, err := os.Stat(brotliPath)
	if err == nil && !brotliInfo.ModTime().Before(sourceInfo.ModTime()) {
		return nil
	}

	return b.compressFile(sourcePath, brotliPath)
}

func (b BrotliPrecompress) shouldCompress(sourcePath string, brotliPath string) bool {
	if strings.HasSuffix(sourcePath, ".br") {
		return false
	}

	ext := filepath.Ext(sourcePath)

	if !b.extensionAllowed(ext) {
		return false
	}

	sourceInfo, err := os.Stat(sourcePath)

	if err != nil || sourceInfo.IsDir() {
		return false
	}

	if sourceInfo.Size() < b.MinSize {
		return false
	}

	brotliInfo, err := os.Stat(brotliPath)

	if err != nil {
		return true
	}

	return brotliInfo.ModTime().Before(sourceInfo.ModTime())
}

func (b BrotliPrecompress) extensionAllowed(ext string) bool {
	for _, allowed := range b.Extensions {
		if ext == allowed {
			return true
		}
	}

	return false
}

func (b BrotliPrecompress) compressFile(sourcePath string, brotliPath string) error {
	source, err := os.Open(sourcePath)

	if err != nil {
		return err
	}

	defer source.Close()

	tempPath := brotliPath + ".tmp"

	target, err := os.Create(tempPath)

	if err != nil {
		return err
	}

	writer := brotli.NewWriterLevel(target, b.Quality)

	_, copyErr := io.Copy(writer, source)
	closeWriterErr := writer.Close()
	closeTargetErr := target.Close()

	if copyErr != nil {
		_ = os.Remove(tempPath)
		return copyErr
	}

	if closeWriterErr != nil {
		_ = os.Remove(tempPath)
		return closeWriterErr
	}

	if closeTargetErr != nil {
		_ = os.Remove(tempPath)
		return closeTargetErr
	}

	return os.Rename(tempPath, brotliPath)
}

func parseCaddyfile(h httpcaddyfile.Helper) (caddyhttp.MiddlewareHandler, error) {
	module := new(BrotliPrecompress)

	for h.Next() {
		for h.NextBlock(0) {
			switch h.Val() {
			case "root":
				args := h.RemainingArgs()
				if len(args) != 1 {
					return nil, h.ArgErr()
				}
				module.Root = args[0]

			case "disk_dir":
				args := h.RemainingArgs()
				if len(args) != 1 {
					return nil, h.ArgErr()
				}
				module.DiskDir = args[0]

			case "quality":
				args := h.RemainingArgs()
				if len(args) != 1 {
					return nil, h.ArgErr()
				}
				quality, err := strconv.Atoi(args[0])
				if err != nil {
					return nil, h.Errf("invalid quality %q: %v", args[0], err)
				}
				module.Quality = quality

			case "min_size":
				args := h.RemainingArgs()
				if len(args) != 1 {
					return nil, h.ArgErr()
				}
				minSize, err := strconv.ParseInt(args[0], 10, 64)
				if err != nil {
					return nil, h.Errf("invalid min_size %q: %v", args[0], err)
				}
				module.MinSize = minSize

			case "extensions":
				module.Extensions = h.RemainingArgs()

			default:
				return nil, h.Errf("unknown directive: %s", h.Val())
			}
		}
	}

	return module, nil
}

var (
	_ caddy.Provisioner           = (*BrotliPrecompress)(nil)
	_ caddyhttp.MiddlewareHandler = (*BrotliPrecompress)(nil)
)
