<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\Tests\Strings;

use GrahamCampbell\GitWrapper\Strings\GitStrings;
use PHPUnit\Framework\TestCase;

final class GitStringsTest extends TestCase
{
    public function testParseRepositoryName(): void
    {
        $nameGit = GitStrings::parseRepositoryName('git@github.com:cpliakas/git-wrapper.git');
        self::assertSame($nameGit, 'git-wrapper');

        $nameHttps = GitStrings::parseRepositoryName('https://github.com/cpliakas/git-wrapper.git');
        self::assertSame($nameHttps, 'git-wrapper');
    }
}
