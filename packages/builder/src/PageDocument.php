<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Pages\BlockForm;
use AdzerCMS\Pages\Blocks;
use AdzerCMS\Pages\BlockStyle;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Pictures;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

final readonly class PageDocument
{
    public const VERSION = 'version';
    public const BLOCKS = 'blocks';
    public const ID = 'id';
    public const VALUES = 'values';

    private const DIGEST = 'xxh128';

    public function __construct(
        private PageBlockRepository $blocks,
        private Blocks $types,
        private BlockForm $form,
        private TranslatorInterface $translator,
        private EntityManagerInterface $em,
        private Pictures $pictures,
    ) {
    }

    /** @return array{version: string, blocks: list<array{id: int, anchor: string, type: string, label: string, available: bool, question: string, values: array<string, string>}>} */
    public function open(Page $page): array
    {
        $blocks = $this->blocks->of($page);

        return [self::VERSION => self::version($blocks), self::BLOCKS => array_map(function (PageBlock $block): array {
            $type = $this->types->find($block->getType());
            $label = null === $type ? $block->getType() : $type->label()->trans($this->translator);

            return [
                'id' => (int) $block->getId(),
                'anchor' => BlockStyle::anchor($block),
                'type' => $block->getType(),
                'label' => $label,
                'available' => null !== $type,
                'question' => $this->translator->trans('pages.block.drop_confirm', ['type' => $label]),
                'values' => $block->getValues(),
            ];
        }, $blocks)];
    }

    /** @param array<mixed> $document */
    public function save(Page $page, array $document): string|Refusal
    {
        $stored = [];
        foreach ($this->blocks->of($page) as $block) {
            $stored[(int) $block->getId()] = $block;
        }
        $entries = $document[self::BLOCKS] ?? null;
        if (!\is_array($entries) || ($document[self::VERSION] ?? null) !== self::version($stored)) {
            return new Refusal(t('builder.refusal.document'));
        }

        $kept = [];
        foreach ($entries as $entry) {
            $id = \is_array($entry) ? $entry[self::ID] ?? null : null;
            $values = \is_array($entry) ? self::values($entry[self::VALUES] ?? null) : null;
            if (!\is_int($id) || !isset($stored[$id]) || isset($kept[$id]) || null === $values) {
                return new Refusal(t('builder.refusal.document'));
            }
            $values = $this->checked($stored[$id], $values);
            if ($values instanceof Refusal) {
                return $values;
            }
            $kept[$id] = $values;
        }

        $before = Pictures::names(array_merge([], ...array_map(static fn (PageBlock $block): array => array_values($block->getValues()), array_values($stored))));
        $placed = [];
        foreach ($kept as $id => $values) {
            $placed[] = $block = $stored[$id];
            $block->place(\count($placed));
            $block->fill($values);
        }
        foreach (array_diff_key($stored, $kept) as $block) {
            $this->em->remove($block);
        }
        $this->em->flush();
        $this->pictures->drop(array_diff($before, Pictures::names(array_merge([], ...array_map(array_values(...), array_values($kept))))));

        return self::version($placed);
    }

    /** @return array<string, string>|null */
    public static function values(mixed $values): ?array
    {
        if (!\is_array($values)) {
            return null;
        }
        foreach ($values as $name => $value) {
            if (!\is_string($name) || !\is_string($value)) {
                return null;
            }
        }

        return $values;
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, string>|Refusal
     */
    private function checked(PageBlock $block, array $values): array|Refusal
    {
        $type = $this->types->find($block->getType());

        return null === $type || self::sorted($values) === self::sorted($block->getValues()) ? $block->getValues() : $this->form->stored($type, $values);
    }

    /** @param iterable<PageBlock> $blocks */
    private static function version(iterable $blocks): string
    {
        $state = [];
        foreach ($blocks as $block) {
            $state[] = [$block->getId(), $block->getType(), self::sorted($block->getValues()), self::sorted($block->getStyle())];
        }

        return hash(self::DIGEST, json_encode($state, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private static function sorted(array $values): array
    {
        ksort($values);

        return array_map(static fn (mixed $value): mixed => \is_array($value) ? self::sorted($value) : $value, $values);
    }
}
