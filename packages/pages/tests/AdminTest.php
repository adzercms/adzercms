<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use AdzerCMS\Pages\Repository\PageRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

use function Symfony\Component\Translation\t;

final class AdminTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    private const LIST = '/admin/pages';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();
    }

    #[Group('ACMS-CORE-1096')]
    #[Group('ACMS-PAGE-0005')]
    #[Group('ACMS-PAGE-0017')]
    #[Group('ACMS-PAGE-0018')]
    #[Group('ACMS-US-0071')]
    public function testOwnerBuildsPageOfBlocksSeenByVisitorAfterSaving(): void
    {
        $this->post(self::LIST.'/new', ['title' => 'Tariffs', 'path' => 'Tariffs/']);
        $page = self::service(PageRepository::class)->findOneBy(['path' => '/tariffs']) ?? self::fail('the page is not created at its address');
        $record = self::LIST.'/'.$page->getId();
        self::assertResponseHeaderSame('HX-Redirect', $record);
        self::assertSame(['Tariffs', '/tariffs', '✗', '✗'], self::cells($this->fragment(self::LIST), 0));

        $preview = $this->arrive($record)->filter('.card-actions a[target="_blank"]')->attr('href');
        $this->client->request('GET', (string) $preview);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        $this->client->request('GET', '/tariffs');
        self::assertResponseStatusCodeSame(404, 'an unpublished page is seen only by its preview');

        $dialog = $this->client->request('GET', $record.'/blocks/new?type=advantages');
        self::assertCount(2, $dialog->filter('input[name^="value[points]"][name$="[title]"]'), 'a group of rows opens with blank rows of input');
        $this->post($record.'/blocks/new', ['type' => 'advantages', 'value' => ['title' => 'Why us', 'points' => [['title' => 'Fiber', 'text' => 'To the house'], ['title' => '', 'text' => '']]]]);
        self::assertResponseHeaderSame('HX-Redirect', $record);
        $refused = $this->post($record.'/blocks/new', ['type' => 'hero', 'value' => ['title' => 'Internet', 'button' => 'Plans', 'link' => 'ftp://plans']]);
        self::assertCount(1, $refused->filter('.flash-error'), 'a value refused by the block type is not saved');
        $this->post($record.'/blocks/new', ['type' => 'hero', 'value' => ['title' => 'Internet', 'button' => 'Plans', 'link' => '#lead']]);
        $blocks = self::service(PageBlockRepository::class)->of($page);
        self::assertSame(['advantages', 'hero'], array_map(static fn (PageBlock $block): string => $block->getType(), $blocks));

        $dialog = $this->client->request('GET', self::LIST.'/blocks/'.$blocks[0]->getId());
        self::assertSame(['Fiber', '', ''], $dialog->filter('input[name^="value[points]"][name$="[title]"]')->each(static fn (Crawler $input): string => (string) $input->attr('value')), 'filled rows go first, blank rows follow');
        $this->post(self::LIST.'/blocks/'.$blocks[0]->getId(), ['value' => ['title' => 'Why us', 'points' => [['title' => 'Fiber', 'text' => 'To the house'], ['title' => 'Support', 'text' => 'Around the clock']]]]);
        $listing = $this->post($record.'/blocks/order', [Order::FIELD => [(string) $blocks[1]->getId(), (string) $blocks[0]->getId()]]);
        self::assertSame(['Internet · Plans · #lead', 'Why us · Fiber · Support'], $listing->filter('tbody tr td:nth-child(3)')->each(static fn (Crawler $cell): string => trim($cell->text())));

        $this->post($record, ['title' => 'Tariffs', 'path' => '/tariffs', 'description' => 'Plans of the network', 'published' => '1', 'menu' => '1', 'menu_label' => 'Plans']);
        self::assertResponseRedirects($record);
        $visit = $this->client->request('GET', '/tariffs');
        self::assertSame(['Internet'], $visit->filter('.site-main h1')->each(static fn (Crawler $node): string => trim($node->text())));
        self::assertSame(['Why us'], $visit->filter('.site-main h2')->each(static fn (Crawler $node): string => trim($node->text())));
        self::assertStringContainsString('Around the clock', $visit->filter('.site-points')->text());
        self::assertSame(['Plans'], $visit->filter('.site-nav a')->each(static fn (Crawler $node): string => trim($node->text())));
        self::assertStringEndsWith('/tariffs', (string) $this->arrive($record)->filter('.card-actions a[target="_blank"]')->attr('href'));

        $retired = new PageBlock(self::service(PageRepository::class)->find($page->getId()) ?? self::fail('the page is gone'), 'retired', ['title' => 'Gone']);
        $retired->place(\count($blocks) + 1);
        $this->em->persist($retired);
        $this->em->flush();
        $row = $this->arrive($record)->filter('tbody tr')->last();
        self::assertSame(self::phrase(t('pages.block.unavailable')), trim($row->filter('.badge')->text()), 'a block of a removed module is marked and keeps its values');
        self::assertNull($row->attr('data-record'));

        $this->post(self::LIST.'/blocks/'.$blocks[1]->getId().'/drop');
        self::assertCount(0, $this->client->request('GET', '/tariffs')->filter('.site-hero'));
        $this->post($record.'/drop');
        self::assertCount(0, self::service(PageBlockRepository::class)->findAll(), 'a dropped page takes its blocks');
        $this->client->request('GET', '/tariffs');
        self::assertResponseStatusCodeSame(404);
    }

    #[Group('ACMS-PAGE-0002')]
    #[Group('ACMS-US-0071')]
    #[DataProvider('addresses')]
    public function testAddressOfAnotherPageOrModuleSectionIsNotSaved(string $path, string $reason): void
    {
        $page = new Page('About', '/about');
        $this->em->persist($page);
        $this->em->flush();

        $dialog = $this->post(self::LIST.'/new', ['title' => 'Other', 'path' => $path]);

        self::assertSame(self::phrase(t($reason, ['path' => $path, 'title' => 'About'])), trim($dialog->filter('.field-refusal')->text()));
        self::assertSame(1, self::service(PageRepository::class)->count());
    }

    #[Group('ACMS-PAGE-0004')]
    #[Group('ACMS-US-0071')]
    public function testChangedAddressLeadsToPageAndPassesToPageTakingIt(): void
    {
        $tariffs = new Page('Tariffs', '/tariffs');
        $tariffs->edit('Tariffs', '/tariffs', null, true, false, null);
        $this->em->persist($tariffs);
        $this->em->flush();
        $record = self::LIST.'/'.$tariffs->getId();

        $this->post($record, ['title' => 'Tariffs', 'path' => '/plans', 'former' => "/prices\n/Rates", 'published' => '1']);
        self::assertResponseRedirects($record);
        foreach (['/tariffs', '/prices', '/rates'] as $former) {
            $this->client->request('GET', $former);
            self::assertResponseRedirects('http://localhost/plans', 301, $former);
        }
        self::assertSame("/prices\n/rates\n/tariffs", trim($this->arrive($record)->filter('textarea[name="former"]')->text(null, false)));

        $refused = $this->post($record, ['title' => 'Tariffs', 'path' => '/plans', 'former' => '/admin/pages', 'published' => '1']);
        self::assertSame(self::phrase(t('pages.refusal.path_route', ['path' => '/admin/pages'])), trim($refused->filter('.field-refusal')->text()));

        $this->post(self::LIST.'/new', ['title' => 'Prices', 'path' => '/prices']);
        self::assertNotNull(self::service(PageRepository::class)->findOneBy(['path' => '/prices']), 'a former address is free for another page');
        self::assertSame("/rates\n/tariffs", trim($this->arrive($record)->filter('textarea[name="former"]')->text(null, false)));
        $this->client->request('GET', '/prices');
        self::assertResponseStatusCodeSame(404, 'the page taking a former address answers it once published');
    }

    /** @return iterable<string, array{string, string}> */
    public static function addresses(): iterable
    {
        yield 'address of another page' => ['/about', 'pages.refusal.path_page'];
        yield 'address of a module section' => ['/admin/pages', 'pages.refusal.path_route'];
        yield 'address out of the format' => ['/about us', 'pages.refusal.path'];
    }

    /** @return list<string> */
    private static function cells(Crawler $listing, int $row): array
    {
        return $listing->filter('tbody tr')->eq($row)->filter('td:not(.row-handle)')->each(static fn (Crawler $cell): string => trim($cell->text()));
    }
}
