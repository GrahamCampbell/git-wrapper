<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\EventSubscriber;

use GrahamCampbell\GitWrapper\Event\GitOutputEvent;

/**
 * Event handler that streams real-time output from Git commands to STDOUT and
 * STDERR.
 */
final class StreamOutputEventSubscriber extends AbstractOutputEventSubscriber
{
    public function handleOutput(GitOutputEvent $gitOutputEvent): void
    {
        $handler = $gitOutputEvent->isError() ? \STDERR : \STDOUT;
        \fwrite($handler, $gitOutputEvent->getBuffer());
    }
}
