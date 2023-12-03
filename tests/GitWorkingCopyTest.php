<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\Tests;

use GrahamCampbell\GitWrapper\Exception\GitException;
use GrahamCampbell\GitWrapper\GitBranches;
use GrahamCampbell\GitWrapper\GitWorkingCopy;
use GrahamCampbell\GitWrapper\Tests\EventSubscriber\Source\TestGitOutputEventSubscriber;
use GrahamCampbell\GitWrapper\Tests\Source\StreamSuppressFilter;
use GrahamCampbell\GitWrapper\ValueObject\CommandName;
use Iterator;
use OndraM\CiDetector\CiDetector;
use Symfony\Component\Process\Process;

final class GitWorkingCopyTest extends AbstractGitWrapperTestCase
{
    /**
     * @var string
     */
    private const REMOTE_REPO_DIR = __DIR__.'/build/tests/remote';

    /**
     * @var string
     */
    private const DIRECTORY = __DIR__.'/build/tests/wc_init';

    /**
     * @var string
     */
    private const PATCH = <<<CODE_SAMPLE
diff --git a/FileCreatedByPatch.txt b/FileCreatedByPatch.txt
new file mode 100644
index 0000000..dfe437b
--- /dev/null
+++ b/FileCreatedByPatch.txt
@@ -0,0 +1 @@
+contents

CODE_SAMPLE;

    /**
     * @var string
     */
    private $currentUserName;

    /**
     * @var string
     */
    private $currentUserEmail;

    /**
     * Creates and initializes the local repository used for testing.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create the local repository
        $this->gitWrapper->init(self::REPO_DIR, [
            'bare' => true,
        ]);

        // cleanup, because tearDown is not working here
        if ($this->filesystem->exists(self::DIRECTORY)) {
            $this->filesystem->remove(self::DIRECTORY);
        }

        $git = $this->gitWrapper->cloneRepository('file://'.self::REPO_DIR, self::DIRECTORY);

        $this->storeCurrentGitUserEmail($git);

        $git->config('user.email', self::CONFIG_EMAIL);
        $git->config('user.name', self::CONFIG_NAME);

        // Create the initial structure.
        $this->filesystem->dumpFile(self::DIRECTORY.'/change.me', "unchanged\n");
        $this->filesystem->touch(self::DIRECTORY.'/move.me');
        $this->filesystem->mkdir(self::DIRECTORY.'/a.directory', 0755);
        $this->filesystem->touch(self::DIRECTORY.'/a.directory/remove.me');

        // Initial commit.
        $git->add('*');
        $git->commit('Initial commit.');
        $git->push('origin', 'master', [
            'u' => true,
        ]);

        // Create a branch, add a file.
        $branch = 'test-branch';
        $this->filesystem->dumpFile(self::DIRECTORY.'/branch.txt', $branch.\PHP_EOL);
        $git->checkoutNewBranch($branch);
        $git->add('branch.txt');
        $git->commit('Committed testing branch.');
        $git->push('origin', $branch, [
            'u' => true,
        ]);

        // Create a tag of the branch.
        $git->tag('test-tag');
        $git->pushTags();

        $this->filesystem->remove(self::DIRECTORY);
    }

    /**
     * Removes the local repository.
     */
    protected function tearDown(): void
    {
        $this->restoreCurrentGitUserEmail();

        $this->filesystem->remove(self::REPO_DIR);
        $this->filesystem->remove(self::DIRECTORY);
        $this->filesystem->remove(self::WORKING_DIR);
        $this->filesystem->remove(self::REMOTE_REPO_DIR);
    }

    /**
     * Clones the local repo and returns an initialized GitWorkingCopy object.
     *
     * @param string $directory the directory that the repository is being cloned to, defaults to "test/wc"
     */
    public function getWorkingCopy(string $directory = self::WORKING_DIR): GitWorkingCopy
    {
        $git = $this->gitWrapper->workingCopy($directory);
        $git->cloneRepository('file://'.self::REPO_DIR);
        $git->config('user.email', self::CONFIG_EMAIL);
        $git->config('user.name', self::CONFIG_NAME);

        return $git;
    }

    public function testIsCloned(): void
    {
        $git = $this->getWorkingCopy();
        self::assertTrue($git->isCloned());
    }

    public function testOutput(): void
    {
        $git = $this->getWorkingCopy();

        // Test getting output of a simple status command.
        $output = $git->status();
        self::assertStringContainsString('nothing to commit', $output);
    }

