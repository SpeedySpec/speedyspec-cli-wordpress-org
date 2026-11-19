<?php

declare(strict_types=1);

namespace SpeedySpec\CliWordPressOrg;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Asks wordpress.org's plugin and theme directories about one slug.
 *
 * The base is wordpress.org's API unless the tests (or a mirror) give another, through
 * `SPEEDYSPEC_WORDPRESS_ORG_API`.
 */
final class Api
{
    public function __construct(
        private ClientInterface $client,
        public readonly string $base = 'https://api.wordpress.org',
    ) {
    }

    /**
     * Builds the API from the environment, with a client that gives up after 30 seconds.
     */
    public static function fromEnvironment(): self
    {
        $base = getenv('SPEEDYSPEC_WORDPRESS_ORG_API') ?: 'https://api.wordpress.org';

        return new self(new \GuzzleHttp\Client(['timeout' => 30, 'connect_timeout' => 10]), rtrim($base, '/'));
    }

    /**
     * Fetches the directory's information about a plugin or theme, with every version's download link.
     *
     * @return ?array<string, mixed>
     *   The decoded answer, or null when the directory does not know the slug.
     */
    public function information(string $kind, string $slug): ?array
    {
        $what = $kind === 'theme' ? 'themes' : 'plugins';
        $action = $kind === 'theme' ? 'theme_information' : 'plugin_information';
        $url = sprintf(
            '%s/%s/info/1.2/?action=%s&request[slug]=%s&request[fields][versions]=1',
            $this->base,
            $what,
            $action,
            rawurlencode($slug),
        );
        $broken = static fn (string $why): \RuntimeException
            => new \RuntimeException(sprintf('wordpress.org: the %s %s: %s', $kind, $slug, $why));
        try
        {
            $response = $this->client->request('GET', $url, ['http_errors' => false]);
        }
        catch (GuzzleException $e)
        {
            throw $broken($e->getMessage());
        }
        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);
        if (is_array($body) && isset($body['error']) && ($status === 200 || $status === 404))
        {
            return null;
        }
        if ($status !== 200)
        {
            throw $broken(sprintf('the directory answered %d', $status));
        }
        if (!is_array($body))
        {
            throw $broken('the answer is not JSON');
        }

        return $body;
    }
}
