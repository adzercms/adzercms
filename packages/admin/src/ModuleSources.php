<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Setting\Page;
use AdzerCMS\Contracts\Ui\Badge;
use AdzerCMS\Contracts\Ui\Fragment;
use AdzerCMS\Core\Composer\Artifacts;
use AdzerCMS\Core\Module\Catalog;
use AdzerCMS\Core\Module\Sources;
use AdzerCMS\Core\Module\Store;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Table;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function Symfony\Component\Translation\t;

/** @phpstan-type Origin array{source: string, down: ?Badge, access: bool, credentials: ?int, drop: ?string, hidden: array<string, string>} */
final readonly class ModuleSources implements Fragment
{
    public const PAGE = Page::Extensions;

    public function __construct(
        private RequestStack $requests,
        private Catalog $catalog,
        private Sources $sources,
        private Artifacts $artifacts,
        private Store $store,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function place(): string
    {
        return self::ADMIN_SETTINGS.self::PAGE->value;
    }

    public function fragment(?int $subject): array
    {
        $table = Table::of($this->requests->getCurrentRequest() ?? throw new \LogicException('module sources render within a request'), 'admin_settings', [
            Column::live('source', t('admin.sources.source'), static fn (array $row): string => $row['source']),
            Column::live('access', t('admin.sources.access'), static fn (array $row): bool => $row['access']),
            Column::plain('actions'),
        ], 'source', sticky: ['page' => self::PAGE->value], live: true, id: 'sources');
        [$base, $own] = $this->origins($this->catalog->rows()['down']);

        return ['template' => '@Admin/admin/_sources.html.twig', 'context' => ['table' => $table, 'rows' => $table->rows($own, $base)]];
    }

    /**
     * @param list<string> $down
     *
     * @return array{list<Origin>, list<Origin>}
     */
    private function origins(array $down): array
    {
        $base = array_map(static fn (string $url): array => self::origin($url, \in_array($url, $down, true)), [$this->store->url(), ...Sources::BASE]);
        $own = [];
        foreach ($this->artifacts->archives() as $archive) {
            $own[] = self::origin($archive['name'].' '.$archive['version'], drop: $this->urls->generate('admin_source_zip_drop'), hidden: ['package' => $archive['name'], 'version' => $archive['normalized']]);
        }
        foreach ($this->sources->own() as $source) {
            $own[] = self::origin(
                $source->getUrl(),
                \in_array($source->getUrl(), $down, true),
                null !== $source->getTokenCipher(),
                $source->getId(),
                $this->urls->generate('admin_source_drop', ['id' => $source->getId()]),
            );
        }

        return [$base, $own];
    }

    /**
     * @param array<string, string> $hidden
     *
     * @return Origin
     */
    private static function origin(string $source, bool $down = false, bool $access = false, ?int $credentials = null, ?string $drop = null, array $hidden = []): array
    {
        return [
            'source' => $source,
            'down' => $down ? Badge::bad(t('admin.sources.down')) : null,
            'access' => $access,
            'credentials' => $credentials,
            'drop' => $drop,
            'hidden' => $hidden,
        ];
    }
}
