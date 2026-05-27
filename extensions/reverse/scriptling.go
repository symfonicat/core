package reverse

// #include <Zend/zend_types.h>
import "C"

import (
	"unsafe"

	"github.com/dunglas/frankenphp"
)

// export_php:function scriptling_reverse(string $value): string
func scriptling_reverse(value *C.zend_string) unsafe.Pointer {
	input := []rune(frankenphp.GoString(unsafe.Pointer(value)))
	for i, j := 0, len(input)-1; i < j; i, j = i+1, j-1 {
		input[i], input[j] = input[j], input[i]
	}

	return frankenphp.PHPString(string(input), false)
}
