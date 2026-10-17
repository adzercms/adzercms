<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

use Symfony\Component\HttpFoundation\Request;

interface Tabs
{
    public const ADMIN_DASHBOARD = 'admin.dashboard';
    public const ADMIN_USER = 'admin.user';
    public const CABINET_HOME = 'cabinet.home';

    public function page(): string;

    /** @return list<Tab> */
    public function tabs(?int $subject): array;

    /**
     * @param array<string, mixed> $sticky
     *
     * @return array{template: string, context: array<string, mixed>}
     */
    public function tab(string $key, ?int $subject, Request $request, array $sticky): array;
}
