<?php

declare(strict_types=1);

/*
 * The speedyspec CLI source plugin for wordpress.org: the plugin and theme directories, as an ExtensionSource for the
 * CLI's `plugin` and `theme` commands. It talks to wordpress.org's API with Guzzle, which the CLI installs from this
 * plugin's composer.lock and autoloads.
 */

use SpeedySpec\Cli\Contracts\ExtensionSource;
use SpeedySpec\CliWordPressOrg\Api;
use SpeedySpec\CliWordPressOrg\Source;
use SpeedySpec\Hooks\Hook;

Hook::add('speedyspec_extension_sources', static function (array $sources): array
{
    $source = new Source(Api::fromEnvironment());
    $sources[] = new ExtensionSource('wordpress.org', $source->resolve(...));

    return $sources;
});
