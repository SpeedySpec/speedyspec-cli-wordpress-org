<?php

declare(strict_types=1);

namespace SpeedySpec\CliWordPressOrg;

use SpeedySpec\Cli\Contracts\ExtensionArchive;

/**
 * Finds the archive of a wordpress.org plugin or theme, for the speedyspec CLI's `plugin` and `theme` commands.
 *
 * wordpress.org publishes no checksum for its archives, so none is given; the download is over https.
 */
final class Source
{
    public function __construct(private Api $api)
    {
    }

    /**
     * Gives the archive of the latest version, or of `$version`, or null when wordpress.org does not have it.
     */
    public function resolve(string $kind, string $slug, ?string $version): ?ExtensionArchive
    {
        $info = $this->api->information($kind, $slug);
        if ($info === null)
        {
            return null;
        }
        if ($version === null)
        {
            $version = is_string($info['version'] ?? null) ? $info['version'] : null;
            $url = $info['download_link'] ?? null;
        }
        else
        {
            $url = is_array($info['versions'] ?? null) ? ($info['versions'][$version] ?? null) : null;
            if ($url === null)
            {
                return null;
            }
        }
        if (!is_string($version) || $version === '' || !is_string($url) || $url === '')
        {
            throw new \RuntimeException(sprintf('wordpress.org: the %s %s: the answer has no version or download link', $kind, $slug));
        }
        $plainAllowed = str_starts_with($this->api->base, 'http://') && str_starts_with($url, $this->api->base . '/');
        if (!str_starts_with($url, 'https://') && !$plainAllowed)
        {
            throw new \RuntimeException(sprintf('wordpress.org: the %s %s: the download link is not https', $kind, $slug));
        }

        return new ExtensionArchive($kind, $slug, $version, $url);
    }
}
