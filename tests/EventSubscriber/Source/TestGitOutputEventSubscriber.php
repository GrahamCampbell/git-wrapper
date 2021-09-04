<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\Tests\EventSubscriber\Source;

use GrahamCampbell\GitWrapper\Event\GitOutputEvent;
use GrahamCampbell\GitWrapper\EventSubscriber\AbstractOutputEventSubscriber;

final class TestGitOutputEventSubscriber extends AbstractOutputEventSubscriber
{
    /**
     * @var GitOutputEvent
     */
    private $gitOutputEvent;

    public function handleOutput(GitOutputEvent $gitOutputEvent): void
    {
        $this->gitOutputEvent = $gitOutputEvent;
    }

    /**
     * For testing
     */
    public function getLastEvent(): GitOutputEvent
    {
        return $this->gitOutputEvent;
    }
}
