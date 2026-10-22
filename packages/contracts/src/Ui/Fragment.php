<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

interface Fragment
{
    public const HEAD = 'head';
    public const HEADER = 'header';
    public const TOP = 'top';
    public const CABINET_HOME = 'cabinet.home';
    public const CABINET_PROFILE = 'cabinet.profile';
    public const ADMIN_DASHBOARD = 'admin.dashboard';
    public const ADMIN_DASHBOARD_ACTIONS = 'admin.dashboard.actions';
    public const ADMIN_USER = 'admin.user';
    public const END = 'end';

    public function place(): string;

    /** @return array{template: string, context: array<string, mixed>}|null */
    public function fragment(?int $subject): ?array;
}
