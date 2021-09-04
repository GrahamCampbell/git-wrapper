<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\EventSubscriber;

use GrahamCampbell\GitWrapper\Contract\OutputEventSubscriberInterface;
use GrahamCampbell\GitWrapper\Event\GitOutputEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractOutputEventSubscriber implements EventSubscriberInterface, OutputEventSubscriberInterface
{
    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            GitOutputEvent::class => 'handleOutput',
        ];
    }
}
