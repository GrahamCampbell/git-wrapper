<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\Tests\EventSubscriber\Source;

use GrahamCampbell\GitWrapper\Event\GitBypassEvent;
use GrahamCampbell\GitWrapper\Event\GitErrorEvent;
use GrahamCampbell\GitWrapper\Event\GitPrepareEvent;
use GrahamCampbell\GitWrapper\Event\GitSuccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class TestEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var string[]
     */
    private $calledMethods = [];

    public static function getSubscribedEvents(): array
    {
        return [
            GitPrepareEvent::class => 'onPrepare',
            GitSuccessEvent::class => 'onSuccess',
            GitErrorEvent::class => 'onError',
            GitBypassEvent::class => 'onBypass',
        ];
    }

    public function wasMethodCalled(string $method): bool
    {
        return in_array($method, $this->calledMethods, true);
    }

    public function onPrepare(): void
    {
        $this->calledMethods[] = 'onPrepare';
    }

    public function onSuccess(): void
    {
        $this->calledMethods[] = 'onSuccess';
    }

    public function onError(): void
    {
        $this->calledMethods[] = 'onError';
    }

    public function onBypass(): void
    {
        $this->calledMethods[] = 'onBypass';
    }
}
