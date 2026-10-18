<?php

declare(strict_types=1);

namespace AdzerCMS\TelegramBot\Tests;

use AdzerCMS\Contracts\Notice\BotGateway;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Entity\ConnectedService;
use AdzerCMS\Core\Entity\Notice;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Notice\MailChannel;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Core\Notice\Status;
use AdzerCMS\Core\Repository\NoticeRepository;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\TelegramBot\TelegramBotGateway;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class TelegramBotTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;

    private const CHANNEL = 'telegrambot';
    private const SETTINGS = 'bot.telegrambot.';
    private const CHAT = '5010';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure([
            MailChannel::HOST => 'smtp.example.org',
            MailChannel::SENDER => 'robot@example.org',
            self::SETTINGS.Switches::ENABLED => '1',
            self::SETTINGS.BotGateway::TOKEN => 'bot-token',
        ]);
    }

    #[Group('ACMS-CORE-0121')]
    #[Group('ACMS-CORE-0123')]
    public function testLinkedBotIsPreferredOverMail(): void
    {
        $answer = new JsonMockResponse(['ok' => true, 'result' => ['message_id' => 1]]);
        self::service(HttpAnswers::class)->respond($answer);

        $notice = $this->message($this->linked());

        self::assertSame(self::CHANNEL, $notice->getChannel());
        self::assertSame(self::CHAT, $notice->getRecipient());
        self::assertSame(Status::Sent, $notice->getStatus());
        self::assertSame(\sprintf(TelegramBotGateway::API, 'bot-token'), $answer->getRequestUrl());
        self::assertSame(['chat_id' => self::CHAT, 'text' => 'Fresh backup is done'], json_decode((string) ($answer->getRequestOptions()['body'] ?? ''), true));
    }

    #[Group('ACMS-CORE-0905')]
    public function testProfileLinksTheBotThroughTheTelegramLogin(): void
    {
        $this->client->loginUser($this->record('79990000001'));
        $this->em->flush();

        $this->post('/profile/bot/'.self::CHANNEL);

        self::assertResponseRedirects('/login/'.TelegramBotGateway::SERVICE);
    }

    #[Group('ACMS-CORE-0121')]
    #[Group('ACMS-CORE-0132')]
    public function testRefusedBotHandsTheEventToTheNextChannel(): void
    {
        self::service(HttpAnswers::class)->respond(new JsonMockResponse(['ok' => false, 'description' => 'chat not found'], ['http_code' => 403]));

        $notice = $this->message($this->linked());
        $refused = self::service(NoticeRepository::class)->findOneBy(['channel' => self::CHANNEL]) ?? self::fail('the bot refusal is not journaled');

        self::assertSame(ContactKind::Email->channel(), $notice->getChannel());
        self::assertSame(Status::Sent, $notice->getStatus());
        self::assertSame(Status::Failed, $refused->getStatus(), 'the bot refusal stays in the journal next to the delivered message');
        self::assertSame('the Telegram bot refused to send: chat not found', $refused->getError());
    }

    #[Group('ACMS-CORE-0121')]
    #[Group('ACMS-CORE-0134')]
    public function testBotWithoutTokenIsNotUsed(): void
    {
        self::service(HttpAnswers::class)->respond([]);
        $user = $this->linked();
        self::service(Settings::class)->clear(self::service(Catalog::class)->fields()[self::SETTINGS.BotGateway::TOKEN] ?? self::fail('the bot token is not in the catalog'));
        $this->em->flush();

        self::assertSame(ContactKind::Email->channel(), $this->message($user)->getChannel(), 'a bot without its token hands the event to the next channel');
    }

    #[Group('ACMS-CORE-0182')]
    public function testEnabledBotTokenGoesToConsoleOnly(): void
    {
        $read = self::command('adzercms:bot:token');

        self::assertSame(Command::SUCCESS, $read->execute(['bot' => self::CHANNEL]));
        self::assertSame("bot-token\n", $read->getDisplay());
    }

    #[Group('ACMS-CORE-0182')]
    public function testDisabledOrForeignBotYieldsNoToken(): void
    {
        $this->configure([self::SETTINGS.Switches::ENABLED => '']);

        foreach ([self::CHANNEL, ContactKind::Phone->channel(), 'missing'] as $bot) {
            $read = self::command('adzercms:bot:token');

            self::assertSame(Command::FAILURE, $read->execute(['bot' => $bot], ['capture_stderr_separately' => true]));
            self::assertSame('', $read->getDisplay(), $bot);
            self::assertStringContainsString(\sprintf('bot "%s" is not enabled', $bot), $read->getErrorOutput(), $bot);
        }
    }

    private function linked(): User
    {
        $user = $this->record('79991234567', 'human@example.org');
        $this->em->persist(new ConnectedService($user, TelegramBotGateway::SERVICE, self::CHAT, null));
        $this->em->flush();

        return $user;
    }

    private function message(User $user): Notice
    {
        return self::service(Notifier::class)->message($user, 'Backup', 'Fresh backup is done') ?? self::fail('the message reached no channel');
    }
}
