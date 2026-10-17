<?php

declare(strict_types=1);

namespace AdzerCMS\Theme;

use AdzerCMS\Contracts\Ui\Badged;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Money;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class ThemeExtension extends AbstractExtension
{
    private const BLANK = '—';
    private const FLAG_ON = '<span class="flag-on">✓</span>';
    private const FLAG_OFF = '<span class="flag-off">✗</span>';
    private const CAPTION = 24;
    private const LINK = '~https?://[^\s<>"\'«»]+~iu';
    private const LINK_TAIL = '~[.,;:!?)\]}]+$~u';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('amount', $this->amount(...), ['is_safe' => ['html']]),
            new TwigFunction('badge', $this->badge(...), ['is_safe' => ['html']]),
            new TwigFunction('blank', static fn (): string => self::BLANK),
            new TwigFunction('flag', static fn (bool $on): string => $on ? self::FLAG_ON : self::FLAG_OFF, ['is_safe' => ['html']]),
            new TwigFunction('clip', $this->clip(...), ['is_safe' => ['html']]),
            new TwigFunction('caption', $this->caption(...), ['is_safe' => ['html']]),
            new TwigFunction('cell', $this->cell(...), ['is_safe' => ['html']]),
            new TwigFunction('moment', $this->moment(...), ['is_safe' => ['html']]),
            new TwigFunction('hint', $this->hint(...), ['is_safe' => ['html']]),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('links', self::links(...), ['is_safe' => ['html']]),
        ];
    }

    public function hint(string $text): string
    {
        return sprintf(
            '<button type="button" class="tip" aria-label="%1$s"><span class="tip-text">%1$s</span></button>',
            htmlspecialchars($text, \ENT_QUOTES),
        );
    }

    public function caption(?string $value): string
    {
        return '' === trim((string) $value) ? '' : '<br>'.$this->clip($value, self::CAPTION, 'muted');
    }

    /** @param string|list<string>|\DateTimeInterface|null $value */
    public function cell(string|array|\DateTimeInterface|null $value): string
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $this->moment($value),
            null === $value, '' === $value, [] === $value => self::BLANK,
            \is_array($value) => $this->lines($value),
            default => htmlspecialchars($value, \ENT_QUOTES),
        };
    }

    public function moment(?\DateTimeInterface $at): string
    {
        return null === $at ? self::BLANK : $this->lines([$at->format(Column::DAY), $at->format(Column::TIME)]);
    }

    public function clip(?string $value, int $length, string $extra = ''): string
    {
        $value = (string) $value;
        $classes = rtrim('clip '.$extra);

        if (mb_strlen($value) <= $length) {
            return sprintf('<span class="%s">%s</span>', $classes, htmlspecialchars($value, \ENT_QUOTES));
        }

        return sprintf(
            '<span class="%s" title="%s">%s…</span>',
            $classes,
            htmlspecialchars($value, \ENT_QUOTES),
            htmlspecialchars(rtrim(mb_substr($value, 0, $length)), \ENT_QUOTES),
        );
    }

    public function amount(string|float|int $value, bool $signed = true): string
    {
        $number = (float) $value;
        $class = match (true) {
            !$signed => '',
            $number > 0 => ' class="plus"',
            $number < 0 => ' class="minus"',
            default => '',
        };

        return sprintf('<strong%s>%s ₽</strong>', $class, Money::rubles($value));
    }

    public function badge(Badged $case): string
    {
        return sprintf('<span class="badge badge-%s">%s</span>', $case->badge(), htmlspecialchars($case->label()->trans($this->translator), \ENT_QUOTES));
    }

    public static function links(string $text): string
    {
        $html = '';
        $at = 0;
        preg_match_all(self::LINK, $text, $found, \PREG_OFFSET_CAPTURE);

        foreach ($found[0] as [$url, $offset]) {
            $url = (string) preg_replace(self::LINK_TAIL, '', $url);
            $html .= htmlspecialchars(substr($text, $at, $offset - $at), \ENT_QUOTES).sprintf(
                '<a href="%1$s" target="_blank" rel="noreferrer noopener">%1$s</a>',
                htmlspecialchars($url, \ENT_QUOTES),
            );
            $at = $offset + \strlen($url);
        }

        return $html.htmlspecialchars(substr($text, $at), \ENT_QUOTES);
    }

    /** @param non-empty-list<string> $lines */
    private function lines(array $lines): string
    {
        $html = htmlspecialchars(array_shift($lines), \ENT_QUOTES);

        foreach ($lines as $line) {
            $html .= "\n".$this->caption($line);
        }

        return $html;
    }
}
