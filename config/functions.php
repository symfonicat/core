<?php

if (!function_exists('symfonicat_json_decode')) {
    /**
     * Decode a JSON string into an array.
     *
     * The native Symfonicat extension provides this function in the FrankenPHP
     * runtime; this PHP fallback keeps CLI tooling and route warmup working.
     *
     * @return array<mixed>
     */
    function symfonicat_json_decode(string $payload): array
    {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
