<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Pages\Album;
use AdzerCMS\Pages\Block;
use AdzerCMS\Pages\BlockForm;
use AdzerCMS\Pages\Blocks;
use AdzerCMS\Pages\BlockStyle;
use AdzerCMS\Pages\Entity\BlockRecord;
use AdzerCMS\Pages\PhotoAlbum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

final readonly class PageDocument
{
    public const VERSION = 'version';
    public const BLOCKS = 'blocks';
    public const ID = 'id';
    public const TYPE = 'type';
    public const VALUES = 'values';
    public const STYLE = 'style';

    private const DIGEST = 'xxh128';

    public function __construct(
        private Blocks $types,
        private BlockForm $form,
        private TranslatorInterface $translator,
        private EntityManagerInterface $em,
    ) {
    }

    /** @return array{version: string, blocks: list<array{id: int, anchor: string, type: string, label: string, available: bool, question: string, values: array<string, string>, style: array<string, array<string, array<string, string>>>}>} */
    public function open(Document $document): array
    {
        $blocks = $document->blocks();

        return [self::VERSION => self::version($blocks), self::BLOCKS => array_map(fn (BlockRecord $block): array => [
            self::ID => (int) $block->getId(),
            'anchor' => BlockStyle::anchor($block),
            ...$this->entry($block->getType()),
            self::VALUES => $block->getValues(),
            self::STYLE => $block->getStyle(),
        ], $blocks)];
    }

    /** @return list<array{type: string, label: string, available: bool, question: string}> */
    public function types(): array
    {
        return array_values(array_map(fn (Block $type): array => $this->entry($type->key()), $this->types->all()));
    }

    /**
     * @param array<mixed> $sent
     *
     * @return array{version: string, blocks: list<BlockRecord>}|Refusal
     */
    public function save(Document $document, array $sent): array|Refusal
    {
        $stored = [];
        foreach ($document->blocks() as $block) {
            $stored[(int) $block->getId()] = $block;
        }
        $entries = $sent[self::BLOCKS] ?? null;
        if (!\is_array($entries) || ($sent[self::VERSION] ?? null) !== self::version($stored)) {
            return new Refusal(t('builder.refusal.document'));
        }

        $kept = [];
        $seen = [];
        foreach ($entries as $entry) {
            $one = $this->entered($document, \is_array($entry) ? $entry : [], array_diff_key($stored, $seen));
            if ($one instanceof Refusal) {
                return $one;
            }
            $kept[] = $one;
            $seen += null === $one[0]->getId() ? [] : [$one[0]->getId() => true];
        }
        $refusal = $document->sizeRefusal(array_map(static fn (array $one): array => [$one[1], $one[2]], $kept));
        if (null !== $refusal) {
            return $refusal;
        }

        $before = PhotoAlbum::names(array_merge([], ...array_map(static fn (BlockRecord $block): array => array_values($block->getValues()), array_values($stored))));
        $placed = [];
        foreach ($kept as [$block, $values, $style]) {
            $placed[] = $block;
            $block->place(\count($placed));
            $block->fill($values);
            $block->restyle($style);
            $this->em->persist($block);
        }
        foreach (array_diff_key($stored, $seen) as $block) {
            $this->em->remove($block);
        }
        $document->touch();
        $this->em->flush();
        $document->album()->drop(array_diff($before, PhotoAlbum::names(array_merge([], ...array_map(static fn (array $one): array => array_values($one[1]), $kept)))));

        return [self::VERSION => self::version($placed), self::BLOCKS => $placed];
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
     * @param array<mixed>            $entry
     * @param array<int, BlockRecord> $left
     *
     * @return array{BlockRecord, array<string, string>, array<string, array<string, array<string, string>>>}|Refusal
     */
    private function entered(Document $document, array $entry, array $left): array|Refusal
    {
        $values = self::values($entry[self::VALUES] ?? null);
        $style = $entry[self::STYLE] ?? null;
        $id = $entry[self::ID] ?? null;
        $key = $entry[self::TYPE] ?? null;
        $block = \is_int($id) ? $left[$id] ?? null : null;
        $type = null === $id && \is_string($key) ? $this->types->find($key) : null;
        if (null !== $values && \is_array($style) && null !== $block) {
            return self::kept($block, $this->checked($block, $values, $document->album()), self::sorted($style) === self::sorted($block->getStyle()) ? $block->getStyle() : BlockStyle::rules($style));
        }
        if (null !== $values && \is_array($style) && null !== $type) {
            return self::kept($document->block($type->key()), $this->stored($type, $values, $document->album()), BlockStyle::rules($style));
        }

        return new Refusal(t('builder.refusal.document'));
    }

    /**
     * @param array<string, string>|Refusal                               $values
     * @param array<string, array<string, array<string, string>>>|Refusal $style
     *
     * @return array{BlockRecord, array<string, string>, array<string, array<string, array<string, string>>>}|Refusal
     */
    private static function kept(BlockRecord $block, array|Refusal $values, array|Refusal $style): array|Refusal
    {
        return match (true) {
            $values instanceof Refusal => $values,
            $style instanceof Refusal => $style,
            default => [$block, $values, $style],
        };
    }

    /** @return array{type: string, label: string, available: bool, question: string} */
    private function entry(string $key): array
    {
        $type = $this->types->find($key);
        $label = null === $type ? $key : $type->label()->trans($this->translator);

        return [self::TYPE => $key, 'label' => $label, 'available' => null !== $type, 'question' => $this->translator->trans('pages.block.drop_confirm', ['type' => $label])];
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, string>|Refusal
     */
    private function checked(BlockRecord $block, array $values, Album $album): array|Refusal
    {
        $type = $this->types->find($block->getType());

        return null === $type || self::sorted($values) === self::sorted($block->getValues()) ? $block->getValues() : $this->stored($type, $values, $album);
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, string>|Refusal
     */
    private function stored(Block $type, array $values, Album $album): array|Refusal
    {
        return $type instanceof FreeBlock ? $type->stored($values, $album) : $this->form->stored($type, $values, $album);
    }

    /** @param iterable<BlockRecord> $blocks */
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
