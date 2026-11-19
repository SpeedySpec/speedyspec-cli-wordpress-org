<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use SpeedySpec\Cli\Contracts\ExtensionArchive;
use SpeedySpec\CliWordPressOrg\Api;
use SpeedySpec\CliWordPressOrg\Source;

/**
 * A source over a mock handler answering with `$responses`; `$requests` collects what was asked.
 */
function source(array $responses, array &$requests = [], string $base = 'https://api.wordpress.org'): Source
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($requests));

    return new Source(new Api(new Client(['handler' => $stack]), $base));
}

function answer(string $name, int $status = 200): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], (string) file_get_contents(__DIR__ . "/fixtures/{$name}.json"));
}

it('gives the latest plugin', function ()
{
    $requests = [];
    $archive = source([answer('plugin-akismet')], $requests)->resolve('plugin', 'akismet', null);
    expect($archive)->toEqual(new ExtensionArchive('plugin', 'akismet', '5.3.5', 'https://downloads.wordpress.org/plugin/akismet.5.3.5.zip'))
        ->and((string) $requests[0]['request']->getUri())
        ->toBe('https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=akismet&request%5Bfields%5D%5Bversions%5D=1');
});

it('gives a given plugin version', function ()
{
    $archive = source([answer('plugin-akismet')])->resolve('plugin', 'akismet', '5.3.4');
    expect($archive?->version)->toBe('5.3.4')
        ->and($archive?->url)->toBe('https://downloads.wordpress.org/plugin/akismet.5.3.4.zip');
});

it('has no unknown version', function ()
{
    expect(source([answer('plugin-akismet')])->resolve('plugin', 'akismet', '0.1'))->toBeNull();
});

it('has no plugin or theme wordpress.org does not know', function (string $kind, string $fixture, int $status)
{
    expect(source([answer($fixture, $status)])->resolve($kind, 'nope', null))->toBeNull();
})->with([
    'plugin, 404' => ['plugin', 'plugin-not-found', 404],
    'plugin, 200' => ['plugin', 'plugin-not-found', 200],
    'theme' => ['theme', 'theme-not-found', 200],
]);

it('gives the latest theme', function ()
{
    $requests = [];
    $archive = source([answer('theme-twentytwentyfive')], $requests)->resolve('theme', 'twentytwentyfive', null);
    expect($archive)->toEqual(new ExtensionArchive('theme', 'twentytwentyfive', '1.2', 'https://downloads.wordpress.org/theme/twentytwentyfive.1.2.zip'))
        ->and((string) $requests[0]['request']->getUri())
        ->toStartWith('https://api.wordpress.org/themes/info/1.2/?action=theme_information&request%5Bslug%5D=twentytwentyfive');
});

it('names wordpress.org and the slug when the answer is broken', function (Response $response)
{
    source([$response])->resolve('plugin', 'akismet', null);
})->with([
    'not json' => [new Response(200, [], '<html>')],
    'no download link' => [new Response(200, [], '{"version":"1.0"}')],
    'server error' => [new Response(500, [], 'oops')],
])->throws(RuntimeException::class, 'wordpress.org: the plugin akismet');

it('refuses a download link that is not https', function ()
{
    source([new Response(200, [], '{"version":"1.0","download_link":"http://evil.example/a.zip"}')])->resolve('plugin', 'akismet', null);
})->throws(RuntimeException::class, 'https');

it('allows a plain http link only under a plain http test base', function ()
{
    $requests = [];
    $archive = source(
        [new Response(200, [], '{"version":"1.0","download_link":"http://127.0.0.1:9/akismet.zip"}')],
        $requests,
        'http://127.0.0.1:9',
    )->resolve('plugin', 'akismet', null);
    expect($archive?->url)->toBe('http://127.0.0.1:9/akismet.zip');
});
