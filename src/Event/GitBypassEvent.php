<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\Event;

/**
 * Event thrown if the command is flagged to skip execution.
 */
final class GitBypassEvent extends AbstractGitEvent
{
}
