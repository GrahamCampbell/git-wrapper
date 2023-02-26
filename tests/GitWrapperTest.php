<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\Tests;

use GrahamCampbell\GitWrapper\Exception\GitException;
use GrahamCampbell\GitWrapper\GitCommand;
use GrahamCampbell\GitWrapper\GitWorkingCopy;
use GrahamCampbell\GitWrapper\Tests\Event\TestDispatcher;

final class GitWrapperTest extends AbstractGitWrapperTestCase
{
    /**
     * @var string
     */
    private const BINARY = '/path/to/binary';

    /**
     * @var string
     */
    private const BAD_KEY = './tests/id_rsa_bad';

    /**
     * @var string
     */
    private const BAD_WRAPPER = './tests/dummy-wrapper-bad.sh';

    public function testSetGitBinary(): void
    {
        $this->gitWrapper->setGitBinary(self::BINARY);
        self::assertSame(self::BINARY, $this->gitWrapper->getGitBinary());
    }

    public function testSetDispatcher(): void
    {
        $dispatcher = new TestDispatcher();
        $this->gitWrapper->setDispatcher($dispatcher);
        self::assertSame($dispatcher, $this->gitWrapper->getDispatcher());
    }

    public function testSetTimeout(): void
    {
        $timeout = \random_int(1, 60);
        $this->gitWrapper->setTimeout($timeout);
        self::assertSame($timeout, $this->gitWrapper->getTimeout());
    }

    public function testEnvVar(): void
    {
        $var = $this->randomString();
        $value = $this->randomString();

        $this->gitWrapper->setEnvVar($var, $value);
        self::assertSame($value, $this->gitWrapper->getEnvVar($var));

        $envvars = $this->gitWrapper->getEnvVars();
        self::assertSame($value, $envvars[$var]);

        $this->gitWrapper->unsetEnvVar($var);
        self::assertNull($this->gitWrapper->getEnvVar($var));
    }

    public function testEnvVarDefault(): void
    {
        $var = $this->randomString();
        $default = $this->randomString();
        self::assertSame($default, $this->gitWrapper->getEnvVar($var, $default));
    }

    public function testGitVersion(): void
    {
        $version = $this->gitWrapper->version();
        self::assertGitVersion($version);
    }

    public function testSetPrivateKey(): void
    {
        $key = './tests/id_rsa';
        $keyExpected = \realpath($key);
        $sshWrapperExpected = \dirname(__DIR__).'/bin/git-ssh-wrapper.sh';

        $this->gitWrapper->setPrivateKey($key);
        self::assertSame($keyExpected, $this->gitWrapper->getEnvVar('GIT_SSH_KEY'));
        self::assertSame(22, $this->gitWrapper->getEnvVar('GIT_SSH_PORT'));
        self::assertSame($sshWrapperExpected, $this->gitWrapper->getEnvVar('GIT_SSH'));
    }

    public function testSetPrivateKeyPort(): void
    {
        $port = \random_int(1024, 10000);
        $this->gitWrapper->setPrivateKey('./tests/id_rsa', $port);
        self::assertSame($port, $this->gitWrapper->getEnvVar('GIT_SSH_PORT'));
    }

    public function testSetPrivateKeyWrapper(): void
    {
        $sshWrapper = './tests/dummy-wrapper.sh';
        $sshWrapperExpected = \realpath($sshWrapper);
        $this->gitWrapper->setPrivateKey('./tests/id_rsa', 22, $sshWrapper);
        self::assertSame($sshWrapperExpected, $this->gitWrapper->getEnvVar('GIT_SSH'));
    }

    public function testSetPrivateKeyError(): void
    {
        $this->expectException(GitException::class);
        $this->gitWrapper->setPrivateKey(self::BAD_KEY);
    }

    public function testSetPrivateKeyWrapperError(): void
    {
        $this->expectException(GitException::class);
        $this->gitWrapper->setPrivateKey('./tests/id_rsa', 22, self::BAD_WRAPPER);
    }

    public function testUnsetPrivateKey(): void
    {
        // Set and unset the private key.
        $key = './tests/id_rsa';
        $sshWrapper = './tests/dummy-wrapper.sh';
        $this->gitWrapper->setPrivateKey($key, 22, $sshWrapper);
        $this->gitWrapper->unsetPrivateKey();

        self::assertNull($this->gitWrapper->getEnvVar('GIT_SSH_KEY'));
        self::assertNull($this->gitWrapper->getEnvVar('GIT_SSH_PORT'));
        self::assertNull($this->gitWrapper->getEnvVar('GIT_SSH'));
    }

    public function testGitCommand(): void
    {
        $version = $this->gitWrapper->git('--version');
        self::assertGitVersion($version);
    }

    public function testGitCommandWithMultipleArguments(): void
    {
        $options = $this->gitWrapper->git('--version --build-options');
        self::assertNotEmpty($options);
    }

    public function testGitCommandError(): void
    {
        $this->expectException(GitException::class);
        $this->runBadCommand();
    }

    public function testGitRun(): void
    {
        $command = new GitCommand();
        $command->setFlag('version');
        // Directory has to exist
        $command->setDirectory('./tests');

        $version = $this->gitWrapper->run($command);
        self::assertGitVersion($version);
    }

    public function testGitRunDirectoryError(): void
    {
        $this->expectException(GitException::class);
        $command = new GitCommand();
        $command->setFlag('version');
        $command->setDirectory('/some/bad/directory');

        $this->gitWrapper->run($command);
    }

    public function testWrapperExecutable(): void
    {
        $sshWrapper = \dirname(__DIR__).'/bin/git-ssh-wrapper.sh';
        self::assertTrue(\is_executable($sshWrapper));
    }

    public function testWorkingCopy(): void
    {
        $directory = './'.$this->randomString();
        $git = $this->gitWrapper->workingCopy($directory);

        self::assertInstanceOf(GitWorkingCopy::class, $git);
        self::assertSame($directory, $git->getDirectory());
        self::assertSame($this->gitWrapper, $git->getWrapper());
    }

    public function testCloneWithoutDirectory(): void
    {
        $this->createRegisterAndReturnBypassEventSubscriber();
        $git = $this->gitWrapper->cloneRepository('file:///'.$this->randomString());
        self::assertTrue($git->isCloned());
    }
}
