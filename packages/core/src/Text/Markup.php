<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Text;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\InlinesOnly\InlinesOnlyExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\MarkdownConverter;
use Twig\Attribute\AsTwigFilter;

final class Markup
{
    private const CONFIG = ['html_input' => 'escape', 'allow_unsafe_links' => false];

    private static ?GithubFlavoredMarkdownConverter $converter = null;
    private static ?MarkdownConverter $inliner = null;

    public function __construct(private readonly TextValues $values)
    {
    }

    public static function environment(): EnvironmentInterface
    {
        return self::converter()->getEnvironment();
    }

    #[AsTwigFilter('markup', isSafe: ['html'])]
    public function html(string $text): string
    {
        return self::converter()->convert($this->values->fill($text))->getContent();
    }

    #[AsTwigFilter('markup_inline', isSafe: ['html'])]
    public function inline(string $text): string
    {
        return rtrim(self::inliner()->convert($this->values->fill($text))->getContent());
    }

    private static function converter(): GithubFlavoredMarkdownConverter
    {
        return self::$converter ??= new GithubFlavoredMarkdownConverter(self::CONFIG);
    }

    private static function inliner(): MarkdownConverter
    {
        if (null === self::$inliner) {
            $environment = new Environment(self::CONFIG);
            $environment->addExtension(new InlinesOnlyExtension());
            $environment->addExtension(new AutolinkExtension());
            $environment->addExtension(new StrikethroughExtension());
            self::$inliner = new MarkdownConverter($environment);
        }

        return self::$inliner;
    }
}
