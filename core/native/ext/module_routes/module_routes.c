#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "ext/json/php_json.h"
#include "ext/standard/info.h"
#include "Zend/zend_exceptions.h"
#include "main/php_streams.h"

#include <ctype.h>
#include <limits.h>
#include <sys/stat.h>

static zend_string *module_routes_read_file(const char *path)
{
	php_stream *stream = php_stream_open_wrapper(path, "rb", 0, NULL);
	if (stream == NULL) {
		return NULL;
	}

	zend_string *contents = php_stream_copy_to_mem(stream, PHP_STREAM_COPY_ALL, 0);
	php_stream_close(stream);

	return contents;
}

static bool module_routes_decode_json_file(const char *path, zval *decoded)
{
	ZVAL_UNDEF(decoded);
	zend_string *contents = module_routes_read_file(path);
	if (contents == NULL) {
		return false;
	}

	if (php_json_decode_ex(decoded, ZSTR_VAL(contents), ZSTR_LEN(contents), PHP_JSON_OBJECT_AS_ARRAY, PHP_JSON_PARSER_DEFAULT_DEPTH) == FAILURE) {
		zend_string_release(contents);
		zval_ptr_dtor(decoded);
		zend_throw_exception(zend_ce_exception, "Unable to decode JSON for module routes.", 0);
		return false;
	}

	zend_string_release(contents);

	return true;
}

static zend_string *module_routes_trim_whitespace(const char *value, size_t length)
{
	size_t start = 0;
	size_t end = length;

	while (start < end && isspace((unsigned char) value[start])) {
		start++;
	}

	while (end > start && isspace((unsigned char) value[end - 1])) {
		end--;
	}

	return zend_string_init(value + start, end - start, 0);
}

static zend_string *module_routes_trim_slashes(const char *value, size_t length)
{
	size_t start = 0;
	size_t end = length;

	while (start < end && value[start] == '/') {
		start++;
	}

	while (end > start && value[end - 1] == '/') {
		end--;
	}

	return zend_string_init(value + start, end - start, 0);
}

static bool module_routes_is_regular_file(const char *path)
{
	struct stat st;

	return VCWD_STAT(path, &st) == 0 && S_ISREG(st.st_mode);
}

static bool module_routes_is_directory(const char *path)
{
	struct stat st;

	return VCWD_STAT(path, &st) == 0 && S_ISDIR(st.st_mode);
}

static zval *module_routes_hash_find(zval *array, const char *key, size_t key_length)
{
	if (Z_TYPE_P(array) != IS_ARRAY) {
		return NULL;
	}

	return zend_hash_str_find(Z_ARRVAL_P(array), key, key_length);
}

static bool module_routes_package_is_symfonicat(zval *composer)
{
	zval *extra = module_routes_hash_find(composer, "extra", sizeof("extra") - 1);
	if (extra == NULL || Z_TYPE_P(extra) != IS_ARRAY) {
		return false;
	}

	zval *symfonicat = module_routes_hash_find(extra, "symfonicat", sizeof("symfonicat") - 1);
	if (symfonicat == NULL || Z_TYPE_P(symfonicat) != IS_TRUE) {
		return false;
	}

	zval *name = module_routes_hash_find(composer, "name", sizeof("name") - 1);
	return name != NULL && Z_TYPE_P(name) != IS_NULL;
}

static void module_routes_add_package(HashTable *packages, zval *composer, const char *install_path, size_t install_path_length)
{
	zval package;
	array_init_size(&package, 2);

	Z_TRY_ADDREF_P(composer);
	add_assoc_zval(&package, "composer", composer);
	add_assoc_str(&package, "installPath", zend_string_init(install_path, install_path_length, 0));

	zend_hash_next_index_insert_new(packages, &package);
}

static bool module_routes_collect_root_package(const char *project_dir, HashTable *packages)
{
	char *composer_path = NULL;
	spprintf(&composer_path, 0, "%s/composer.json", project_dir);

	bool success = false;
	if (module_routes_is_regular_file(composer_path)) {
		zval composer;
		if (module_routes_decode_json_file(composer_path, &composer) && !EG(exception) && Z_TYPE(composer) == IS_ARRAY && module_routes_package_is_symfonicat(&composer)) {
			module_routes_add_package(packages, &composer, project_dir, strlen(project_dir));
			success = true;
		}
		if (EG(exception)) {
			zval_ptr_dtor(&composer);
			efree(composer_path);
			return false;
		}
		zval_ptr_dtor(&composer);
	}

	efree(composer_path);

	return success;
}

