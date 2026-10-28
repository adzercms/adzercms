<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Notice\MailChannel;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelayGateway;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Mime\Email;

trait Delivery
{
    protected const MAIL = [MailChannel::HOST => 'smtp.example.org', MailChannel::SENDER => 'robot@example.org', Brand::TITLE => 'Village network'];
    protected const RELAY = [RelayGateway::SETTINGS.Switches::ENABLED => '1', RelayGateway::SETTINGS.RelayGateway::TOKEN => 'relay-token'];

    protected function texted(): string
    {
        $sent = self::service(RelayGateway::class)->sent;
        $last = end($sent);

        return false === $last ? self::fail('no message went through the gateway') : $last[2];
    }

    protected function letter(): Email
    {
        $messages = self::getMailerMessages();
        $last = end($messages);

        return $last instanceof Email ? $last : self::fail('no letter was sent');
    }

    protected function letterText(): string
    {
        return (string) $this->letter()->getTextBody();
    }

    protected function notices(): string
    {
        $notices = self::command('adzercms:notices');
        self::assertSame(Command::SUCCESS, $notices->execute([]));

        return trim($notices->getDisplay());
    }

    protected static function code(string $text): string
    {
        return 1 === preg_match('/\b(\d{6})\b/', $text, $match) ? $match[1] : self::fail('the message carries no code');
    }
}
