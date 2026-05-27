package symfonicat

// #include <Zend/zend_types.h>
import "C"

import (
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"crypto/subtle"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"errors"
	"io"
	"strings"
	"unsafe"

	"github.com/andybalholm/brotli"
	"github.com/dunglas/frankenphp"
)

const tokenSeparator = "."

// export_php:function symfonicat_json_decode(string $payload): array
func symfonicat_json_decode(payload *C.zend_string) unsafe.Pointer {
	decoded, err := decodeJSONPayload(zendStringBytes(payload))
	if err != nil {
		return emptyPHPArray()
	}

	result, ok := decoded.(map[string]any)
	if !ok {
		return emptyPHPArray()
	}

	return frankenphp.PHPMap(result)
}

// export_php:function symfonicat_json_encode(mixed $payload): string
func symfonicat_json_encode(payload *C.zval) unsafe.Pointer {
	value, err := frankenphp.GoValue[any](unsafe.Pointer(payload))
	if err != nil {
		return frankenphp.PHPString("", false)
	}

	encoded, err := encodeJSONValue(normalizeJSONValue(value))
	if err != nil {
		return frankenphp.PHPString("", false)
	}

	return frankenphp.PHPString(encoded, false)
}

// export_php:function symfonicat_module_request_token_sign(array $payload, string $secret): string
func symfonicat_module_request_token_sign(payload *C.zend_array, secret *C.zend_string) unsafe.Pointer {
	value, err := frankenphp.GoMap[any](unsafe.Pointer(payload))
	if err != nil {
		return frankenphp.PHPString("", false)
	}

	encodedJSON, err := encodeJSONValue(normalizeJSONValue(value))
	if err != nil {
		return frankenphp.PHPString("", false)
	}

	encoded := base64.RawURLEncoding.EncodeToString([]byte(encodedJSON))
	signature := requestTokenSignature(encoded, frankenphp.GoString(unsafe.Pointer(secret)))

	return frankenphp.PHPString(encoded+tokenSeparator+signature, false)
}

// export_php:function symfonicat_module_request_token_verify(string $token, string $secret): array
func symfonicat_module_request_token_verify(token *C.zend_string, secret *C.zend_string) unsafe.Pointer {
	parts := strings.SplitN(frankenphp.GoString(unsafe.Pointer(token)), tokenSeparator, 2)
	if len(parts) != 2 {
		return emptyPHPArray()
	}

	expected := requestTokenSignature(parts[0], frankenphp.GoString(unsafe.Pointer(secret)))
	if subtle.ConstantTimeCompare([]byte(expected), []byte(parts[1])) != 1 {
		return emptyPHPArray()
	}

	decoded, err := base64.RawURLEncoding.DecodeString(parts[0])
	if err != nil {
		return emptyPHPArray()
	}

	payload, err := decodeJSONBytes(decoded)
	if err != nil {
		return emptyPHPArray()
	}

	payloadMap, ok := payload.(map[string]any)
	if !ok {
		return emptyPHPArray()
	}

	return frankenphp.PHPMap(payloadMap)
}

// export_php:function symfonicat_hash_sha256(string $value): string
func symfonicat_hash_sha256(value *C.zend_string) unsafe.Pointer {
	sum := sha256.Sum256([]byte(frankenphp.GoString(unsafe.Pointer(value))))

	return frankenphp.PHPString(hex.EncodeToString(sum[:]), false)
}

func decodeJSONPayload(payload []byte) (any, error) {
	decoded, err := decodeJSONBytes(payload)
	if err == nil {
		return decoded, nil
	}

	reader := brotli.NewReader(bytes.NewReader(payload))
	decompressed, err := io.ReadAll(reader)
	if err != nil {
		return nil, err
	}

	return decodeJSONBytes(decompressed)
}

func decodeJSONBytes(payload []byte) (any, error) {
	decoder := json.NewDecoder(bytes.NewReader(payload))
	decoder.UseNumber()

	var decoded any
	if err := decoder.Decode(&decoded); err != nil {
		return nil, err
	}

	var extra any
	if err := decoder.Decode(&extra); err != io.EOF {
		if err == nil {
			return nil, errors.New("unexpected extra JSON data")
		}

		return nil, err
	}

	return normalizeJSONValue(decoded), nil
}

func normalizeJSONValue(value any) any {
	switch typed := value.(type) {
	case map[string]any:
		normalized := make(map[string]any, len(typed))
		for key, nested := range typed {
			normalized[key] = normalizeJSONValue(nested)
		}

		return normalized
	case frankenphp.AssociativeArray[any]:
		normalized := make(map[string]any, len(typed.Map))
		for key, nested := range typed.Map {
			normalized[key] = normalizeJSONValue(nested)
		}

		return normalized
	case []any:
		normalized := make([]any, len(typed))
		for index, nested := range typed {
			normalized[index] = normalizeJSONValue(nested)
		}

		return normalized
	case json.Number:
		if strings.ContainsAny(typed.String(), ".eE") {
			if value, err := typed.Float64(); err == nil {
				return value
			}

			return typed.String()
		}

		if value, err := typed.Int64(); err == nil {
			return value
		}

		if value, err := typed.Float64(); err == nil {
			return value
		}

		return typed.String()
	default:
		return value
	}
}

func encodeJSONValue(value any) (string, error) {
	var buffer bytes.Buffer
	encoder := json.NewEncoder(&buffer)
	encoder.SetEscapeHTML(false)

	if err := encoder.Encode(value); err != nil {
		return "", err
	}

	return strings.TrimSuffix(buffer.String(), "\n"), nil
}

func requestTokenSignature(encoded string, secret string) string {
	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write([]byte(encoded))

	return hex.EncodeToString(mac.Sum(nil))
}

func zendStringBytes(value *C.zend_string) []byte {
	if value == nil {
		return nil
	}

	return C.GoBytes(unsafe.Pointer(&value.val), C.int(value.len))
}

func emptyPHPArray() unsafe.Pointer {
	return frankenphp.PHPMap(map[string]any{})
}
