<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Entity\Subdomain;
use Symfony\Component\HttpFoundation\Request;

final class ModuleRequestContextStore
{
    private const TOKEN_SEPARATOR = '.';

    public function __construct(
        private readonly string $secret,
    ) {
    }

    public function issue(Request $request, Domain|Subdomain|Endpoint $entity): array
    {
        $contextId = bin2hex(random_bytes(16));
        $payload = [
            'context_id' => $contextId,
            'domain_id' => $entity instanceof Domain ? $this->entityId($entity) : null,
            'subdomain_affix' => $entity instanceof Subdomain ? $this->entityId($entity) : null,
            'endpoint_id' => $entity instanceof Endpoint ? $this->entityId($entity) : null,
            'issued_at' => time(),
        ];

        return [
            'context_id' => $contextId,
            'token' => $this->signPayload($payload),
        ];
    }

    /**
     * @return array{context_id: string, token: string, domain_id: ?string, subdomain_affix: ?string, endpoint_id: ?string}|null
     */
    public function resolve(Request $request): ?array
    {
        $contextId = trim((string) $request->headers->get('X-Symfonicat-Module-Context', ''));
        $token = trim((string) $request->headers->get('X-CSRF-Token', ''));
        if ($contextId === '' || $token === '') {
            return null;
        }

        $payload = $this->verifyPayload($token);
        if (!is_array($payload)) {
            return null;
        }

        $storedContextId = trim((string) ($payload['context_id'] ?? ''));
        if ($storedContextId === '' || !hash_equals($storedContextId, $contextId)) {
            return null;
        }

        return [
            'context_id' => $contextId,
            'token' => $token,
            'domain_id' => isset($payload['domain_id']) ? trim((string) $payload['domain_id']) : null,
            'subdomain_affix' => isset($payload['subdomain_affix']) ? trim((string) $payload['subdomain_affix']) : null,
            'endpoint_id' => isset($payload['endpoint_id']) ? trim((string) $payload['endpoint_id']) : null,
        ];
    }

    private function entityId(Domain|Subdomain|Endpoint $entity): ?string
    {
        if ($entity instanceof Subdomain) {
            $id = trim((string) $entity->getAffix());
        } else {
            $id = trim((string) $entity->getId(false));
        }

        return $id === '' ? null : $id;
    }

    /**
     * @param array{context_id: string, domain_id: ?string, subdomain_affix: ?string, endpoint_id: ?string, issued_at: int} $payload
     */
    private function signPayload(array $payload): string
    {
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encodedPayload, $this->secret, true);

        return $encodedPayload.self::TOKEN_SEPARATOR.$this->base64UrlEncode($signature);
    }

    /**
     * @return array{context_id?: mixed, domain_id?: mixed, subdomain_affix?: mixed, endpoint_id?: mixed, issued_at?: mixed}|null
     */
    private function verifyPayload(string $token): ?array
    {
        $parts = explode(self::TOKEN_SEPARATOR, $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $encodedSignature] = $parts;
        $signature = $this->base64UrlDecode($encodedSignature);
        if ($signature === null) {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $encodedPayload, $this->secret, true);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payloadJson = $this->base64UrlDecode($encodedPayload);
        if ($payloadJson === null) {
            return null;
        }

        try {
            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $padded = strtr($value, '-_', '+/');
        $padding = strlen($padded) % 4;
        if ($padding !== 0) {
            $padded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}
