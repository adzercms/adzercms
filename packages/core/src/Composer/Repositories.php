<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Composer;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class Repositories
{
    public const PACKAGIST = 'https://repo.packagist.org';

    private const ROOT = '/packages.json';
    private const NAME = '%package%';
    private const QUERY = '%query%';
    private const TYPE = '%type%';
    private const BRANCHES = '~dev';
    private const TIMEOUT = 10.0;

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /** @return list<string>|null */
    public function names(string $repository, string $type): ?array
    {
        $root = $this->root($repository);
        if (null === $root) {
            return null;
        }

        $search = $root['search'] ?? null;
        if (!\is_string($search) || !str_contains($search, self::TYPE)) {
            $available = $root['available-packages'] ?? null;

            return \is_array($available) ? array_values(array_unique(array_filter($available, \is_string(...)))) : [];
        }

        $names = [];
        $seen = [];
        $page = self::absolute($repository, str_replace([self::QUERY, self::TYPE], ['', rawurlencode($type)], $search));
        try {
            while (null !== $page && !isset($seen[$page])) {
                $seen[$page] = true;
                $answer = $this->get($page)->toArray();
                foreach (\is_array($answer['results'] ?? null) ? $answer['results'] : [] as $result) {
                    if (\is_array($result) && \is_string($result['name'] ?? null)) {
                        $names[] = $result['name'];
                    }
                }
                $page = \is_string($answer['next'] ?? null) ? self::absolute($repository, $answer['next']) : null;
            }
        } catch (ExceptionInterface) {
            return null;
        }

        return array_values(array_unique($names));
    }

    /**
     * @param list<string> $repositories
     * @param list<string> $names
     */
    public function lookup(array $repositories, array $names): Lookup
    {
        $pending = array_fill_keys($names, true);
        $packages = [];
        $sources = [];
        $down = [];

        foreach ($repositories as $repository) {
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
                    $sources[$name] = $repository;
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

        return new Lookup($packages, array_values(array_unique($down)), array_intersect_key($sources, array_filter($packages)));
    }

    private function template(string $repository): ?string
    {
        $template = $this->root($repository)['metadata-url'] ?? null;

        return \is_string($template) && str_contains($template, self::NAME) ? self::absolute($repository, $template) : null;
    }

    /** @return array<mixed>|null */
    private function root(string $repository): ?array
    {
        try {
            return $this->get(rtrim($repository, '/').self::ROOT)->toArray();
        } catch (ExceptionInterface) {
            return null;
        }
    }

    private static function absolute(string $repository, string $url): string
    {
        return str_starts_with($url, '/') ? self::origin($repository).$url : $url;
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
