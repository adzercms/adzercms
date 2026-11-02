<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Admin\Controller\TranslationsController;
use AdzerCMS\Contracts\Ui\AdminEntry;
use AdzerCMS\Contracts\Ui\Tab;
use AdzerCMS\Contracts\Ui\Tabs;
use AdzerCMS\Core\Setting\Language;
use AdzerCMS\Core\Text\Texts;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

final readonly class TranslationsTab implements Tabs
{
    private const KEY = 'translations';
    private const ORDER = 'message';
    private const LANGUAGE = 'language';

    public function __construct(
        private Texts $texts,
        private Language $language,
    ) {
    }

    public function page(): string
    {
        return self::ADMIN_REFERENCES;
    }

    public function tabs(?int $subject): array
    {
        return [new Tab(self::KEY, t('admin.translations.tab'), 0)];
    }

    public function tab(string $key, ?int $subject, Request $request, array $sticky): array
    {
        $language = Filter::choice(self::LANGUAGE, $this->language->choices(), $this->language->instance());
        $table = Table::of($request, AdminEntry::References->route(), [
            Column::live('text', t('admin.translations.text'), static fn (array $row): string => $row['text']),
            Column::live('message', t('admin.translations.message'), static fn (array $row): string => $row['message'])->aside(),
        ], self::ORDER, t('admin.translations.empty'), filters: [Filter::search(), $language], sticky: $sticky, live: true);

        $locale = $language->pick($table->value(self::LANGUAGE));
        $stored = $this->texts->stored($locale);
        $rows = [];

        foreach ($this->texts->catalogue($locale)->all() as $domain => $messages) {
            foreach ($messages as $message => $catalog) {
                $rows[] = ['domain' => $domain, 'message' => (string) $message, 'text' => $stored[$domain][$message] ?? $catalog, 'stored' => isset($stored[$domain][$message])];
            }
        }

        return ['template' => '@Admin/admin/_translations.html.twig', 'context' => [
            'table' => $table,
            'rows' => $table->rows($rows),
            'locale' => $locale,
            'changed' => TranslationsController::CHANGED,
        ]];
    }
}