static void module_routes_collect_installed_packages(const char *project_dir, HashTable *packages)
{
	char *installed_path = NULL;
	spprintf(&installed_path, 0, "%s/vendor/composer/installed.json", project_dir);

	if (!module_routes_is_regular_file(installed_path)) {
		efree(installed_path);
		return;
	}

	zval installed;
	if (!module_routes_decode_json_file(installed_path, &installed) || Z_TYPE(installed) != IS_ARRAY) {
		zval_ptr_dtor(&installed);
		efree(installed_path);
		return;
	}

	zval *installed_packages = module_routes_hash_find(&installed, "packages", sizeof("packages") - 1);
	if (installed_packages == NULL || Z_TYPE_P(installed_packages) != IS_ARRAY) {
		zval_ptr_dtor(&installed);
		efree(installed_path);
		return;
	}

	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(installed_packages), zval *package) {
		if (Z_TYPE_P(package) != IS_ARRAY) {
			continue;
		}

		zval *extra = module_routes_hash_find(package, "extra", sizeof("extra") - 1);
		if (extra == NULL || Z_TYPE_P(extra) != IS_ARRAY) {
			continue;
		}

		zval *symfonicat = module_routes_hash_find(extra, "symfonicat", sizeof("symfonicat") - 1);
		if (symfonicat == NULL || Z_TYPE_P(symfonicat) != IS_TRUE) {
			continue;
		}

		zval *install_path_zv = module_routes_hash_find(package, "install-path", sizeof("install-path") - 1);
		if (install_path_zv == NULL || Z_TYPE_P(install_path_zv) != IS_STRING) {
			continue;
		}

		zend_string *relative_install_path = module_routes_trim_whitespace(Z_STRVAL_P(install_path_zv), Z_STRLEN_P(install_path_zv));
		if (ZSTR_LEN(relative_install_path) == 0) {
			zend_string_release(relative_install_path);
			continue;
		}

		char *combined_path = NULL;
		char resolved_path[MAXPATHLEN];
		spprintf(&combined_path, 0, "%s/vendor/composer/%s", project_dir, ZSTR_VAL(relative_install_path));
		zend_string_release(relative_install_path);

		if (VCWD_REALPATH(combined_path, resolved_path) == NULL || !module_routes_is_directory(resolved_path)) {
			efree(combined_path);
			continue;
		}

		char *composer_path = NULL;
		spprintf(&composer_path, 0, "%s/composer.json", resolved_path);
		if (!module_routes_is_regular_file(composer_path)) {
			efree(composer_path);
			efree(combined_path);
			continue;
		}

		zval composer;
		if (module_routes_decode_json_file(composer_path, &composer) && !EG(exception) && Z_TYPE(composer) == IS_ARRAY && module_routes_package_is_symfonicat(&composer)) {
			module_routes_add_package(packages, &composer, resolved_path, strlen(resolved_path));
		}
		if (EG(exception)) {
			zval_ptr_dtor(&composer);
			efree(composer_path);
			efree(combined_path);
			zval_ptr_dtor(&installed);
			efree(installed_path);
			return;
		}
		zval_ptr_dtor(&composer);

		efree(composer_path);
		efree(combined_path);
	}
	ZEND_HASH_FOREACH_END();

	zval_ptr_dtor(&installed);
	efree(installed_path);
}

static void module_routes_collect_scan_target(HashTable *scan_targets, const char *directory, size_t directory_length, const char *class_prefix, size_t class_prefix_length, const char *package_name)
{
	zval target;
	array_init_size(&target, 3);
	add_assoc_str(&target, "classPrefix", zend_string_init(class_prefix, class_prefix_length, 0));
	add_assoc_str(&target, "directory", zend_string_init(directory, directory_length, 0));
	add_assoc_str(&target, "packageName", zend_string_init(package_name, strlen(package_name), 0));

	zend_string *key = strpprintf(0, "%s|%s", directory, class_prefix);
	zend_hash_update(scan_targets, key, &target);
}

static void module_routes_collect_scan_targets_from_path(HashTable *scan_targets, zval *install_path_zv, zend_string *prefix, zval *path_entry, zval *name, HashTable *direct_module_targets)
{
	if (Z_TYPE_P(path_entry) != IS_STRING) {
		return;
	}

	zend_string *trimmed_path = module_routes_trim_slashes(Z_STRVAL_P(path_entry), Z_STRLEN_P(path_entry));
	if (ZSTR_LEN(trimmed_path) == 0) {
		zend_string_release(trimmed_path);
		return;
	}

	char *base_path = NULL;
	char *candidate_path = NULL;
	char resolved_path[MAXPATHLEN];
	spprintf(&base_path, 0, "%s/%s", Z_STRVAL_P(install_path_zv), ZSTR_VAL(trimmed_path));
	zend_string_release(trimmed_path);

	if (VCWD_REALPATH(base_path, resolved_path) == NULL || !module_routes_is_directory(resolved_path)) {
		efree(base_path);
		return;
	}

	const char *basename = strrchr(resolved_path, '/');
	basename = basename != NULL ? basename + 1 : resolved_path;

	if (strcmp(basename, "Module") == 0) {
		zend_hash_str_add_empty_element(direct_module_targets, resolved_path, strlen(resolved_path));
		module_routes_collect_scan_target(scan_targets, resolved_path, strlen(resolved_path), ZSTR_VAL(prefix), ZSTR_LEN(prefix), Z_STRVAL_P(name));
		efree(base_path);
		return;
	}

	spprintf(&candidate_path, 0, "%s/Module", resolved_path);
	if (module_routes_is_directory(candidate_path) && !zend_hash_str_exists(direct_module_targets, candidate_path, strlen(candidate_path))) {
		char *fallback_class_prefix = NULL;
		spprintf(&fallback_class_prefix, 0, "%sModule\\", ZSTR_VAL(prefix));
		module_routes_collect_scan_target(scan_targets, candidate_path, strlen(candidate_path), fallback_class_prefix, strlen(fallback_class_prefix), Z_STRVAL_P(name));
		efree(fallback_class_prefix);
	}

	efree(candidate_path);
	efree(base_path);
}

