<?php

namespace Symfonicat\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Symfonicat\Entity\Admin;
use Symfonicat\Contract\AdminYamlDumper;
use Doctrine\Persistence\Proxy;
use Symfony\Component\HttpFoundation\RequestStack;

final class AdminYamlDumpSubscriber implements EventSubscriber
{
    private bool $shouldDump = false;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly AdminYamlDumper $adminYaml,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getSubscribedEvents(): array
    {
        return ['onFlush', 'postFlush'];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $this->shouldDump = false;

        $request = $this->requestStack->getMainRequest() ?? $this->requestStack->getCurrentRequest();
        if ($request === null || !str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }

        $this->shouldDump = $this->hasTrackedChanges($args->getObjectManager()->getUnitOfWork());
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->shouldDump) {
            return;
        }

        $this->shouldDump = false;
        $request = $this->requestStack->getMainRequest() ?? $this->requestStack->getCurrentRequest();
        $request?->attributes->set('symfonicat_admin_yaml_dumped', true);
        $this->adminYaml->dump();
    }

    private function hasTrackedChanges(UnitOfWork $unitOfWork): bool
    {
        foreach ([
            $unitOfWork->getScheduledEntityInsertions(),
            $unitOfWork->getScheduledEntityUpdates(),
            $unitOfWork->getScheduledEntityDeletions(),
        ] as $entities) {
            foreach ($entities as $entity) {
                if ($this->isTrackedEntity($entity)) {
                    return true;
                }
            }
        }

        foreach ([
            $unitOfWork->getScheduledCollectionUpdates(),
            $unitOfWork->getScheduledCollectionDeletions(),
        ] as $collections) {
            foreach ($collections as $collection) {
                $owner = method_exists($collection, 'getOwner') ? $collection->getOwner() : null;
                if (is_object($owner) && $this->isTrackedEntity($owner)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isTrackedEntity(object $entity): bool
    {
        $class = $entity instanceof Proxy ? (get_parent_class($entity) ?: get_class($entity)) : get_class($entity);

        return str_starts_with($class, 'Symfonicat\\Entity\\') && $class !== Admin::class;
    }
}
