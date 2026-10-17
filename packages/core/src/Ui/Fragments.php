<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Ui\Fragment;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Twig\Attribute\AsTwigFunction;

final readonly class Fragments
{
    /** @param iterable<Fragment> $fragments */
    public function __construct(
        #[AutowireIterator(Fragment::class)]
        private iterable $fragments,
    ) {
    }

    /** @return list<array{template: string, context: array<string, mixed>}> */
    #[AsTwigFunction('fragments')]
    public function of(string $place, ?int $subject = null): array
    {
        $parts = [];
        foreach ($this->fragments as $fragment) {
            $part = $place === $fragment->place() ? $fragment->fragment($subject) : null;
            if (null !== $part) {
                $parts[] = $part;
            }
        }

        return $parts;
    }
}
