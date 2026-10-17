<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Notice\Fixture;

use AdzerCMS\Core\Entity\Notice;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Notice\CoreEvent;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Security\CsrfGuard;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/fixture/notice', name: 'fixture_notice_', defaults: [CsrfGuard::OWN => true], methods: ['POST'])]
final class NoticeController
{
    public const SKIPPED = 'skipped';

    #[Route('/message/{id}', name: 'message')]
    public function message(#[MapEntity(id: 'id')] User $user, Request $request, Notifier $notifier): Response
    {
        return self::outcome($notifier->message($user, $request->request->getString('subject'), $request->request->getString('text'), self::item($request)));
    }

    #[Route('/contact', name: 'contact')]
    public function contact(Request $request, Notifier $notifier, UserRepository $users): Response
    {
        $user = $request->request->has('user') ? $users->find($request->request->getInt('user')) : null;

        return self::outcome($notifier->contact($request->request->getString('contact'), CoreEvent::Message, self::letter($request), $user));
    }

    #[Route('/mail', name: 'mail')]
    public function mail(Request $request, Notifier $notifier): Response
    {
        return self::outcome($notifier->mail($request->request->getString('address'), CoreEvent::Message, self::letter($request), self::item($request)));
    }

    /** @return array{subject: string, text: string} */
    private static function letter(Request $request): array
    {
        return ['subject' => $request->request->getString('subject'), 'text' => $request->request->getString('text')];
    }

    private static function item(Request $request): ?string
    {
        $item = $request->request->getString('item');

        return '' === $item ? null : $item;
    }

    private static function outcome(?Notice $notice): Response
    {
        return new Response(null === $notice ? self::SKIPPED : $notice->getStatus()->value);
    }
}
