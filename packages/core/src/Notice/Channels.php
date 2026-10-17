<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Contracts\Notice\BotEvents;
use AdzerCMS\Contracts\Notice\BotFailed;
use AdzerCMS\Contracts\Notice\BotGateway;
use AdzerCMS\Contracts\Ui\ContentSource;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class Channels implements ContentSource
{
    public function __construct(
        private SmsChannel $sms,
        private MailChannel $mail,
        private Switches $switches,
        private ConnectedServiceRepository $services,
        private UrlGeneratorInterface $urls,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    /** @return list<array{Channel, string}> */
    public function reaching(User $user, ?Channel $only = null): array
    {
        return $this->reachingEach([$user], $only)[0];
    }

    /**
     * @param list<User> $users
     *
     * @return list<list<array{Channel, string}>>
     */
    public function reachingEach(array $users, ?Channel $only = null): array
    {
        $reaching = array_fill(0, \count($users), []);
        foreach (null === $only ? $this->all() : [$only] as $channel) {
            foreach ($channel->recipients($users) as $at => $recipient) {
                if ($users[$at]->hears($channel->key())) {
                    $reaching[$at][] = [$channel, $recipient];
                }
            }
        }

        return $reaching;
    }

    public function of(ContactKind $kind): ?Channel
    {
        return $this->ready($kind->channel());
    }

    public function ready(string $key): ?Channel
    {
        foreach ($this->all() as $channel) {
            if ($key === $channel->key()) {
                return $channel->ready() ? $channel : null;
            }
        }

        return null;
    }

    /** @return list<Channel> */
    public function offered(): array
    {
        return array_values(array_filter($this->all(), static fn (Channel $channel): bool => $channel->ready()));
    }

    public function bot(string $key): ?BotChannel
    {
        foreach ($this->bots() as $bot) {
            if ($key === $bot->key()) {
                return $bot;
            }
        }

        return null;
    }

    public function webhookSecret(string $key): string
    {
        return hash_hmac('sha256', 'bot.'.$key.'.webhook', $this->secret);
    }

    /** @return array<string, string> */
    public function subscribe(): array
    {
        $failures = [];
        foreach ($this->bots() as $bot) {
            if (!$bot->gateway instanceof BotEvents) {
                continue;
            }
            $url = $this->urls->generate('core_webhook_bot', ['bot' => $bot->key()], UrlGeneratorInterface::ABSOLUTE_URL);
            try {
                $bot->gateway->subscribe($bot->credentials, $url, $this->webhookSecret($bot->key()));
            } catch (BotFailed $failure) {
                $failures[$bot->key()] = $failure->getMessage();
            }
        }

        return $failures;
    }

    public function sources(Request $request): array
    {
        $origins = [];
        foreach ($this->bots() as $bot) {
            if ($bot->gateway instanceof BotEvents) {
                $origins[] = $bot->gateway->origin();
            }
        }

        return [] === $origins ? [] : [self::FORM => $origins];
    }

    /** @return list<Channel> */
    public function all(): array
    {
        return [...$this->bots(), $this->sms, $this->mail];
    }

    /** @return list<BotChannel> */
    private function bots(): array
    {
        $bots = [];
        foreach ($this->switches->enabled(BotGateway::class) as $key => [$gateway, $credentials]) {
            $bots[] = new BotChannel($key, $gateway, $credentials, $this->services);
        }

        return $bots;
    }
}
