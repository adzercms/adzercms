<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Pages\Entity\BlockRecord;
use AdzerCMS\Pages\Style\Property;
use AdzerCMS\Pages\Style\Screen;

use function Symfony\Component\Translation\t;

final class BlockStyle
{
    public const BLOCK = '';
    public const ELEMENT = '/^[a-z][a-z0-9-]{0,31}$/';
    private const ANCHOR = 'block-';

    /**
     * @param array<mixed> $rules
     *
     * @return array<string, array<string, array<string, string>>>|Refusal
     */
    public static function rules(array $rules): array|Refusal
    {
        $style = [];
        foreach ($rules as $target => $screens) {
            $target = (string) $target;
            if (!\is_array($screens) || (self::BLOCK !== $target && 1 !== preg_match(self::ELEMENT, $target))) {
                return new Refusal(t('pages.refusal.style'));
            }
            foreach ($screens as $screen => $properties) {
                $width = Screen::tryFrom((string) $screen);
                if (null === $width || !\is_array($properties)) {
                    return new Refusal(t('pages.refusal.style'));
                }
                foreach ($properties as $name => $value) {
                    $property = Property::tryFrom((string) $name);
                    if (null === $property || !\is_string($value) || !$property->accepts($value)) {
                        return new Refusal(t('pages.refusal.style'));
                    }
                    $style[$target][$width->value][$property->value] = $value;
                }
            }
        }

        return $style;
    }

    public static function anchor(BlockRecord $block): string
    {
        return self::ANCHOR.$block->getId();
    }

    /** @param iterable<BlockRecord> $blocks */
    public static function css(iterable $blocks): string
    {
        $sheets = [];
        foreach ($blocks as $block) {
            foreach ($block->getStyle() as $target => $screens) {
                $selector = '#'.self::anchor($block).(self::BLOCK === $target ? '' : ' [data-element='.$target.']');
                foreach ($screens as $screen => $properties) {
                    $declarations = implode(';', array_map(static fn (string $property, string $value): string => $property.':'.$value, array_keys($properties), $properties));
                    $sheets[$screen] = ($sheets[$screen] ?? '').$selector.'{'.$declarations.'}';
                }
            }
        }

        $css = '';
        foreach (Screen::cases() as $screen) {
            if (isset($sheets[$screen->value])) {
                $width = $screen->width();
                $css .= null === $width ? $sheets[$screen->value] : '@media (max-width:'.$width.'px){'.$sheets[$screen->value].'}';
            }
        }

        return $css;
    }
}
