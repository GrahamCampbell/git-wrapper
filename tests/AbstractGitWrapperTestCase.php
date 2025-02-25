<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\Tests;

use GrahamCampbell\GitWrapper\Exception\GitException;
use GrahamCampbell\GitWrapper\GitWrapper;
use GrahamCampbell\GitWrapper\Tests\Event\TestBypassEventSubscriber;
use GrahamCampbell\GitWrapper\Tests\EventSubscriber\Source\TestEventSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractGitWrapperTestCase extends TestCase
{
    /**
     * @var string
     */
    protected const REPO_DIR = __DIR__.'/build/tests/repo';

    /**
     * @var string
     */
    protected const WORKING_DIR = __DIR__.'/build/tests/wc';

    /**
     * @var string
     */
    protected const CONFIG_EMAIL = 'testing@email.com';

    /**
     * @var string
     */
    protected const CONFIG_NAME = 'Testing name';

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var GitWrapper
     */
    protected $gitWrapper;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->gitWrapper = new GitWrapper();
    }

    protected function registerAndReturnEventSubscriber(): TestEventSubscriber
    {
        $eventDispatcher = $this->gitWrapper->getDispatcher();
        $testEventSubscriber = new TestEventSubscriber();
        $eventDispatcher->addSubscriber($testEventSubscriber);

        return $testEventSubscriber;
    }

    /**
     * Adds the bypass event subscriber so that Git commands are not run.
     */
    protected function createRegisterAndReturnBypassEventSubscriber(): TestBypassEventSubscriber
    {
        $testBypassEventSubscriber = new TestBypassEventSubscriber();
        $eventDispatcher = $this->gitWrapper->getDispatcher();
        $eventDispatcher->addSubscriber($testBypassEventSubscriber);

        return $testBypassEventSubscriber;
    }

    /**
     * Asserts a correct Git version string was returned.
     *
     * @param string $version the version returned by the `git --version` command
     */
    protected function assertGitVersion(string $version): void
    {
        $match = \preg_match('#^git version [.0-9]+#', $version);
        self::assertNotEmpty($match);
    }

    protected function runBadCommand(bool $catchException = false): void
    {
        try {
            $this->gitWrapper->git('a-bad-command');
        } catch (GitException $gitException) {
            if ($catchException) {
                return;
            }

            throw $gitException;
        }
    }

    protected function randomString(): string
    {
        return \bin2hex(\random_bytes(16));
    }
}
