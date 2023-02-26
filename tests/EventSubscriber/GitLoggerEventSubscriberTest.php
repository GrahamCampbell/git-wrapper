<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\Tests\EventSubscriber;

use GrahamCampbell\GitWrapper\EventSubscriber\GitLoggerEventSubscriber;
use GrahamCampbell\GitWrapper\Exception\GitException;
use GrahamCampbell\GitWrapper\GitCommand;
use GrahamCampbell\GitWrapper\Tests\AbstractGitWrapperTestCase;
use GrahamCampbell\GitWrapper\Tests\EventSubscriber\Source\TestLogger;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Throwable;

final class GitLoggerEventSubscriberTest extends AbstractGitWrapperTestCase
{
    protected function tearDown(): void
    {
        if (\is_dir(self::REPO_DIR)) {
            $this->filesystem->remove(self::REPO_DIR);
        }
    }

    public function testSetLogLevelMapping(): void
    {
        $gitLoggerEventSubscriber = new GitLoggerEventSubscriber(new NullLogger());
        $gitLoggerEventSubscriber->setLogLevelMapping('test.event', 'test-level');

        self::assertSame('test-level', $gitLoggerEventSubscriber->getLogLevelMapping('test.event'));
    }

    public function testGetInvalidLogLevelMapping(): void
    {
        $this->expectException(GitException::class);

        $gitLoggerEventSubscriber = new GitLoggerEventSubscriber(new NullLogger());
        $gitLoggerEventSubscriber->getLogLevelMapping('bad.event');
    }

    public function testRegisterLogger(): void
    {
        $logger = new TestLogger();
        $this->gitWrapper->addLoggerEventSubscriber(new GitLoggerEventSubscriber($logger));
        $git = $this->gitWrapper->init(self::REPO_DIR, [
            'bare' => true,
        ]);

        self::assertSame('Git command preparing to run', $logger->messages[0]);
        self::assertSame(
            'Initialized empty Git repository in '.\realpath(self::REPO_DIR)."/\n",
            $logger->messages[1]
        );
        self::assertSame('Git command successfully run', $logger->messages[2]);

        self::assertArrayHasKey('command', $logger->contexts[0]);
        self::assertArrayHasKey('command', $logger->contexts[1]);
        self::assertArrayHasKey('error', $logger->contexts[1]);
        self::assertArrayHasKey('command', $logger->contexts[2]);

        self::assertSame(LogLevel::INFO, $logger->levels[0]);
        self::assertSame(LogLevel::DEBUG, $logger->levels[1]);
        self::assertSame(LogLevel::INFO, $logger->levels[2]);

        try {
            $logger->clearMessages();
            $git->commit('fatal: This operation must be run in a work tree');
        } catch (Throwable $throwable) {
            // Nothing to do, this is expected.
        }

        self::assertSame('Error running Git command', $logger->messages[2]);
        self::assertArrayHasKey('command', $logger->contexts[2]);
        self::assertSame(LogLevel::ERROR, $logger->levels[2]);
    }

    public function testLogBypassedCommand(): void
    {
        $logger = new TestLogger();
        $this->gitWrapper->addLoggerEventSubscriber(new GitLoggerEventSubscriber($logger));

        $command = new GitCommand('status', [
            's' => true,
        ]);
        $command->bypass();

        $this->gitWrapper->run($command);

        self::assertSame('Git command bypassed', $logger->messages[1]);
        self::assertArrayHasKey('command', $logger->contexts[1]);
        self::assertSame(LogLevel::INFO, $logger->levels[1]);
    }
}
