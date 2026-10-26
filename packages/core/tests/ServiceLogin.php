<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Translation\TranslatableMessage;

trait ServiceLogin
{
    /** @var list<array<string, mixed>> */
    private array $sent = [];

    /** @var list<MockResponse> */
    private array $answers = [];

    protected function answerService(): void
    {
        self::service(HttpAnswers::class)->respond(function (string $method, string $url, array $options): MockResponse {
            $this->sent[] = ['url' => $url] + $options;

            return array_shift($this->answers) ?? throw new \LogicException(\sprintf('unexpected request %s %s', $method, $url));
        });
    }

    /** @return array<string, string> */
    protected function authorize(string $service, string $endpoint): array
    {
        $this->client->request('GET', '/login/'.$service);

        return $this->authorized($endpoint);
    }

    /** @return array<string, string> */
    protected function authorized(string $endpoint): array
    {
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith($endpoint.'?', $location);

        return self::pairs((string) parse_url($location, \PHP_URL_QUERY));
    }

    protected function flashed(string $kind, string $to): TranslatableMessage
    {
        self::assertResponseRedirects($to);
        $session = $this->client->getRequest()->getSession();
        $messages = $session instanceof FlashBagAwareSessionInterface ? $session->getFlashBag()->peek($kind) : [];
        $message = end($messages);

        return $message instanceof TranslatableMessage ? $message : self::fail(\sprintf('no %s message after the request', $kind));
    }

    /** @return array<string, string> */
    protected function sentForm(int $request): array
    {
        $body = $this->sent[$request]['body'] ?? null;

        return self::pairs(\is_string($body) ? $body : self::fail('the request carries no body'));
    }

    protected function sentHeader(int $request, string $name): string
    {
        $lines = $this->sent[$request]['normalized_headers'][strtolower($name)] ?? null;
        $line = \is_array($lines) ? ($lines[0] ?? null) : null;

        return \is_string($line) ? substr($line, \strlen($name) + 2) : self::fail(\sprintf('the request carries no %s header', $name));
    }

    protected static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /** @return array<string, string> */
    private static function pairs(string $encoded): array
    {
        $pairs = [];
        foreach (explode('&', $encoded) as $pair) {
            [$name, $value] = explode('=', $pair, 2) + [1 => ''];
            $pairs[urldecode($name)] = urldecode($value);
        }

        return $pairs;
    }
}
