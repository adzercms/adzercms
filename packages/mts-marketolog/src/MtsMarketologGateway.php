<?php

declare(strict_types=1);

namespace AdzerCMS\MtsMarketolog;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Notice\SmsFailed;
use AdzerCMS\Contracts\Notice\SmsGateway;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\Translation\t;

final readonly class MtsMarketologGateway implements SmsGateway
{
    public const URL = 'api_url';
    public const LOGIN = 'login';
    public const PASSWORD = 'password';
    public const SENDER = 'sender';

    private const TIMEOUT = 15.0;

    public function __construct(private HttpClientInterface $http)
    {
    }

    public function credentials(): array
    {
        return [
            new Field(self::URL, t('mtsmarketolog.api_url'), required: true),
            new Field(self::LOGIN, t('mtsmarketolog.login'), required: true),
            new Field(self::PASSWORD, t('mtsmarketolog.password'), Type::Secret, required: true),
            new Field(self::SENDER, t('mtsmarketolog.sender'), required: true),
        ];
    }

    public function send(Credentials $credentials, string $phone, string $text): void
    {
        try {
            $response = $this->http->request('POST', rtrim($credentials->get(self::URL), '/').'/messages', [
                'auth_basic' => [$credentials->get(self::LOGIN), $credentials->get(self::PASSWORD)],
                'json' => ['messages' => [[
                    'content' => ['short_text' => $text],
                    'from' => ['sms_address' => $credentials->get(self::SENDER)],
                    'to' => [['msisdn' => $phone, 'message_id' => Uuid::v4()->toRfc4122()]],
                ]]],
                'timeout' => self::TIMEOUT,
            ]);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (ExceptionInterface $failure) {
            throw new SmsFailed($failure->getMessage(), previous: $failure);
        }

        if ($status >= 400) {
            throw new SmsFailed(\sprintf('the gateway answered %d %s', $status, $content));
        }
        $data = json_decode($content, true);
        if (!\is_array($data) || !\is_string($data['messages'][0]['internal_id'] ?? null)) {
            throw new SmsFailed(\sprintf('the gateway did not accept the message: %s', $content));
        }
    }

    public function contract(Credentials $credentials): array
    {
        throw new SmsFailed('MTS Marketolog gives no contract');
    }
}
