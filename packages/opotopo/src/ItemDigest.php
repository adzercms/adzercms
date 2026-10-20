<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use AdzerCMS\Opotopo\Entity\Item;
use AdzerCMS\Support\DigestSection;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

final readonly class ItemDigest implements DigestSection
{
    public function __construct(
        private ItemRegistry $items,
        private TranslatorInterface $translator,
    ) {
    }

    public function lines(): array
    {
        return array_map(fn (Item $item): string => $this->translator->trans('opotopo.digest.line', [
            'catalog' => $item->getCatalog(),
            'sku' => $item->getSku(),
            'error' => $item->getError(),
            'details' => json_encode($item->getErrorDetails(), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR),
        ]), $this->items->failed());
    }

    public function summary(int $count): TranslatableInterface
    {
        return t('opotopo.digest.summary', ['count' => $count]);
    }
}
