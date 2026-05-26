<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Entity\Subdomain;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class ModuleRequestContextStore
{
    private const SESSION_KEY = 'symfonicat.module_request_contexts';

    public function issue(Request $request, Domain|Subdomain|Endpoint $entity): array
    {
        $session = $this->session($request);
        $contextId = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));

        if ($session instanceof SessionInterface) {
            $session->set($this->contextKey($contextId), [
                'token' => $token,
                'domain_id' => $entity instanceof Domain ? $this->entityId($entity) : null,
                'subdomain_id' => $entity instanceof Subdomain ? $this->entityId($entity) : null,
                'endpoint_id' => $entity instanceof Endpoint ? $this->entityId($entity) : null,
            ]);
        }

        return [
            'context_id' => $contextId,
            'token' => $token,
        ];
    }

    /**
     * @return array{context_id: string, token: string, domain_id: ?string, subdomain_id: ?string, endpoint_id: ?string}|null
     */
    public function resolve(Request $request): ?array
    {
        $contextId = trim((string) $request->headers->get('X-Symfonicat-Module-Context', ''));
        $token = trim((string) $request->headers->get('X-CSRF-Token', ''));
        if ($contextId === '' || $token === '') {
            return null;
        }

        $session = $this->session($request);
        if (!$session instanceof SessionInterface) {
            return null;
        }

        $context = $session->get($this->contextKey($contextId));
        if (!is_array($context)) {
            return null;
        }

        $storedToken = trim((string) ($context['token'] ?? ''));
        if ($storedToken === '' || !hash_equals($storedToken, $token)) {
            return null;
        }

        return [
            'context_id' => $contextId,
            'token' => $storedToken,
            'domain_id' => isset($context['domain_id']) ? trim((string) $context['domain_id']) : null,
            'subdomain_id' => isset($context['subdomain_id']) ? trim((string) $context['subdomain_id']) : null,
            'endpoint_id' => isset($context['endpoint_id']) ? trim((string) $context['endpoint_id']) : null,
        ];
    }

    private function session(Request $request): ?SessionInterface
    {
        if (!$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();
        if ($session instanceof SessionInterface) {
            return $session;
        }

        return null;
    }

    private function contextKey(string $contextId): string
    {
        return self::SESSION_KEY.'.'.$contextId;
    }

    private function entityId(Domain|Subdomain|Endpoint $entity): ?string
    {
        $id = trim((string) $entity->getId(false));

        return $id === '' ? null : $id;
    }
}
