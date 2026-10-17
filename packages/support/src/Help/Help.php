<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Help;

use AdzerCMS\Core\Module\Snapshot;
use AdzerCMS\Support\HelpView;
use AdzerCMS\Support\SupportSettings;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @phpstan-type Article array{link: HelpLink, heading: string, haystack: string, pages: list<string>}
 * @phpstan-type Index array{home: string, categories: list<HelpLink>, articles: list<Article>}
 */
final class Help
{
    public const WINDOW_LIMIT = 15;
    public const REPLY_LIMIT = 8;
    public const SUBJECT_WORD = 3;

    private const SNAPSHOT = 'support.help';
    private const TIMEOUT = 5.0;
    private const MISSING_LIFETIME = 60;
    private const WORD_BREAK = '/[^\p{L}\p{N}]+/u';

    public function __construct(
        private readonly SupportSettings $support,
        private readonly HttpClientInterface $httpClient,
        private readonly Snapshot $snapshot,
    ) {
    }

    public function window(string $query, string $path): HelpAnswer
    {
        $address = $this->support->helpIndex();
        $index = $this->index($address);

        if (null === $index) {
            return new HelpAnswer(HelpView::Missing, self::origin($address));
        }

        $words = self::words($query);
        if ([] !== $words) {
            return new HelpAnswer(HelpView::Found, $index['home'], self::ranked($index['articles'], $words, self::WINDOW_LIMIT));
        }

        $here = array_values(array_filter(
            $index['articles'],
            static fn (array $article): bool => self::covers($article['pages'], $path),
        ));

        return [] === $here
            ? new HelpAnswer(HelpView::Categories, $index['home'], $index['categories'])
            : new HelpAnswer(HelpView::Page, $index['home'], array_map(static fn (array $article): HelpLink => $article['link'], $here));
    }

    /** @return list<HelpLink>|null */
    public function reply(string $query, string $subject): ?array
    {
        $index = $this->index($this->support->helpIndex());

        if (null === $index) {
            return null;
        }

        $words = self::words($query);
        if ([] !== $words) {
            return self::ranked($index['articles'], $words, self::REPLY_LIMIT);
        }

        return self::related($index['articles'], array_values(array_filter(
            self::words($subject),
            static fn (string $word): bool => mb_strlen($word) >= self::SUBJECT_WORD,
        )));
    }

    /** @return Index|null */
    private function index(string $address): ?array
    {
        if ('' === $address) {
            return null;
        }

        $held = $this->snapshot->read(self::SNAPSHOT);
        if (null !== $held && $address === ($held['rows']['source'] ?? null)) {
            return self::parsed($address, $held['rows']['raw'] ?? null);
        }

        $raw = $this->fetch($address);
        $this->snapshot->write(
            self::SNAPSHOT,
            ['source' => $address, 'raw' => $raw],
            null === $raw ? self::MISSING_LIFETIME : Snapshot::LIFETIME,
        );

        return self::parsed($address, $raw);
    }