    public function testHasChanges(): void
    {
        $gitWorkingCopy = $this->getWorkingCopy();
        self::assertFalse($gitWorkingCopy->hasChanges());

        $this->filesystem->dumpFile(self::WORKING_DIR.'/change.me', "changed\n");
        self::assertTrue($gitWorkingCopy->hasChanges());
    }

    public function testGetBranches(): void
    {
        $git = $this->getWorkingCopy();
        $branches = $git->getBranches();

        self::assertTrue($branches instanceof GitBranches);

        // Dumb count checks. Is there a better way to do this?
        $allBranches = 0;
        foreach ($branches as $branch) {
            ++$allBranches;
        }

        self::assertSame($allBranches, 4);

        $remoteBranches = $branches->remote();
        self::assertSame(\count($remoteBranches), 3);
    }

    public function testFetchAll(): void
    {
        $git = $this->getWorkingCopy();

        self::assertIsString($git->fetchAll());
    }

    public function testGitAdd(): void
    {
        $git = $this->getWorkingCopy();
        $this->filesystem->touch(self::WORKING_DIR.'/add.me');

        $git->add('add.me');

        if (\method_exists($this, 'assertMatchesRegularExpression')) {
            self::assertMatchesRegularExpression('#A\\s+add\\.me#s', $git->getStatus());
        } else {
            self::assertRegExp('#A\\s+add\\.me#s', $git->getStatus());
        }
    }

    public function testGitApply(): void
    {
        $git = $this->getWorkingCopy();
        $this->filesystem->dumpFile(self::WORKING_DIR.'/patch.txt', self::PATCH);
        $git->apply('patch.txt');

        if (\method_exists($this, 'assertMatchesRegularExpression')) {
            self::assertMatchesRegularExpression('#\?\?\\s+FileCreatedByPatch\\.txt#s', $git->getStatus());
        } else {
            self::assertRegExp('#\?\?\\s+FileCreatedByPatch\\.txt#s', $git->getStatus());
        }

        self::assertStringEqualsFile(self::WORKING_DIR.'/FileCreatedByPatch.txt', "contents\n");
    }

    public function testGitRm(): void
    {
        $git = $this->getWorkingCopy();
        $git->rm('a.directory/remove.me');
        self::assertFalse(\is_file(self::WORKING_DIR.'/a.directory/remove.me'));
    }

    public function testGitMv(): void
    {
        $git = $this->getWorkingCopy();
        $git->mv('move.me', 'moved');

        self::assertFalse(\is_file(self::WORKING_DIR.'/move.me'));
        self::assertTrue(\is_file(self::WORKING_DIR.'/moved'));
    }

    public function testGitBranch(): void
    {
        $branchName = $this->randomString();

        // Create the branch.
        $git = $this->getWorkingCopy();
        $git->branch($branchName);

        // Get list of local branches.
        $branches = $git->branch();

        // Check that our branch is there.
        self::assertStringContainsString($branchName, $branches);
    }

    public function testGitLog(): void
    {
        $git = $this->getWorkingCopy();
        $output = $git->log();
        self::assertStringContainsString('Initial commit.', $output);
    }

    public function testGitConfig(): void
    {
        $git = $this->getWorkingCopy();
        $email = \rtrim($git->config('user.email'));
        self::assertSame('testing@email.com', $email);
    }

    public function testGitTag(): void
    {
        $tag = $this->randomString();

        $git = $this->getWorkingCopy();
        $git->tag($tag);
        $git->pushTag($tag);

        $tags = $git->tag();
        self::assertStringContainsString($tag, $tags);
    }

    public function testGitClean(): void
    {
        $git = $this->getWorkingCopy();

        $this->filesystem->dumpFile(self::WORKING_DIR.'/untracked.file', "untracked\n");

        $result = $git->clean('-d', '-f');

        self::assertSame('Removing untracked.file'.\PHP_EOL, $result);

        $expectedFileNameToExist = self::WORKING_DIR.'/untracked.file';
        // PHPUnit 10+ future compact
        if (\method_exists($this, 'assertFileDoesNotExist')) {
            self::assertFileDoesNotExist($expectedFileNameToExist);
        } else {
            self::assertFileNotExists($expectedFileNameToExist);
        }
    }

    public function testGitReset(): void
    {
        $git = $this->getWorkingCopy();
        $this->filesystem->dumpFile(self::WORKING_DIR.'/change.me', "changed\n");

        self::assertTrue($git->hasChanges());
        $git->reset([
            'hard' => true,
        ]);
        self::assertFalse($git->hasChanges());
    }

