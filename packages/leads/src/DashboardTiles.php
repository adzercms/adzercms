<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Ui\Fragment;
use AdzerCMS\Leads\Repository\LeadRepository;

use function Symfony\Component\Translation\t;

final readonly class DashboardTiles implements Fragment
{
    public function __construct(private LeadRepository $leads)
    {
    }

    public function place(): string
    {
        return self::ADMIN_DASHBOARD;
    }

    public function fragment(?int $subject): array
    {
        $tiles = [];
        foreach (Slice::WATCH as $slice) {
            $tiles[] = [
                'label' => t('leads.dashboard.'.$slice->value),
                'count' => $this->leads->total($slice),
                'query' => ['tab' => DashboardTab::KEY] + (Slice::WATCH[0] === $slice ? [] : [LeadRows::SLICE => $slice->value]),
            ];
        }

        return ['template' => '@Leads/admin/_dashboard_tiles.html.twig', 'context' => ['tiles' => $tiles, 'route' => DashboardTab::ROUTE]];
    }
}
