<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class Marks
{
    public const REGISTRY = 'https://raw.githubusercontent.com/adzercms/adzercms/verified/verified.json';
    public const PASSED = 'passed';

    private const TIMEOUT = 10.0;

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /** @return array<string, list<string>>|null */
    public function passed(): ?array
    {
        try {
            $registry = $this->httpClient->request('GET', self::REGISTRY, ['timeout' => self::TIMEOUT])->toArray();
        } catch (ExceptionInterface) {
            return null;
        }

        $passed = [];
        foreach ($registry as $name => $versions) {
            $references = \is_array($versions)
                ? array_keys(array_filter($versions, static fn (mixed $record): bool => \is_array($record) && self::PASSED === ($record['outcome'] ?? null)))
                : [];
            if ([] !== $references) {
                $passed[(string) $name] = array_map(strval(...), $references);
            }
        }

        return $passed;
    }
}