    public function testGitStatus(): void
    {
        $git = $this->getWorkingCopy();
        $this->filesystem->dumpFile(self::WORKING_DIR.'/change.me', "changed\n");
        $output = $git->status([
            's' => true,
        ]);
        self::assertSame(" M change.me\n", $output);
    }

    public function testGitPull(): void
    {
        $git = $this->getWorkingCopy();
        $output = $git->pull();

        if (\method_exists($this, 'assertMatchesRegularExpression')) {
            self::assertMatchesRegularExpression("/^Already up[- ]to[ -]date\.$/", \rtrim($output));
        } else {
            self::assertRegExp("/^Already up[- ]to[ -]date\.$/", \rtrim($output));
        }
    }

    public function testGitArchive(): void
    {
        $archiveName = \uniqid().'.tar';
        $archivePath = '/tmp/'.$archiveName;
        $git = $this->getWorkingCopy();
        $output = $git->archive('HEAD', [
            'o' => $archivePath,
        ]);
        self::assertSame('', $output);
        self::assertFileExists($archivePath);
    }

    /**
     * This tests an odd case where sometimes even though a command fails and an exception is thrown
     * the result of Process::getErrorOutput() is empty because the output is sent to STDOUT instead of STDERR. So
     * there's a code path in GitProcess::run() to check the output from Process::getErrorOutput() and if it's empty use
     * the result from Process::getOutput() instead.
     */
    public function testGitPullErrorWithEmptyErrorOutput(): void
    {
        $git = $this->getWorkingCopy();

        $this->expectExceptionMessageMatches("/Your branch is up[- ]to[- ]date with 'origin\\/master'./");
        $git->commit('Nothing to commit so generates an error / not error');
    }

    public function testGitDiff(): void
    {
        $git = $this->getWorkingCopy();
        $this->filesystem->dumpFile(self::WORKING_DIR.'/change.me', "changed\n");
        $output = $git->diff();

        self::assertStringStartsWith('diff --git a/change.me b/change.me', $output);
    }

    public function testGitGrep(): void
    {
        $git = $this->getWorkingCopy();
        $output = $git->grep('changed', '--', '*.me');

        self::assertStringStartsWith('change.me', $output);
    }

    public function testGitShow(): void
    {
        $git = $this->getWorkingCopy();
        $output = $git->show('test-tag');

        self::assertStringStartsWith('commit ', $output);
    }

    public function testGitRemote(): void
    {
        $git = $this->getWorkingCopy();
        $output = $git->remote();
        self::assertSame('origin', \rtrim($output));
    }

    public function testRebase(): void
    {
        $git = $this->getWorkingCopy();
        $git->checkout('test-branch');

        $output = $git->rebase('test-branch', [
            'onto' => 'master',
        ]);

        // nothing to rebase
        self::assertSame('', $output);
    }

    public function testMerge(): void
    {
        $git = $this->getWorkingCopy();
        $git->checkout('test-branch');
        $git->checkout('master');

        $output = $git->merge('test-branch');

        self::assertStringStartsWith('Updating ', $output);
    }

    public function testOutputListener(): void
    {
        $git = $this->getWorkingCopy();

        $listener = new TestGitOutputEventSubscriber();
        $git->getWrapper()
            ->addOutputEventSubscriber($listener);

        $git->status();
        $event = $listener->getLastEvent();

        $expectedType = Process::OUT;
        self::assertSame($expectedType, $event->getType());

        self::assertStringContainsString('nothing to commit', $event->getBuffer());
    }

    public function testLiveOutput(): void
    {
        $git = $this->getWorkingCopy();

        // Capture output written to STDOUT and use echo so we can suppress and
        // capture it using normal output buffering.
        \stream_filter_register('suppress', StreamSuppressFilter::class);
        /** @var resource $stdoutSuppress */
        $stdoutSuppress = \stream_filter_append(\STDOUT, 'suppress');

        $git->getWrapper()
            ->streamOutput(true);
        \ob_start();
        $git->status();
        $contents = \ob_get_contents();
        \ob_end_clean();

        /** @var string $contents */
        self::assertStringContainsString('nothing to commit', $contents);

        $git->getWrapper()
            ->streamOutput(false);
        \ob_start();
        $git->status();
        $empty = \ob_get_contents();
        \ob_end_clean();

        self::assertEmpty($empty);

        \stream_filter_remove($stdoutSuppress);
    }

