<?php

declare(strict_types=1);

namespace AdzerCMS\TelegramBot;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Notice\BotFailed;
use AdzerCMS\Contracts\Notice\BotGateway;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Guided;
use AdzerCMS\Contracts\Setting\Type;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\Translation\t;

final readonly class TelegramBotGateway implements BotGateway, Guided
{
    public const API = 'https://api.telegram.org/bot%s/sendMessage';
    public const SERVICE = 'telegram';

    private const TIMEOUT = 15.0;

    public function __construct(private HttpClientInterface $http)
    {
    }

    public function credentials(): array
    {
        return [new Field(self::TOKEN, t('core.bot.token'), Type::Secret, required: true)];
    }

    public function guide(): array
    {
        return [t('telegrambot.guide.bot'), t('telegrambot.guide.token')];
    }

    public function service(): string
    {
        return self::SERVICE;
    }

    public function send(Credentials $credentials, string $recipient, string $text): void
    {
        try {
            $answer = $this->http->request('POST', \sprintf(self::API, $credentials->get(self::TOKEN)), [
                'json' => ['chat_id' => $recipient, 'text' => $text],
                'timeout' => self::TIMEOUT,
            ]);
            $status = $answer->getStatusCode();
            $body = $answer->toArray(false);
        } catch (ExceptionInterface $failure) {
            throw new BotFailed(\sprintf('the Telegram bot did not answer: %s', $failure->getMessage()), previous: $failure);
        }

        if ($status < 200 || $status >= 300 || false === ($body['ok'] ?? true)) {
            throw new BotFailed(\sprintf('the Telegram bot refused to send: %s', (string) ($body['description'] ?? $status)));
        }
    }
}
