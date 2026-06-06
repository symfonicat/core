#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "ext/standard/info.h"

PHP_FUNCTION(remove_string);

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_remove_string, 0, 2, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, value, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, needle, IS_STRING, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry remove_string_functions[] = {
    PHP_FE(remove_string, arginfo_remove_string)
    PHP_FE_END
};

zend_module_entry remove_string_module_entry = {
    STANDARD_MODULE_HEADER,
    "remove_string",
    remove_string_functions,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    "0.1.0",
    STANDARD_MODULE_PROPERTIES
};

ZEND_GET_MODULE(remove_string)

PHP_FUNCTION(remove_string)
{
    zend_string *value;
    zend_string *needle;

    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STR(value)
        Z_PARAM_STR(needle)
    ZEND_PARSE_PARAMETERS_END();

    const char *value_ptr = ZSTR_VAL(value);
    const char *needle_ptr = ZSTR_VAL(needle);

    size_t value_len = ZSTR_LEN(value);
    size_t needle_len = ZSTR_LEN(needle);

    if (needle_len == 0 || value_len == 0 || needle_len > value_len) {
        RETURN_STR_COPY(value);
    }

    size_t match_count = 0;

    for (size_t i = 0; i <= value_len - needle_len;) {
        if (memcmp(value_ptr + i, needle_ptr, needle_len) == 0) {
            match_count++;
            i += needle_len;
            continue;
        }

        i++;
    }

    if (match_count == 0) {
        RETURN_STR_COPY(value);
    }

    size_t result_len = value_len - (match_count * needle_len);
    zend_string *result = zend_string_alloc(result_len, 0);
    char *result_ptr = ZSTR_VAL(result);

    size_t write_index = 0;

    for (size_t i = 0; i < value_len;) {
        if (
            i <= value_len - needle_len
            && memcmp(value_ptr + i, needle_ptr, needle_len) == 0
        ) {
            i += needle_len;
            continue;
        }

        result_ptr[write_index] = value_ptr[i];
        write_index++;
        i++;
    }

    result_ptr[result_len] = '\0';

    RETURN_STR(result);
}