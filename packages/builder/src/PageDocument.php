<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Contracts\Refusal;
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
    public const KNOWN = 'known';
    public const BLOCKS = 'blocks';
    public const ID = 'id';

    public function __construct(
        private PageBlockRepository $blocks,
        private Blocks $types,
        private TranslatorInterface $translator,
        private EntityManagerInterface $em,
        private Pictures $pictures,
    ) {
    }

    /** @return list<array{id: int, anchor: string, type: string, label: string, available: bool, question: string}> */
    public function blocks(Page $page): array
    {
        return array_map(function (PageBlock $block): array {
            $type = $this->types->find($block->getType());
            $label = null === $type ? $block->getType() : $type->label()->trans($this->translator);

            return [
                'id' => (int) $block->getId(),
                'anchor' => BlockStyle::anchor($block),
                'type' => $block->getType(),
                'label' => $label,
                'available' => null !== $type,
                'question' => $this->translator->trans('pages.block.drop_confirm', ['type' => $label]),
            ];
        }, $this->blocks->of($page));
    }

    /** @param array<mixed> $document */
    public function save(Page $page, array $document): ?Refusal
    {
        $stored = [];
        foreach ($this->blocks->of($page) as $block) {
            $stored[(int) $block->getId()] = $block;
        }
        $known = $document[self::KNOWN] ?? null;
        $entries = $document[self::BLOCKS] ?? null;
        if (!\is_array($known) || !\is_array($entries) || !self::same(array_keys($stored), $known)) {
            return new Refusal(t('builder.refusal.document'));
        }

        $kept = [];
        foreach ($entries as $entry) {
            $id = \is_array($entry) ? $entry[self::ID] ?? null : null;
            if (!\is_int($id) || !isset($stored[$id]) || isset($kept[$id])) {
                return new Refusal(t('builder.refusal.document'));
            }
            $kept[$id] = $stored[$id];
        }

        $position = 0;
        foreach ($kept as $block) {
            $block->place(++$position);
        }
        $dropped = array_diff_key($stored, $kept);
        foreach ($dropped as $block) {
            $this->em->remove($block);
        }
        $this->em->flush();
        $this->pictures->drop(array_merge([], ...array_map(static fn (PageBlock $block): array => array_values($block->getValues()), array_values($dropped))));

        return null;
    }

    /**
     * @param list<int>    $stored
     * @param array<mixed> $known
     */
    private static function same(array $stored, array $known): bool
    {
        sort($stored);
        sort($known);

        return $stored === $known;
    }
}