    /** @return array<mixed>|null */
    private function fetch(string $address): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $address, ['timeout' => self::TIMEOUT]);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $data = json_decode($response->getContent(false), true);
        } catch (ExceptionInterface) {
            return null;
        }

        return \is_array($data) ? $data : null;
    }

    /** @return Index|null */
    private static function parsed(string $address, mixed $raw): ?array
    {
        if (!\is_array($raw) || !\is_array($raw['articles'] ?? null)) {
            return null;
        }

        $home = self::absolute($address, self::text($raw, 'url')) ?? self::origin($address);
        $titles = [];
        $categories = [];

        foreach (\is_array($raw['categories'] ?? null) ? $raw['categories'] : [] as $item) {
            $key = self::text($item, 'slug');
            $title = self::text($item, 'title');
            if ('' === $key || '' === $title) {
                continue;
            }

            $titles[$key] = $title;
            $categories[] = new HelpLink(
                $title,
                self::text($item, 'summary'),
                self::absolute($address, self::text($item, 'url')) ?? $home,
            );
        }

        $articles = [];

        foreach ($raw['articles'] as $item) {
            $title = self::text($item, 'title');
            $url = self::absolute($address, self::text($item, 'url'));
            if ('' === $title || null === $url) {
                continue;
            }

            $summary = self::text($item, 'summary');
            $articles[] = [
                'link' => new HelpLink($title, $summary, $url, $titles[self::text($item, 'category')] ?? ''),
                'heading' => self::fold($title),
                'haystack' => self::fold(implode(' ', [$title, $summary, ...self::strings($item, 'tags'), self::text($item, 'body')])),
                'pages' => self::strings($item, 'pages'),
            ];
        }

        return ['home' => $home, 'categories' => $categories, 'articles' => $articles];
    }

    /**
     * @param list<Article> $articles
     * @param list<string>  $words
     *
     * @return list<HelpLink>
     */
    private static function ranked(array $articles, array $words, int $limit): array
    {
        $found = [];

        foreach ($articles as $position => $article) {
            if (\count($words) === self::hits($article['haystack'], $words)) {
                $found[] = [self::hits($article['heading'], $words), $position, $article['link']];
            }
        }

        return self::best($found, $limit);
    }

    /**
     * @param list<Article> $articles
     * @param list<string>  $words
     *
     * @return list<HelpLink>
     */
    private static function related(array $articles, array $words): array
    {
        $found = [];

        foreach ($articles as $position => $article) {
            $hits = self::hits($article['haystack'], $words);
            if ($hits > 0) {
                $found[] = [$hits, $position, $article['link']];
            }
        }

        return self::best($found, self::REPLY_LIMIT);
    }

    /**
     * @param list<array{int, int, HelpLink}> $found
     *
     * @return list<HelpLink>
     */
    private static function best(array $found, int $limit): array
    {
        usort($found, static fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_map(static fn (array $row): HelpLink => $row[2], \array_slice($found, 0, $limit));
    }

    /** @param list<string> $words */
    private static function hits(string $text, array $words): int
    {
        return \count(array_filter($words, static fn (string $word): bool => str_contains($text, $word)));
    }

    /** @param list<string> $pages */
    private static function covers(array $pages, string $path): bool
    {
        foreach ($pages as $page) {
            if ($page === $path || ('/' !== $page && str_starts_with($path, rtrim($page, '/').'/'))) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        $parts = preg_split(self::WORD_BREAK, self::fold($text), -1, \PREG_SPLIT_NO_EMPTY);

        return false === $parts ? [] : array_values(array_unique($parts));
    }

    private static function fold(string $text): string
    {
        return str_replace('ё', 'е', mb_strtolower($text));
    }

    private static function text(mixed $item, string $key): string
    {
        return \is_array($item) && \is_string($item[$key] ?? null) ? trim($item[$key]) : '';
    }

    /** @return list<string> */
    private static function strings(mixed $item, string $key): array
    {
        $values = \is_array($item) && \is_array($item[$key] ?? null) ? $item[$key] : [];

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => \is_string($value) ? trim($value) : '',
            $values,
        ), static fn (string $value): bool => '' !== $value));
    }

    private static function absolute(string $base, string $url): ?string
    {
        if (1 === preg_match('~^https?://~i', $url)) {
            return $url;
        }

        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        $scheme = parse_url($base, \PHP_URL_SCHEME);
        $host = parse_url($base, \PHP_URL_HOST);
        $port = parse_url($base, \PHP_URL_PORT);

        if (!\is_string($scheme) || !\is_string($host) || !\in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        return sprintf('%s://%s%s%s', strtolower($scheme), $host, \is_int($port) ? ':'.$port : '', $url);
    }

    private static function origin(string $address): string
    {
        return self::absolute($address, '/') ?? $address;
    }
}