    public function testCommitWithAuthor(): void
    {
        $git = $this->getWorkingCopy();
        $this->filesystem->dumpFile(self::WORKING_DIR.'/commit.txt', "created\n");

        self::assertTrue($git->hasChanges());

        $git->add('commit.txt');
        $git->commit([
            'm' => 'Committed testing branch.',
            'a' => true,
            'author' => 'test <test@lol.com>',
        ]);

        $output = $git->log();
        self::assertStringContainsString('Committed testing branch', $output);
        self::assertStringContainsString('Author: test <test@lol.com>', $output);
    }

    public function testIsTracking(): void
    {
        $git = $this->getWorkingCopy();

        // The master branch is a remote tracking branch.
        self::assertTrue($git->isTracking());

        // Create a new branch without pushing it, so it does not have a remote.
        $git->checkoutNewBranch('non-tracking-branch');
        self::assertFalse($git->isTracking());
    }

    public function testIsUpToDate(): void
    {
        $git = $this->getWorkingCopy();

        // The default test branch is up-to-date with its remote.
        $git->checkout('test-branch');
        self::assertTrue($git->isUpToDate());

        // If we create a new commit, we are still up-to-date.
        $this->filesystem->dumpFile(self::WORKING_DIR.'/commit.txt', "created\n");
        $git->add('commit.txt');
        $git->commit([
            'm' => '1 commit ahead. Still up-to-date.',
            'a' => true,
        ]);
        self::assertTrue($git->isUpToDate());

        // Reset the branch to its first commit, so that it is 1 commit behind.
        $git->reset('HEAD~2', [
            'hard' => true,
        ]);

        self::assertFalse($git->isUpToDate());
    }

    public function testIsAhead(): void
    {
        $git = $this->getWorkingCopy();

        // The default master branch is not ahead of the remote.
        self::assertFalse($git->isAhead());

        // Create a new commit, so that the branch is 1 commit ahead.
        $this->filesystem->dumpFile(self::WORKING_DIR.'/commit.txt', "created\n");
        $git->add('commit.txt');
        $git->commit([
            'm' => '1 commit ahead.',
        ]);

        self::assertTrue($git->isAhead());
    }

    public function testIsBehind(): void
    {
        $git = $this->getWorkingCopy();

        // The default test branch is not behind the remote.
        $git->checkout('test-branch');
        self::assertFalse($git->isBehind());

        // Reset the branch to its parent commit, so that it is 1 commit behind.
        $git->reset('HEAD^', [
            'hard' => true,
        ]);

        self::assertTrue($git->isBehind());
    }

    public function testNeedsMerge(): void
    {
        $git = $this->getWorkingCopy();

        // The default test branch does not need to be merged with the remote.
        $git->checkout('test-branch');
        self::assertFalse($git->needsMerge());

        // Reset the branch to its parent commit, so that it is 1 commit behind.
        // This does not require the branches to be merged.
        $git->reset('HEAD^', [
            'hard' => true,
        ]);
        self::assertFalse($git->needsMerge());

        // Create a new commit, so that the branch is also 1 commit ahead. Now a
        // merge is needed.
        $this->filesystem->dumpFile(self::WORKING_DIR.'/commit.txt', "created\n");
        $git->add('commit.txt');
        $git->commit([
            'm' => '1 commit ahead.',
        ]);
        self::assertTrue($git->needsMerge());

        // Merge the remote, so that we are no longer behind, but only ahead. A
        // merge should then no longer be needed.
        $git->merge('@{u}');
        self::assertFalse($git->needsMerge());
    }

    /**
     * @dataProvider addRemoteDataProvider()
     *
     * @param mixed[] $options
     * @param mixed[] $asserts
     */
    public function testAddRemote(array $options, array $asserts): void
    {
        $this->createRemote();
        $git = $this->getWorkingCopy();
        $git->addRemote('remote', 'file://'.self::REMOTE_REPO_DIR, $options);
        self::assertTrue($git->hasRemote('remote'));
        foreach ($asserts as $method => $parameters) {
            \array_unshift($parameters, $git);
            $this->{$method}(...$parameters);
        }
    }

