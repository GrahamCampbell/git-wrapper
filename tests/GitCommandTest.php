<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\Tests;

use GrahamCampbell\GitWrapper\GitCommand;

final class GitCommandTest extends AbstractGitWrapperTestCase
{
    public function testCommand(): void
    {
        $command = $this->randomString();
        $argument = $this->randomString();
        $flag = $this->randomString();
        $optionName = $this->randomString();
        $optionValue = $this->randomString();

        $git = new GitCommand($command);
        $git->addArgument($argument);
        $git->setFlag($flag);
        $git->setOption($optionName, $optionValue);

        $expected = [$command, \sprintf('--%s', $flag), \sprintf('--%s', $optionName), $optionValue, $argument];

        self::assertSame($expected, $git->getCommandLine());
    }

    public function testMultiOption(): void
    {
        $git = new GitCommand('test-command');
        $git->setOption('test-arg', [true, true]);

        $expected = ['test-command', '--test-arg', '--test-arg'];
        $commandLine = $git->getCommandLine();

        self::assertSame($expected, $commandLine);
    }
}
