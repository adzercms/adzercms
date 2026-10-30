<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Controller;

use AdzerCMS\Contracts\Account\Newcomer;
use AdzerCMS\Contracts\Account\UserCreation;
use AdzerCMS\Contracts\Account\UserPlaces;
use AdzerCMS\Contracts\Ui\Tabs;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\Password;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Account\UserRemoval;
use AdzerCMS\Core\Account\UserRows;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Notice\CoreEvent;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Core\Notice\Status as NoticeStatus;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Setting\Toggle;
use AdzerCMS\Core\Ui\Back;
use AdzerCMS\Core\Ui\PageTabs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

#[Route('/admin/users')]
#[IsGranted(Role::ADMIN)]
final class UserController extends AbstractController
{
    private const PROFILE = 'profile';
    private const NOTES = 'notes';

    private readonly ?UserPlaces $places;

    /**
     * @param iterable<UserPlaces>   $places
     * @param iterable<UserCreation> $creations
     */
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserRows $rows,
        private readonly UserRemoval $removal,
        private readonly PageTabs $pages,
        private readonly EntityManagerInterface $em,
        #[AutowireIterator(UserPlaces::class)]
        iterable $places,
        #[AutowireIterator(UserCreation::class)]
        private readonly iterable $creations,
    ) {
        $this->places = [...$places][0] ?? null;
    }

    #[Route('', name: 'admin_users', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $archive = 'archive' === $request->query->get('tab');
        $context = $this->rows->listing($request, 'admin_users', $archive, $archive ? ['tab' => 'archive'] : []);

        if ($request->headers->has('HX-Request')) {
            return $this->render(UserRows::TEMPLATE, $context);
        }

        return $this->render('@Admin/admin/users.html.twig', $context + [
            'counts' => $this->users->counts(),
            'creation' => $this->here() || [] !== ($this->places?->places() ?? []),
        ]);
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function create(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $here = $this->here();
        $places = $this->places?->places() ?? [];
        if (!$here && [] === $places) {
            throw $this->createNotFoundException();
        }

        $user = new User();
        $error = null;
        $place = $request->request->getString('place');

        if ($request->isMethod('POST') && ('' !== $place || !$here)) {
            $error = isset($places[$place]) ? $this->place($place, $user, $request) : t('admin.user.place');

            if (null === $error) {
                $this->addFlash('success', t('admin.user.placed', ['place' => $places[$place]]));

                $response = new Response('', Response::HTTP_NO_CONTENT);
                $response->headers->set('HX-Redirect', $this->generateUrl('admin_users'));

                return $response;
            }
        } elseif ($request->isMethod('POST')) {
            $error = $this->fill($user, $request, $hasher);

            if (null === $error) {
                $user->setNote($request->request->getString('note'));
                $this->em->flush();
                $this->addFlash('success', t('admin.user.created', ['id' => $user->getId()]));

                $response = new Response('', Response::HTTP_NO_CONTENT);
                $response->headers->set('HX-Redirect', $this->generateUrl('admin_user', ['id' => $user->getId()]));

                return $response;
            }
        }

        return $this->render('@Admin/admin/_user_new.html.twig', [
            'user' => $user,
            'error' => $error,
            'here' => $here,
            'places' => $places,
            'place' => $place,
            'place_label' => $this->places?->label(),
        ]);
    }

    #[Route('/{id}', name: 'admin_user', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(User $user, #[CurrentUser] User $operator, Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $error = null;

        if ($request->isMethod('POST')) {
            $error = $this->fill($user, $request, $hasher);

            if (null === $error) {
                $this->em->flush();
                $this->addFlash('success', t('admin.user.saved'));

                return $this->redirectToRoute('admin_user', ['id' => $user->getId()]);
            }
        }

        $id = (int) $user->getId();
        $tabs = $this->pages->of(Tabs::ADMIN_USER, $id);
        $asked = $request->query->getString('tab');
        $tab = \in_array($asked, [self::NOTES, ...array_keys($tabs)], true) ? $asked : self::PROFILE;
        $fragment = $this->pages->render($tabs, $tab, $id, $request, ['id' => $id, 'tab' => $tab]);

        if (null !== $fragment && $request->headers->has('HX-Request')) {
            return $this->render($fragment['template'], $fragment['context']);
        }

        return $this->render('@Admin/admin/user.html.twig', [
            'user' => $user,
            'tab' => $tab,
            'tabs' => $tabs,
            'error' => $error,
            'kept' => self::PROFILE === $tab && $user !== $operator ? $this->removal->kept($user) : null,
            'fragment' => $fragment,
        ]);
    }

    #[Route('/{id}/drop', name: 'admin_user_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function drop(User $user, #[CurrentUser] User $operator): Response
    {
        $refusal = $this->removal->refusal($user, $operator);

        if (null !== $refusal) {
            $this->addFlash('error', $refusal->reason);

            return $this->redirectToRoute('admin_user', ['id' => $user->getId()]);
        }

        $id = $user->getId();
        $this->removal->remove($user);
        $this->addFlash('success', t('admin.user.removed', ['id' => $id]));

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/{id}/archive', name: 'admin_user_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(User $user, #[CurrentUser] User $operator): Response
    {
        if ($user !== $operator && Status::Archived !== $user->getStatus()) {
            $this->removal->archive($user);
            $this->addFlash('success', t('admin.user.archived', ['id' => $user->getId()]));
        }

        return $this->redirectToRoute('admin_user', ['id' => $user->getId()]);
    }

    #[Route('/{id}/password', name: 'admin_user_password_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function passwordForm(User $user): Response
    {
        return $this->render('@Admin/admin/_user_password.html.twig', [
            'user' => $user,
            'password' => Password::issue(),
            'issued' => null,
        ]);
    }

    #[Route('/{id}/note', name: 'admin_user_note', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function note(User $user, Request $request): Response
    {
        $user->setNote($request->request->getString('note'));
        $this->em->flush();
        $this->addFlash('success', t('admin.user.note_saved'));

        return $this->redirect(Back::of($request, $this->generateUrl('admin_user', ['id' => $user->getId(), 'tab' => self::NOTES])));
    }

    #[Route('/{id}/password', name: 'admin_user_password', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function password(User $user, Request $request, UserPasswordHasherInterface $hasher, Notifier $notifier): Response
    {
        $password = trim($request->request->getString('password'));
        $refusal = Password::refusal($password);

        if (null !== $refusal) {
            return $this->render('@Admin/admin/_user_password.html.twig', ['user' => $user, 'password' => $password, 'issued' => null, 'error' => $refusal->reason]);
        }

        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->flush();

        $notice = Toggle::On === Toggle::tryFrom($request->request->getString('mail'))
            ? $notifier->notifyMail($user, CoreEvent::PasswordIssued, ['password' => $password])
            : null;

        return $this->render('@Admin/admin/_user_password.html.twig', [
            'user' => $user,
            'password' => $password,
            'issued' => $password,
            'mailed' => NoticeStatus::Sent === $notice?->getStatus(),
        ]);
    }

    private function here(): bool
    {
        foreach ($this->creations as $creation) {
            if (!$creation->here()) {
                return false;
            }
        }

        return true;
    }

    private function place(string $place, User $user, Request $request): ?TranslatableInterface
    {
        $input = $request->request;
        $user->setName($input->getString('last_name'), $input->getString('first_name'), $input->getString('middle_name'));
        $contacts = $this->contacts($request, null);
        if (!\is_array($contacts)) {
            return $contacts;
        }
        $user->setPhone($contacts[ContactKind::Phone->value])->setEmail($contacts[ContactKind::Email->value]);

        $password = $input->getString('password');
        $refusal = '' === $password ? null : Password::refusal($password);

        return ($refusal ?? $this->places?->create($place, new Newcomer(
            (string) $user->getLastName(),
            (string) $user->getFirstName(),
            (string) $user->getMiddleName(),
            $user->getPhone(),
            $user->getEmail(),
            $password,
        )))?->reason;
    }

    /** @return array<string, ?string>|TranslatableInterface */
    private function contacts(Request $request, ?User $user): array|TranslatableInterface
    {
        $contacts = [];

        foreach (ContactKind::cases() as $kind) {
            $value = trim($request->request->getString($kind->value));
            $contact = '' === $value ? null : $kind->normalize($value);
            if ('' !== $value && null === $contact) {
                return t('admin.user.malformed.'.$kind->value);
            }

            $holder = null === $contact || null === $user ? null : $this->users->findOneBy([$kind->value => $contact]);
            if (null !== $holder && $holder !== $user) {
                return t('admin.user.taken.'.$kind->value, ['id' => $holder->getId()]);
            }
            $contacts[$kind->value] = $contact;
        }

        return $contacts;
    }

    private function fill(User $user, Request $request, UserPasswordHasherInterface $hasher): ?TranslatableInterface
    {
        $input = $request->request;
        $contacts = $this->contacts($request, $user);
        if (!\is_array($contacts)) {
            return $contacts;
        }

        $password = $input->getString('password');
        if ('' !== $password) {
            $refusal = Password::refusal($password);
            if (null !== $refusal) {
                return $refusal->reason;
            }
            $user->setPassword($hasher->hashPassword($user, $password));
        }

        $was = $user->getStatus();

        $user->setComment($input->getString('comment'))
            ->setName($input->getString('last_name'), $input->getString('first_name'), $input->getString('middle_name'))
            ->setPhone($contacts[ContactKind::Phone->value])
            ->setEmail($contacts[ContactKind::Email->value]);

        if ($user !== $this->getUser()) {
            $user->setStatus($input->getEnum('status', Status::class, Status::Active))
                ->setRole($input->getEnum('role', Role::class, Role::User));
        }

        $this->em->persist($user);

        if ($was !== $user->getStatus()) {
            Status::Archived === $user->getStatus() ? $this->removal->archive($user) : $this->removal->changed($user);
        }

        return null;
    }
}
