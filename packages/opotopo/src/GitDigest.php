<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use AdzerCMS\Support\DigestSection;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

final readonly class GitDigest implements DigestSection
{
    public function __construct(
        private ItemRegistry $items,
        private TranslatorInterface $translator,
    ) {
    }

    public function lines(): array
    {
        $lines = [];
        foreach ($this->items->sourced() as $source) {
            foreach ($source->getFailures() as $failure) {
                $lines[] = $this->translator->trans(null === $failure['tag'] ? 'opotopo.digest.git.fetch' : 'opotopo.digest.git.tag', [
                    'catalog' => $source->getItem()->getCatalog(),
                    'sku' => $source->getItem()->getSku(),
                    'repository' => $source->getRepositoryUrl(),
                    'tag' => $failure['tag'],
                    'commit' => $failure['commit'],
                    'error' => $failure['code'],
                    'details' => json_encode($failure['details'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR),
                ]);
            }
        }

        return $lines;
    }

    public function summary(int $count): TranslatableInterface
    {
        return t('opotopo.digest.git.summary', ['count' => $count]);
    }
}
