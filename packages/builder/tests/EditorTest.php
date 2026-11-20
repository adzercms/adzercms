<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use PHPUnit\Framework\Attributes\Group;

use function Symfony\Component\Translation\t;

final class EditorTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
    }

    #[Group('ACMS-BLD-0001')]
    #[Group('ACMS-BLD-0009')]
    #[Group('ACMS-US-0077')]
    public function testOwnerOpensPageInEditorAsVisitorSeesIt(): void
    {
        $page = new Page('Tariffs', '/tariffs');
        $page->edit('Tariffs', '/tariffs', null, true, null);
        $text = new PageBlock($page, 'text', ['title' => 'About us', 'text' => 'We build networks']);
        $text->place(1);
        $retired = new PageBlock($page, 'retired', ['title' => 'Gone']);
        $retired->place(2);
        $this->em->persist($page);
        $this->em->persist($text);
        $this->em->persist($retired);
        $this->em->flush();

        $record = $this->client->request('GET', '/admin/pages/'.$page->getId());
        $editor = $this->client->click($record->selectLink(self::phrase(t('builder.action')))->link());
        self::assertResponseIsSuccessful();
        $builder = $editor->filter('[data-builder]');
        self::assertSame([
            ['id' => $text->getId(), 'anchor' => 'block-'.$text->getId(), 'type' => 'text', 'label' => self::phrase(t('pages.block.text')), 'available' => true],
            ['id' => $retired->getId(), 'anchor' => 'block-'.$retired->getId(), 'type' => 'retired', 'label' => 'retired', 'available' => false],
        ], json_decode((string) $builder->attr('data-blocks'), true), 'the editor takes the blocks of the page in order, a block of a removed module too');

        $frame = $this->client->request('GET', (string) $builder->attr('data-frame'));
        self::assertResponseIsSuccessful();
        self::assertSame('About us', trim($frame->filter('.site-main #block-'.$text->getId().' h1')->text()), 'the canvas is the page on the site frame as the visitor sees it');
        self::assertCount(1, $frame->filter('#block-'.$text->getId().' + template[data-builder-end]'), 'the end of the blocks marks the place of new ones');
    }
}
