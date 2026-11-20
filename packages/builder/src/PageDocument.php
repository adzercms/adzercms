<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Pages\Blocks;
use AdzerCMS\Pages\BlockStyle;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class PageDocument
{
    public function __construct(
        private PageBlockRepository $blocks,
        private Blocks $types,
        private TranslatorInterface $translator,
    ) {
    }

    /** @return list<array{id: int, anchor: string, type: string, label: string, available: bool}> */
    public function blocks(Page $page): array
    {
        return array_map(function (PageBlock $block): array {
            $type = $this->types->find($block->getType());

            return [
                'id' => (int) $block->getId(),
                'anchor' => BlockStyle::anchor($block),
                'type' => $block->getType(),
                'label' => null === $type ? $block->getType() : $type->label()->trans($this->translator),
                'available' => null !== $type,
            ];
        }, $this->blocks->of($page));
    }
}
