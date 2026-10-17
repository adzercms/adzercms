<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Support\Entity\Ticket;
use AdzerCMS\Support\HelpView;
use AdzerCMS\Support\SupportSettings;
use AdzerCMS\Support\Tickets;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HelpTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;

    private const INDEX = 'https://help.example.test/api/help.php';
    private const SITE = 'https://help.example.test';

    private int $fetched = 0;
    private bool $down = false;
    private User $user;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        self::service(HttpAnswers::class)->respond(function (string $method, string $url): MockResponse {
            ++$this->fetched;
            self::assertSame(self::INDEX, $url);

            return $this->down ? new MockResponse('', ['http_code' => 502]) : new JsonMockResponse(self::index());
        });

        $this->user = $this->record('79990000001');
        $this->staff = $this->record('79990000002')->setRole(Role::Admin);
        $this->configure([SupportSettings::HELP_INDEX => self::INDEX]);
    }

    #[Group('ACMS-UI-0001')]
    #[Group('ACMS-US-0009')]
    public function testMenuOpensHelpWindowOnlyWithIndex(): void
    {
        $this->client->loginUser($this->user);

        $page = $this->client->request('GET', '/support');
        self::assertCount(1, $page->filter('button.nav-item[data-record="/help/window"]'));
        self::assertCount(1, $this->client->request('GET', '/help/window')->filter('.help input[type="search"][hx-get="/help"]'));

        $this->configure([SupportSettings::HELP_INDEX => '']);

        self::assertCount(0, $this->client->request('GET', '/support')->filter('[data-record="/help/window"]'));
        $this->client->request('GET', '/help');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->fetched);
    }

    #[Group('ACMS-SUP-0013')]
    #[Group('ACMS-SUP-0014')]
    #[Group('ACMS-SUP-0015')]
    #[Group('ACMS-US-0009')]
    public function testWindowListsPageArticlesCategoriesAndFound(): void
    {
        $this->client->loginUser($this->user);

        $here = $this->fragment('/help', 'https://lk.example.test/service/hosting/domains/example.org/mail');
        self::assertSame(self::phrase(HelpView::Page->label()), trim($here->filter('.help-group')->text()));
        self::assertSame([self::SITE.'/help/domain/add'], self::urls($here));
        self::assertStringContainsString('Domains', $here->filter('.help-link')->text());

        self::assertSame([self::SITE.'/help/billing/topup'], self::urls($this->fragment('/help', '/')));
        self::assertSame([self::SITE.'/help/billing/topup'], self::urls($this->fragment('/help', '/payment/auto')));

        $none = $this->fragment('/help', '/profile');
        self::assertSame(self::phrase(HelpView::Categories->label()), trim($none->filter('.help-group')->text()));
        self::assertSame([self::SITE.'/help/#domain', self::SITE.'/help/#billing'], self::urls($none));
        self::assertSame(self::SITE.'/help/', $none->filter('a.button')->attr('href'));

        $found = $this->fragment('/help?q=Domain');
        self::assertSame(self::phrase(HelpView::Found->label()), trim($found->filter('.help-group')->text()));
        self::assertSame([self::SITE.'/help/domain/add', self::SITE.'/help/billing/invoices'], self::urls($found), 'the heading match goes first, an unsafe address is dropped');

        self::assertSame([self::SITE.'/help/billing/topup'], self::urls($this->fragment('/help?q='.rawurlencode('top-up card'))), 'every word of the query is required');

        $nothing = $this->fragment('/help?q='.rawurlencode('no such thing'));
        self::assertSame('/support/new', $nothing->filter('.help-empty a')->attr('href'));

        self::assertSame(1, $this->fetched, 'the index is fetched once per term');
    }

    #[Group('ACMS-SUP-0014')]
    #[Group('ACMS-US-0009')]
    public function testUnreachableIndexIsNamedAndSiteStaysLinked(): void
    {
        $this->down = true;
        $this->client->loginUser($this->user);

        $window = $this->fragment('/help');
        self::assertSame(self::phrase(HelpView::Missing->label()), trim($window->filter('.help-empty')->text()));
        self::assertSame(self::SITE.'/', $window->filter('a.button')->attr('href'));

        $this->client->request('GET', '/support');
        self::assertResponseIsSuccessful();
    }

    #[Group('ACMS-SUP-0016')]
    #[Group('ACMS-US-0008')]
    public function testStaffPicksArticleForReplyByTicketSubject(): void
    {
        $id = $this->ticket('Cannot add a domain')->getId();

        $this->client->loginUser($this->staff);
        $page = $this->client->request('GET', \sprintf('/support/%d', $id));
        self::assertSame(\sprintf('/help/ticket/%d', $id), $page->filter('.help-insert input')->attr('hx-get'));

        self::assertSame(
            ['How to add a domain — '.self::SITE.'/help/domain/add', 'Invoices to organizations — '.self::SITE.'/help/billing/invoices'],
            self::inserts($this->fragment(\sprintf('/help/ticket/%d', $id))),
        );
        self::assertSame(['Balance top-up — '.self::SITE.'/help/billing/topup'], self::inserts($this->fragment(\sprintf('/help/ticket/%d?q=balance', $id))));

        $this->client->loginUser($this->user);
        self::assertCount(0, $this->client->request('GET', \sprintf('/support/%d', $id))->filter('.help-insert'));
        $this->fragment(\sprintf('/help/ticket/%d', $id));
        self::assertResponseRedirects('/');
    }

    #[Group('ACMS-SUP-0004')]
    #[Group('ACMS-US-0008')]
    public function testMessageAddressIsLinkAndTextStaysEscaped(): void
    {
        $id = $this->ticket('Question', 'Article https://help.example.test/help/a?b=1&c=2. Otherwise <script>x</script>')->getId();

        $this->client->loginUser($this->user);
        $page = $this->client->request('GET', \sprintf('/support/%d', $id));
        self::assertResponseIsSuccessful();
        $text = $page->filter('.chat-text')->first();

        $link = $text->filter('a');
        self::assertSame('https://help.example.test/help/a?b=1&c=2', $link->attr('href'));
        self::assertSame('_blank', $link->attr('target'));
        self::assertSame('noreferrer noopener', $link->attr('rel'));
        self::assertCount(0, $text->filter('script'));
        self::assertStringContainsString('<script>x</script>', $text->text());
    }

    /** @return array<string, mixed> */
    private static function index(): array
    {
        return [
            'url' => '/help/',
            'categories' => [
                ['slug' => 'domain', 'title' => 'Domains', 'summary' => 'Registration and transfer', 'url' => '/help/#domain'],
                ['slug' => 'billing', 'title' => 'Payment', 'summary' => 'Balance and invoices', 'url' => '/help/#billing'],
            ],
            'articles' => [
                ['category' => 'domain', 'title' => 'How to add a domain', 'summary' => 'A site on your own name', 'tags' => ['dns'], 'pages' => ['/service/hosting/domains'], 'body' => 'Open the domains section', 'url' => '/help/domain/add'],
                ['category' => 'billing', 'title' => 'Balance top-up', 'summary' => 'Ways to pay', 'tags' => ['payment'], 'pages' => ['/payment', '/'], 'body' => 'Card or SBP', 'url' => '/help/billing/topup'],
                ['category' => 'billing', 'title' => 'Invoices to organizations', 'summary' => 'Issuing an invoice', 'tags' => [], 'pages' => [], 'body' => 'No domain is needed for an invoice', 'url' => self::SITE.'/help/billing/invoices'],
                ['category' => 'billing', 'title' => 'Foreign scheme', 'summary' => 'Not a link', 'pages' => ['/profile'], 'body' => 'domain', 'url' => 'javascript:alert(1)'],
            ],
        ];
    }

    private function ticket(string $subject, string $body = 'First message'): Ticket
    {
        $opened = self::service(Tickets::class)->open($this->user, $subject, self::service(SupportSettings::class)->departments()[0], $body);

        return $opened instanceof Ticket ? $opened : self::fail('no ticket was opened');
    }

    /** @return list<string> */
    private static function urls(Crawler $fragment): array
    {
        return $fragment->filter('a.help-link')->each(static fn (Crawler $link): string => (string) $link->attr('href'));
    }

    /** @return list<string> */
    private static function inserts(Crawler $fragment): array
    {
        return $fragment->filter('[data-help-insert]')->each(static fn (Crawler $link): string => (string) $link->attr('data-help-insert'));
    }
}
