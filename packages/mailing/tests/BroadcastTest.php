<?php

declare(strict_types=1);

namespace AdzerCMS\Mailing\Tests;

use AdzerCMS\Contracts\Notice\BotGateway;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\ConnectedService;
use AdzerCMS\Core\Entity\Notice;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Notice\QuietHours;
use AdzerCMS\Core\Notice\Status;
use AdzerCMS\Core\Repository\NoticeRepository;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Delivery;
use AdzerCMS\Core\Tests\Notice\Fixture\Chime\ChimeGateway;
use AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelayGateway;
use AdzerCMS\Core\Tests\Queries;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Mailing\Broadcast;
use AdzerCMS\Mailing\Tests\Fixture\CommentAudience;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class BroadcastTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;
    use Delivery;
    use Queries;

    private const BODY = 'Works from 10 to 12';
    private const CHIME = [ChimeGateway::SETTINGS.Switches::ENABLED => '1', ChimeGateway::SETTINGS.BotGateway::TOKEN => 'chime-token'];
    private const QUIET = [QuietHours::FROM => '22:00', QuietHours::TO => '07:00'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->record('79990000001')->setRole(Role::Admin);
        $this->em->flush();
        $this->client->loginUser($this->admin);
    }

    /** @return iterable<string, array{array<string, string>, int}> */
    public static function scopes(): iterable
    {
        yield 'everyone by the first channel of each' => [[], 4];
        yield 'everyone hearing messages' => [['channel' => 'sms'], 2];
        yield 'everyone hearing mail' => [['channel' => 'mail'], 2];
        yield 'selection of a module' => [['scope' => CommentAudience::KEY, CommentAudience::KEY => 'North'], 2];
        yield 'selection of a module by a channel its users lack' => [['scope' => CommentAudience::KEY, CommentAudience::KEY => 'South', 'channel' => 'sms'], 0];
        yield 'unknown value of a selection takes its first' => [['scope' => CommentAudience::KEY, CommentAudience::KEY => 'East'], 2];
    }

    /** @param array<string, string> $query */
    #[DataProvider('scopes')]
    #[Group('ACMS-CORE-0146')]
    #[Group('ACMS-US-0005')]
    public function testRecipientsFollowScopeAndChannel(array $query, int $count): void
    {
        $this->configure(self::MAIL + self::RELAY);
        $this->record('79991234561')->setComment('North');
        $this->record('79991234562', 'south@example.org')->setPhone(null)->setComment('South');
        $this->record('79991234563', 'north@example.org')->setComment('North')->mute(['sms']);
        $this->record('79991234564', 'gone@example.org')->setComment('North')->delete();
        $this->em->flush();

        $scope = $this->fragment('/admin/notices/recipients?'.http_build_query($query));

        self::assertSame((string) $count, self::tile($scope, self::phrase('mailing.recipients')));
        self::assertCount(isset($query['scope']) ? 1 : 0, $scope->filter(\sprintf('select[name="%s"]', CommentAudience::KEY)), 'the selection of a module brings its field');
    }

    #[Group('ACMS-TEST-0034')]
    #[Group('ACMS-US-0005')]
    public function testRecipientsAreCountedByOneQuery(): void
    {
        $this->configure(self::MAIL + self::CHIME);

        $this->assertListingKeepsItsQueries('/admin/notices/recipients', function (int $from, int $to): void {
            for ($number = $from; $number <= $to; ++$number) {
                $user = $this->record(\sprintf('7999300%04d', $number), \sprintf('row%d@example.org', $number));
                $this->em->persist(new ConnectedService($user, ChimeGateway::SERVICE, 'chime-'.$number, null));
            }
            $this->em->flush();
        });
    }

    #[Group('ACMS-CORE-0121')]
    #[Group('ACMS-CORE-0146')]
    #[Group('ACMS-US-0005')]
    public function testBroadcastGoesToEachByItsChannelIntoItsJournal(): void
    {
        $this->configure(self::MAIL + self::RELAY + self::CHIME);
        $chimed = $this->record('79991234561', 'chimed@example.org');
        $this->em->persist(new ConnectedService($chimed, ChimeGateway::SERVICE, 'chime-1', null));
        $mailed = $this->record('79991234562', 'mailed@example.org')->setPhone(null);
        $this->em->flush();

        $sent = $this->broadcast(['channel' => '']);

        self::assertSame(self::phrase('mailing.flash.sent', ['sent' => 3, 'deferred' => 0, 'failed' => 0]), self::toast($sent, 'success'));
        self::assertSame(['chime', 'mail'], [$this->notice($chimed)->getChannel(), $this->notice($mailed)->getChannel()]);
        self::assertSame([['chime-1', self::BODY]], self::service(ChimeGateway::class)->sent);
        self::assertCount(1, $this->client->request('GET', '/admin/notices?view=journal&tab=mail')->filter('tbody tr')->reduce(static fn (Crawler $row): bool => str_contains($row->text(), 'mailed@example.org')));
    }

    #[Group('ACMS-CORE-0146')]
    #[Group('ACMS-US-0005')]
    public function testBroadcastKeepsToTheChosenChannel(): void
    {
        $this->configure(self::MAIL + self::RELAY);
        $user = $this->record('79991234561', 'human@example.org');
        $this->em->flush();

        $sent = $this->broadcast(['channel' => ContactKind::Email->channel()]);

        self::assertSame(self::phrase('mailing.flash.sent', ['sent' => 1, 'deferred' => 0, 'failed' => 0]), self::toast($sent, 'success'));
        self::assertSame('/admin/notices?view=journal&tab=mail', $this->client->getRequest()->getRequestUri(), 'the outcome opens the journal of the channel');
        self::assertSame(ContactKind::Email->channel(), $this->notice($user)->getChannel(), 'the chosen channel does not give way to the phone');
        self::assertSame([], self::service(RelayGateway::class)->sent);
    }

    #[Group('ACMS-CORE-0131')]
    #[Group('ACMS-US-0005')]
    public function testOutcomeNamesBroadcastDeferredByQuiet(): void
    {
        self::mockTime('2026-10-16 23:30:00');
        $this->configure(self::MAIL + self::QUIET);
        $user = $this->record('79991234561', 'human@example.org');
        $this->em->flush();

        $sent = $this->broadcast(['channel' => '']);

        self::assertSame(self::phrase('mailing.flash.sent', ['sent' => 0, 'deferred' => 1, 'failed' => 0]), self::toast($sent, 'success'));
        self::assertSame(Status::Deferred, $this->notice($user)->getStatus());
    }

    /** @return iterable<string, array{string, string}> */
    public static function bodies(): iterable
    {
        yield 'empty' => [' ', 'mailing.refusal.empty'];
        yield 'past the limit' => [str_repeat('a', Broadcast::LIMIT + 1), 'mailing.refusal.long'];
    }

    #[DataProvider('bodies')]
    #[Group('ACMS-CORE-0146')]
    #[Group('ACMS-US-0005')]
    public function testBroadcastOutOfItsBoundsIsRefused(string $body, string $reason): void
    {
        $this->configure(self::MAIL + self::RELAY);

        $refused = $this->broadcast(['body' => $body, 'channel' => '']);

        self::assertSame(self::phrase($reason, ['limit' => Broadcast::LIMIT]), self::toast($refused, 'error'));
        self::assertSame([], self::service(NoticeRepository::class)->findAll());
    }

    #[Group('ACMS-US-0005')]
    public function testJournalTabWarnsOfChannelNotSetUp(): void
    {
        $this->configure(self::RELAY);

        $mail = $this->client->request('GET', '/admin/notices?view=journal&tab=mail');
        self::assertSame(['sms', 'mail'], $mail->filter('.tabs')->last()->filter('a')->each(static fn (Crawler $tab): string => self::tab((string) $tab->attr('href'))), 'the journal has a tab of every channel');
        self::assertSame(self::phrase('mailing.unconfigured'), trim($mail->filter('.flash-error')->text()));

        $default = $this->client->request('GET', '/admin/notices?view=journal&tab=unknown');
        self::assertSame('sms', self::tab((string) $default->filter('.tabs')->last()->filter('a.active')->attr('href')), 'an unknown tab opens the first channel set up');
        self::assertCount(0, $default->filter('.flash-error'));
    }

    /** @param array<string, string> $fields */
    private function broadcast(array $fields): Crawler
    {
        $this->post('/admin/notices/broadcast', $fields + ['body' => self::BODY, 'scope' => Broadcast::EVERYONE]);

        return $this->client->followRedirect();
    }

    private function notice(User $user): Notice
    {
        return self::service(NoticeRepository::class)->findOneBy(['user' => $user]) ?? self::fail('the record got nothing');
    }

    private static function tab(string $href): string
    {
        parse_str((string) parse_url($href, \PHP_URL_QUERY), $query);

        return \is_string($query['tab'] ?? null) ? $query['tab'] : '';
    }
}
