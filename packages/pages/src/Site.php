<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Seo\Breadcrumbs;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Setting\Values;
use AdzerCMS\Core\Text\TextValues;
use AdzerCMS\Pages\Controller\PageController;
use AdzerCMS\Pages\Entity\BlockRecord;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use AdzerCMS\Pages\Repository\PageRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class Site
{
    public function __construct(
        private Blocks $blocks,
        private PageBlockRepository $repository,
        private PageRepository $pages,
        private Brand $brand,
        private UrlGeneratorInterface $urls,
        private Pictures $pictures,
        private Breadcrumbs $breadcrumbs,
        private TextValues $values,
    ) {
    }

    /** @return array{title: string, description: string, heading: ?string, tabs: list<array{label: string, href: string, active: bool}>, blocks: list<array{template: string, key: string, id: string, values: array<string, mixed>, data: array<string, mixed>, level: int}>, style: string, schema: list<array<string, mixed>>} */
    public function view(Page $page): array
    {
        $view = $this->sheet($this->title($page), $this->repository->of($page), $this->pictures);

        return ['description' => $this->values->fill($page->getDescription() ?? ''), 'tabs' => $this->tabs($page), 'schema' => array_values(array_filter([...$this->page($page), ...$view['schema']]))] + $view;
    }

    /**
     * @param iterable<BlockRecord> $records
     *
     * @return array{title: string, description: string, heading: ?string, tabs: list<array{label: string, href: string, active: bool}>, blocks: list<array{template: string, key: string, id: string, values: array<string, mixed>, data: array<string, mixed>, level: int}>, style: string, schema: list<array<string, mixed>>}
     */
    public function sheet(string $title, iterable $records, Album $album): array
    {
        $blocks = [];
        $shown = [];
        $schema = [];
        foreach ($records as $record) {
            $type = $this->blocks->find($record->getType());
            if (null === $type) {
                continue;
            }
            $shown[] = $record;
            $blocks[] = $this->block($type, $record->getValues(), BlockStyle::anchor($record), [] === $blocks, $album);
            $schema[] = $type->schema($record->getValues());
        }

        return ['title' => $title, 'description' => '', 'heading' => 1 === ($blocks[0]['level'] ?? null) ? null : $title, 'tabs' => [], 'blocks' => $blocks, 'style' => BlockStyle::css($shown), 'schema' => array_values(array_filter($schema))];
    }

    /**
     * @param array<string, string> $stored
     *
     * @return array{template: string, key: string, id: string, values: array<string, mixed>, data: array<string, mixed>, level: int}
     */
    public function block(Block $type, array $stored, string $id, bool $first, Album $album): array
    {
        $values = [];
        foreach ($type->fields() as $field) {
            $value = $stored[$field->name] ?? '';
            $values[$field->name] = match (true) {
                Type::Rows === $field->type => array_map(static fn (array $row): array => self::shown($field->fields, $row, $album), Values::entries($field, $value)),
                $field->type->picture() => self::picture($value, $album),
                default => $value,
            };
        }

        return ['template' => $type->template(), 'key' => $type->key(), 'id' => $id, 'values' => $values, 'data' => $type->data($stored, $album), 'level' => $first && '' !== ($values[Block::TITLE] ?? '') ? 1 : 2];
    }

    /** @return list<array{label: string, href: string, active: bool}> */
    private function tabs(Page $page): array
    {
        if (Page::ROOT === $page->getPath()) {
            return [];
        }
        $root = Page::ROOT.explode('/', substr($page->getPath(), 1), 2)[0];
        $section = $this->pages->section($root);
        $paths = array_map(static fn (Page $one): string => $one->getPath(), $section);
        if (\count($section) < 2 || !\in_array($root, $paths, true) || !\in_array($page, $section, true)) {
            return [];
        }

        return array_map(fn (Page $one): array => [
            'label' => $this->label($one),
            'href' => $this->url($one, UrlGeneratorInterface::ABSOLUTE_PATH),
            'active' => $one === $page,
        ], $section);
    }

    /** @return list<array<string, mixed>> */
    private function page(Page $page): array
    {
        if (Page::ROOT !== $page->getPath()) {
            return [Breadcrumbs::schema($this->breadcrumbs->trail([['name' => $this->title($page), 'url' => $this->url($page)]]))];
        }
        $root = $this->urls->generate(PageController::ROOT, [], UrlGeneratorInterface::ABSOLUTE_URL);

        return [
            ['@type' => 'WebSite', 'name' => $this->brand->title(), 'url' => $root],
            array_filter(['@type' => 'Organization', 'name' => $this->brand->title(), 'url' => $root, 'logo' => $this->brand->logo()]),
        ];
    }

    /**
     * @param list<Field>           $cells
     * @param array<string, string> $row
     *
     * @return array<string, mixed>
     */
    private static function shown(array $cells, array $row, Album $album): array
    {
        $shown = $row;
        foreach ($cells as $cell) {
            if ($cell->type->picture()) {
                $shown[$cell->name] = self::picture($row[$cell->name] ?? '', $album);
            }
        }

        return $shown;
    }

    /** @return array{src: string, width: int, height: int}|null */
    private static function picture(string $name, Album $album): ?array
    {
        return '' === $name ? null : $album->shown($name);
    }

    public function title(Page $page): string
    {
        return $this->values->fill($page->getTitle());
    }

    public function label(Page $page): string
    {
        return $page->getMenuLabel() ?? $this->title($page);
    }

    public function url(Page $page, int $reference = UrlGeneratorInterface::ABSOLUTE_URL): string
    {
        return Page::ROOT === $page->getPath()
            ? $this->urls->generate(PageController::ROOT, [], $reference)
            : $this->urls->generate(PageController::PAGE, ['path' => substr($page->getPath(), 1)], $reference);
    }
}
