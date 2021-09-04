<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\Strings;

use GrahamCampbell\GitWrapper\Exception\GitException;

final class GitStrings
{
    /**
     * For example, passing the "git@github.com:GrahamCampbell/git-wrapper.git"
     * repository would return "git-wrapper".
     */
    public static function parseRepositoryName(string $repositoryUrl): string
    {
        $scheme = parse_url($repositoryUrl, PHP_URL_SCHEME);

        if ($scheme === null) {
            $parts = explode('/', $repositoryUrl);
            $path = end($parts);
        } else {
            $path = substr($repositoryUrl, strpos($repositoryUrl, ':') + 1);
        }

        /** @var string $path */
        return basename($path, '.git');
    }

    /**
     * @throws GitException
     */
    public static function split(string $subject, string $pattern): array
    {
        $result = @preg_split($pattern, $subject, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (preg_last_error() !== PREG_NO_ERROR) {
            throw new GitException(preg_last_error_msg());
        }

        return $result;
    }
}
