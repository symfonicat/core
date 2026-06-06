PHP_ARG_ENABLE(remove_string, whether to enable native support, [ --enable-native-remove-string Enable native remove string])

if test "$PHP_NATIVE_REMOVE_STRING" != "no"; then
	AC_DEFINE(HAVE_NATIVE_REMOVE_STRING, 1, [Have native remove string support])
	PHP_NEW_EXTENSION(remove_string, remove_string.c, $ext_shared)
fi