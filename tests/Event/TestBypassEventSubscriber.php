<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\Tests\Event;

use GrahamCampbell\GitWrapper\Event\GitPrepareEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class TestBypassEventSubscriber implements EventSubscriberInterface
{
    /**
     * @return int[][]|string[][]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            GitPrepareEvent::class => ['onPrepare', -5],
        ];
    }

    public function onPrepare(GitPrepareEvent $gitPrepareEvent): void
    {
        $gitPrepareEvent->getCommand()
            ->bypass();
    }
}
