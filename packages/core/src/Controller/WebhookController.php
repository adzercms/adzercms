<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Controller;

use AdzerCMS\Contracts\Notice\BotEvents;
use AdzerCMS\Core\Notice\BotLink;
use AdzerCMS\Core\Notice\Channels;
use AdzerCMS\Core\Notice\Letter;
use AdzerCMS\Core\Security\CsrfGuard;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class WebhookController
{
    public function __construct(private Channels $channels, private BotLink $links, private TranslatorInterface $translator)
    {
    }

    #[Route('/webhook/bot/{bot}', name: 'core_webhook_bot', defaults: [CsrfGuard::OWN => true], methods: ['POST'])]
    public function __invoke(Request $request, string $bot): Response
    {
        $channel = $this->channels->bot($bot);
        $gateway = $channel?->gateway;
        if (null === $channel || !$gateway instanceof BotEvents) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        if (!$gateway->authentic($request, $this->channels->webhookSecret($bot))) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $update = json_decode($request->getContent(), true);
        $message = $gateway->incoming(\is_array($update) ? $update : []);
        if (null !== $message && null !== $this->links->accept($gateway->service(), $message)) {
            $channel->send($message->sender, new Letter('', $this->translator->trans('core.bot.linked'), ''));
        }

        return new Response('');
    }
}
