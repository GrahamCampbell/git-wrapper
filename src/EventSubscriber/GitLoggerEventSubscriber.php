<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\EventSubscriber;

use GrahamCampbell\GitWrapper\Event\AbstractGitEvent;
use GrahamCampbell\GitWrapper\Event\GitBypassEvent;
use GrahamCampbell\GitWrapper\Event\GitErrorEvent;
use GrahamCampbell\GitWrapper\Event\GitOutputEvent;
use GrahamCampbell\GitWrapper\Event\GitPrepareEvent;
use GrahamCampbell\GitWrapper\Event\GitSuccessEvent;
use GrahamCampbell\GitWrapper\Exception\GitException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class GitLoggerEventSubscriber implements EventSubscriberInterface, LoggerAwareInterface
{
    /**
     * Mapping of event to log level.
     *
     * @var string[]
     */
    private $logLevelMappings = [
        GitPrepareEvent::class => LogLevel::INFO,
        GitOutputEvent::class => LogLevel::DEBUG,
        GitSuccessEvent::class => LogLevel::INFO,
        GitErrorEvent::class => LogLevel::ERROR,
        GitBypassEvent::class => LogLevel::INFO,
    ];

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Required by interface.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setLogLevelMapping(string $eventName, string $logLevel): void
    {
        $this->logLevelMappings[$eventName] = $logLevel;
    }

    /**
     * Returns the log level mapping for an event.
     */
    public function getLogLevelMapping(string $eventName): string
    {
        if (!isset($this->logLevelMappings[$eventName])) {
            throw new GitException(\sprintf('Unknown event "%s"', $eventName));
        }

        return $this->logLevelMappings[$eventName];
    }

    /**
     * @return array<class-string, string|array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            GitPrepareEvent::class => ['onPrepare', 0],
            GitOutputEvent::class => ['handleOutput', 0],
            GitSuccessEvent::class => ['onSuccess', 0],
            GitErrorEvent::class => ['onError', 0],
            GitBypassEvent::class => ['onBypass', 0],
        ];
    }

    /**
     * Adds a log message using the level defined in the mappings.
     *
     * @param mixed[] $context
     */
    public function log(AbstractGitEvent $gitEvent, string $message, array $context = []): void
    {
        $method = $this->getLogLevelMapping(\get_class($gitEvent));
        $context += [
            'command' => $gitEvent->getProcess()
                ->getCommandLine(),
        ];

        $this->logger->{$method}($message, $context);
    }

    public function onPrepare(GitPrepareEvent $gitPrepareEvent): void
    {
        $this->log($gitPrepareEvent, 'Git command preparing to run');
    }

    public function handleOutput(GitOutputEvent $gitOutputEvent): void
    {
        $context = [
            'error' => $gitOutputEvent->isError(),
        ];
        $this->log($gitOutputEvent, $gitOutputEvent->getBuffer(), $context);
    }

    public function onSuccess(GitSuccessEvent $gitSuccessEvent): void
    {
        $this->log($gitSuccessEvent, 'Git command successfully run');
    }

    public function onError(GitErrorEvent $gitErrorEvent): void
    {
        $this->log($gitErrorEvent, 'Error running Git command');
    }

    public function onBypass(GitBypassEvent $gitBypassEvent): void
    {
        $this->log($gitBypassEvent, 'Git command bypassed');
    }
}
