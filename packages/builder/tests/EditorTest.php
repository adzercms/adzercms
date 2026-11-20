<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Pictures;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

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
        [$page, [$text, $retired]] = $this->page(['text' => ['title' => 'About us', 'text' => 'We build networks'], 'retired' => ['title' => 'Gone']]);

        $record = $this->client->request('GET', '/admin/pages/'.$page->getId());
        $editor = $this->client->click($record->selectLink(self::phrase(t('builder.action')))->link());
        self::assertResponseIsSuccessful();
        $builder = $editor->filter('[data-builder]');
        self::assertEquals([
            ['id' => $text->getId(), 'anchor' => 'block-'.$text->getId(), 'type' => 'text', 'label' => self::phrase(t('pages.block.text')), 'available' => true, 'question' => self::phrase(t('pages.block.drop_confirm', ['type' => self::phrase(t('pages.block.text'))])), 'values' => ['title' => 'About us', 'text' => 'We build networks'], 'dialog' => '/admin/pages/'.$page->getId().'/builder/blocks/'.$text->getId()],
            ['id' => $retired->getId(), 'anchor' => 'block-'.$retired->getId(), 'type' => 'retired', 'label' => 'retired', 'available' => false, 'question' => self::phrase(t('pages.block.drop_confirm', ['type' => 'retired'])), 'values' => ['title' => 'Gone'], 'dialog' => null],
        ], json_decode((string) $builder->attr('data-blocks'), true), 'the editor takes the blocks of the page in order with their values, a block of a removed module too');

        $frame = $this->client->request('GET', (string) $builder->attr('data-frame'));
        self::assertResponseIsSuccessful();
        self::assertSame('About us', trim($frame->filter('.site-main #block-'.$text->getId().' h1')->text()), 'the canvas is the page on the site frame as the visitor sees it');
        self::assertCount(1, $frame->filter('#block-'.$text->getId().' + template[data-builder-end]'), 'the end of the blocks marks the place of new ones');
    }

    #[Group('ACMS-BLD-0002')]
    #[Group('ACMS-PAGE-0012')]
    #[Group('ACMS-US-0077')]
    public function testOwnerSavesOrderAndRemovalOfBlocksWithTheDocumentWhole(): void
    {
        $pictures = self::service(Pictures::class);
        $picture = $pictures->store($this->picture('hero.png'));
        [$page, [$text, $hero, $retired]] = $this->page(['text' => ['title' => 'About us', 'text' => 'We build networks'], 'hero' => ['title' => 'Internet', 'picture' => $picture], 'retired' => ['title' => 'Gone']]);
        $save = '/admin/pages/'.$page->getId().'/builder';
        $opened = (string) $this->client->request('GET', $save)->filter('[data-builder]')->attr('data-version');

        $this->post($save, ['document' => json_encode(['version' => $opened, 'blocks' => [['id' => $retired->getId(), 'values' => $retired->getValues()], ['id' => $text->getId(), 'values' => $text->getValues()]]])]);
        self::assertResponseIsSuccessful();
        $saved = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(self::phrase(t('builder.saved')), $saved['message']);
        $stored = self::service(PageBlockRepository::class)->findBy(['page' => $page->getId()], ['position' => 'ASC']);
        self::assertSame(['retired', 'text'], array_map(static fn (PageBlock $block): string => $block->getType(), $stored), 'the document goes whole: its order, without the removed block');
        self::assertNull($pictures->path($picture), 'a removed block takes its picture');
        self::assertSame($saved['version'], $this->client->request('GET', $save)->filter('[data-builder]')->attr('data-version'), 'the editor goes on from the saved page');

        $this->post($save, ['document' => json_encode(['version' => $opened, 'blocks' => [['id' => $text->getId(), 'values' => $text->getValues()], ['id' => $retired->getId(), 'values' => $retired->getValues()]]])]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['message' => self::phrase(t('builder.refusal.document'))], json_decode((string) $this->client->getResponse()->getContent(), true), 'a page changed after the editor opened is not overwritten');
        self::assertSame(['retired', 'text'], array_map(static fn (PageBlock $block): string => $block->getType(), self::service(PageBlockRepository::class)->findBy(['page' => $page->getId()], ['position' => 'ASC'])));
    }

    #[Group('ACMS-BLD-0003')]
    #[Group('ACMS-BLD-0002')]
    #[Group('ACMS-PAGE-0012')]
    #[Group('ACMS-US-0077')]
    public function testOwnerFillsBlockInDialogAndPageTakesItsValuesOnSaving(): void
    {
        $pictures = self::service(Pictures::class);
        $first = $pictures->store($this->picture('hero.png'));
        [$page, [$hero]] = $this->page(['hero' => ['title' => 'Internet', 'picture' => $first]]);
        $editor = '/admin/pages/'.$page->getId().'/builder';
        $builder = $this->client->request('GET', $editor)->filter('[data-builder]');
        $block = json_decode((string) $builder->attr('data-blocks'), true)[0];

        $dialog = $this->post($block['dialog'], ['stored' => json_encode(['title' => 'Internet at home', 'picture' => $first]), 'first' => '1']);
        self::assertSame('Internet at home', $dialog->filter('input[name="value[title]"]')->attr('value'), 'the dialog shows the values the editor holds');
        $fill = (string) $dialog->filter('form[hx-post]')->attr('hx-post');
        $fields = ['stored' => $dialog->filter('input[name="stored"]')->attr('value'), 'first' => $dialog->filter('input[name="first"]')->attr('value'), 'value' => ['title' => 'Internet at home']];

        $refused = $this->post($fill, array_replace_recursive($fields, ['value' => ['button' => 'Order', 'link' => 'ftp://example.com']]));
        self::assertSame(self::phrase(t('pages.refusal.link', ['field' => self::phrase(t('pages.field.link'))])), $refused->filter('.flash-error')->text(), 'a refused value stays in the dialog');

        $this->post($fill, array_replace_recursive($fields, ['value' => ['title' => 'Fiber at home']]), ['value' => ['picture' => $this->picture('next.png')]]);
        self::assertResponseHeaderSame('HX-Reswap', 'none');
        $filled = json_decode((string) $this->client->getResponse()->getContent(), true);
        $next = $filled['values']['picture'] ?? self::fail('the picture of the dialog is not stored');
        self::assertSame(['title' => 'Fiber at home', 'picture' => $next], $filled['values']);
        self::assertSame('Fiber at home', (new Crawler($filled['html']))->filter('#block-'.$hero->getId().' h1')->text(), 'the canvas takes the block the server draws at its place');
        self::assertSame(['title' => 'Internet', 'picture' => $first], $this->reread($hero)->getValues(), 'the dialog writes nothing before saving');

        $this->post($editor, ['document' => json_encode(['version' => $builder->attr('data-version'), 'blocks' => [['id' => $hero->getId(), 'values' => $filled['values']]]])]);
        self::assertResponseIsSuccessful();
        self::assertEquals($filled['values'], $this->reread($hero)->getValues(), 'saving takes the values of the dialog');
        self::assertNull($pictures->path($first), 'a replaced picture takes its file on saving');

        $version = json_decode((string) $this->client->getResponse()->getContent(), true)['version'];
        $this->post($editor, ['document' => json_encode(['version' => $version, 'blocks' => [['id' => $hero->getId(), 'values' => ['title' => 'Fiber at home', 'picture' => 'gone.webp']]]])]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['message' => self::phrase(t('pages.refusal.picture', ['field' => self::phrase(t('pages.field.picture'))]))], json_decode((string) $this->client->getResponse()->getContent(), true), 'saving checks the values of the document');
    }

    /**
     * @param array<string, array<string, string>> $blocks
     *
     * @return array{Page, list<PageBlock>}
     */
    private function page(array $blocks): array
    {
        $page = new Page('Tariffs', '/tariffs');
        $page->edit('Tariffs', '/tariffs', null, true, null);
        $this->em->persist($page);
        $placed = [];
        foreach ($blocks as $type => $values) {
            $placed[] = $block = new PageBlock($page, $type, $values);
            $block->place(\count($placed));
            $this->em->persist($block);
        }
        $this->em->flush();

        return [$page, $placed];
    }
}
