<?php

namespace Symfonicat\Form\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;

final class CoreYamlDumpSubscriber implements EventSubscriberInterface
{
    public const REQUEST_ATTRIBUTE = 'symfonicat_core_form_submitted';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1?: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::POST_SUBMIT => 'onPostSubmit',
        ];
    }

    public function onPostSubmit(FormEvent $event): void
    {
        $form = $event->getForm();
        if (!$form->isRoot()) {
            return;
        }

        $request = $this->requestStack->getMainRequest() ?? $this->requestStack->getCurrentRequest();
        if ($request === null || !str_starts_with($request->getPathInfo(), '/core')) {
            return;
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, true);
    }
}
