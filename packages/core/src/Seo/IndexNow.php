<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Seo;

use AdzerCMS\Core\Entity\SourceChange;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class IndexNow
{
    public const KEY = 'indexnow_key';
    public const KEY_PATTERN = '[0-9a-f]{64}';

    private const ENDPOINT = 'https://api.indexnow.org/indexnow';
    private const BATCH = 10000;
    private const ACCEPTED = [200, 202];

    public function __construct(
        private EntityManagerInterface $em,
        private PageMap $pages,
        private Indexing $indexing,
        private HttpClientInterface $http,
        private UrlGeneratorInterface $urls,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    public function key(): string
    {
        return hash_hmac('sha256', self::KEY, $this->secret);
    }

    public function owns(string $key): bool
    {
        return $this->indexing->enabled() && hash_equals($this->key(), $key);
    }

    public function announce(): void
    {
        if (!$this->indexing->enabled()) {
            return;
        }
        $changes = array_filter($this->em->getRepository(SourceChange::class)->findAll(), static fn (SourceChange $change): bool => $change->unannounced());
        if ([] === $changes) {
            return;
        }

        $changed = array_flip(array_map(static fn (SourceChange $change): string => $change->getSource(), $changes));
        $location = $this->urls->generate(self::KEY, ['key' => $this->key()], UrlGeneratorInterface::ABSOLUTE_URL);
        $host = (string) parse_url($location, \PHP_URL_HOST);
        $urls = [];
        foreach ($this->pages->all() as $page) {
            $url = $this->pages->url($page);
            if ($host === parse_url($url, \PHP_URL_HOST) && array_any(PageMap::sources($this->em, $page), static fn (string $source): bool => isset($changed[$source]))) {
                $urls[$url] = $url;
            }
        }
        foreach (array_chunk(array_values($urls), self::BATCH) as $batch) {
            $response = $this->http->request('POST', self::ENDPOINT, ['json' => ['host' => $host, 'key' => $this->key(), 'keyLocation' => $location, 'urlList' => $batch]]);
            if (!\in_array($response->getStatusCode(), self::ACCEPTED, true)) {
                throw new \RuntimeException(\sprintf('IndexNow refused %d addresses: %d %s', \count($batch), $response->getStatusCode(), $response->getContent(false)));
            }
        }

        foreach ($changes as $change) {
            $change->announce();
        }
        $this->em->flush();
    }
}
