<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Style;

enum Property: string
{
    private const LENGTH = '(?:0|\d{1,4}(?:\.\d{1,2})?(?:px|rem|em|%|vw|vh))';
    private const COLOR = '(?:#[0-9a-f]{3,4}|#[0-9a-f]{6}|#[0-9a-f]{8}|transparent)';

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

    public function accepts(string $value): bool
    {
        return 1 === preg_match('/^(?:'.$this->form().')$/i', $value);
    }

    private function form(): string
    {
        return match ($this) {
            self::Color, self::Background, self::BorderColor => self::COLOR,
            self::FontSize, self::PaddingTop, self::PaddingRight, self::PaddingBottom, self::PaddingLeft, self::BorderWidth, self::BorderRadius, self::MinHeight => self::LENGTH,
            self::LetterSpacing => '-?'.self::LENGTH,
            self::MarginTop, self::MarginRight, self::MarginBottom, self::MarginLeft => '-?'.self::LENGTH.'|auto',
            self::Width => self::LENGTH.'|auto',
            self::MaxWidth => self::LENGTH.'|none',
            self::LineHeight => '\d(?:\.\d{1,2})?|'.self::LENGTH,
            self::FontWeight => 'normal|bold|[1-9]00',
            self::FontStyle => 'normal|italic',
            self::TextAlign => 'left|center|right|justify',
            self::TextTransform => 'none|uppercase|lowercase|capitalize',
            self::TextDecoration => 'none|underline|line-through',
            self::BorderStyle => 'none|solid|dashed|dotted',
            self::Opacity => '0|1|0?\.\d{1,2}',
        };
    }
}
