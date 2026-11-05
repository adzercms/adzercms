<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Text;

use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Twig\Attribute\AsTwigFilter;

final class Markup
{
    private static ?GithubFlavoredMarkdownConverter $converter = null;

    public static function environment(): EnvironmentInterface
    {
        return self::converter()->getEnvironment();
    }

    #[AsTwigFilter('markup', isSafe: ['html'])]
    public static function html(string $text): string
    {
        return self::converter()->convert($text)->getContent();
    }

    private static function converter(): GithubFlavoredMarkdownConverter
    {
        return self::$converter ??= new GithubFlavoredMarkdownConverter(['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }
}
