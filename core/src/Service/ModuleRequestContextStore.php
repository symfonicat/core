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
        return symfonicat_module_request_token_sign($payload, $this->secret);
    }

    /**
     * @return array{context_id?: mixed, domain_id?: mixed, subdomain_affix?: mixed, endpoint_id?: mixed, issued_at?: mixed}|null
     */
    private function verifyPayload(string $token): ?array
    {
        $payload = symfonicat_module_request_token_verify($token, $this->secret);

        return is_array($payload) ? $payload : null;
    }
}
