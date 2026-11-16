<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use AdzerCMS\Contracts\Ui\AdminEntry;
use AdzerCMS\Contracts\Ui\Fragment;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Opotopo\Entity\GitSource;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\Translation\t;

final readonly class PackageSources implements Fragment
{
    public function __construct(private RequestStack $requests, private ItemRegistry $items)
    {
    }

    public function place(): string
    {
        return self::ADMIN_SETTINGS_MODULE.Registry::lowerKey(OpotopoBundle::class);
    }

    public function fragment(?int $subject): ?array
    {
        $sources = $this->items->sourced();
        if ([] === $sources) {
            return null;
        }

        $table = Table::of($this->requests->getCurrentRequest() ?? throw new \LogicException('package sources render within a request'), AdminEntry::ModuleSettings->route(), [
            Column::live('position', t('opotopo.sources.position'), static fn (GitSource $source): string => $source->getItem()->getCatalog().' '.$source->getItem()->getSku()),
            Column::live('repository', t('opotopo.sources.repository'), static fn (GitSource $source): string => $source->getRepositoryUrl()),
            Column::live('key', t('opotopo.sources.key'), static fn (GitSource $source): string => $source->getDeployKey())->aside(),
            Column::live('webhook', t('opotopo.sources.webhook'), static fn (GitSource $source): string => $source->getWebhookUrl())->aside(),
        ], 'position', sticky: ['key' => Registry::lowerKey(OpotopoBundle::class)], live: true, id: 'sources');

        return ['template' => '@Opotopo/_sources.html.twig', 'context' => ['table' => $table, 'rows' => $table->rows($sources)]];
    }
}
