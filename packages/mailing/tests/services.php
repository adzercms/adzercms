<?php

declare(strict_types=1);

use AdzerCMS\Mailing\Tests\Fixture\CommentAudience;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->services()->set(CommentAudience::class)->autoconfigure();
};