    /**
     * @return mixed[][]
     */
    public static function addRemoteDataProvider(): array
    {
        return [
            // Test default options: nothing is fetched.
            [
                [],
                [
                    'assertNoRemoteBranches' => [['remote/master', 'remote/remote-branch']],
                    'assertNoGitTag' => ['remote-tag'],
                    'assertNoRemoteMaster' => [],
                ],
            ],
            // The fetch option should retrieve the remote branches and tags,
            // but not set up a master branch.
            [
                [
                    '-f' => true,
                ],
                [
                    'assertRemoteBranches' => [['remote/master', 'remote/remote-branch']],
                    'assertGitTag' => ['remote-tag'],
                    'assertNoRemoteMaster' => [],
                ],
            ],
            // The --no-tags options should omit importing tags.
            [
                [
                    '-f' => true,
                    '--no-tags' => true,
                ],
                [
                    'assertRemoteBranches' => [['remote/master', 'remote/remote-branch']],
                    'assertNoGitTag' => ['remote-tag'],
                    'assertNoRemoteMaster' => [],
                ],
            ],
            // The -t option should limit the remote branches that are imported.
            // By default git fetch only imports the tags of the fetched
            // branches. No tags were added to the master branch, so the tag
            // should not be imported.
            [
                [
                    '-f' => true,
                    '-t' => ['master'],
                ],
                [
                    'assertRemoteBranches' => [['remote/master']],
                    'assertNoRemoteBranches' => [['remote/remote-branch']],
                    'assertNoGitTag' => ['remote-tag'],
                    'assertNoRemoteMaster' => [],
                ],
            ],
            // The -t option in combination with the --tags option should fetch
            // all tags, so now the tag should be there.
            [
                [
                    '-f' => true,
                    '-t' => ['master'],
                    '--tags' => true,
                ],
                [
                    'assertRemoteBranches' => [['remote/master']],
                    'assertNoRemoteBranches' => [['remote/remote-branch']],
                    'assertGitTag' => ['remote-tag'],
                    'assertNoRemoteMaster' => [],
                ],
            ],
            // The -m option should set up a remote master branch.
            [
                [
                    '-f' => true,
                    '-m' => 'remote-branch',
                ],
                [
                    'assertRemoteBranches' => [['remote/master', 'remote/remote-branch']],
                    'assertGitTag' => ['remote-tag'],
                    'assertRemoteMaster' => [],
                ],
            ],
        ];
    }

    public function testRemoveRemote(): void
    {
        $this->createRemote();
        $git = $this->getWorkingCopy();
        $git->addRemote('remote', 'file://'.self::REMOTE_REPO_DIR);
        self::assertTrue($git->hasRemote('remote'));

        // The remote should be gone after it is removed.
        $git->removeRemote('remote');
        self::assertFalse($git->hasRemote('remote'));
    }

    public function testHasRemote(): void
    {
        $this->createRemote();
        $git = $this->getWorkingCopy();
        // The remote should be absent before it is added.
        self::assertFalse($git->hasRemote('remote'));
        $git->addRemote('remote', 'file://'.self::REMOTE_REPO_DIR);
        // The remote should be present after it is added.
        self::assertTrue($git->hasRemote('remote'));
    }

    public function testGetRemote(): void
    {
        $this->createRemote();
        $git = $this->getWorkingCopy();
        $path = 'file://'.self::REMOTE_REPO_DIR;
        $git->addRemote('remote', $path);

        // Both the 'fetch' and 'push' URIs should be populated and point to the
        // correct location.
        $remote = $git->getRemote('remote');
        self::assertSame($path, $remote['fetch']);
        self::assertSame($path, $remote['push']);
    }

    public function testGetRemotes(): void
    {
        $this->createRemote();
        $git = $this->getWorkingCopy();

        // Since our working copy is a clone, the 'origin' remote should be
        // present by default.
        $remotes = $git->getRemotes();
        self::assertArrayHasKey('origin', $remotes);
        self::assertArrayNotHasKey('remote', $remotes);

        // If we add a second remote, both it and the 'origin' remotes should be
        // present.
        $git->addRemote('remote', 'file://'.self::REMOTE_REPO_DIR);
        $remotes = $git->getRemotes();
        self::assertArrayHasKey('origin', $remotes);
        self::assertArrayHasKey('remote', $remotes);
    }

    /**
     * @dataProvider getRemoteUrlDataProvider
     */
    public function testGetRemoteUrl(string $remote, string $operation, string $expectedRemoteUrl): void
    {
        $this->createRemote();
        $git = $this->getWorkingCopy();
        $git->addRemote('remote', 'file://'.self::REMOTE_REPO_DIR);

        $resolveRemoveUrl = $git->getRemoteUrl($remote, $operation);
        self::assertSame('file://'.$expectedRemoteUrl, $resolveRemoveUrl);
    }

