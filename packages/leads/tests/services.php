<?php

declare(strict_types=1);

use AdzerCMS\Contracts\Ui\Pdf;
use AdzerCMS\Leads\Tests\Fixture\ItemSubject;
use AdzerCMS\Leads\Tests\Fixture\RecordedPdf;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $container->services()->set(ItemSubject::class)->autoconfigure()->public();
    $container->services()->set(RecordedPdf::class)->decorate(Pdf::class)->args([service('.inner')])->public();
};
