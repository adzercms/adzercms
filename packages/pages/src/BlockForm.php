<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Setting\Values;
use AdzerCMS\Core\Ui\Once;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BlockForm
{
    public const TYPE = 'type';
    public const VALUE = 'value';

    private const ADD = 'pages.block';
    private const SUMMARY = ' · ';

    public function __construct(
        private PageBlockRepository $blocks,
        private EntityManagerInterface $em,
        private Once $once,
    ) {
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, string|list<array<string, string>>>
     */
    public static function shown(Block $type, array $values): array
    {
        $shown = [];
        foreach ($type->fields() as $field) {
            $shown[$field->name] = Values::form($field, Values::posted($field, $values[$field->name] ?? null) ?? '');
        }

        return $shown;
    }

    /**
     * @param array<string, mixed> $posted
     *
     * @return PageBlock|list<Refusal>
     */
    public function add(Page $page, Block $type, array $posted): PageBlock|array
    {
        [$values, $refusals] = self::checked($type, $posted);
        if ([] !== $refusals) {
            return $refusals;
        }
        if (!$this->once->fresh(self::ADD, [(int) $page->getId(), $type->key(), json_encode($values, \JSON_THROW_ON_ERROR)])) {
            return [new Refusal(Once::repeat())];
        }

        $block = new PageBlock($page, $type->key(), $values);
        $block->place(\count($this->blocks->of($page)) + 1);
        $this->em->persist($block);
        $this->em->flush();

        return $block;
    }

    /**
     * @param array<string, mixed> $posted
     *
     * @return list<Refusal>
     */
    public function fill(PageBlock $block, Block $type, array $posted): array
    {
        [$values, $refusals] = self::checked($type, $posted);
        if ([] !== $refusals) {
            return $refusals;
        }

        $block->fill($values);
        $this->em->flush();

        return [];
    }

    public function drop(PageBlock $block): void
    {
        $this->em->remove($block);
        $this->em->flush();
    }

    /** @param list<int> $moved */
    public function arrange(Page $page, array $moved): void
    {
        Order::place($this->blocks->of($page), $moved);
        $this->em->flush();
    }

    public static function summary(Block $type, PageBlock $block): string
    {
        $parts = [];
        foreach ($type->fields() as $field) {
            $value = $block->getValues()[$field->name] ?? '';
            $texts = Type::Rows === $field->type
                ? array_map(static fn (array $row): string => (string) array_find($row, static fn (string $cell): bool => '' !== $cell), Values::entries($field, $value))
                : [$value];
            foreach ($texts as $text) {
                $text = trim((string) preg_replace('/\s+/u', ' ', $text));
                if ('' !== $text) {
                    $parts[] = $text;
                }
            }
        }

        return implode(self::SUMMARY, $parts);
    }

    /**
     * @param array<string, mixed> $posted
     *
     * @return array{array<string, string>, list<Refusal>}
     */
    private static function checked(Block $type, array $posted): array
    {
        $values = [];
        $refusals = [];
        foreach ($type->fields() as $field) {
            $value = Values::check($field, Values::posted($field, $posted[$field->name] ?? null) ?? '');
            if (\is_string($value) && '' !== $value) {
                $value = $type->check($field, $value) ?? $value;
            }
            if ($value instanceof Refusal) {
                $refusals[] = $value;
            } elseif ('' !== $value) {
                $values[$field->name] = $value;
            }
        }

        return [$values, $refusals];
    }
}
