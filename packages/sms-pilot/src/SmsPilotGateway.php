<?php

declare(strict_types=1);

namespace AdzerCMS\SmsPilot;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Notice\SmsFailed;
use AdzerCMS\Contracts\Notice\SmsGateway;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Guided;
use AdzerCMS\Contracts\Setting\Type;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\Translation\t;

final readonly class SmsPilotGateway implements SmsGateway, Guided
{
    public const API = 'https://smspilot.ru/api2.php';
    public const KEY = 'apikey';
    public const SENDER = 'sender';

    private const TIMEOUT = 15.0;

    public function __construct(private HttpClientInterface $http)
    {
    }

    public function credentials(): array
    {
        return [
            new Field(self::KEY, t('smspilot.apikey'), Type::Secret, required: true),
            new Field(self::SENDER, t('smspilot.sender')),
        ];
    }

    public function guide(): array
    {
        return [t('smspilot.guide.key'), t('smspilot.guide.sender')];
    }

    public function send(Credentials $credentials, string $phone, string $text): void
    {
        $message = ['to' => $phone, 'text' => $text];
        if ('' !== $credentials->get(self::SENDER)) {
            $message['from'] = $credentials->get(self::SENDER);
        }

        $data = $this->request($credentials, ['send' => [$message]]);
        if (null === ($data['send'][0]['server_id'] ?? null)) {
            throw new SmsFailed(\sprintf('the gateway did not accept the message: %s', (string) json_encode($data, \JSON_UNESCAPED_UNICODE)));
        }
    }

    public function contract(Credentials $credentials): array
    {
        $data = $this->request($credentials, ['info' => true]);
        $info = \is_array($data['info'] ?? null) ? $data['info'] : $data;
        $rows = [];
        foreach ($info as $key => $value) {
            if (\is_scalar($value)) {
                $rows[(string) $key] = (string) $value;
            }
        }

        return [] === $rows
            ? throw new SmsFailed(\sprintf('the gateway gave no contract: %s', (string) json_encode($data, \JSON_UNESCAPED_UNICODE))) : $rows;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<mixed>
     */
    private function request(Credentials $credentials, array $payload): array
    {
        try {
            $response = $this->http->request('POST', self::API, [
                'json' => ['apikey' => $credentials->get(self::KEY)] + $payload,
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
        if (!\is_array($data)) {
            throw new SmsFailed(\sprintf('the gateway answered an unparsable body: %s', $content));
        }
        if (\is_array($data['error'] ?? null)) {
            $error = $data['error'];
            throw new SmsFailed(\sprintf('the gateway refused: %s', (string) ($error['description_ru'] ?? $error['description'] ?? $content)));
        }

        return $data;
    }
}
