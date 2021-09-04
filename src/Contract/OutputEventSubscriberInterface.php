<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\Contract;

use GrahamCampbell\GitWrapper\Event\GitOutputEvent;

interface OutputEventSubscriberInterface
{
    public function handleOutput(GitOutputEvent $gitOutputEvent): void;
}
