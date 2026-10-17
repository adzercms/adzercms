<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Core\Setting\Settings;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

#[AutoconfigureTag('mailer.transport_factory')]
final class MailTransport implements TransportFactoryInterface
{
    private const SCHEME = 'settings';
    public const DSN = self::SCHEME.'://default';
    private const SMTPS_PORT = 465;

    public function __construct(
        private readonly Settings $settings,
        #[Autowire(service: 'mailer.transport_factory.smtp')]
        private readonly TransportFactoryInterface $smtp,
    ) {
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $port = $this->settings->int(MailChannel::PORT, self::SMTPS_PORT);

        return $this->smtp->create(new Dsn(
            self::SMTPS_PORT === $port ? 'smtps' : 'smtp',
            $this->settings->get(MailChannel::HOST),
            $this->settings->get(MailChannel::LOGIN) ?: null,
            $this->settings->get(MailChannel::PASSWORD) ?: null,
            $port,
        ));
    }

    public function supports(Dsn $dsn): bool
    {
        return self::SCHEME === $dsn->getScheme();
    }
}
