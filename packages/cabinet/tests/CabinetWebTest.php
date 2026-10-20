<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Tests;

use AdzerCMS\Cabinet\Tests\Fixture\Pager;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Entity\ConnectedService;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Analytics\Fixture\Tally\TallyCounter;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Delivery;
use AdzerCMS\Core\Tests\Login\Fixture\Latch\LatchService;
use AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelayGateway;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\ServiceLogin;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function Symfony\Component\Translation\t;

final class CabinetWebTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;
    use Delivery;
    use ServiceLogin;

    #[Group('ACMS-UI-0068')]
    public function testRootWithoutPublicHomeLeadsToCabinetHome(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/cabinet');
    }

    #[Group('ACMS-UI-0035')]
    #[Group('ACMS-US-0001')]
    public function testLoginServiceButtonTakesColorOfService(): void
    {
        $this->configure([LatchService::SETTINGS.Switches::ENABLED => '1', LatchService::SETTINGS.LatchService::CLIENT => 'latch-client']);

        $button = $this->client->request('GET', '/login')->filter('.oauth-buttons a[href="/login/latch"]');

        self::assertSame('--service: '.LatchService::COLOR, $button->attr('style'));
    }

    #[Group('ACMS-CORE-0951')]
    #[Group('ACMS-US-0036')]
    public function testProfileReopensConsentWindowOfActiveCounter(): void
    {
        $this->client->loginUser($this->record('79990000011'));
        self::assertCount(0, $this->client->request('GET', '/profile')->filter('[data-consent-open]'), 'a site without a counter has no window to reopen');

        $this->configure([TallyCounter::SETTINGS.Switches::ENABLED => '1', TallyCounter::SETTINGS.TallyCounter::SITE => 'shop']);

        self::assertCount(1, $this->client->request('GET', '/profile')->filter('button[data-consent-open]'));
    }

    #[Group('ACMS-CORE-0125')]
    #[Group('ACMS-CORE-0174')]
    #[Group('ACMS-US-0002')]
    public function testLoginServiceIsConnectedAndUnlinkedInProfile(): void
    {
        $this->answerService();
        $this->configure([LatchService::SETTINGS.Switches::ENABLED => '1', LatchService::SETTINGS.LatchService::CLIENT => 'latch-client']);
        $user = $this->record('79990000010');
        $this->client->loginUser($user);
        self::assertCount(1, $this->client->request('GET', '/profile')->filter('a[href="/login/latch"]'), 'the profile connects the service');

        $state = $this->authorize('latch', LatchService::AUTHORIZE)['state'];
        $this->answers = [new JsonMockResponse(['access_token' => 'token']), new JsonMockResponse(['id' => 'p1', 'login' => 'ivan'])];
        $this->client->request('GET', '/login/latch/callback', ['code' => 'c1', 'state' => $state]);
        self::assertResponseRedirects('/profile');

        $profile = $this->client->followRedirect();
        self::assertSame(self::phrase(t('core.login.connected', ['service' => t('Latch')])), self::toast($profile, 'success'));
        self::assertContains('ivan', $profile->filter('.settings-value')->each(static fn (Crawler $value): string => trim($value->text())), 'the connection is named by its account');

        $this->client->submit($profile->selectButton(self::phrase('cabinet.services.unlink'))->form());
        self::assertResponseRedirects('/profile');
        self::assertCount(1, $this->client->followRedirect()->filter('a[href="/login/latch"]'), 'the unlinked service is offered again');
        self::assertNull(self::service(ConnectedServiceRepository::class)->findOneBy(['user' => $user->getId(), 'service' => 'latch']));
    }

    #[Group('ACMS-CORE-0084')]
    #[Group('ACMS-US-0002')]
    public function testLastWayOfSigningInLeadsToPasswordChange(): void
    {
        $this->configure(self::MAIL + [LatchService::SETTINGS.Switches::ENABLED => '1', LatchService::SETTINGS.LatchService::CLIENT => 'latch-client']);
        $user = $this->record('79990000010', 'abonent@example.org')->setPassword(null);
        $this->em->persist(new ConnectedService($user, 'latch', 'p1', 'ivan'));
        $this->em->flush();
        $this->client->loginUser($user);

        $this->post('/profile/service/latch');
        self::assertResponseRedirects('/profile?tab=security');
        $security = $this->client->followRedirect();
        self::assertSame(self::phrase('core.login.last_way'), self::toast($security, 'error'));
        self::assertCount(1, $security->filter('form[action="/profile/password"]'), 'the refusal leads to the password change');
        self::assertNotNull(self::service(ConnectedServiceRepository::class)->findOneBy(['user' => $user->getId(), 'service' => 'latch']));
    }

    #[Group('ACMS-CORE-0088')]
    #[Group('ACMS-US-0002')]
    public function testRecordDeletedByItsUserIsLedFromServiceToRecovery(): void
    {
        $this->answerService();
        $this->configure([LatchService::SETTINGS.Switches::ENABLED => '1', LatchService::SETTINGS.LatchService::CLIENT => 'latch-client']);
        $this->record('79990000010')->delete();
        $this->em->flush();

        $state = $this->authorize('latch', LatchService::AUTHORIZE)['state'];
        $this->answers = [new JsonMockResponse(['access_token' => 'token']), new JsonMockResponse(['id' => 'p1', 'phone' => '79990000010'])];
        $this->client->request('GET', '/login/latch/callback', ['code' => 'c1', 'state' => $state]);

        self::assertResponseRedirects('/forgot');
        self::assertSame(self::phrase(t('core.account.deleted', [], 'security')), self::toast($this->client->followRedirect(), 'error'));
    }

    #[Group('ACMS-CORE-0065')]
    #[Group('ACMS-US-0002')]
    public function testUserKeepsOwnNameAndSeesContacts(): void
    {
        $user = $this->record('79990000010', 'abonent@example.org');
        $this->client->loginUser($user);

        $page = $this->client->request('GET', '/profile');
        foreach ([(string) $user->getId(), '79990000010', 'abonent@example.org'] as $value) {
            self::assertStringContainsString($value, $page->filter('.panel-content')->text());
        }

        $this->client->submit($page->selectButton(self::phrase('cabinet.profile.save'))->form(['last_name' => 'Petrov', 'first_name' => 'Ivan', 'middle_name' => 'Sergeevich']));

        self::assertResponseRedirects('/profile');
        $named = $this->reread($user);
        self::assertSame(['Petrov', 'Ivan', 'Sergeevich'], [$named->getLastName(), $named->getFirstName(), $named->getMiddleName()]);
    }

    #[Group('ACMS-UI-0001')]
    public function testProfileCarriesFragmentsOfModulesUnderContacts(): void
    {
        $user = $this->record('79990000010');
        $this->client->loginUser($user);

        self::assertCount(1, $this->client->request('GET', '/profile')->filter('a[href="'.Pager::HREF.$user->getId().'"]'));
    }

    #[Group('ACMS-CORE-0168')]
    #[Group('ACMS-US-0002')]
    public function testProfileChoosesLanguageOfRecord(): void
    {
        $user = $this->record('79990000010', 'abonent@example.org');
        $this->client->loginUser($user);

        $this->client->submit($this->client->request('GET', '/profile')->filter('form[action="/profile/language"] button[value="ru"]')->form());

        self::assertResponseRedirects();
        self::assertSame('ru', $this->reread($user)->getLanguage());
        $page = $this->client->followRedirect();
        self::assertSame('ru', $page->filter('html')->attr('lang'));
        self::assertStringContainsString(self::phrase('cabinet.language.title', [], 'ru'), $page->filter('.panel-content')->text());

        $this->post('/profile/language', ['language' => 'xx']);
        self::assertNotSame('', self::toast($this->client->followRedirect(), 'error'));
        self::assertSame('ru', $this->reread($user)->getLanguage());
    }

    #[Group('ACMS-US-0002')]
    public function testProfileKeepsItsSectionsOnTabs(): void
    {
        $this->client->loginUser($this->record('79990000010', 'abonent@example.org'));

        $account = $this->client->request('GET', '/profile');
        self::assertSame(
            array_map(static fn (string $tab): string => self::phrase('cabinet.profile.tab.'.$tab), ['account', 'notices', 'security']),
            $account->filter('.tabs .tab')->each(static fn (Crawler $tab): string => trim($tab->text())),
        );
        self::assertCount(1, $account->filter('form[action="/profile/name"]'));
        self::assertCount(0, $account->filter('form[action="/profile/password"]'), 'the password lives on its own tab');

        $security = $this->client->request('GET', '/profile?tab=security');
        self::assertCount(0, $security->filter('form[action="/profile/name"]'));
        self::assertStringContainsString(self::phrase('cabinet.password.title'), $security->filter('.panel-content')->text());
    }

    #[Group('ACMS-CORE-0090')]
    #[Group('ACMS-CORE-0096')]
    #[Group('ACMS-US-0002')]
    public function testMailOfRecordWithoutOneWaitsForOwnershipOfNewOne(): void
    {
        $this->configure(self::MAIL);
        $user = $this->record('79990000010');
        $this->client->loginUser($user);

        $this->client->submit($this->contactForm(ContactKind::Email, 'fresh@example.org'));

        self::assertResponseRedirects('/profile');
        self::assertNull($this->reread($user)->getEmail(), 'the record has no mail until it is confirmed');
        self::assertSame('fresh@example.org', $this->letter()->getTo()[0]->getAddress());
        $link = self::link('#/contact/[0-9a-f]{64}#', $this->letterText());

        $this->client->request('GET', str_replace('/contact/', '/password/', $link));
        self::assertResponseRedirects('/login', null, 'the contact link does not change the password');

        $this->client->request('GET', $link);
        self::assertResponseRedirects('/profile');
        self::assertSame('fresh@example.org', $this->reread($user)->getEmail());
    }

    #[Group('ACMS-CORE-0097')]
    #[Group('ACMS-US-0002')]
    public function testMailChangeTakesConsentOfFormerAddressFirst(): void
    {
        $this->configure(self::MAIL);
        $user = $this->record('79990000010', 'abonent@example.org');
        $this->client->loginUser($user);

        $this->client->submit($this->contactForm(ContactKind::Email, 'fresh@example.org'));

        self::assertSame('abonent@example.org', $this->letter()->getTo()[0]->getAddress(), 'the former address agrees first');
        $release = self::link('#/contact/release/[0-9a-f]{64}#', $this->letterText());

        $page = $this->client->request('GET', $release);
        self::assertEmailCount(0, null, 'opening the consent link sends nothing');
        $this->client->submit($page->selectButton(self::phrase('cabinet.release.agree'))->form());

        self::assertSame('fresh@example.org', $this->letter()->getTo()[0]->getAddress(), 'then the new address confirms');
        self::assertSame('abonent@example.org', $this->reread($user)->getEmail());

        $this->client->request('GET', self::link('#/contact/[0-9a-f]{64}#', $this->letterText()));
        self::assertSame('fresh@example.org', $this->reread($user)->getEmail());

        $this->client->request('GET', $release);
        self::assertResponseRedirects('/login', null, 'the consent link works once');
    }

    #[Group('ACMS-CORE-0091')]
    #[Group('ACMS-CORE-0096')]
    #[Group('ACMS-US-0002')]
    public function testPhoneIsTakenByCodeSentToIt(): void
    {
        $this->configure(self::RELAY);
        $user = $this->record('79990000010');
        $user->setPhone(null);
        $this->em->flush();
        $this->client->loginUser($user);

        $this->client->submit($this->contactForm(ContactKind::Phone, '+7 (999) 000-11-22'));
        $awaiting = $this->client->followRedirect();
        self::assertNull($this->reread($user)->getPhone());

        $this->client->submit($awaiting->selectButton(self::phrase('cabinet.contact.confirm'))->form(['code' => '000000']));
        self::assertSame(self::phrase('cabinet.contact.code'), self::toast($this->client->followRedirect(), 'error'));

        $this->client->submit($this->client->request('GET', '/profile')->selectButton(self::phrase('cabinet.contact.confirm'))->form(['code' => self::code($this->texted())]));

        self::assertResponseRedirects('/profile');
        self::assertSame('79990001122', $this->reread($user)->getPhone());
    }

    #[Group('ACMS-US-0002')]
    public function testAwaitedPhoneChangeIsCancelled(): void
    {
        $this->configure(self::RELAY);
        $user = $this->record('79990000010');
        $this->client->loginUser($user);

        $this->client->submit($this->contactForm(ContactKind::Phone, '+7 (999) 000-11-22'));
        $code = self::code($this->texted());
        $this->client->submit($this->client->followRedirect()->filter('#contact-cancel')->form());

        $page = $this->client->followRedirect();
        self::assertSame(self::phrase('cabinet.contact.cancelled'), self::toast($page, 'success'));
        self::assertCount(0, $page->filter('input[name="code"]'));

        $this->post('/profile/contact/code', ['code' => $code]);
        self::assertSame('79990000010', $this->reread($user)->getPhone(), 'the cancelled code changes nothing');
    }

    #[Group('ACMS-CORE-0098')]
    #[Group('ACMS-US-0002')]
    public function testProfileTakesOnlyReachableContactKind(): void
    {
        $this->configure(self::MAIL);
        $user = $this->record('79990000010');
        $user->setPhone(null);
        $this->em->flush();
        $this->client->loginUser($user);

        $page = $this->client->request('GET', '/profile');
        self::assertCount(1, $page->filter('form[action="/profile/contact"]'), 'only the reachable kind is changed');
        self::assertCount(1, $page->filter('form[action="/profile/contact"] input[type="email"]'));

        $this->post('/profile/contact', ['contact' => '+7 (999) 000-11-22']);
        self::assertSame(self::phrase('cabinet.contact.unreachable'), self::toast($this->client->followRedirect(), 'error'));
        self::assertNull($this->reread($user)->getPhone());
    }

    #[Group('ACMS-CORE-0068')]
    #[Group('ACMS-CORE-0096')]
    #[Group('ACMS-US-0002')]
    public function testContactTakenByAnotherUserIsRefused(): void
    {
        $this->configure(self::MAIL);
        $this->record('79990000011', 'taken@example.org');
        $this->client->loginUser($this->record('79990000010'));

        $this->client->submit($this->contactForm(ContactKind::Email, 'taken@example.org'));

        self::assertEmailCount(0);
        self::assertSame(self::phrase('cabinet.contact.taken'), self::toast($this->client->followRedirect(), 'error'));
    }

    #[Group('ACMS-CORE-0090')]
    #[Group('ACMS-CORE-0092')]
    #[Group('ACMS-CORE-0163')]
    #[Group('ACMS-US-0002')]
    public function testPasswordChangeGoesThroughMailedLink(): void
    {
        $this->configure(self::MAIL);
        $user = $this->record('79990000010', 'abonent@example.org');
        $this->client->loginUser($user);
        $hasher = self::service(UserPasswordHasherInterface::class);

        $this->client->submit($this->client->request('GET', '/profile?tab=security')->selectButton(self::phrase('cabinet.password.change'))->form());

        self::assertResponseRedirects('/profile?tab=security');
        self::assertSame('abonent@example.org', $this->letter()->getTo()[0]->getAddress());
        $link = self::link('#/password/[0-9a-f]{64}#', $this->letterText());
        self::assertCount(1, (new Crawler((string) $this->letter()->getHtmlBody()))->filter(\sprintf('a.button[href$="%s"]', $link)), 'the button of the letter leads by the link');

        $this->client->submit($this->client->request('GET', $link)->selectButton(self::phrase('core.action.save'))->form(['password' => 'renewed-password', 'repeat' => 'another-password']));
        self::assertSame(self::phrase('cabinet.register.mismatch'), trim($this->client->getCrawler()->filter('.flash-error')->text()));
        self::assertTrue($hasher->isPasswordValid($this->reread($user), self::PASSWORD));

        $this->client->submit($this->client->request('GET', $link)->selectButton(self::phrase('core.action.save'))->form(['password' => 'renewed-password', 'repeat' => 'renewed-password']));
        self::assertResponseRedirects('/login');
        self::assertTrue($hasher->isPasswordValid($this->reread($user), 'renewed-password'));

        $this->client->request('GET', $link);
        self::assertResponseRedirects('/login', null, 'the link works once');
    }

    #[Group('ACMS-CORE-0093')]
    #[Group('ACMS-US-0002')]
    public function testContactCodeAndPasswordLinkWaitForPause(): void
    {
        $this->configure(self::MAIL + self::RELAY);
        $user = $this->record('79990000010', 'abonent@example.org');
        $user->setPhone(null);
        $this->em->flush();
        $this->client->loginUser($user);

        $this->post('/profile/contact', ['contact' => '+7 (999) 000-11-22']);
        self::assertSame(self::phrase('cabinet.contact.sent', ['contact' => '+7 (999) 000-11-22']), self::toast($this->client->followRedirect(), 'success'));
        $this->post('/profile/contact', ['contact' => '+7 (999) 000-11-33']);
        self::assertSame(self::phrase('core.confirmation.too_often'), self::toast($this->client->followRedirect(), 'error'));
        self::assertCount(1, self::service(RelayGateway::class)->sent, 'the second code waits for the pause');

        $this->post('/profile/password');
        self::assertEmailCount(1);
        $this->post('/profile/password');
        self::assertEmailCount(0, null, 'the second link waits for the pause');
        self::assertSame(self::phrase('core.confirmation.too_often'), self::toast($this->client->followRedirect(), 'error'));
    }

    #[Group('ACMS-UI-0029')]
    #[Group('ACMS-US-0002')]
    public function testPasswordChangeWithoutMailIsUnavailableWithReason(): void
    {
        $this->configure(self::MAIL);
        $this->client->loginUser($this->record('79990000010'));

        $security = $this->client->request('GET', '/profile?tab=security');
        $reason = $security->filter('.flash-error');

        self::assertSame(self::phrase('cabinet.password.no_mail'), trim($reason->text()));
        self::assertSame('/profile', $reason->filter('a')->attr('href'), 'the reason leads to the fix');
        self::assertCount(0, $security->filter('form[action="/profile/password"]'));
        self::assertNotNull($security->selectButton(self::phrase('cabinet.password.change'))->attr('disabled'), 'the change button is unavailable');
    }

    #[Group('ACMS-CORE-0121')]
    #[Group('ACMS-US-0002')]
    public function testProfileHandsChannelsOverToUser(): void
    {
        $this->configure(self::MAIL + self::RELAY);
        $user = $this->record('79990000010', 'abonent@example.org');
        $user->setPhone(null);
        $this->em->flush();
        $this->client->loginUser($user);
        $mail = ContactKind::Email->channel();

        $page = $this->client->request('GET', '/profile?tab=notices');
        self::assertSame([$mail], $page->filter('.checkbox-item input:not([disabled])')->each(static fn (Crawler $box): string => (string) $box->attr('value')), 'a channel without recipient is not offered to mark');
        self::assertStringContainsString(self::phrase('core.channel.missing_sms'), $page->filter('.checkbox-list')->text());

        $this->post('/profile/notices');
        self::assertResponseRedirects('/profile?tab=notices');
        self::assertFalse($this->reread($user)->hears($mail), 'the cleared mark mutes the channel');

        $this->post('/profile/notices', ['channels' => [$mail]]);
        self::assertTrue($this->reread($user)->hears($mail));
    }

    private function contactForm(ContactKind $kind, string $contact): Form
    {
        $type = ContactKind::Phone === $kind ? 'tel' : 'email';
        $form = $this->client->request('GET', '/profile')->filter('form[action="/profile/contact"]')
            ->reduce(static fn (Crawler $form): bool => 1 === $form->filter(\sprintf('input[type="%s"]', $type))->count());

        return 0 === $form->count() ? self::fail(\sprintf('the profile offers no change of %s', $kind->value)) : $form->form(['contact' => $contact]);
    }

    private static function link(string $pattern, string $text): string
    {
        return 1 === preg_match($pattern, $text, $match) ? $match[0] : self::fail('the message carries no link');
    }
}
