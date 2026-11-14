<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Support\HelpView;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class HelpTest extends SupportTestCase
{
    use KnowledgeRecords;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->member();
    }

    #[Group('ACMS-SUP-0036')]
    #[Group('ACMS-US-0009')]
    public function testMenuOpensHelpWindowOnlyWithPublishedArticle(): void
    {
        $this->client->loginUser($this->user);
        $this->article($this->category('Domains', 'domain'), 'How to add a domain', 'add', false);

        self::assertCount(0, $this->client->request('GET', '/support')->filter('[data-record="/support/help/window"]'));
        $this->client->request('GET', '/support/help');
        self::assertResponseStatusCodeSame(404);

        $this->article($this->category('Payment', 'billing'), 'Balance top-up', 'topup', true);

        self::assertCount(1, $this->client->request('GET', '/support')->filter('button.nav-item[data-record="/support/help/window"]'));
        self::assertCount(1, $this->client->request('GET', '/support/help/window')->filter('.help input[type="search"][hx-get="/support/help"]'));
    }

    #[Group('ACMS-SUP-0014')]
    #[Group('ACMS-SUP-0015')]
    #[Group('ACMS-SUP-0030')]
    #[Group('ACMS-US-0009')]
    public function testWindowListsPageArticlesCategoriesAndFound(): void
    {
        $this->knowledge();
        $this->client->loginUser($this->user);

        $here = $this->fragment('/support/help', 'https://lk.example.test/service/hosting/domains/example.org/mail');
        self::assertSame(self::phrase(HelpView::Page->label()), trim($here->filter('.help-group')->text()));
        self::assertSame(['/help/domain/add'], self::addresses($here));
        self::assertStringContainsString('Domains', $here->filter('.help-link')->text());
        self::assertSame('_blank', $here->filter('a.help-link')->attr('target'));

        self::assertSame(['/help/billing/topup'], self::addresses($this->fragment('/support/help', '/')));
        self::assertSame(['/help/billing/topup'], self::addresses($this->fragment('/support/help', '/payment/auto')));

        $none = $this->fragment('/support/help', '/payments');
        self::assertSame(self::phrase(HelpView::Categories->label()), trim($none->filter('.help-group')->text()), 'a path matches only up to a segment');
        self::assertSame(['/help#domain', '/help#billing'], self::addresses($none), 'a category without a published article stays hidden');
        self::assertSame('/help', self::address((string) $none->filter('a.button')->attr('href')));

        $found = $this->fragment('/support/help?q=Domain');
        self::assertSame(self::phrase(HelpView::Found->label()), trim($found->filter('.help-group')->text()));
        self::assertSame(['/help/domain/add', '/help/billing/invoices'], self::addresses($found), 'the heading match goes first, an unpublished article stays hidden');

        self::assertSame(['/help/billing/topup'], self::addresses($this->fragment('/support/help?q='.rawurlencode('top-up card'))), 'every word of the query is required');

        $nothing = $this->fragment('/support/help?q='.rawurlencode('no such thing'));
        self::assertSame('/support/new', $nothing->filter('.help-empty a')->attr('href'));
    }

    #[Group('ACMS-SUP-0016')]
    #[Group('ACMS-US-0008')]
    public function testStaffPicksArticleForReplyByTicketSubject(): void
    {
        $this->knowledge();
        $id = $this->ticket($this->user, 'Cannot add a domain')->getId();

        $this->client->loginUser($this->staff);
        $page = $this->client->request('GET', \sprintf('/support/%d', $id));
        self::assertSame(\sprintf('/support/help/ticket/%d', $id), $page->filter('.help-insert input')->attr('hx-get'));

        self::assertSame(
            ['How to add a domain — /help/domain/add', 'Invoices to organizations — /help/billing/invoices'],
            self::inserts($this->fragment(\sprintf('/support/help/ticket/%d', $id))),
        );
        self::assertSame(['Balance top-up — /help/billing/topup'], self::inserts($this->fragment(\sprintf('/support/help/ticket/%d?q=balance', $id))));

        $this->client->loginUser($this->user);
        self::assertCount(0, $this->client->request('GET', \sprintf('/support/%d', $id))->filter('.help-insert'));
        $this->fragment(\sprintf('/support/help/ticket/%d', $id));
        self::assertResponseRedirects('/');
    }

    #[Group('ACMS-SUP-0004')]
    #[Group('ACMS-US-0008')]
    public function testMessageAddressIsLinkAndTextStaysEscaped(): void
    {
        $id = $this->ticket($this->user, 'Question', body: 'Article https://help.example.test/help/a?b=1&c=2. Otherwise <script>x</script>')->getId();

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

    private function knowledge(): void
    {
        $domains = $this->category('Domains', 'domain', 'Registration and transfer');
        $billing = $this->category('Payment', 'billing', 'Balance and invoices');
        $hidden = $this->category('Hidden', 'hidden');
        $this->article($domains, 'How to add a domain', 'add', true, 'Open the domains section', ['dns'], 'A site on your own name', ['/service/hosting/domains']);
        $this->article($billing, 'Balance top-up', 'topup', true, 'Card or SBP', ['payment'], 'Ways to pay', ['/payment', '/']);
        $this->article($billing, 'Invoices to organizations', 'invoices', true, 'No domain is needed for an invoice', [], 'Issuing an invoice');
        $this->article($hidden, 'Domain draft', 'draft', false, 'domain', [], null, ['/payments']);
    }

    private static function address(string $url): string
    {
        $fragment = parse_url($url, \PHP_URL_FRAGMENT);

        return parse_url($url, \PHP_URL_PATH).(\is_string($fragment) ? '#'.$fragment : '');
    }

    /** @return list<string> */
    private static function addresses(Crawler $fragment): array
    {
        return $fragment->filter('a.help-link')->each(static fn (Crawler $link): string => self::address((string) $link->attr('href')));
    }

    /** @return list<string> */
    private static function inserts(Crawler $fragment): array
    {
        return $fragment->filter('[data-help-insert]')->each(static function (Crawler $link): string {
            [$title, $url] = explode(' — ', (string) $link->attr('data-help-insert'), 2);

            return $title.' — '.self::address($url);
        });
    }
}