static void module_routes_collect_scan_targets_from_package(zval *package, HashTable *scan_targets)
{
	zval *composer = module_routes_hash_find(package, "composer", sizeof("composer") - 1);
	zval *install_path_zv = module_routes_hash_find(package, "installPath", sizeof("installPath") - 1);
	if (composer == NULL || Z_TYPE_P(composer) != IS_ARRAY || install_path_zv == NULL || Z_TYPE_P(install_path_zv) != IS_STRING) {
		return;
	}

	zval *name = module_routes_hash_find(composer, "name", sizeof("name") - 1);
	if (name == NULL || Z_TYPE_P(name) != IS_STRING) {
		return;
	}

	zval *autoload = module_routes_hash_find(composer, "autoload", sizeof("autoload") - 1);
	if (autoload == NULL || Z_TYPE_P(autoload) != IS_ARRAY) {
		return;
	}

	zval *psr4 = module_routes_hash_find(autoload, "psr-4", sizeof("psr-4") - 1);
	if (psr4 == NULL || Z_TYPE_P(psr4) != IS_ARRAY) {
		return;
	}

	HashTable direct_module_targets;
	zend_hash_init(&direct_module_targets, 8, NULL, NULL, 0);

	ZEND_HASH_FOREACH_KEY_VAL(Z_ARRVAL_P(psr4), zend_ulong index, zend_string *prefix, zval *paths) {
		(void) index;
		if (prefix == NULL) {
			continue;
		}

		if (Z_TYPE_P(paths) == IS_STRING) {
			module_routes_collect_scan_targets_from_path(scan_targets, install_path_zv, prefix, paths, name, &direct_module_targets);
			continue;
		}

		if (Z_TYPE_P(paths) != IS_ARRAY) {
			continue;
		}

		ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(paths), zval *path_entry) {
			module_routes_collect_scan_targets_from_path(scan_targets, install_path_zv, prefix, path_entry, name, &direct_module_targets);
		} ZEND_HASH_FOREACH_END();
	}
	ZEND_HASH_FOREACH_END();

	zend_hash_destroy(&direct_module_targets);
}

static void module_routes_collect_scan_targets_impl(HashTable *packages, HashTable *scan_targets)
{
	ZEND_HASH_FOREACH_VAL(packages, zval *package) {
		if (Z_TYPE_P(package) != IS_ARRAY) {
			continue;
		}

		module_routes_collect_scan_targets_from_package(package, scan_targets);
	} ZEND_HASH_FOREACH_END();
}

PHP_FUNCTION(module_routes_collect_packages)
{
	zend_string *project_dir;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(project_dir)
	ZEND_PARSE_PARAMETERS_END();

	array_init(return_value);
	module_routes_collect_root_package(ZSTR_VAL(project_dir), Z_ARRVAL_P(return_value));
	module_routes_collect_installed_packages(ZSTR_VAL(project_dir), Z_ARRVAL_P(return_value));
}

PHP_FUNCTION(module_routes_collect_scan_targets)
{
	HashTable *packages;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ARRAY_HT(packages)
	ZEND_PARSE_PARAMETERS_END();

	array_init(return_value);
	module_routes_collect_scan_targets_impl(packages, Z_ARRVAL_P(return_value));
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_module_routes_collect_packages, 0, 0, 1)
	ZEND_ARG_TYPE_INFO(0, projectDir, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_module_routes_collect_scan_targets, 0, 0, 1)
	ZEND_ARG_ARRAY_INFO(0, packages, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry module_routes_functions[] = {
	PHP_FE(module_routes_collect_packages, arginfo_module_routes_collect_packages)
	PHP_FE(module_routes_collect_scan_targets, arginfo_module_routes_collect_scan_targets)
	PHP_FE_END
};

zend_module_entry module_routes_module_entry = {
	STANDARD_MODULE_HEADER,
	"module_routes",
	module_routes_functions,
	NULL,
	NULL,
	NULL,
	NULL,
	NULL,
	"0.1.0",
	STANDARD_MODULE_PROPERTIES
};

ZEND_GET_MODULE(module_routes)
