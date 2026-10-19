<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$package = (string) getenv('ADZERCMS_PACKAGE');
/** @var array{name: string, require?: array<string, string>, suggest?: array<string, string>} $manifest */
$manifest = json_decode((string) file_get_contents($package.'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
$own = $package.'/composer-dependency-analyser.php';

$configuration = new Configuration();
if (is_file($own)) {
    $configuration = require $own;
    assert($configuration instanceof Configuration);
    $configuration->addPathToExclude($own);
}
if (is_dir($package.'/tests')) {
    $configuration->addPathToExclude($package.'/tests');
}

$fileless = array_filter(
    array_keys($manifest['require'] ?? []),
    static fn (string $required): bool => str_contains($required, '/') && InstalledVersions::isInstalled($required) && null === InstalledVersions::getInstallPath($required),
);
$suggested = array_keys($manifest['suggest'] ?? []);
$extensions = array_filter($suggested, static fn (string $suggestion): bool => str_starts_with($suggestion, 'ext-'));

return $configuration
    ->disableComposerAutoloadPathScan()
    ->disableReportingUnmatchedIgnores()
    ->addPathToScan($package, false)
    ->ignoreErrorsOnPackage($manifest['name'], [ErrorType::SHADOW_DEPENDENCY])
    ->ignoreErrorsOnPackages(array_values(array_diff($suggested, $extensions)), [ErrorType::SHADOW_DEPENDENCY, ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnExtensions(array_values($extensions), [ErrorType::SHADOW_DEPENDENCY, ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(array_values($fileless), [ErrorType::UNUSED_DEPENDENCY]);
