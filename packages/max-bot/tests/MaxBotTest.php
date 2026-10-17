<?php

declare(strict_types=1);

namespace AdzerCMS\MaxBot\Tests;

use AdzerCMS\Contracts\Notice\BotGateway;
use AdzerCMS\Core\Entity\ConnectedService;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Notice\BotLink;
use AdzerCMS\Core\Notice\Channels;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\MaxBot\MaxBotGateway;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MaxBotTest extends SchemaTestCase
{
    use Accounts;
    use Configuration;

    private const BOT = 'maxbot';
    private const SETTINGS = 'bot.maxbot.';
    private const SENDER = '4242';

    /** @var list<string> */
    private array $calls = [];

    /** @var array<string, array<string, mixed>> */
    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure([
            self::SETTINGS.Switches::ENABLED => '1',
            self::SETTINGS.BotGateway::TOKEN => 'bot-token',
        ]);
        self::service(HttpAnswers::class)->respond(function (string $method, string $url): JsonMockResponse {
            $this->calls[] = $method.' '.$url;

            return new JsonMockResponse($this->answers[(string) parse_url($url, \PHP_URL_PATH)] ?? []);
        });
    }

    #[Group('ACMS-CORE-0028')]
    #[Group('ACMS-CORE-0124')]
    public function testEnabledBotOpensItsOriginToForms(): void
    {
        $this->client->request('POST', '/webhook/bot/'.self::BOT);
        self::assertMatchesRegularExpression("~form-action 'self'[^;]* https://max\\.ru~", $this->policy(), 'without the bot address the browser drops the way of the link button to the messenger');

        $this->configure([self::SETTINGS.Switches::ENABLED => '']);
        $this->client->request('POST', '/webhook/bot/'.self::BOT);
        self::assertStringNotContainsString('https://max.ru', $this->policy(), 'a switched off bot opens nothing');
    }

    #[Group('ACMS-CORE-0123')]
    #[Group('ACMS-CORE-0125')]
    #[Group('ACMS-CORE-0126')]
    #[Group('ACMS-US-0002')]
    public function testCodeSentToTheBotLinksTheDialogOfItsSender(): void
    {
        $user = $this->record('79991234567');
        $update = [
            'update_type' => 'message_created',
            'message' => [
                'body' => ['text' => self::service(BotLink::class)->request($user, MaxBotGateway::SERVICE)],
                'sender' => ['user_id' => (int) self::SENDER, 'username' => 'tester'],
                'recipient' => ['chat_id' => 777, 'user_id' => 55],
            ],
        ];

        $this->update($update, null);
        self::assertResponseStatusCodeSame(403, 'an update without the subscription secret is not taken');
        self::assertNull($this->linked($user));

        $this->update($update, self::service(Channels::class)->webhookSecret(self::BOT));
        self::assertResponseIsSuccessful();

        $linked = $this->linked($user) ?? self::fail('the dialog is not linked');
        self::assertSame(self::SENDER, $linked->getProfile(), 'the sender of the code becomes the address of notices');
        self::assertSame('@tester', $linked->getAccount(), 'the link holds the visible name of the account');
        self::assertSame('POST '.MaxBotGateway::API.'/messages?user_id='.self::SENDER, end($this->calls), 'a personal dialog is addressed by the interlocutor, not by the chat');
    }

    #[Group('ACMS-CORE-0123')]
    #[Group('ACMS-CORE-0124')]
    #[Group('ACMS-US-0002')]
    public function testStartLinkCarriesTheCodeAndTheStartLinksTheDialog(): void
    {
        $user = $this->record('79991234567');
        $code = self::service(BotLink::class)->request($user, MaxBotGateway::SERVICE);
        $bot = self::service(Channels::class)->bot(self::BOT) ?? self::fail('the bot is not ready');

        self::assertNull($bot->startLink($code), 'without the bot name in the answer of the messenger there is no link');

        $this->answers['/me'] = ['user_id' => 55, 'username' => 'id1_bot', 'is_bot' => true];
        self::assertSame('https://max.ru/id1_bot?start='.$code, $bot->startLink($code));

        $this->update([
            'update_type' => 'bot_started',
            'chat_id' => 777,
            'user' => ['user_id' => (int) self::SENDER, 'first_name' => 'Test', 'last_name' => 'Human'],
            'payload' => $code,
        ], self::service(Channels::class)->webhookSecret(self::BOT));

        self::assertResponseIsSuccessful();
        $linked = $this->linked($user) ?? self::fail('the start of the bot by the link does not link the dialog');
        self::assertSame(self::SENDER, $linked->getProfile());
        self::assertSame('Test Human', $linked->getAccount(), 'an account without username is named by its name');
    }

    #[Group('ACMS-CORE-0126')]
    public function testWebhookSubscriptionIsPlaced(): void
    {
        self::assertSame([], self::service(Channels::class)->subscribe());
        self::assertSame(['GET '.MaxBotGateway::API.'/subscriptions', 'POST '.MaxBotGateway::API.'/subscriptions'], $this->calls, 'the subscription is asked first, then placed');
    }

    #[Group('ACMS-CORE-0126')]
    public function testStandingWebhookSubscriptionIsLeftAlone(): void
    {
        $this->answers['/subscriptions'] = ['subscriptions' => [['url' => $this->webhook(), 'update_types' => ['bot_started', 'message_created']]]];

        self::assertSame([], self::service(Channels::class)->subscribe());
        self::assertSame(['GET '.MaxBotGateway::API.'/subscriptions'], $this->calls, 'a placed subscription is not placed twice');
    }

    #[Group('ACMS-CORE-0126')]
    public function testWebhookSubscriptionWithOtherEventsIsReplaced(): void
    {
        $this->answers['/subscriptions'] = ['subscriptions' => [['url' => $this->webhook(), 'update_types' => ['message_created']]]];

        self::assertSame([], self::service(Channels::class)->subscribe());
        self::assertSame([
            'GET '.MaxBotGateway::API.'/subscriptions',
            'DELETE '.MaxBotGateway::API.'/subscriptions?url='.rawurlencode($this->webhook()),
            'POST '.MaxBotGateway::API.'/subscriptions',
        ], $this->calls, 'a subscription without the start event is dropped and placed anew');
    }

    /** @param array<string, mixed> $update */
    private function update(array $update, ?string $secret): void
    {
        $this->client->request('POST', '/webhook/bot/'.self::BOT, server: ['CONTENT_TYPE' => 'application/json'] + (null === $secret ? [] : ['HTTP_X_MAX_BOT_API_SECRET' => $secret]), content: json_encode($update, \JSON_THROW_ON_ERROR));
    }

    private function linked(User $user): ?ConnectedService
    {
        return self::service(ConnectedServiceRepository::class)->of($user)[MaxBotGateway::SERVICE] ?? null;
    }

    private function webhook(): string
    {
        return self::service(UrlGeneratorInterface::class)->generate('core_webhook_bot', ['bot' => self::BOT], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function policy(): string
    {
        return (string) $this->client->getResponse()->headers->get('Content-Security-Policy');
    }
}
