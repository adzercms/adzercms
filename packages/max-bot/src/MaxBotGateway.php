<?php

declare(strict_types=1);

namespace AdzerCMS\MaxBot;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Notice\BotEvents;
use AdzerCMS\Contracts\Notice\BotFailed;
use AdzerCMS\Contracts\Notice\Incoming;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\Translation\t;

final readonly class MaxBotGateway implements BotEvents
{
    public const API = 'https://platform-api2.max.ru';
    public const SECRET_HEADER = 'X-Max-Bot-Api-Secret';
    public const SERVICE = 'maxbot';

    private const ORIGIN = 'https://max.ru';
    private const START = self::ORIGIN.'/%s?start=%s';
    private const CREATED = 'message_created';
    private const STARTED = 'bot_started';
    private const EVENTS = [self::STARTED, self::CREATED];
    private const TIMEOUT = 15.0;

    public function __construct(private HttpClientInterface $http)
    {
    }

    public function credentials(): array
    {
        return [new Field(self::TOKEN, t('core.bot.token'), Type::Secret, required: true)];
    }

    public function service(): string
    {
        return self::SERVICE;
    }

    public function send(Credentials $credentials, string $recipient, string $text): void
    {
        $this->api($credentials, 'POST', '/messages?user_id='.urlencode($recipient), ['text' => $text, 'notify' => true]);
    }

    public function origin(): string
    {
        return self::ORIGIN;
    }

    public function startLink(Credentials $credentials, string $code): string
    {
        $name = $this->api($credentials, 'GET', '/me')['username'] ?? null;

        return \is_string($name) && '' !== $name
            ? \sprintf(self::START, rawurlencode($name), rawurlencode($code))
            : throw new BotFailed('the MAX bot gave no username');
    }

    public function subscribe(Credentials $credentials, string $url, string $secret): void
    {
        foreach ($this->api($credentials, 'GET', '/subscriptions')['subscriptions'] ?? [] as $subscription) {
            if (!\is_array($subscription) || ($subscription['url'] ?? null) !== $url) {
                continue;
            }
            $types = $subscription['update_types'] ?? null;
            if (!\is_array($types) || [] === array_diff(self::EVENTS, $types)) {
                return;
            }
            $this->api($credentials, 'DELETE', '/subscriptions?url='.rawurlencode($url));
        }

        $this->api($credentials, 'POST', '/subscriptions', ['url' => $url, 'secret' => $secret, 'update_types' => self::EVENTS]);
    }

    public function authentic(Request $request, string $secret): bool
    {
        return hash_equals($secret, (string) $request->headers->get(self::SECRET_HEADER));
    }

    public function incoming(array $update): ?Incoming
    {
        $message = \is_array($update['message'] ?? null) ? $update['message'] : [];
        [$text, $sender] = match ($update['update_type'] ?? null) {
            self::STARTED => [$update['payload'] ?? null, $update['user'] ?? null],
            self::CREATED => [$message['body']['text'] ?? null, $message['sender'] ?? null],
            default => [null, null],
        };
        $id = \is_array($sender) ? $sender['user_id'] ?? null : null;

        return \is_string($text) && \is_array($sender) && is_numeric($id) ? new Incoming($text, (string) $id, self::who($sender)) : null;
    }

    /** @param array<mixed> $sender */
    private static function who(array $sender): ?string
    {
        $username = $sender['username'] ?? null;
        if (\is_string($username) && '' !== $username) {
            return '@'.$username;
        }
        $name = trim(\sprintf('%s %s', $sender['first_name'] ?? '', $sender['last_name'] ?? ''));

        return '' === $name ? null : $name;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<mixed>
     */
    private function api(Credentials $credentials, string $method, string $path, array $body = []): array
    {
        $options = ['headers' => ['Authorization' => $credentials->get(self::TOKEN)], 'timeout' => self::TIMEOUT];
        if ([] !== $body) {
            $options['json'] = $body;
        }

        try {
            $answer = $this->http->request($method, self::API.$path, $options);
            $status = $answer->getStatusCode();
            $content = $answer->toArray(false);
        } catch (ExceptionInterface $failure) {
            throw new BotFailed(\sprintf('the MAX bot did not answer: %s', $failure->getMessage()), previous: $failure);
        }

        if ($status < 200 || $status >= 300) {
            throw new BotFailed(\sprintf('the MAX bot refused: %s', (string) ($content['message'] ?? $status)));
        }

        return $content;
    }
}
