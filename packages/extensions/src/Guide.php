<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\Node\StringContainerHelper;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;

final readonly class Guide
{
    private const SOURCE = __DIR__.'/../docs/publishing';

    private function __construct(public string $title, public string $html)
    {
    }

    public static function read(string $language): self
    {
        $file = self::SOURCE.'.'.$language.'.md';
        $environment = (new GithubFlavoredMarkdownConverter(['html_input' => 'escape', 'allow_unsafe_links' => false]))->getEnvironment();
        $document = (new MarkdownParser($environment))->parse((string) file_get_contents(is_file($file) ? $file : self::SOURCE.'.md'));
        $title = $document->firstChild();

        if (!$title instanceof Heading || 1 !== $title->getLevel()) {
            throw new \LogicException('The guide starts with its title');
        }

        $title->detach();

        return new self(StringContainerHelper::getChildText($title), (new HtmlRenderer($environment))->renderDocument($document)->getContent());
    }
}
