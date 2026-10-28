<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Controller;

use AdzerCMS\Cabinet\ContactFlash;
use AdzerCMS\Cabinet\Login\ServiceProof;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Ui\Badge;
use AdzerCMS\Contracts\Ui\Tabs;
use AdzerCMS\Core\Account\AccountDeletion;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\ContactOutcome;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Account\Theme;
use AdzerCMS\Core\Confirmation\Confirmations;
use AdzerCMS\Core\Confirmation\ConfirmationService;
use AdzerCMS\Core\Confirmation\Purpose;
use AdzerCMS\Core\Entity\Confirmation;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Login\Authorizations;
use AdzerCMS\Core\Login\ConnectedServices;
use AdzerCMS\Core\Notice\BotChannel;
use AdzerCMS\Core\Notice\BotLink;
use AdzerCMS\Core\Notice\Channel;
use AdzerCMS\Core\Notice\Channels;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use AdzerCMS\Core\Session\Logins;
use AdzerCMS\Core\Setting\Language;
use AdzerCMS\Core\Ui\Back;
use AdzerCMS\Core\Ui\Intent;
use AdzerCMS\Core\Ui\PageTabs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class AccountController extends AbstractController
{
    private const PROFILE_TABS = ['account', 'notices', 'security'];

    #[Route('/', name: 'cabinet_root', methods: ['GET'], priority: -1)]
    public function root(): Response
    {
        return $this->redirectToRoute('cabinet_account');
    }

    #[Route('/cabinet', name: 'cabinet_account', methods: ['GET'])]
    #[IsGranted(Role::USER)]
    public function account(#[CurrentUser] User $user, Request $request, PageTabs $pages): Response
    {
        $id = (int) $user->getId();
        $tabs = $pages->of(Tabs::CABINET_HOME, $id);
        $asked = $request->query->getString('tab');
        $tab = isset($tabs[$asked]) ? $asked : array_key_first($tabs);

        if (null === $tab) {
            return $this->redirectToRoute('cabinet_profile');
        }

        $fragment = $pages->render($tabs, $tab, $id, $request, ['tab' => $tab]);

        if (null !== $fragment && $request->headers->has('HX-Request')) {
            return $this->render($fragment['template'], $fragment['context']);
        }

        return $this->render('@Cabinet/cabinet/account.html.twig', ['tabs' => $tabs, 'tab' => $tab, 'fragment' => $fragment]);
    }

    #[Route('/profile', name: 'cabinet_profile', methods: ['GET'])]
    #[IsGranted(Role::USER)]
    public function profile(
        #[CurrentUser] User $user,
        Request $request,
        Notifier $notifier,
        Authorizations $logins,
        Channels $channels,
        ConfirmationService $confirmations,
        ConnectedServiceRepository $linked,
        AccountDeletion $deletion,
        Logins $sessions,
        Language $language,
    ): Response {
        $tab = \in_array($request->query->get('tab'), self::PROFILE_TABS, true) ? $request->query->getString('tab') : self::PROFILE_TABS[0];
        $kinds = $notifier->reachableContacts();
        $connected = $linked->of($user);
        $offered = $channels->offered();
        $delayed = $confirmations->delayed($user);

        return $this->render('@Cabinet/cabinet/profile.html.twig', [
            'user' => $user,
            'tabs' => self::PROFILE_TABS,
            'tab' => $tab,
            'notices' => array_map(static fn (Channel $channel): array => [
                'channel' => $channel,
                'on' => $user->hears($channel->key()),
                'ready' => [] !== $channel->recipients([$user]),
                'missing' => $channel->missing(),
            ], $offered),
            'mailReady' => null !== $user->getEmail() && $notifier->reachable(ContactKind::Email),
            'contacts' => array_map(static fn (ContactKind $kind): array => [
                'kind' => $kind,
                'value' => $user->getContact($kind),
                'state' => match (true) {
                    ContactKind::Email === $kind && null !== $delayed => Badge::warn(t('cabinet.contact.delayed', ['contact' => $delayed->getAddress(), 'until' => $delayed->getDueAt()])),
                    null === $user->getContact($kind) => Badge::idle(t('cabinet.contact.empty')),
                    null === $user->reachableContact($kind) => Badge::bad(t('cabinet.contact.undeliverable')),
                    default => Badge::good(t('cabinet.contact.confirmed')),
                },
                'bounce' => ContactKind::Email === $kind ? $user->getUndeliverableReason() : null,
                'open' => \in_array($kind, $kinds, true),
            ], ContactKind::cases()),
            'awaited' => $confirmations->awaited($user, Purpose::Contact),
            'logins' => $logins->enabled(),
            'bots' => array_values(array_filter($offered, static fn (Channel $channel): bool => $channel instanceof BotChannel)),
            'botCode' => $request->getSession()->remove('bot_code'),
            'linked' => $connected,
            'thisLogin' => Badge::good(t('cabinet.sessions.this')),
            'current' => Logins::current($request),
            'deletion' => $deletion->offeredTo($user),
            'sessions' => 'security' === $tab ? $sessions->of($user) : [],
            'languages' => $language->choices(),
        ]);
    }

    #[Route('/profile/sessions', name: 'cabinet_sessions_end', methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function endSessions(#[CurrentUser] User $user, Request $request, Logins $sessions): Response
    {
        $sessions->endOthers($user, $request);
        $this->addFlash('success', t('cabinet.sessions.others_ended'));

        return $this->redirectToRoute('cabinet_profile', ['tab' => 'security']);
    }

    #[Route('/profile/sessions/{handle}', name: 'cabinet_session_end', requirements: ['handle' => '[0-9a-f]{32}'], methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function endSession(#[CurrentUser] User $user, string $handle, Logins $sessions): Response
    {
        $ended = $sessions->end($user, $handle);
        $this->addFlash($ended ? 'success' : 'error', t($ended ? 'cabinet.sessions.ended' : 'cabinet.sessions.gone'));

        return $this->redirectToRoute('cabinet_profile', ['tab' => 'security']);
    }

    #[Route('/profile/name', name: 'cabinet_profile_name', methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function name(#[CurrentUser] User $user, Request $request, EntityManagerInterface $em): Response
    {
        $user->setName(
            $request->request->getString('last_name'),
            $request->request->getString('first_name'),
            $request->request->getString('middle_name'),
        );
        $em->flush();
        $this->addFlash('success', t('cabinet.profile.name_saved'));

        return $this->redirectToRoute('cabinet_profile');
    }

    #[Route('/profile/contact', name: 'cabinet_profile_contact', methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function contact(#[CurrentUser] User $user, Request $request, ConfirmationService $confirmations): Response
    {
        $wanted = trim($request->request->getString('contact'));
        $outcome = $confirmations->requestContact($user, $wanted);

        if (ContactOutcome::Owner === $outcome) {
            return $this->redirectToRoute('cabinet_profile_email', ['contact' => $wanted]);
        }

        $this->addFlash(...ContactFlash::of($outcome, $wanted, $user));

        return $this->redirectToRoute('cabinet_profile');
    }

    #[Route('/profile/email', name: 'cabinet_profile_email', methods: ['GET'])]
    #[IsGranted(Role::USER)]
    public function email(
        #[CurrentUser] User $user,
        Request $request,
        ConfirmationService $confirmations,
        Confirmations $secrets,
        Authorizations $logins,
        ConnectedServiceRepository $linked,
    ): Response {
        $awaited = $confirmations->awaited($user, Purpose::Release);
        $contact = $awaited ?? trim($request->query->getString('contact'));

        if ('' === $contact || null === $user->getEmail()) {
            return $this->redirectToRoute('cabinet_profile');
        }

        $connected = $linked->of($user);

        return $this->render('@Cabinet/cabinet/email.html.twig', [
            'user' => $user,
            'contact' => $contact,
            'awaited' => null !== $awaited,
            'former' => $user->reachableContact(ContactKind::Email),
            'phone' => $confirmations->releasePhone($user),
            'services' => array_intersect_key($logins->enabled(), $connected),
            'linked' => $connected,
            'delay' => null === $user->getPassword() ? null : $secrets->emailDelayDays(),
        ]);
    }

    #[Route('/profile/email/service/{service}', name: 'cabinet_profile_email_service', requirements: ['service' => '[a-z0-9_]+'], methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function emailService(Request $request, string $service, ServiceProof $proof): Response
    {
        $wanted = trim($request->request->getString('contact'));
        $authorization = $proof->start($service, $wanted, $request);

        if ($authorization instanceof Refusal) {
            $this->addFlash('error', $authorization->reason);

            return $this->redirectToRoute('cabinet_profile_email', ['contact' => $wanted]);
        }

        return $this->redirect($authorization);
    }

    #[Route('/profile/email/{way}', name: 'cabinet_profile_email_ask', requirements: ['way' => 'former|phone|password'], methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function askEmail(#[CurrentUser] User $user, Request $request, string $way, ConfirmationService $confirmations): Response
    {
        $wanted = trim($request->request->getString('contact'));
        $byPhone = 'phone' === $way;
        $outcome = match ($way) {
            'phone' => $confirmations->askPhone($user, $wanted),
            'password' => $confirmations->askDelayed($user, $wanted, $request->request->getString('password')),
            default => $confirmations->askFormer($user, $wanted),
        };

        $this->addFlash(...ContactFlash::of($outcome, $byPhone ? (string) $user->getPhone() : $wanted, $user));

        return ContactOutcome::Former === $outcome || (!$byPhone && ContactOutcome::Sent === $outcome)
            ? $this->redirectToRoute('cabinet_profile')
            : $this->redirectToRoute('cabinet_profile_email', ['contact' => $wanted]);
    }

    #[Route('/profile/email/code', name: 'cabinet_profile_email_code', methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function emailCode(#[CurrentUser] User $user, Request $request, ConfirmationService $confirmations): Response
    {
        $contact = (string) $confirmations->awaited($user, Purpose::Release);
        $outcome = $confirmations->releaseByCode($user, $request->request->getString('code'));

        if (null === $outcome) {
            $this->addFlash('error', t('cabinet.contact.code'));

            return $this->redirectToRoute('cabinet_profile_email');
        }

        $this->addFlash(...ContactFlash::of($outcome, $contact, $user));

        return $this->redirectToRoute('cabinet_profile');
    }

    #[Route('/profile/email/cancel', name: 'cabinet_profile_email_cancel', methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function emailCancel(#[CurrentUser] User $user, ConfirmationService $confirmations): Response
    {
        $confirmations->cancel($user, Purpose::Release);
        $this->addFlash('success', t('cabinet.contact.cancelled'));

        return $this->redirectToRoute('cabinet_profile');
    }

    #[Route('/profile/email/verify', name: 'cabinet_profile_email_verify', methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function verifyEmail(#[CurrentUser] User $user, ConfirmationService $confirmations): Response
    {
        if (null !== $user->getUndeliverableAt()) {
            $this->addFlash(...ContactFlash::of($confirmations->requestVerify($user), (string) $user->getEmail(), $user));
        }

        return $this->redirectToRoute('cabinet_profile');
    }

    #[Route('/profile/contact/code', name: 'cabinet_profile_contact_code', methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function contactCode(#[CurrentUser] User $user, Request $request, ConfirmationService $confirmations, Intent $intent): Response
    {
        $applied = $confirmations->completeContactCode($user, $request->request->getString('code'));
        $this->addFlash($applied ? 'success' : 'error', t($applied ? 'cabinet.contact.phone_confirmed' : 'cabinet.contact.code'));

        return $this->redirect(($applied ? $intent->take() : null) ?? $this->generateUrl('cabinet_profile'));
    }

    #[Route('/profile/contact/cancel', name: 'cabinet_profile_contact_cancel', methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function contactCancel(#[CurrentUser] User $user, ConfirmationService $confirmations): Response
    {
        $confirmations->cancel($user, Purpose::Contact);
        $this->addFlash('success', t('cabinet.contact.cancelled'));

        return $this->redirectToRoute('cabinet_profile');
    }

    #[Route('/contact/{token}', name: 'cabinet_contact_confirm', requirements: ['token' => '[0-9a-f]{64}'], methods: ['GET'])]
    public function contactConfirm(string $token, ConfirmationService $confirmations, Intent $intent): Response
    {
        $pending = $confirmations->contactLink($token);
        $applied = null !== $pending && $confirmations->applyContact($pending);
        $delayed = $applied ? $confirmations->delayed($pending->requireUser()) : null;

        $this->addFlash($applied ? 'success' : 'error', match (true) {
            null !== $delayed => t('cabinet.contact.email_delayed', ['until' => $delayed->getDueAt()]),
            $applied => t('cabinet.contact.email_confirmed'),
            default => t('cabinet.contact.link'),
        });

        return $this->redirect($applied
            ? $intent->take() ?? $this->generateUrl('cabinet_profile')
            : $this->generateUrl('cabinet_login'));
    }

    #[Route('/contact/release/{token}', name: 'cabinet_contact_release', requirements: ['token' => '[0-9a-f]{64}'], methods: ['GET', 'POST'])]
    public function contactRelease(Request $request, string $token, ConfirmationService $confirmations): Response
    {
        return $this->confirmationPage(
            $request,
            $confirmations->pending(Purpose::Release, $token),
            '@Cabinet/cabinet/contact_release.html.twig',
            'cabinet.contact.release_link',
            static fn (Confirmation $pending): array => ContactFlash::of($confirmations->release($pending), (string) $pending->getContact(), $pending->requireUser()),
        );
    }

    #[Route('/contact/revert/{token}', name: 'cabinet_contact_revert', requirements: ['token' => '[0-9a-f]{64}'], methods: ['GET', 'POST'])]
    public function contactRevert(Request $request, string $token, ConfirmationService $confirmations): Response
    {
        return $this->confirmationPage(
            $request,
            $confirmations->pending(Purpose::Revert, $token),
            '@Cabinet/cabinet/contact_revert.html.twig',
            'cabinet.contact.link',
            static fn (Confirmation $pending): array => $confirmations->revertMail($pending) ? ['success', t('cabinet.revert.done')] : ['error', t('cabinet.contact.taken')],
        );
    }

    #[Route('/contact/verify/{token}', name: 'cabinet_contact_verify', requirements: ['token' => '[0-9a-f]{64}'], methods: ['GET', 'POST'])]
    public function contactVerify(Request $request, string $token, ConfirmationService $confirmations): Response
    {
        return $this->confirmationPage(
            $request,
            $confirmations->pending(Purpose::Verify, $token),
            '@Cabinet/cabinet/contact_verify.html.twig',
            'cabinet.contact.link',
            static fn (Confirmation $pending): array => $confirmations->verifyMail($pending) ? ['success', t('cabinet.verify.done')] : ['error', t('cabinet.contact.link')],
        );
    }

    #[Route('/profile/service/{service}', name: 'cabinet_service_unlink', requirements: ['service' => '[a-z0-9_]+'], methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function unlink(#[CurrentUser] User $user, string $service, ConnectedServices $services, Intent $intent): Response
    {
        $refusal = $services->unlink($user, $service);
        $back = $this->generateUrl('cabinet_profile');

        if (null !== $refusal) {
            $this->addFlash('error', $refusal->reason);

            return $this->redirect($intent->detour($refusal, $back));
        }

        $this->addFlash('success', t('cabinet.services.unlinked'));

        return $this->redirect($back);
    }

    #[Route('/profile/bot/{bot}', name: 'cabinet_bot_link', methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function linkBot(#[CurrentUser] User $user, Request $request, string $bot, Channels $channels, BotLink $links): Response
    {
        $channel = $channels->bot($bot);

        if (null === $channel) {
            $this->addFlash('error', t('cabinet.notices.bot_off'));

            return $this->redirectToRoute('cabinet_profile');
        }

        if (!$channel->linksByCode()) {
            return $this->redirectToRoute('login_service', ['service' => $channel->gateway->service()]);
        }

        $code = $links->request($user, $bot);
        $request->getSession()->set('bot_code', ['bot' => $bot, 'code' => $code]);
        $start = $channel->startLink($code);

        return null === $start ? $this->redirectToRoute('cabinet_profile') : $this->redirect($start);
    }

    #[Route('/profile/notices', name: 'cabinet_profile_notices', methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function notices(#[CurrentUser] User $user, Request $request, Channels $channels, EntityManagerInterface $em): Response
    {
        $wanted = $request->request->all('channels');
        $muted = [];

        foreach ($channels->offered() as $channel) {
            if (!\in_array($channel->key(), $wanted, true)) {
                $muted[] = $channel->key();
            }
        }

        $user->mute($muted);
        $em->flush();
        $this->addFlash('success', t('cabinet.notices.saved'));

        return $this->redirectToRoute('cabinet_profile', ['tab' => 'notices']);
    }

    #[Route('/profile/theme', name: 'cabinet_profile_theme', methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function theme(#[CurrentUser] User $user, Request $request, EntityManagerInterface $em): Response
    {
        $theme = Theme::tryFrom($request->request->getString('theme'));

        if (null === $theme) {
            $this->addFlash('error', t('cabinet.theme.unknown'));
        } else {
            $user->setTheme($theme);
            $em->flush();
        }

        return $this->redirect(Back::of($request, $this->generateUrl('cabinet_account')));
    }

    #[Route('/profile/language', name: 'cabinet_profile_language', methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function language(#[CurrentUser] User $user, Request $request, Language $language, EntityManagerInterface $em): Response
    {
        $chosen = $request->request->getString('language');

        if (!isset($language->choices()[$chosen])) {
            $this->addFlash('error', t('cabinet.language.unknown'));
        } else {
            $user->setLanguage($chosen);
            $em->flush();
        }

        return $this->redirect(Back::of($request, $this->generateUrl('cabinet_profile')));
    }

    #[Route('/profile/password', name: 'cabinet_password_request', methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function requestPasswordChange(#[CurrentUser] User $user, ConfirmationService $resets): Response
    {
        [$level, $message] = match ($resets->requestLink($user)) {
            ContactOutcome::Sent => ['success', t('cabinet.password.sent', ['email' => $user->getEmail()])],
            ContactOutcome::TooOften => ['error', t('core.confirmation.too_often')],
            default => ['error', t('cabinet.password.unsent')],
        };
        $this->addFlash($level, $message);

        return $this->redirectToRoute('cabinet_profile', ['tab' => 'security']);
    }

    #[Route('/profile/delete', name: 'cabinet_delete_request', methods: ['POST'])]
    #[IsGranted(Role::USER)]
    public function requestDeletion(#[CurrentUser] User $user, AccountDeletion $deletion, Intent $intent): Response
    {
        $this->deletable($deletion);
        $refusal = $deletion->request($user);
        $back = $this->generateUrl('cabinet_profile', ['tab' => 'security']);

        if (null !== $refusal) {
            $this->addFlash('error', $refusal->reason);

            return $this->redirect($intent->detour($refusal, $back));
        }

        $this->addFlash('success', t('cabinet.deletion.sent', ['email' => $user->getEmail()]));

        return $this->redirect($back);
    }

    #[Route('/delete/{token}', name: 'cabinet_delete', requirements: ['token' => '[0-9a-f]{64}'], methods: ['GET', 'POST'])]
    public function delete(Request $request, string $token, AccountDeletion $deletion, ConfirmationService $confirmations): Response
    {
        $this->deletable($deletion);

        return $this->confirmationPage(
            $request,
            $confirmations->pending(Purpose::Deletion, $token),
            '@Cabinet/cabinet/delete.html.twig',
            'cabinet.deletion.link',
            static function (Confirmation $pending) use ($deletion): array {
                $refusal = $deletion->confirm($pending);

                return null === $refusal ? ['success', t('cabinet.deletion.done')] : ['error', $refusal->reason];
            },
        );
    }

    /** @param \Closure(Confirmation): array{string, TranslatableInterface} $apply */
    private function confirmationPage(Request $request, ?Confirmation $pending, string $template, string $expired, \Closure $apply): Response
    {
        if (null === $pending) {
            $this->addFlash('error', t($expired));

            return $this->redirectToRoute('cabinet_login');
        }

        if (!$request->isMethod('POST')) {
            return $this->render($template, ['user' => $pending->requireUser(), 'contact' => (string) $pending->getContact()]);
        }

        $this->addFlash(...$apply($pending));

        return $this->redirectToRoute('cabinet_login');
    }

    private function deletable(AccountDeletion $deletion): void
    {
        if (!$deletion->offered()) {
            throw $this->createNotFoundException();
        }
    }
}
