<?php

declare(strict_types=1);

use AdzerCMS\Cabinet\Tests\Fixture\Pager;
use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Core\Tests\Account\Fixture\OpenPolicy;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Core\Tests\Login\Fixture\CatchListener;
use AdzerCMS\Core\Tests\Notice\Fixture\EchoListener;
use AdzerCMS\Core\Tests\Notice\Fixture\Flare\FlareChannel;
use AdzerCMS\Core\Tests\Notice\Fixture\NoticeController;
use AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelaySettings;
use AdzerCMS\Core\Tests\Security\Fixture\AccountController;
use AdzerCMS\Core\Tests\Security\Fixture\AccountNumbers;
use AdzerCMS\Core\Tests\Seo\Fixture\GuideController;
use AdzerCMS\Core\Tests\Seo\Fixture\GuidePages;
use AdzerCMS\Core\Tests\Setting\Fixture\Shop;
use AdzerCMS\Core\Tests\Translation\Fixture\LocaleController;
use AdzerCMS\Core\Tests\Ui\Fixture\FrameController;
use AdzerCMS\Core\Tests\Ui\Fixture\Ledger;
use AdzerCMS\Core\Tests\Ui\Fixture\ListController;
use AdzerCMS\Core\Tests\Ui\Fixture\Masthead;
use AdzerCMS\Leads\LeadsBundle;
use AdzerCMS\Leads\Tests\Fixture\ItemSubject;
use AdzerCMS\Mailing\MailingBundle;
use AdzerCMS\Mailing\Tests\Fixture\CommentAudience;
use AdzerCMS\Opotopo\OpotopoBundle;
use AdzerCMS\Opotopo\Tests\Fixture\ShelfCatalog;
use AdzerCMS\Payments\PaymentKind;
use AdzerCMS\Payments\PaymentsBundle;
use AdzerCMS\Realtime\HubKind;
use AdzerCMS\Realtime\RealtimeBundle;
use AdzerCMS\Realtime\Tests\Fixture\HeraldHub;
use AdzerCMS\Shop\ShopBundle;
use AdzerCMS\Shop\Tests\Fixture\Counters;
use AdzerCMS\Shop\Tests\Fixture\Till;
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
    $services->set(Pager::class)->autoconfigure();
    $services->set(EchoListener::class)->autoconfigure();
    $services->set(CatchListener::class)->autoconfigure();
    $services->set(FlareChannel::class)->autoconfigure()->autowire()->public();
    $services->set(Shop::class)->autoconfigure();
    $services->set(RelaySettings::class)->autoconfigure();
    $services->set(AccountNumbers::class)->autoconfigure();
    $services->set(AccountController::class)->autoconfigure()->autowire();
    $services->set(NoticeController::class)->autoconfigure()->autowire();
    $services->set(LocaleController::class)->autoconfigure()->autowire();
    $services->set(FrameController::class)->autoconfigure()->autowire();
    $services->set(ListController::class)->autoconfigure()->autowire();
    $services->set(GuidePages::class)->autoconfigure();
    $services->set(GuideController::class)->autoconfigure()->autowire();
    $services->set('logger', NullLogger::class);

    $bundles = $builder->getParameter('kernel.bundles');
    if (\is_array($bundles) && \in_array(SupportBundle::class, $bundles, true)) {
        $services->set(SubjectFixture::class)->autoconfigure()->public();
    }
    if (\is_array($bundles) && \in_array(LeadsBundle::class, $bundles, true)) {
        $services->set(ItemSubject::class)->autoconfigure()->public();
    }
    if (\is_array($bundles) && \in_array(MailingBundle::class, $bundles, true)) {
        $services->set(CommentAudience::class)->autoconfigure();
    }
    if (\is_array($bundles) && \in_array(OpotopoBundle::class, $bundles, true)) {
        $services->set(ShelfCatalog::class)->autowire()->autoconfigure()->public();
    }
    if (\is_array($bundles) && \in_array(ShopBundle::class, $bundles, true)) {
        $services->set(Counters::class)->autoconfigure()->public();
    }
    if (\is_array($bundles) && \in_array(ShopBundle::class, $bundles, true) && \in_array(PaymentsBundle::class, $bundles, true)
        && [] === array_filter($bundles, static fn (string $bundle): bool => is_subclass_of($bundle, Module::class) && PaymentKind::Acquiring === $bundle::kind())) {
        $services->set(Till::class)->autoconfigure()->public();
    }
    if (\is_array($bundles) && \in_array(RealtimeBundle::class, $bundles, true)
        && [] === array_filter($bundles, static fn (string $bundle): bool => is_subclass_of($bundle, Module::class) && HubKind::Hub === $bundle::kind())) {
        $services->set(HeraldHub::class)->autoconfigure()->public();
    }
};
