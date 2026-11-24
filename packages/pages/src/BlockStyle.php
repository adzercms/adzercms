<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Pages\Entity\BlockRecord;
use AdzerCMS\Pages\Style\Kind;
use AdzerCMS\Pages\Style\Property;
use AdzerCMS\Pages\Style\Screen;

use function Symfony\Component\Translation\t;

final class BlockStyle
{
    public const BLOCK = '';
    public const ELEMENT = '/^[a-z][a-z0-9-]{0,31}$/';
    public const BASE = '@property '.self::OVERLAY.'{syntax:"<color>";inherits:false;initial-value:transparent}';
    private const ANCHOR = 'block-';
    private const OVERLAY = '--overlay';

    /**
     * @param array<mixed> $rules
     *
     * @return array<string, array<string, array<string, string>>>|Refusal
     */
    public static function rules(array $rules, ?Album $album): array|Refusal
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
                    if (Kind::Picture === $property->kind() && null !== $album && null === $album->path($value)) {
                        return new Refusal(t('pages.refusal.picture', ['field' => $property->label()]));
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

    /**
     * @param array<string, array<string, array<string, string>>> $style
     *
     * @return list<string>
     */
    public static function pictures(array $style): array
    {
        $names = [];
        foreach ($style as $screens) {
            foreach ($screens as $properties) {
                $names = [...$names, ...array_values(array_filter($properties, static fn (string $value, string $property): bool => Kind::Picture === Property::tryFrom($property)?->kind(), \ARRAY_FILTER_USE_BOTH))];
            }
        }

        return $names;
    }

    /**
     * @param array<string, array<string, array<string, string>>> $style
     * @param array<string, string>                               $names
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public static function renamed(array $style, array $names): array
    {
        return array_map(static fn (array $screens): array => array_map(static fn (array $properties): array => array_map(static fn (string $value): string => $names[$value] ?? $value, $properties), $screens), $style);
    }

    /** @param iterable<BlockRecord> $blocks */
    public static function css(iterable $blocks, Album $album): string
    {
        $sheets = [];
        foreach ($blocks as $block) {
            foreach ($block->getStyle() as $target => $screens) {
                $selector = '#'.self::anchor($block).(self::BLOCK === $target ? '' : ' [data-element='.$target.']');
                foreach ($screens as $screen => $properties) {
                    $declarations = implode(';', array_filter(array_map(static fn (string $property, string $value): ?string => self::declaration($property, $value, $album), array_keys($properties), $properties)));
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

        return '' === $css ? '' : self::BASE.$css;
    }

    private static function declaration(string $property, string $value, Album $album): ?string
    {
        if (Kind::Picture !== Property::tryFrom($property)?->kind()) {
            return $property.':'.$value;
        }
        $shown = $album->shown($value);

        return null === $shown ? null : $property.':linear-gradient(var('.self::OVERLAY.'),var('.self::OVERLAY.')),url("'.$shown['src'].'")';
    }
}
