<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Tests;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Pages\BlockStyle;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Style\Screen;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

final class StyleTest extends SchemaTestCase
{
    #[Group('ACMS-PAGE-0024')]
    #[Group('ACMS-US-0070')]
    public function testBlockStyleGoesToThePageAsCssOfItsScreens(): void
    {
        $block = $this->block();

        $style = BlockStyle::rules([
            'title' => ['tablet' => ['font-size' => '24px', 'margin-left' => 'auto']],
            '' => ['phone' => ['padding-top' => '1rem'], 'desktop' => ['color' => '#1a2b3c', 'padding-top' => '2rem']],
        ]);
        self::assertIsArray($style);
        $block->restyle($style);
        $this->em->flush();

        $page = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $id = 'block-'.$block->getId();
        self::assertSame($id, $page->filter('.site-text')->attr('id'), 'the section of the block carries its anchor');
        self::assertSame('#'.$id.'{color:#1a2b3c;padding-top:2rem}'
            .'@media (max-width:'.Screen::TABLET.'px){#'.$id.' [data-element=title]{font-size:24px;margin-left:auto}}'
            .'@media (max-width:'.Screen::PHONE.'px){#'.$id.'{padding-top:1rem}}', $page->filter('head style')->last()->text(), 'the computer rules go for every width, the tablet and the phone rules follow under their widths');
    }

    /** @param array<mixed> $rules */
    #[Group('ACMS-PAGE-0024')]
    #[DataProvider('refused')]
    public function testStyleOutsideItsListIsRefused(array $rules): void
    {
        $refusal = BlockStyle::rules($rules);

        self::assertInstanceOf(Refusal::class, $refusal);
        self::assertSame(self::phrase('pages.refusal.style'), self::phrase($refusal->reason));
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function refused(): iterable
    {
        yield 'property outside the list' => [['' => ['desktop' => ['position' => 'fixed']]]];
        yield 'value outside the form of its property' => [['' => ['desktop' => ['color' => '#fff;background:url(//x)']]]];
        yield 'unknown screen width' => [['' => ['watch' => ['color' => '#fff']]]];
        yield 'element key outside its form' => [['Title}' => ['desktop' => ['color' => '#fff']]]];
    }

    private function block(): PageBlock
    {
        $page = new Page('Home', Page::ROOT);
        $page->edit('Home', Page::ROOT, null, true, null);
        $block = new PageBlock($page, 'text', ['title' => 'About us', 'text' => 'We build networks']);
        $this->em->persist($page);
        $this->em->persist($block);
        $this->em->flush();

        return $block;
    }
}
