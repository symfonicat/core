PHP_ARG_ENABLE(module_routes, whether to enable module_routes support,
[  --enable-module-routes       Enable module_routes support])

if test "$PHP_MODULE_ROUTES" != "no"; then
	PHP_ADD_EXTENSION_DEP(module_routes, json)
	PHP_NEW_EXTENSION(module_routes, module_routes.c, $ext_shared)
fi
