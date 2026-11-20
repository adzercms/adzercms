<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Builder\FreeBlock;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Pictures;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use AdzerCMS\Pages\Style\Property;
use PHPUnit\Framework\Attributes\DataProvider;
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
            ['id' => $text->getId(), 'anchor' => 'block-'.$text->getId(), 'type' => 'text', 'label' => self::phrase(t('pages.block.text')), 'available' => true, 'question' => self::phrase(t('pages.block.drop_confirm', ['type' => self::phrase(t('pages.block.text'))])), 'values' => ['title' => 'About us', 'text' => 'We build networks'], 'style' => [], 'dialog' => '/admin/pages/'.$page->getId().'/builder/types/text'],
            ['id' => $retired->getId(), 'anchor' => 'block-'.$retired->getId(), 'type' => 'retired', 'label' => 'retired', 'available' => false, 'question' => self::phrase(t('pages.block.drop_confirm', ['type' => 'retired'])), 'values' => ['title' => 'Gone'], 'style' => [], 'dialog' => null],
        ], json_decode((string) $builder->attr('data-blocks'), true), 'the editor takes the blocks of the page in order with their values, a block of a removed module too');
        $types = array_column(json_decode((string) $builder->attr('data-types'), true), 'dialog', 'type');
        self::assertSame('/admin/pages/'.$page->getId().'/builder/types/hero', $types['hero'] ?? null, 'the panel of blocks carries the types of the enabled modules');
        self::assertArrayNotHasKey('retired', $types);

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

        $this->post($save, ['document' => json_encode(['version' => $opened, 'blocks' => [['id' => $retired->getId(), 'values' => $retired->getValues(), 'style' => []], ['id' => $text->getId(), 'values' => $text->getValues(), 'style' => []]]])]);
        self::assertResponseIsSuccessful();
        $saved = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(self::phrase(t('builder.saved')), $saved['message']);
        $stored = self::service(PageBlockRepository::class)->findBy(['page' => $page->getId()], ['position' => 'ASC']);
        self::assertSame(['retired', 'text'], array_map(static fn (PageBlock $block): string => $block->getType(), $stored), 'the document goes whole: its order, without the removed block');
        self::assertNull($pictures->path($picture), 'a removed block takes its picture');
        self::assertSame($saved['version'], $this->client->request('GET', $save)->filter('[data-builder]')->attr('data-version'), 'the editor goes on from the saved page');

        $this->post($save, ['document' => json_encode(['version' => $opened, 'blocks' => [['id' => $text->getId(), 'values' => $text->getValues(), 'style' => []], ['id' => $retired->getId(), 'values' => $retired->getValues(), 'style' => []]]])]);
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
        [$first, $next] = array_map(fn (string $name): string => $pictures->store($this->picture($name)), ['hero.png', 'next.png']);
        [$page, [$hero]] = $this->page(['hero' => ['title' => 'Internet', 'picture' => $first]]);
        $editor = '/admin/pages/'.$page->getId().'/builder';
        $builder = $this->client->request('GET', $editor)->filter('[data-builder]');
        $block = json_decode((string) $builder->attr('data-blocks'), true)[0];

        $dialog = $this->post($block['dialog'], ['stored' => json_encode(['title' => 'Internet at home', 'picture' => $first]), 'first' => '1']);
        self::assertSame('Internet at home', $dialog->filter('input[name="value[title]"]')->attr('value'), 'the dialog shows the values the editor holds');
        self::assertSame($first, $dialog->filter('input[name="value[picture]"]')->attr('value'));
        $fill = (string) $dialog->filter('form[hx-post]')->attr('hx-post');
        $fields = ['first' => $dialog->filter('input[name="first"]')->attr('value'), 'value' => ['title' => 'Internet at home', 'picture' => $first]];

        $refused = $this->post($fill, array_replace_recursive($fields, ['value' => ['button' => 'Order', 'link' => 'ftp://example.com']]));
        self::assertSame(self::phrase(t('pages.refusal.link', ['field' => self::phrase(t('pages.field.link'))])), $refused->filter('.flash-error')->text(), 'a refused value stays in the dialog');

        $this->post($fill, array_replace_recursive($fields, ['value' => ['title' => 'Fiber at home', 'picture' => $next]]));
        self::assertResponseHeaderSame('HX-Reswap', 'none');
        $filled = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['title' => 'Fiber at home', 'picture' => $next], $filled['values']);
        self::assertSame('Fiber at home', (new Crawler($filled['html']))->filter('#'.$filled['anchor'].' h1')->text(), 'the canvas takes the block the server draws at its place');
        self::assertSame(['title' => 'Internet', 'picture' => $first], $this->reread($hero)->getValues(), 'the dialog writes nothing before saving');

        $this->post($editor, ['document' => json_encode(['version' => $builder->attr('data-version'), 'blocks' => [['id' => $hero->getId(), 'values' => $filled['values'], 'style' => []]]])]);
        self::assertResponseIsSuccessful();
        self::assertEquals($filled['values'], $this->reread($hero)->getValues(), 'saving takes the values of the dialog');
        self::assertNull($pictures->path($first), 'a replaced picture takes its file on saving');

        $version = json_decode((string) $this->client->getResponse()->getContent(), true)['version'];
        $this->post($editor, ['document' => json_encode(['version' => $version, 'blocks' => [['id' => $hero->getId(), 'values' => ['title' => 'Fiber at home', 'picture' => 'gone.webp'], 'style' => []]]])]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['message' => self::phrase(t('pages.refusal.picture', ['field' => self::phrase(t('pages.field.picture'))]))], json_decode((string) $this->client->getResponse()->getContent(), true), 'saving checks the values of the document');
    }

    #[Group('ACMS-BLD-0008')]
    #[Group('ACMS-FLOW-0022')]
    #[Group('ACMS-US-0077')]
    public function testOwnerUploadsPictureToPanelWhereDialogTakesIt(): void
    {
        $pictures = self::service(Pictures::class);
        $older = $pictures->store($this->picture('older.png'));
        touch((string) $pictures->path($older), (int) strtotime('-1 hour'));
        $held = $pictures->store($this->picture('held.png'));
        [$page] = $this->page(['hero' => ['title' => 'Internet', 'picture' => $held]]);
        $builder = $this->client->request('GET', '/admin/pages/'.$page->getId().'/builder')->filter('[data-builder]');
        self::assertSame([$held, $older], array_column(json_decode((string) $builder->attr('data-pictures'), true), 'name'), 'the panel lists the photos of the owner, new ones first');

        $upload = (string) $builder->attr('data-upload');
        $this->post($upload, [], ['files' => [$this->scratchFile('fake.png', 'not a picture')]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['message' => self::phrase(t('core.photo.type', ['name' => 'fake.png']))], json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->post($upload, [], ['files' => [$this->picture('next.png')]]);
        self::assertResponseIsSuccessful();
        [$entry] = json_decode((string) $this->client->getResponse()->getContent(), true)['data'];
        self::assertNotNull($pictures->path($entry['name']), 'an upload goes to the photos of the owner at once');
        $this->client->request('GET', $entry['src']);
        self::assertResponseHeaderSame('Content-Type', 'image/webp');

        $block = json_decode((string) $builder->attr('data-blocks'), true)[0];
        $dialog = $this->post($block['dialog'], ['stored' => json_encode($block['values'])]);
        self::assertSame([0, 1], [$dialog->filter('input[type="file"]')->count(), $dialog->filter('[data-picture-pick]')->count()], 'the picture of the dialog comes from the panel');
    }

    #[Group('ACMS-BLD-0003')]
    #[Group('ACMS-FLOW-0022')]
    #[Group('ACMS-US-0077')]
    public function testOwnerPlacesBlockOfPanelAndSavingNumbersIt(): void
    {
        [$page, [$text]] = $this->page(['text' => ['title' => 'About us', 'text' => 'We build networks']]);
        $editor = '/admin/pages/'.$page->getId().'/builder';
        $version = (string) $this->client->request('GET', $editor)->filter('[data-builder]')->attr('data-version');
        $call = ['id' => null, 'type' => 'call', 'values' => ['title' => 'Connect today', 'button' => 'Request', 'link' => '#lead'], 'style' => []];
        $kept = ['id' => $text->getId(), 'type' => 'text', 'values' => $text->getValues(), 'style' => []];

        $refusals = [
            self::phrase(t('builder.refusal.document')) => ['type' => 'retired'] + $call,
            self::phrase(t('pages.refusal.link', ['field' => self::phrase(t('pages.field.link'))])) => ['values' => ['link' => 'ftp://example.com']] + $call,
        ];
        foreach ($refusals as $reason => $entry) {
            $this->post($editor, ['document' => json_encode(['version' => $version, 'blocks' => [$entry, $kept]])]);
            self::assertResponseStatusCodeSame(422);
            self::assertSame(['message' => $reason], json_decode((string) $this->client->getResponse()->getContent(), true), 'a new block takes the types of the enabled modules and the check of its values');
        }

        $this->post($editor, ['document' => json_encode(['version' => $version, 'blocks' => [$call, $kept]])]);
        self::assertResponseIsSuccessful();
        $stored = self::service(PageBlockRepository::class)->findBy(['page' => $page->getId()], ['position' => 'ASC']);
        self::assertSame(['call', 'text'], array_map(static fn (PageBlock $block): string => $block->getType(), $stored), 'a new block takes its place in the document');
        self::assertEquals($call['values'], $stored[0]->getValues());
        self::assertSame(array_map(static fn (PageBlock $block): array => ['id' => $block->getId(), 'anchor' => 'block-'.$block->getId()], $stored), json_decode((string) $this->client->getResponse()->getContent(), true)['blocks'], 'saving gives the blocks their numbers');
    }

    #[Group('ACMS-BLD-0006')]
    #[Group('ACMS-PAGE-0024')]
    #[Group('ACMS-FLOW-0022')]
    #[Group('ACMS-US-0077')]
    public function testOwnerStylesBlockByScreenWidthAndSavingKeepsItsRules(): void
    {
        [$page, [$text]] = $this->page(['text' => ['title' => 'About us', 'text' => 'We build networks']]);
        $text->restyle(['' => ['desktop' => ['color' => '#112233']]]);
        $this->em->flush();
        $editor = '/admin/pages/'.$page->getId().'/builder';
        $builder = $this->client->request('GET', $editor)->filter('[data-builder]');
        self::assertSame(['' => ['desktop' => ['color' => '#112233']]], json_decode((string) $builder->attr('data-blocks'), true)[0]['style'], 'the editor takes the style of the block');
        $panel = array_merge(...array_map(static fn (array $sector): array => array_column($sector['properties'], 'property'), json_decode((string) $builder->attr('data-style'), true)));
        self::assertEqualsCanonicalizing(array_map(static fn (Property $property): string => $property->value, Property::cases()), $panel, 'the panel of style carries the list of block style once');

        $style = ['' => ['desktop' => ['color' => '#445566'], 'phone' => ['padding-top' => '1rem']]];
        $this->post($editor, ['document' => json_encode(['version' => $builder->attr('data-version'), 'blocks' => [['id' => $text->getId(), 'values' => $text->getValues(), 'style' => $style]]])]);
        self::assertResponseIsSuccessful();
        self::assertEquals($style, $this->reread($text)->getStyle(), 'saving takes the rules of every screen width');

        $version = json_decode((string) $this->client->getResponse()->getContent(), true)['version'];
        $this->post($editor, ['document' => json_encode(['version' => $version, 'blocks' => [['id' => $text->getId(), 'values' => $text->getValues(), 'style' => ['' => ['desktop' => ['position' => 'fixed']]]]]])]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['message' => self::phrase(t('pages.refusal.style'))], json_decode((string) $this->client->getResponse()->getContent(), true), 'saving checks the style against its list');
        self::assertEquals($style, $this->reread($text)->getStyle());
    }

    #[Group('ACMS-BLD-0004')]
    #[Group('ACMS-BLD-0005')]
    #[Group('ACMS-PAGE-0018')]
    #[Group('ACMS-US-0077')]
    public function testOwnerBuildsFreeBlockOfElementsTheSiteShowsAsMarkupOfTheServer(): void
    {
        $picture = self::service(Pictures::class)->store($this->picture('fiber.png'));
        [$page, [$text]] = $this->page(['text' => ['title' => 'About us', 'text' => 'We build networks']]);
        $editor = '/admin/pages/'.$page->getId().'/builder';
        $builder = $this->client->request('GET', $editor)->filter('[data-builder]');
        $free = array_column(json_decode((string) $builder->attr('data-types'), true), null, 'type')[FreeBlock::KEY];
        self::assertNull($free['dialog'], 'a free block takes no dialog of values');

        $this->post($free['draw'], ['first' => '1']);
        $drawn = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame([], $drawn['values']);
        $empty = new Crawler($drawn['html']);
        self::assertSame([1, 0], [$empty->filter('#'.$drawn['anchor'].'.site-free')->count(), $empty->filter('.site-free-body > *')->count()], 'a new free block comes empty');

        $tree = [
            ['kind' => 'text', 'key' => 'intro', 'paragraphs' => [
                [['text' => 'Fiber '], ['text' => 'to every flat', 'bold' => true, 'italic' => true]],
                [['text' => 'Tariffs', 'link' => '/tariffs'], ['text' => ', '], ['text' => 'mail', 'link' => 'mailto:hi@example.com'], ['text' => ', '], ['text' => 'call', 'link' => 'tel:+79001112233']],
            ]],
            ['kind' => 'box', 'key' => 'side', 'items' => [
                ['kind' => 'picture', 'key' => 'photo', 'name' => $picture, 'alt' => 'Fiber'],
                ['kind' => 'line', 'key' => 'rule'],
                ['kind' => 'button', 'key' => 'call', 'label' => 'Write', 'link' => 'https://t.me/fiber'],
            ]],
        ];
        $style = ['intro' => ['desktop' => ['color' => '#112233']], 'call' => ['phone' => ['width' => '100%']]];
        $this->post($editor, ['document' => json_encode(['version' => $builder->attr('data-version'), 'blocks' => [
            ['id' => null, 'type' => FreeBlock::KEY, 'values' => [FreeBlock::ELEMENTS => json_encode($tree)], 'style' => $style],
            ['id' => $text->getId(), 'values' => $text->getValues(), 'style' => []],
        ]])]);
        self::assertResponseIsSuccessful();
        [$block] = self::service(PageBlockRepository::class)->findBy(['page' => $page->getId()], ['position' => 'ASC']);
        self::assertSame([FreeBlock::KEY, $tree, $style], [$block->getType(), json_decode($block->getValues()[FreeBlock::ELEMENTS], true), $block->getStyle()], 'saving keeps the tree of elements and their style');

        $this->client->request('GET', '/admin/pages/blocks/'.$block->getId());
        self::assertResponseStatusCodeSame(404, 'the page record has no dialog for the tree');

        $site = $this->client->request('GET', '/tariffs');
        $shown = $site->filter('#block-'.$block->getId());
        self::assertSame('<p>Fiber <strong><em>to every flat</em></strong></p><p><a href="/tariffs">Tariffs</a>, <a href="mailto:hi@example.com">mail</a>, <a href="tel:+79001112233">call</a></p>', $shown->filter('.prose[data-element="intro"]')->html());
        self::assertSame(
            [['img', 'photo', 'Fiber', 'lazy'], ['hr', 'rule', null, null], ['a', 'call', null, null]],
            $shown->filter('[data-element="side"] > *')->each(static fn (Crawler $element): array => [$element->nodeName(), $element->attr('data-element'), $element->attr('alt'), $element->attr('loading')]),
            'a box holds its elements in order',
        );
        self::assertSame($picture, basename((string) $shown->filter('img')->attr('src')));
        self::assertSame(['https://t.me/fiber', '_blank', 'Write'], [$shown->filter('a.button')->attr('href'), $shown->filter('a.button')->attr('target'), $shown->filter('a.button')->text()]);
        self::assertStringContainsString('#block-'.$block->getId().' [data-element=intro]{color:#112233}', $site->filter('head style')->text(), 'an element takes its style by its key');
    }

    /** @param list<array<string, mixed>> $tree */
    #[Group('ACMS-BLD-0005')]
    #[DataProvider('trees')]
    public function testFreeBlockTakesOnlyElementsAttributesLinksAndPicturesOfItsLists(array $tree, string $reason): void
    {
        [$page] = $this->page([]);
        $editor = '/admin/pages/'.$page->getId().'/builder';
        $version = (string) $this->client->request('GET', $editor)->filter('[data-builder]')->attr('data-version');

        $this->post($editor, ['document' => json_encode(['version' => $version, 'blocks' => [['id' => null, 'type' => FreeBlock::KEY, 'values' => [FreeBlock::ELEMENTS => json_encode($tree)], 'style' => []]]])]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['message' => self::phrase(t($reason, ['link' => 'javascript:alert(1)']))], json_decode((string) $this->client->getResponse()->getContent(), true));
        self::assertSame([], self::service(PageBlockRepository::class)->findBy(['page' => $page->getId()]));
    }

    /** @return iterable<string, array{list<array<string, mixed>>, string}> */
    public static function trees(): iterable
    {
        yield 'an element out of the list' => [[['kind' => 'video', 'key' => 'clip']], 'builder.refusal.element'];
        yield 'an attribute out of the list' => [[['kind' => 'button', 'key' => 'call', 'label' => 'Write', 'link' => '/', 'onclick' => 'steal()']], 'builder.refusal.element'];
        yield 'a key twice' => [[['kind' => 'line', 'key' => 'rule'], ['kind' => 'box', 'key' => 'side', 'items' => [['kind' => 'line', 'key' => 'rule']]]], 'builder.refusal.element'];
        yield 'a key a style rule does not take' => [[['kind' => 'line', 'key' => 'Rule 1']], 'builder.refusal.element'];
        yield 'a link of another scheme' => [[['kind' => 'text', 'key' => 'intro', 'paragraphs' => [[['text' => 'Win', 'link' => 'javascript:alert(1)']]]]], 'builder.refusal.link'];
        yield 'a picture out of the photos' => [[['kind' => 'picture', 'key' => 'photo', 'name' => 'gone.webp']], 'builder.refusal.picture'];
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
