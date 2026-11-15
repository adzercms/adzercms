<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Seo\Breadcrumbs;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Setting\Values;
use AdzerCMS\Pages\Controller\PageController;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class Site
{
    public function __construct(
        private Blocks $blocks,
        private PageBlockRepository $repository,
        private Brand $brand,
        private UrlGeneratorInterface $urls,
        private Pictures $pictures,
        private Breadcrumbs $breadcrumbs,
    ) {
    }

    /** @return array{heading: ?string, blocks: list<array{template: string, key: string, values: array<string, mixed>, data: array<string, mixed>, level: int}>, schema: list<array<string, mixed>>} */
    public function view(Page $page): array
    {
        $blocks = [];
        $schema = [...self::page($page)];
        foreach ($this->repository->of($page) as $block) {
            $type = $this->blocks->find($block->getType());
            if (null === $type) {
                continue;
            }
            $stored = $block->getValues();
            $values = [];
            foreach ($type->fields() as $field) {
                $value = $stored[$field->name] ?? '';
                $values[$field->name] = match (true) {
                    Type::Rows === $field->type => array_map(fn (array $row): array => $this->shown($field->fields, $row), Values::entries($field, $value)),
                    $field->type->picture() => $this->picture($value),
                    default => $value,
                };
            }
            $blocks[] = ['template' => $type->template(), 'key' => $type->key(), 'values' => $values, 'data' => $type->data($stored), 'level' => 2];
            $schema[] = $type->schema($stored);
        }

        $heading = $page->getTitle();
        if ([] !== $blocks && '' !== ($blocks[0]['values'][Block::TITLE] ?? '')) {
            $blocks[0]['level'] = 1;
            $heading = null;
        }

        return ['heading' => $heading, 'blocks' => $blocks, 'schema' => array_values(array_filter($schema))];
    }

    /** @return list<array<string, mixed>> */
    private function page(Page $page): array
    {
        if (Page::ROOT !== $page->getPath()) {
            return [Breadcrumbs::schema($this->breadcrumbs->trail([['name' => $page->getTitle(), 'url' => $this->url($page)]]))];
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
    private function shown(array $cells, array $row): array
    {
        $shown = $row;
        foreach ($cells as $cell) {
            if ($cell->type->picture()) {
                $shown[$cell->name] = $this->picture($row[$cell->name] ?? '');
            }
        }

        return $shown;
    }

    /** @return array{src: string, width: int, height: int}|null */
    private function picture(string $name): ?array
    {
        return '' === $name ? null : $this->pictures->shown($name);
    }

    public function url(Page $page, int $reference = UrlGeneratorInterface::ABSOLUTE_URL): string
    {
        return Page::ROOT === $page->getPath()
            ? $this->urls->generate(PageController::ROOT, [], $reference)
            : $this->urls->generate(PageController::PAGE, ['path' => substr($page->getPath(), 1)], $reference);
    }
}
