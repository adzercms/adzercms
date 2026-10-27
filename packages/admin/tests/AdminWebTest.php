<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests;

use AdzerCMS\Contracts\Setting\Tab;
use AdzerCMS\Contracts\Ui\Scheme;
use AdzerCMS\Core\Account\ConsentKind;
use AdzerCMS\Core\Account\Password;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Account\Theme;
use AdzerCMS\Core\Confirmation\Kind;
use AdzerCMS\Core\Confirmation\Purpose;
use AdzerCMS\Core\Entity\Confirmation;
use AdzerCMS\Core\Entity\ConnectedService;
use AdzerCMS\Core\Entity\Consent;
use AdzerCMS\Core\Entity\Login;
use AdzerCMS\Core\Entity\Notice;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Notice\MailChannel;
use AdzerCMS\Core\Notice\QuietHours;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Seo\Indexing;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Setting\Language;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Setting\Toggle;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Delivery;
use AdzerCMS\Core\Tests\Queries;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\Ui\Fixture\Ledger;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;

final class AdminWebTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;
    use Delivery;
    use Queries;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->record('79990000001')->setRole(Role::Admin);
        $this->em->flush();
    }

    #[Group('ACMS-CORE-0086')]
    #[Group('ACMS-UI-0003')]
    #[Group('ACMS-UI-0004')]
    #[Group('ACMS-US-0003')]
    public function testOperatorEntersCabinetOfServedRecordOnlyAndComesBack(): void
    {
        $served = $this->record('79990000010');
        $stopped = $this->record('79990000011')->setStatus(Status::Stopped);
        $colleague = $this->record('79990000012')->setRole(Role::Admin);
        $this->em->flush();
        $this->client->loginUser($this->admin);

        self::assertCount(1, $this->switchLinks($served));
        self::assertCount(0, $this->switchLinks($stopped), 'the record out of service is not offered');
        self::assertCount(0, $this->switchLinks($colleague), 'another administrator is not offered');

        $under = $this->arrive('/?_switch_user='.$served->getId());

        self::assertStringContainsString((string) $served->getId(), $under->filter('.impersonate-bar')->text());
        self::assertStringContainsString('impersonating', (string) $under->filter('html')->attr('class'));
        self::assertCount(0, $under->filter('.admin-bar, .nav-section-admin'), 'under another record there are no administrative sections');

        $back = $this->arrive('/admin/users');

        self::assertSame('/admin/users', $this->client->getRequest()->getPathInfo(), 'the operator returns to the own record and opens the asked page');
        self::assertCount(0, $back->filter('.impersonate-bar'));
        self::assertSame(
            $back->filter('.nav-section-admin .nav-item')->each(static fn (Crawler $item): string => trim($item->text())),
            $back->filter('.admin-bar-link')->each(static fn (Crawler $item): string => trim($item->text())),
            'the side menu and the bar take the same items',
        );

        foreach ([$stopped, $colleague] as $refused) {
            $this->client->request('GET', '/?_switch_user='.$refused->getId());
            self::assertResponseRedirects('/');
            self::assertCount(0, $this->client->followRedirect()->filter('.impersonate-bar'));
        }
    }

    #[Group('ACMS-CORE-0183')]
    #[Group('ACMS-CORE-0086')]
    #[Group('ACMS-US-0003')]
    public function testClosedPagesSendGuestToLoginAndUserHome(): void
    {
        $paths = [];
        foreach (self::service(RouterInterface::class, 'router')->getRouteCollection() as $route) {
            $methods = $route->getMethods();
            if (str_starts_with($route->getPath(), '/admin') && [] === $route->compile()->getPathVariables() && ([] === $methods || \in_array('GET', $methods, true))) {
                $paths[] = $route->getPath();
            }
        }
        self::assertContains('/admin/users', $paths);

        foreach ($paths as $path) {
            $this->client->request('GET', $path);
            self::assertResponseRedirects('/login', null, $path);
        }

        $this->client->loginUser($this->record('79990000010'));
        foreach ($paths as $path) {
            $this->client->request('GET', $path);
            self::assertResponseRedirects('/', null, $path);
        }

        $this->client->followRedirects();
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
    }

    #[Group('ACMS-CORE-0068')]
    #[Group('ACMS-CORE-0099')]
    #[Group('ACMS-US-0003')]
    public function testContactsAreKeptNormalizedAndTakenOnesNameTheirHolder(): void
    {
        $holder = $this->record('79991234567', 'holder@example.org');
        $this->client->loginUser($this->admin);

        $this->post('/admin/users/new', ['phone' => '+7 (999) 765-43-21', 'email' => 'abonent@example.org']);
        $user = $this->created();

        self::assertSame('79997654321', $user->getPhone());
        self::assertSame('abonent@example.org', $user->getEmail());

        $url = \sprintf('/admin/users/%d', $user->getId());
        foreach (['phone' => '12345', 'email' => 'no-at-sign'] as $kind => $value) {
            $page = $this->post($url, [$kind => $value] + ['phone' => '79997654321', 'email' => 'abonent@example.org']);
            self::assertSame(self::phrase('admin.user.malformed.'.$kind), trim($page->filter('.flash-error')->text()));
        }

        $kept = $this->reread($user);
        self::assertSame('79997654321', $kept->getPhone());
        self::assertSame('abonent@example.org', $kept->getEmail());

        foreach (['phone' => '8 999 123 45 67', 'email' => 'holder@example.org'] as $kind => $value) {
            $dialog = $this->post('/admin/users/new', [$kind => $value]);
            self::assertResponseIsSuccessful();
            self::assertSame(self::phrase('admin.user.taken.'.$kind, ['id' => $holder->getId()]), trim($dialog->filter('.flash-error')->text()));
        }
        self::assertSame(3, self::service(UserRepository::class)->count([]));
    }

    #[Group('ACMS-CORE-0066')]
    #[Group('ACMS-CORE-0163')]
    #[Group('ACMS-US-0003')]
    public function testCreationAsksNothingAndKeepsWhatIsGiven(): void
    {
        $this->client->loginUser($this->admin);

        $this->post('/admin/users/new');
        $empty = $this->created();

        self::assertSame(Status::Active, $empty->getStatus());
        self::assertSame(Role::User, $empty->getRole());
        self::assertNull($empty->getPhone());
        self::assertNull($empty->getEmail());
        self::assertNull($empty->getComment());

        $short = $this->post('/admin/users/new', ['password' => 'short']);
        self::assertSame(self::phrase('core.password.short', ['min' => Password::MIN_LENGTH]), trim($short->filter('.flash-error')->text()));

        $long = str_repeat('я', User::COMMENT_LIMIT + 5);
        $this->post('/admin/users/new', ['comment' => $long, 'note' => 'Key at the neighbour', 'last_name' => 'Ivanov', 'password' => 'given-password']);
        $named = $this->created();

        self::assertSame(mb_substr($long, 0, User::COMMENT_LIMIT), $named->getComment(), 'the comment is cut to the core limit');
        self::assertSame('Key at the neighbour', $named->getNote());
        self::assertSame('Ivanov', $named->getLastName());
        self::assertTrue(self::service(UserPasswordHasherInterface::class)->isPasswordValid($named, 'given-password'));
    }

    #[Group('ACMS-CORE-0101')]
    #[Group('ACMS-CORE-0163')]
    #[Group('ACMS-US-0003')]
    public function testPasswordIsIssuedByDialogAndMailedOnlyWhenAsked(): void
    {
        $this->configure(self::MAIL);
        $user = $this->record('79990000010', 'abonent@example.org');
        $bare = $this->record('79990000011');
        $this->client->loginUser($this->admin);
        $url = \sprintf('/admin/users/%d/password', $user->getId());
        $hasher = self::service(UserPasswordHasherInterface::class);

        self::assertCount(1, $this->client->request('GET', \sprintf('/admin/users/%d', $user->getId()))->filter(\sprintf('[hx-get="%s"]', $url)));

        $dialog = $this->fragment($url);
        self::assertGreaterThanOrEqual(Password::MIN_LENGTH, mb_strlen((string) $dialog->filter('input[name="password"]')->attr('value')), 'the cabinet offers a ready password');
        self::assertCount(1, $dialog->filter('input[name="mail"]'));

        $short = $this->post($url, ['password' => 'short', 'mail' => Toggle::On->value]);
        self::assertSame(self::phrase('core.password.short', ['min' => Password::MIN_LENGTH]), trim($short->filter('.flash-error')->text()));
        self::assertCount(1, $short->filter('form input[name="password"]'), 'the refusal stays in the dialog');
        self::assertEmailCount(0);
        self::assertTrue($hasher->isPasswordValid($this->reread($user), self::PASSWORD));

        $issued = $this->post($url, ['password' => 'issued-password', 'mail' => Toggle::On->value]);
        self::assertSame('issued-password', $issued->filter('#issued-password')->attr('value'));
        self::assertSame('abonent@example.org', $this->letter()->getTo()[0]->getAddress());
        self::assertStringContainsString('issued-password', $this->letterText());
        self::assertTrue($hasher->isPasswordValid($this->reread($user), 'issued-password'));

        $this->post($url, ['password' => 'unmailed-password', 'mail' => Toggle::Off->value]);
        self::assertEmailCount(0);
        self::assertTrue($hasher->isPasswordValid($this->reread($user), 'unmailed-password'));

        self::assertCount(0, $this->fragment(\sprintf('/admin/users/%d/password', $bare->getId()))->filter('input[name="mail"]'), 'a record without email is not offered a letter');
    }

    #[Group('ACMS-CORE-0074')]
    #[Group('ACMS-US-0003')]
    public function testRecordDeletedByItsUserShowsMomentUntilOperatorReturnsIt(): void
    {
        self::mockTime('2026-09-20 10:15');
        $user = $this->record('79990000010')->delete();
        $this->em->flush();
        $this->client->loginUser($this->admin);

        $page = $this->client->request('GET', \sprintf('/admin/users/%d', $user->getId()));
        self::assertSame(self::phrase('core.moment.day_time', ['at' => new \DateTimeImmutable('2026-09-20 10:15')]), self::tile($page, self::phrase('admin.user.deleted')));

        $this->client->submit($page->selectButton(self::phrase('core.action.save'))->form(['status' => Status::Active->value]));

        $returned = $this->reread($user);
        self::assertSame(Status::Active, $returned->getStatus());
        self::assertNull($returned->getDeletedAt(), 'the return from the archive takes the moment off');
        self::assertSame('79990000010', $returned->getPhone());
    }

    #[Group('ACMS-UI-0021')]
    #[Group('ACMS-CORE-0069')]
    #[Group('ACMS-US-0003')]
    public function testArchiveIsOwnTabAndTabsCarryCounts(): void
    {
        $served = $this->record('79990000010');
        $stopped = $this->record('79990000011')->setStatus(Status::Stopped);
        $archived = $this->record('79990000012')->setStatus(Status::Archived);
        $this->em->flush();
        $this->client->loginUser($this->admin);

        self::assertSame(
            [self::phrase('admin.users.tab', ['count' => 3]), self::phrase('admin.users.archive', ['count' => 1])],
            $this->client->request('GET', '/admin/users')->filter('.tabs .tab')->each(static fn (Crawler $tab): string => trim($tab->text())),
        );
        self::assertEqualsCanonicalizing([$this->admin->getId(), $served->getId(), $stopped->getId()], self::ids($this->fragment('/admin/users')));
        self::assertSame([$archived->getId()], self::ids($this->fragment('/admin/users?tab=archive')));
    }

    #[Group('ACMS-UI-0015')]
    #[Group('ACMS-UI-0016')]
    #[Group('ACMS-CORE-0076')]
    #[Group('ACMS-US-0003')]
    public function testUserListNamesRecordByLoginAndCarriesModuleColumnsBeforeState(): void
    {
        $owing = $this->record('79990000010');
        $paid = $this->record('79990000011');
        $ledger = self::service(Ledger::class);
        $ledger->balances = [(int) $owing->getId() => '-150', (int) $paid->getId() => '300'];
        $this->client->loginUser($this->admin);

        $listing = $this->fragment('/admin/users');

        $login = self::phrase('core.user.login');
        self::assertSame(
            [$login, self::phrase('core.user.created'), Ledger::LABEL, self::phrase('core.user.state')],
            $listing->filter('thead th')->each(static fn (Crawler $head): string => trim($head->text())),
        );
        self::assertCount(0, $listing->filter('thead th a')->reduce(static fn (Crawler $link): bool => Ledger::LABEL === trim($link->text())), 'a column of a module is not sorted');

        $row = 'u'.$owing->getId();
        self::assertSame($row, trim(self::cell($listing, $row, $login)->filter('strong')->text()), 'the login of the module names the record');
        self::assertSame('-150', trim(self::cell($listing, $row, Ledger::LABEL)->text()));
        self::assertStringContainsString('minus', (string) self::cell($listing, $row, Ledger::LABEL)->attr('class'));
        self::assertSame([self::ids($listing)], $ledger->asked, 'the module gives the cells of the page at once');
    }

    #[Group('ACMS-UI-0010')]
    #[Group('ACMS-UI-0011')]
    #[Group('ACMS-US-0003')]
    public function testSearchTakesFieldsOfStoryAndStateNarrowsList(): void
    {
        $found = $this->record('79991112233', 'north@example.org')->setComment('North 12')->setNote('Key at the neighbour')->setName('Ivanov', 'Petr', null);
        $stopped = $this->record('79994445566')->setStatus(Status::Stopped);
        $this->em->flush();
        self::service(Ledger::class)->balances = [(int) $found->getId() => 'ledger-150'];
        $this->client->loginUser($this->admin);

        foreach (['north@example', 'north 12', 'neighbour', 'ivanov', '1112233', '79991112233', '+7 999 111-22-33', 'ledger-150'] as $term) {
            self::assertSame([$found->getId()], self::ids($this->fragment('/admin/users?q='.rawurlencode($term))), $term);
        }

        self::assertSame([$stopped->getId()], self::ids($this->fragment('/admin/users?state='.Status::Stopped->value)));
        self::assertEqualsCanonicalizing([$this->admin->getId(), $found->getId()], self::ids($this->fragment('/admin/users?state='.Status::Active->value)));

        $page = $this->client->request('GET', '/admin/users');
        $search = $page->filter('.table-filters input[type="search"]');
        self::assertStringEndsWith('-data', (string) $search->attr('hx-target'), 'the typed field redraws only the data of the listing');
        self::assertCount(0, $page->filter('[id$="-data"] .table-filters'));
        self::assertNull($page->filter('.table-filters select')->attr('hx-target'), 'the choice redraws the listing whole');
    }

    #[Group('ACMS-TEST-0034')]
    #[Group('ACMS-UI-0015')]
    #[Group('ACMS-US-0003')]
    public function testListingTakesRowsByOneQuery(): void
    {
        $this->client->loginUser($this->admin);

        $this->assertListingKeepsItsQueries('/admin/users', function (int $from, int $to): void {
            for ($number = $from; $number <= $to; ++$number) {
                $this->record(\sprintf('7999100%04d', $number), \sprintf('row%d@example.org', $number))->setComment('Row '.$number);
            }
            $this->em->flush();
        });
    }

    #[Group('ACMS-CORE-0066')]
    #[Group('ACMS-CORE-0067')]
    #[Group('ACMS-US-0003')]
    public function testNotesLiveOnOwnTabAndSaveApartFromProfile(): void
    {
        $user = $this->record('79990000010')->setComment('North 12')->setNote('Former note');
        $this->em->flush();
        $this->client->loginUser($this->admin);

        $notes = $this->client->request('GET', \sprintf('/admin/users/%d?tab=notes', $user->getId()));
        self::assertContains(self::phrase('admin.user.notes'), $notes->filter('.tab')->each(static fn (Crawler $tab): string => trim($tab->text())));

        $this->post(\sprintf('/admin/users/%d/note', $user->getId()), ['note' => 'Key at the neighbour']);
        self::assertResponseRedirects(\sprintf('/admin/users/%d?tab=notes', $user->getId()));

        $profile = $this->client->request('GET', \sprintf('/admin/users/%d', $user->getId()));
        self::assertCount(0, $profile->filter('form textarea[name="note"]'), 'the profile carries no notes');
        $this->client->submit($profile->selectButton(self::phrase('core.action.save'))->form(['comment' => 'South 3']));

        $saved = $this->reread($user);
        self::assertSame('Key at the neighbour', $saved->getNote(), 'the profile leaves the notes as they are');
        self::assertSame('South 3', $saved->getComment());
    }

    #[Group('ACMS-CORE-0072')]
    #[Group('ACMS-UI-0026')]
    #[Group('ACMS-US-0003')]
    public function testRemovalTakesCoreRecordsAlong(): void
    {
        $user = $this->record('79990000010', 'gone@example.org');
        $this->em->persist(new Login($user, null, null, null));
        $this->em->persist(new ConnectedService($user, 'wicket', 'profile-1', null));
        $this->em->persist(new Confirmation($user, 'gone@example.org', Purpose::Contact, Kind::Link, hash('sha256', 'link')));
        $this->em->persist(new Consent($user, ConsentKind::Offer, '1', null));
        $this->em->persist(new Notice($user, 'core.event.password_issued', 'email', 'gone@example.org', 'Letter'));
        $this->em->flush();
        $id = $user->getId();
        $this->client->loginUser($this->admin);

        $page = $this->client->request('GET', \sprintf('/admin/users/%d', $id));
        self::assertNotSame('', (string) $page->filter('form[action$="/drop"]')->attr('data-confirm'), 'the removal asks first');
        self::assertCount(0, $page->filter('form[action$="/archive"]'));

        $this->post(\sprintf('/admin/users/%d/drop', $id));
        self::assertResponseRedirects('/admin/users');

        self::assertNull(self::service(UserRepository::class)->find($id));
        foreach (['login', 'connected_service', 'confirmation', 'consent', 'notice'] as $table) {
            self::assertSame(0, (int) $this->em->getConnection()->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE user_id = ?', $table), [$id]), $table);
        }
    }

    #[Group('ACMS-CORE-0071')]
    #[Group('ACMS-US-0003')]
    public function testOwnRecordIsNeitherRemovedNorSwitched(): void
    {
        $other = $this->record('79990000010');
        $this->client->loginUser($this->admin);
        $url = \sprintf('/admin/users/%d', $this->admin->getId());

        $own = $this->client->request('GET', $url);
        self::assertCount(0, $own->filter('form[action$="/drop"], form[action$="/archive"]'));
        self::assertCount(0, $own->filter('[name="status"], [name="role"]'));

        $this->post($url.'/drop');
        self::assertResponseRedirects($url);
        self::assertSame(self::phrase('core.user.remove_self'), self::toast($this->client->followRedirect(), 'error'));

        $this->post($url, ['phone' => '79990000001', 'status' => Status::Stopped->value, 'role' => Role::User->value]);
        $kept = $this->reread($this->admin);
        self::assertSame(Role::Admin, $kept->getRole());
        self::assertSame(Status::Active, $kept->getStatus());

        $page = $this->client->request('GET', \sprintf('/admin/users/%d', $other->getId()));
        self::assertCount(1, $page->filter('form[action$="/drop"]'));
        self::assertCount(2, $page->filter('select[name="status"], select[name="role"]'));
    }

    #[Group('ACMS-CORE-0021')]
    #[Group('ACMS-US-0003')]
    public function testDashboardTilesCountServedAndArchive(): void
    {
        $this->record('79990000010');
        $this->record('79990000011')->setStatus(Status::Archived);
        $this->em->flush();
        $this->client->loginUser($this->admin);

        $page = $this->client->request('GET', '/admin');
        self::assertSame('2', self::tile($page, self::phrase('admin.dashboard.users')));
        self::assertSame('1', self::tile($page, self::phrase('admin.dashboard.archive')));
    }

    #[Group('ACMS-CORE-0053')]
    #[Group('ACMS-CORE-0054')]
    #[Group('ACMS-CORE-0055')]
    #[Group('ACMS-CORE-0057')]
    #[Group('ACMS-US-0004')]
    public function testSettingsTabSavesWholeAndRefusesValueOutsideItsForm(): void
    {
        $this->client->loginUser($this->admin);
        $brand = Tab::Brand->value;

        $general = $this->client->request('GET', '/admin/settings');
        self::assertSame(Tab::General->value, $general->filter('form input[name="tab"]')->attr('value'), 'the settings open on the general tab');
        $tabs = $general->filter('.tabs .tab')->each(static fn (Crawler $tab): string => trim($tab->text()));
        self::assertSame(array_map(static fn (Tab $tab): string => self::phrase($tab->label()), Tab::cases()), \array_slice($tabs, 0, \count(Tab::cases())), 'the core tabs go first in their order');
        $modules = $sorted = \array_slice($tabs, \count(Tab::cases()));
        (new \Collator(self::service(Language::class)->instance()))->sort($sorted);
        self::assertSame($sorted, $modules, 'the module tabs follow by their title');
        $top = $general->filter('form .section')->first();
        self::assertCount(0, $top->filter('.section-head'), 'the fields without a section go first without a heading');
        self::assertCount(1, $top->filter(\sprintf('select[name="value[%s]"]', Language::NAME)));
        self::assertSame(Toggle::Off->value, $general->filter(\sprintf('select[name="value[%s]"] option[selected]', Indexing::NAME))->attr('value'),
            'an unset switch shows its off state');

        $page = $this->client->request('GET', '/admin/settings?tab='.$brand);
        $names = $page->filter('form [name]')->each(static fn (Crawler $node): string => (string) $node->attr('name'));
        self::assertNotContains(Brand::TITLE, $names, 'PHP replaces the dot of a field name');
        self::assertContains(\sprintf('value[%s]', Brand::TITLE), $names);
        self::assertSame(
            [self::phrase('admin.setting.unset'), ...array_map(static fn (Theme $theme): string => self::phrase($theme->label()), Theme::cases())],
            $page->filter(\sprintf('select[name="value[%s]"] option', Brand::THEME))->each(static fn (Crawler $option): string => trim($option->text())),
            'an unset choice goes with an empty variant first',
        );

        $this->client->submit($page->selectButton(self::phrase('core.action.save'))->form([
            \sprintf('value[%s]', Brand::TITLE) => 'Village network',
            \sprintf('value[%s]', Brand::THEME) => Theme::Dark->value,
        ]));
        self::assertResponseRedirects('/admin/settings?tab='.$brand);

        $settings = self::service(Settings::class);
        self::assertSame('Village network', $settings->get(Brand::TITLE));
        self::assertSame(Theme::Dark->value, $this->client->request('GET', '/admin/settings?tab='.$brand)
            ->filter(\sprintf('select[name="value[%s]"] option[selected]', Brand::THEME))->attr('value'));

        $this->post('/admin/settings', ['tab' => $brand, 'value' => [Brand::TITLE => 'Village', Brand::THEME => 'purple']]);
        self::assertResponseRedirects();
        self::assertNotSame('', self::toast($this->client->followRedirect(), 'error'));
        self::assertSame(Theme::Dark->value, $settings->get(Brand::THEME), 'the value outside the form is refused');
        self::assertSame('Village', $settings->get(Brand::TITLE), 'other fields of the tab are saved');

        $this->post('/admin/settings', ['tab' => Tab::Mail->value, 'value' => [QuietHours::FROM => '25:99', QuietHours::TO => '08:00']]);
        self::assertNotSame('', self::toast($this->client->followRedirect(), 'error'));
        self::assertSame('', $settings->get(QuietHours::FROM));
        self::assertSame('08:00', $settings->get(QuietHours::TO));
    }

    #[Group('ACMS-CORE-0058')]
    #[Group('ACMS-CORE-0059')]
    #[Group('ACMS-CORE-0161')]
    #[Group('ACMS-US-0004')]
    public function testMailAccountIsEditedByItsDialogWhole(): void
    {
        $this->client->loginUser($this->admin);
        $settings = self::service(Settings::class);
        $dialog = '/admin/settings/credentials/'.MailChannel::ACCOUNT;
        $login = \sprintf('value[%s]', MailChannel::LOGIN);
        $password = \sprintf('value[%s]', MailChannel::PASSWORD);

        $page = $this->client->request('GET', '/admin/settings?tab='.Tab::Mail->value);
        $names = $page->filter('form [name]')->each(static fn (Crawler $node): string => (string) $node->attr('name'));
        self::assertContains(\sprintf('value[%s]', MailChannel::HOST), $names);
        self::assertNotContains($login, $names, 'the access data is not in the form of the tab');
        self::assertNotContains($password, $names);
        self::assertCount(1, $page->filter(\sprintf('[hx-get="%s"]', $dialog)));

        $fresh = $this->fragment($dialog);
        self::assertSame(
            [self::phrase('core.setting.mail_login'), self::phrase('core.setting.mail_password')],
            $fresh->filter('form label')->each(static fn (Crawler $label): string => trim($label->text())),
        );
        $this->client->submit($fresh->selectButton(self::phrase('core.action.save'))->form([$login => 'robot', $password => 'mail-secret']));
        self::assertResponseRedirects();
        self::assertSame('robot', $settings->get(MailChannel::LOGIN));
        self::assertSame('mail-secret', $settings->get(MailChannel::PASSWORD));

        $stored = $this->fragment($dialog);
        self::assertSame('', (string) $stored->filter(\sprintf('input[name="%s"]', $password))->attr('value'), 'the secret does not go out');
        $this->client->submit($stored->selectButton(self::phrase('core.action.save'))->form([$login => 'robot2', $password => '']));
        self::assertSame('robot2', $settings->get(MailChannel::LOGIN));
        self::assertSame('mail-secret', $settings->get(MailChannel::PASSWORD), 'an empty field keeps the stored secret');

        $this->client->submit($this->fragment($dialog)->selectButton(self::phrase('theme.clear'))->form());
        self::assertSame('', $settings->get(MailChannel::LOGIN));
        self::assertSame('', $settings->get(MailChannel::PASSWORD));
    }

    #[Group('ACMS-CORE-0061')]
    #[Group('ACMS-UI-0007')]
    #[Group('ACMS-US-0004')]
    public function testBrandImagesAreUploadedServedAndDropped(): void
    {
        $this->configure([Brand::TITLE => 'Village network']);
        $this->client->loginUser($this->admin);
        $tab = Tab::Brand->value;

        $this->post('/admin/settings', ['tab' => $tab], ['file' => [Brand::ICON => $this->picture('icon.png')]]);
        self::assertResponseRedirects();

        $this->client->request('GET', '/setting/'.Brand::ICON);
        self::assertResponseIsSuccessful();
        self::assertResponseHasHeader('ETag');
        self::assertStringContainsString('sandbox', (string) $this->client->getResponse()->headers->get('Content-Security-Policy'));
        $this->client->request('GET', '/setting/'.Brand::LOGO);
        self::assertResponseStatusCodeSame(404);

        $header = $this->client->request('GET', '/admin');
        self::assertSame('Village network', trim($header->filter('.header-logo span')->text()), 'without a logo the title carries the brand');
        self::assertStringEndsWith('/setting/'.Brand::ICON, (string) $header->filter('link[rel="icon"]')->attr('href'));
        self::assertStringEndsWith('/setting/'.Brand::ICON, (string) $this->client->request('GET', '/admin/settings?tab='.$tab)->filter('img.brand-preview')->attr('src'));

        $this->post('/admin/settings', ['tab' => $tab], ['file' => [Brand::LOGO => $this->picture('logo.png')]]);
        $logo = $this->client->request('GET', '/admin')->filter('.header-logo');
        self::assertCount(0, $logo->filter('span'), 'the logo stands in place of the title');
        self::assertSame('Village network', $logo->filter('img')->attr('alt'));
        self::assertStringEndsWith('/setting/'.Brand::LOGO, (string) $logo->filter('img')->attr('src'));

        $this->post('/admin/settings', ['tab' => $tab, 'drop' => [Brand::ICON => '1']]);
        self::assertSame('', self::service(Settings::class)->get(Brand::ICON));
        $this->client->request('GET', '/setting/'.Brand::ICON);
        self::assertResponseStatusCodeSame(404);
    }

    #[Group('ACMS-UI-0086')]
    #[Group('ACMS-UI-0088')]
    #[Group('ACMS-US-0004')]
    public function testBrandColorIsSchemeOfThemeOrOwnColorResetToTheFirstScheme(): void
    {
        $this->client->loginUser($this->admin);
        $schemes = (self::service(Catalog::class)->fields()[Brand::COLOR] ?? self::fail('the theme declares no palette'))->schemes;
        $tab = Tab::Brand->value;
        $field = \sprintf('value[%s]', Brand::COLOR);
        $picked = \sprintf('input[name="%s"][checked]', $field);
        $reset = \sprintf('button[name="drop[%s]"]', Brand::COLOR);

        $page = $this->client->request('GET', '/admin/settings?tab='.$tab);
        self::assertSame(
            array_map(static fn (Scheme $scheme): string => $scheme->key, $schemes),
            $page->filter(\sprintf('input.swatch[name="%s"]', $field))->each(static fn (Crawler $swatch): string => (string) $swatch->attr('value')),
        );
        self::assertSame($schemes[0]->key, $page->filter($picked)->attr('value'), 'the first scheme stands for an empty setting');
        self::assertSame('--brand: '.$schemes[0]->color, $page->filter('html')->attr('style'));
        self::assertCount(0, $page->filter($reset), 'an empty setting has nothing to reset');

        $this->post('/admin/settings', ['tab' => $tab, 'value' => [Brand::COLOR => $schemes[1]->key]]);
        self::assertSame('--brand: '.$schemes[1]->color, $this->client->request('GET', '/admin')->filter('html')->attr('style'));

        $this->post('/admin/settings', ['tab' => $tab, 'value' => [Brand::COLOR => '#C8F031']]);
        $own = $this->client->request('GET', '/admin/settings?tab='.$tab);
        self::assertSame('#c8f031', $own->filter($picked)->attr('value'), 'the own color is the picked one');
        self::assertSame('--brand: #c8f031', $own->filter('html')->attr('style'));

        $this->client->submit($own->filter($reset)->form());
        self::assertSame('', self::service(Settings::class)->get(Brand::COLOR), 'the reset clears the setting');
        self::assertSame($schemes[0]->key, $this->client->request('GET', '/admin/settings?tab='.$tab)->filter($picked)->attr('value'));
    }

    #[Group('ACMS-CORE-0027')]
    public function testFormWithoutTokenIsRefused(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('POST', '/admin/users/new', ['comment' => 'North 12']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, self::service(UserRepository::class)->count(['comment' => 'North 12']));
    }

    private function switchLinks(User $user): Crawler
    {
        return $this->client->request('GET', \sprintf('/admin/users/%d', $user->getId()))->filter('a[href*="_switch_user"]');
    }

    private function created(): User
    {
        self::assertResponseStatusCodeSame(204);
        $address = (string) $this->client->getResponse()->headers->get('HX-Redirect');

        return 1 === preg_match('#^/admin/users/(\d+)$#', $address, $match)
            ? self::service(UserRepository::class)->find((int) $match[1]) ?? self::fail('the created record does not exist')
            : self::fail('the creation leads nowhere: '.$address);
    }

    /** @return list<int> */
    private static function ids(Crawler $listing): array
    {
        return $listing->filter('tbody tr[data-href]')->each(static fn (Crawler $row): int => (int) basename((string) $row->attr('data-href')));
    }
}
