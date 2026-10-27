<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Ui\AdminEntry;
use AdzerCMS\Contracts\Ui\Tab;
use AdzerCMS\Contracts\Ui\Tabs;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

final readonly class DashboardTab implements Tabs
{
    public const KEY = 'leads';

    public function __construct(private LeadRows $rows)
    {
    }

    public function page(): string
    {
        return self::ADMIN_DASHBOARD;
    }

    public function tabs(?int $subject): array
    {
        return [new Tab(self::KEY, t('leads.menu'), 200)];
    }

    public function tab(string $key, ?int $subject, Request $request, array $sticky): array
    {
        return ['template' => '@Leads/admin/_leads.html.twig', 'context' => $this->rows->listing($request, AdminEntry::Dashboard->route(), Slice::WATCH, $sticky)];
    }
}
