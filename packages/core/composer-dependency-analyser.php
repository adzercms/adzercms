<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())->ignoreErrorsOnPackages([
    'doctrine/doctrine-migrations-bundle',
    'symfony/asset',
    'symfony/http-client',
    'symfony/lock',
    'symfony/twig-bundle',
], [ErrorType::UNUSED_DEPENDENCY]);
