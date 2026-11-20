<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Style;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Property: string implements Titled
{
    public const UNITS = ['px', 'rem', 'em', '%', 'vw', 'vh'];

    private const NUMBER = '\d{1,4}(?:\.\d{1,2})?';
    private const COLOR = '#[0-9a-f]{3,4}|#[0-9a-f]{6}|#[0-9a-f]{8}';
    private const FRACTION = '0|1|0?\.\d{1,2}';

    case Color = 'color';
    case Background = 'background-color';
    case BorderColor = 'border-color';
    case FontSize = 'font-size';
    case FontWeight = 'font-weight';
    case FontStyle = 'font-style';
    case LineHeight = 'line-height';
    case LetterSpacing = 'letter-spacing';
    case TextAlign = 'text-align';
    case TextTransform = 'text-transform';
    case TextDecoration = 'text-decoration';
    case PaddingTop = 'padding-top';
    case PaddingRight = 'padding-right';
    case PaddingBottom = 'padding-bottom';
    case PaddingLeft = 'padding-left';
    case MarginTop = 'margin-top';
    case MarginRight = 'margin-right';
    case MarginBottom = 'margin-bottom';
    case MarginLeft = 'margin-left';
    case BorderWidth = 'border-width';
    case BorderStyle = 'border-style';
    case BorderRadius = 'border-radius';
    case Width = 'width';
    case MaxWidth = 'max-width';
    case MinHeight = 'min-height';
    case Opacity = 'opacity';

    public function label(): TranslatableInterface
    {
        return t('pages.style.'.$this->value);
    }

    public static function word(string $keyword): TranslatableInterface
    {
        return t('pages.style.value.'.$keyword);
    }

    public function kind(): Kind
    {
        return match ($this) {
            self::Color, self::Background, self::BorderColor => Kind::Color,
            self::FontWeight, self::FontStyle, self::TextAlign, self::TextTransform, self::TextDecoration, self::BorderStyle => Kind::Choice,
            self::Opacity => Kind::Fraction,
            default => Kind::Length,
        };
    }

    public function group(): Group
    {
        return match ($this) {
            self::Color, self::FontSize, self::FontWeight, self::FontStyle, self::LineHeight, self::LetterSpacing, self::TextAlign, self::TextTransform, self::TextDecoration => Group::Text,
            self::Background, self::BorderColor, self::BorderWidth, self::BorderStyle, self::BorderRadius, self::Opacity => Group::Decoration,
            self::PaddingTop, self::PaddingRight, self::PaddingBottom, self::PaddingLeft, self::MarginTop, self::MarginRight, self::MarginBottom, self::MarginLeft => Group::Spacing,
            self::Width, self::MaxWidth, self::MinHeight => Group::Size,
        };
    }

    /** @return list<string> */
    public function keywords(): array
    {
        return match ($this) {
            self::Color, self::Background, self::BorderColor => ['transparent'],
            self::FontWeight => ['normal', 'bold', '100', '200', '300', '400', '500', '600', '700', '800', '900'],
            self::FontStyle => ['normal', 'italic'],
            self::TextAlign => ['left', 'center', 'right', 'justify'],
            self::TextTransform => ['none', 'uppercase', 'lowercase', 'capitalize'],
            self::TextDecoration => ['none', 'underline', 'line-through'],
            self::BorderStyle => ['none', 'solid', 'dashed', 'dotted'],
            self::MarginTop, self::MarginRight, self::MarginBottom, self::MarginLeft, self::Width => ['auto'],
            self::MaxWidth => ['none'],
            default => [],
        };
    }

    /** @return list<string> */
    public function units(): array
    {
        return match (true) {
            self::LineHeight === $this => ['', ...self::UNITS],
            Kind::Length === $this->kind() => self::UNITS,
            default => [],
        };
    }

    public function signed(): bool
    {
        return \in_array($this, [self::LetterSpacing, self::MarginTop, self::MarginRight, self::MarginBottom, self::MarginLeft], true);
    }

    public function accepts(string $value): bool
    {
        return 1 === preg_match('/^(?:'.$this->form().')$/i', $value);
    }

    private function form(): string
    {
        $quoted = static fn (string $word): string => preg_quote($word, '/');
        $own = match ($this->kind()) {
            Kind::Color => [self::COLOR],
            Kind::Choice => [],
            Kind::Fraction => [self::FRACTION],
            Kind::Length => [($this->signed() ? '-?' : '').'(?:0|'.self::NUMBER.'(?:'.implode('|', array_map($quoted, $this->units())).'))'],
        };

        return implode('|', [...$own, ...array_map($quoted, $this->keywords())]);
    }
}
