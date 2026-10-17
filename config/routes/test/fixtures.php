<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('../../../packages/core/tests/Security/Fixture', 'attribute');
    $routes->import('../../../packages/core/tests/Notice/Fixture', 'attribute');
    $routes->import('../../../packages/core/tests/Translation/Fixture', 'attribute');
    $routes->import('../../../packages/core/tests/Ui/Fixture', 'attribute');
};
