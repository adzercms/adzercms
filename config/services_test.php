<?php

declare(strict_types=1);

use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Core\Tests\Account\Fixture\OpenPolicy;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Core\Tests\Notice\Fixture\EchoListener;
use AdzerCMS\Core\Tests\Notice\Fixture\NoticeController;
use AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelaySettings;
use AdzerCMS\Core\Tests\Security\Fixture\AccountController;
use AdzerCMS\Core\Tests\Security\Fixture\AccountNumbers;
use AdzerCMS\Core\Tests\Setting\Fixture\Shop;
use AdzerCMS\Core\Tests\Translation\Fixture\LocaleController;
use AdzerCMS\Core\Tests\Ui\Fixture\FrameController;
use AdzerCMS\Core\Tests\Ui\Fixture\Ledger;
use AdzerCMS\Core\Tests\Ui\Fixture\Masthead;
use AdzerCMS\Mailing\MailingBundle;
use AdzerCMS\Mailing\Tests\Fixture\CommentAudience;
use AdzerCMS\Realtime\HubKind;
use AdzerCMS\Realtime\RealtimeBundle;
use AdzerCMS\Realtime\Tests\Fixture\HeraldHub;
use AdzerCMS\Support\SupportBundle;
use AdzerCMS\Support\Tests\Fixture\SubjectFixture;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $services = $container->services();
    $services->set(HttpAnswers::class)->public();
    $services->set(OpenPolicy::class)->autoconfigure()->public();
    $services->set(Ledger::class)->autoconfigure()->public();
    $services->set(Masthead::class)->autoconfigure();
    $services->set(EchoListener::class)->autoconfigure();
    $services->set(Shop::class)->autoconfigure();
    $services->set(RelaySettings::class)->autoconfigure();
    $services->set(AccountNumbers::class)->autoconfigure();
    $services->set(AccountController::class)->autoconfigure()->autowire();
    $services->set(NoticeController::class)->autoconfigure()->autowire();
    $services->set(LocaleController::class)->autoconfigure()->autowire();
    $services->set(FrameController::class)->autoconfigure()->autowire();
    $services->set('logger', NullLogger::class);

    $bundles = $builder->getParameter('kernel.bundles');
    if (\is_array($bundles) && \in_array(SupportBundle::class, $bundles, true)) {
        $services->set(SubjectFixture::class)->autoconfigure()->public();
    }
    if (\is_array($bundles) && \in_array(MailingBundle::class, $bundles, true)) {
        $services->set(CommentAudience::class)->autoconfigure();
    }
    if (\is_array($bundles) && \in_array(RealtimeBundle::class, $bundles, true)
        && [] === array_filter($bundles, static fn (string $bundle): bool => is_subclass_of($bundle, Module::class) && HubKind::Hub === $bundle::kind())) {
        $services->set(HeraldHub::class)->autoconfigure()->public();
    }
};
