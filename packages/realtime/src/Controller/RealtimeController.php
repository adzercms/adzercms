<?php

declare(strict_types=1);

namespace AdzerCMS\Realtime\Controller;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Realtime\Channels;
use AdzerCMS\Realtime\Hub;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(Role::USER)]
final class RealtimeController extends AbstractController
{
    #[Route('/realtime/token', name: 'realtime_token', methods: ['GET'])]
    public function token(
        #[CurrentUser] User $user,
        Hub $hub,
        Channels $channels,
        #[MapQueryParameter] string $channel = '',
    ): JsonResponse {
        $module = $hub->ready() ?? throw $this->createNotFoundException();
        $subject = $channels->subject($user);

        return match (true) {
            '' === $channel => new JsonResponse(['token' => $module->connectionToken($subject)]),
            \in_array($channel, $channels->granted($user), true) => new JsonResponse(['token' => $module->subscriptionToken($subject, $channel)]),
            default => new JsonResponse(null, Response::HTTP_FORBIDDEN),
        };
    }
}
