<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Ui\AdminEntry;
use AdzerCMS\Contracts\Ui\Tab;
use AdzerCMS\Contracts\Ui\Tabs;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Leads\Repository\StageRepository;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

final readonly class StageTab implements Tabs
{
    public const KEY = 'lead-stages';
    public const TEMPLATE = '@Leads/admin/_stages.html.twig';

    public function __construct(private StageRepository $stages)
    {
    }

    public function page(): string
    {
        return self::ADMIN_REFERENCES;
    }

    public function tabs(?int $subject): array
    {
        return [new Tab(self::KEY, t('leads.tab.stages'), 200)];
    }

    public function tab(string $key, ?int $subject, Request $request, array $sticky): array
    {
        return ['template' => self::TEMPLATE, 'context' => $this->listing($request)];
    }

    /** @return array<string, mixed> */
    public function listing(Request $request): array
    {
        $table = Table::of($request, AdminEntry::References->route(), [
            Column::plain('label', t('leads.stage.title')),
            Column::plain('group', t('leads.stage.group')),
            Column::plain('actions'),
        ], 'label', t('leads.stage.empty'), sticky: ['tab' => self::KEY], order: Order::of('admin_lead_stages_order', 's.position', 's.id'));

        return [
            'table' => $table,
            'rows' => $table->rows($this->stages->funnel()),
            'heading' => t('leads.tab.stages'),
            'actions' => [['route' => 'admin_lead_stage_new', 'params' => [], 'label' => t('leads.stage.add')]],
        ];
    }
}