    /**
     * @return Iterator<string[]>
     */
    public static function getRemoteUrlDataProvider(): Iterator
    {
        yield ['origin', 'fetch', self::REPO_DIR];
        yield ['origin', 'push', self::REPO_DIR];
        yield ['remote', 'fetch', self::REMOTE_REPO_DIR];
        yield ['remote', 'push', self::REMOTE_REPO_DIR];
    }

    protected function assertGitTag(GitWorkingCopy $gitWorkingCopy, string $tag): void
    {
        $gitWorkingCopy->run(CommandName::REV_PARSE, [$tag]);
    }

    protected function assertNoGitTag(GitWorkingCopy $gitWorkingCopy, string $tag): void
    {
        try {
            $gitWorkingCopy->run(CommandName::REV_PARSE, [$tag]);
        } catch (GitException $gitException) {
            // Expected result. The tag does not exist.
            return;
        }

        throw new GitException(\sprintf('Tag "%s" should not exist', $tag));
    }

    protected function assertRemoteMaster(GitWorkingCopy $gitWorkingCopy): void
    {
        $gitWorkingCopy->run(CommandName::REV_PARSE, ['remote/HEAD']);
    }

    protected function assertNoRemoteMaster(GitWorkingCopy $gitWorkingCopy): void
    {
        try {
            $gitWorkingCopy->run(CommandName::REV_PARSE, ['remote/HEAD']);
        } catch (GitException $gitException) {
            // Expected result. The remote master does not exist.
            return;
        }

        throw new GitException('Branch `master` should not exist');
    }

    /**
     * @param string[] $branches
     */
    protected function assertRemoteBranches(GitWorkingCopy $gitWorkingCopy, array $branches): void
    {
        foreach ($branches as $branch) {
            self::assertRemoteBranch($gitWorkingCopy, $branch);
        }
    }

    protected function assertRemoteBranch(GitWorkingCopy $gitWorkingCopy, string $branch): void
    {
        $branches = $gitWorkingCopy->getBranches()
            ->remote();
        self::assertArrayHasKey($branch, \array_flip($branches));
    }

    /**
     * @param string[] $branches
     */
    protected function assertNoRemoteBranches(GitWorkingCopy $gitWorkingCopy, array $branches): void
    {
        foreach ($branches as $branch) {
            self::assertNoRemoteBranch($gitWorkingCopy, $branch);
        }
    }

    protected function assertNoRemoteBranch(GitWorkingCopy $gitWorkingCopy, string $branch): void
    {
        $branches = $gitWorkingCopy->getBranches()
            ->remote();
        self::assertArrayNotHasKey($branch, \array_flip($branches));
    }

    private function createRemote(): void
    {
        // Create a clone of the working copy that will serve as a remote.
        $git = $this->gitWrapper->cloneRepository('file://'.self::REPO_DIR, self::REMOTE_REPO_DIR);
        $git->config('user.email', self::CONFIG_EMAIL);
        $git->config('user.name', self::CONFIG_NAME);

        // Make a change to the remote repo.
        $this->filesystem->dumpFile(self::REMOTE_REPO_DIR.'/remote.file', "remote code\n");
        $git->add('*');
        $git->commit('Remote change.');

        // Create a branch.
        $branch = 'remote-branch';
        $this->filesystem->dumpFile(self::REMOTE_REPO_DIR.'/remote-branch.txt', $branch.\PHP_EOL);
        $git->checkoutNewBranch($branch);
        $git->add('*');
        $git->commit('Commit remote testing branch.');

        // Create a tag.
        $git->tag('remote-tag');
    }

    private function storeCurrentGitUserEmail(GitWorkingCopy $gitWorkingCopy): void
    {
        // relevant only locally
        $ciDetector = new CiDetector();
        if ($ciDetector->isCiDetected()) {
            return;
        }

        // prevent local user.* override
        $this->currentUserEmail = $gitWorkingCopy->config('user.email');
        $this->currentUserName = $gitWorkingCopy->config('user.name');
    }

    private function restoreCurrentGitUserEmail(): void
    {
        // relevant only locally
        $ciDetector = new CiDetector();
        if ($ciDetector->isCiDetected()) {
            return;
        }

        $gitWorkingCopy = $this->gitWrapper->workingCopy(self::REPO_DIR);
        $gitWorkingCopy->config('user.email', $this->currentUserEmail);
        $gitWorkingCopy->config('user.name', $this->currentUserName);
    }
}
