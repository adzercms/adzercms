<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Ui\WebLink;
use AdzerCMS\Pages\Block;
use AdzerCMS\Pages\BlockStyle;
use AdzerCMS\Pages\Pictures;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final readonly class FreeBlock implements Block
{
    public const KEY = 'free';
    public const ELEMENTS = 'elements';
    public const TEXT = 'text';
    public const PICTURE = 'picture';
    public const BUTTON = 'button';
    public const LINE = 'line';
    public const BOX = 'box';

    private const KINDS = [
        self::TEXT => ['paragraphs' => true],
        self::PICTURE => ['name' => true, 'alt' => false],
        self::BUTTON => ['label' => true, 'link' => true],
        self::LINE => [],
        self::BOX => ['items' => true],
    ];
    private const RUN = ['text' => true, 'bold' => false, 'italic' => false, 'link' => false];
    private const DEPTH = 32;
    private const PHONE = '/^tel:\+?\d{3,15}$/';
    private const MAIL = 'mailto:';
    private const SPACE = '/\s/';
    private const JSON = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR;

    public function __construct(
        private Pictures $pictures,
    ) {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): TranslatableInterface
    {
        return t('builder.block.free');
    }

    public function fields(): array
    {
        return [];
    }

    public function template(): string
    {
        return '@Builder/blocks/free.html.twig';
    }

    public function data(array $values): array
    {
        $tree = $this->tree($values);

        return [self::ELEMENTS => \is_array($tree) ? $this->shown($tree) : []];
    }

    public function schema(array $values): ?array
    {
        return null;
    }

    public function sources(): array
    {
        return [];
    }

    public function check(Field $field, string $value): ?Refusal
    {
        return null;
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, string>|Refusal
     */
    public function stored(array $values): array|Refusal
    {
        $tree = $this->tree($values);
        if ($tree instanceof Refusal) {
            return $tree;
        }
        $keys = [];
        $refusal = $this->refusal($tree, $keys);

        return match (true) {
            null !== $refusal => $refusal,
            [] === $tree => [],
            default => [self::ELEMENTS => json_encode($tree, self::JSON)],
        };
    }

    public static function link(string $link): bool
    {
        return 1 !== preg_match(self::SPACE, $link) && (
            WebLink::valid($link)
            || (str_starts_with($link, self::MAIL) && null !== ContactKind::Email->normalize(substr($link, \strlen(self::MAIL))))
            || 1 === preg_match(self::PHONE, $link)
            || (str_starts_with($link, '/') && !str_starts_with($link, '//'))
        );
    }

    /**
     * @param array<string, string> $values
     *
     * @return list<mixed>|Refusal
     */
    private function tree(array $values): array|Refusal
    {
        if ([] !== array_diff_key($values, [self::ELEMENTS => true])) {
            return new Refusal(t('builder.refusal.element'));
        }
        try {
            $tree = json_decode($values[self::ELEMENTS] ?? '[]', true, self::DEPTH, self::JSON);
        } catch (\JsonException) {
            return new Refusal(t('builder.refusal.element'));
        }

        return \is_array($tree) && array_is_list($tree) ? $tree : new Refusal(t('builder.refusal.element'));
    }

    /**
     * @param list<mixed>         $nodes
     * @param array<string, true> $keys
     */
    private function refusal(array $nodes, array &$keys): ?Refusal
    {
        foreach ($nodes as $node) {
            $kind = \is_array($node) ? $node['kind'] ?? null : null;
            $key = \is_array($node) ? $node['key'] ?? null : null;
            $own = \is_string($kind) ? self::KINDS[$kind] ?? null : null;
            if (null === $own || !\is_string($key) || 1 !== preg_match(BlockStyle::ELEMENT, $key) || isset($keys[$key]) || !self::shaped($node, ['kind' => true, 'key' => true, ...$own])) {
                return new Refusal(t('builder.refusal.element'));
            }
            $keys[$key] = true;
            $refusal = match ($kind) {
                self::TEXT => $this->text($node['paragraphs']),
                self::PICTURE => !\is_string($node['name']) || !\is_string($node['alt'] ?? '') ? new Refusal(t('builder.refusal.element'))
                    : (null === $this->pictures->path($node['name']) ? new Refusal(t('builder.refusal.picture')) : null),
                self::BUTTON => !\is_string($node['label']) || '' === trim($node['label']) || !\is_string($node['link']) ? new Refusal(t('builder.refusal.element')) : self::linked($node['link']),
                self::BOX => \is_array($node['items']) && array_is_list($node['items']) ? $this->refusal($node['items'], $keys) : new Refusal(t('builder.refusal.element')),
                default => null,
            };
            if (null !== $refusal) {
                return $refusal;
            }
        }

        return null;
    }

    private function text(mixed $paragraphs): ?Refusal
    {
        if (!\is_array($paragraphs) || !array_is_list($paragraphs)) {
            return new Refusal(t('builder.refusal.element'));
        }
        foreach ($paragraphs as $runs) {
            foreach (\is_array($runs) && array_is_list($runs) ? $runs : [null] as $run) {
                if (!\is_array($run) || !self::shaped($run, self::RUN) || !\is_string($run['text']) || '' === $run['text']
                    || !\is_bool($run['bold'] ?? false) || !\is_bool($run['italic'] ?? false) || !\is_string($run['link'] ?? '')) {
                    return new Refusal(t('builder.refusal.element'));
                }
                if (isset($run['link']) && null !== ($refusal = self::linked($run['link']))) {
                    return $refusal;
                }
            }
        }

        return null;
    }

    private static function linked(string $link): ?Refusal
    {
        return self::link($link) ? null : new Refusal(t('builder.refusal.link', ['link' => $link]));
    }

    /**
     * @param array<array-key, mixed> $node
     * @param array<string, bool>     $own
     */
    private static function shaped(array $node, array $own): bool
    {
        return [] === array_diff_key($node, $own) && [] === array_diff_key(array_filter($own), $node);
    }

    /**
     * @param list<mixed> $nodes
     *
     * @return list<array<string, mixed>>
     */
    private function shown(array $nodes): array
    {
        $shown = [];
        foreach ($nodes as $node) {
            if (!\is_array($node)) {
                continue;
            }
            $shown[] = match ($node['kind'] ?? null) {
                self::PICTURE => $node + ['picture' => \is_string($node['name'] ?? null) ? $this->pictures->shown($node['name']) : null, 'alt' => ''],
                self::BOX => ['items' => $this->shown(\is_array($node['items'] ?? null) ? array_values($node['items']) : [])] + $node,
                default => $node,
            };
        }

        return $shown;
    }
}
