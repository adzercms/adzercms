<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class Repositories
{
    private const ROOT = '/packages.json';
    private const NAME = '%package%';
    private const BRANCHES = '~dev';
    private const TIMEOUT = 10.0;

    public function __construct(
        private HttpClientInterface $httpClient,
        private ExtensionsSettings $settings,
    ) {
    }

    /** @param list<string> $names */
    public function lookup(array $names): Lookup
    {
        $pending = array_fill_keys($names, true);
        $packages = [];
        $down = [];

        foreach ($this->settings->repositories() as $repository) {
            if ([] === $pending) {
                break;
            }

            $template = $this->template($repository);
            if (null === $template) {
                $down[] = $repository;
                $pending = [];
                break;
            }

            $responses = [];
            foreach (array_keys($pending) as $name) {
                try {
                    $responses[(string) $name] = $this->get(str_replace(self::NAME, (string) $name, $template));
                } catch (ExceptionInterface) {
                    unset($pending[$name]);
                    $down[$repository] = $repository;
                }
            }

            $branches = [];
            foreach ($responses as $name => $response) {
                try {
                    $status = $response->getStatusCode();
                    if (404 === $status) {
                        continue;
                    }

                    unset($pending[$name]);
                    if (200 !== $status) {
                        $down[$repository] = $repository;
                        continue;
                    }

                    $packages[$name] = Package::of($name, $response->toArray(false));
                    if (null === $packages[$name]) {
                        $branches[$name] = $this->get(str_replace(self::NAME, $name.self::BRANCHES, $template));
                    }
                } catch (ExceptionInterface) {
                    unset($pending[$name], $packages[$name]);
                    $down[$repository] = $repository;
                }
            }

            foreach ($branches as $name => $response) {
                try {
                    $status = $response->getStatusCode();
                    if (404 !== $status && 200 !== $status) {
                        unset($packages[$name]);
                        $down[$repository] = $repository;
                        continue;
                    }

                    $packages[$name] = 200 === $status ? Package::branch($name, $response->toArray(false)) : null;
                } catch (ExceptionInterface) {
                    unset($packages[$name]);
                    $down[$repository] = $repository;
                }
            }
        }

        foreach (array_keys($pending) as $name) {
            $packages[(string) $name] = null;
        }

        return new Lookup($packages, array_values(array_unique($down)));
    }

    private function template(string $repository): ?string
    {
        try {
            $root = $this->get(rtrim($repository, '/').self::ROOT)->toArray();
        } catch (ExceptionInterface) {
            return null;
        }

        $template = $root['metadata-url'] ?? null;
        if (!\is_string($template) || !str_contains($template, self::NAME)) {
            return null;
        }

        return str_starts_with($template, '/') ? self::origin($repository).$template : $template;
    }

    private function get(string $url): ResponseInterface
    {
        return $this->httpClient->request('GET', $url, ['timeout' => self::TIMEOUT]);
    }

    private static function origin(string $url): string
    {
        $port = parse_url($url, \PHP_URL_PORT);

        return \sprintf('%s://%s%s', parse_url($url, \PHP_URL_SCHEME), parse_url($url, \PHP_URL_HOST), \is_int($port) ? ':'.$port : '');
    }
}
