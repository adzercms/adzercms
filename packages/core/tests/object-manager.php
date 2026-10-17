<?php

declare(strict_types=1);

use AdzerCMS\Core\Module\Registry;
use Composer\InstalledVersions;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

$entities = array_filter(
    array_map(
        static fn (string $package): string => (InstalledVersions::getInstallPath($package) ?? '').'/src/Entity',
        ['adzercms/core', ...InstalledVersions::getInstalledPackagesByType(Registry::TYPE)],
    ),
    is_dir(...),
);

$config = ORMSetup::createAttributeMetadataConfig(array_values(array_unique($entities)), true);
$config->enableNativeLazyObjects(true);

return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.0'], $config), $config);
