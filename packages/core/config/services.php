<?php

declare(strict_types=1);

use AdzerCMS\Contracts\Login\Redirects;
use AdzerCMS\Core\Build\PreviewMark;
use AdzerCMS\Core\Composer\Artifacts;
use AdzerCMS\Core\Composer\Repositories;
use AdzerCMS\Core\Login\Authorizations;
use AdzerCMS\Core\Module\Catalog;
use AdzerCMS\Core\Module\ComposerRunner;
use AdzerCMS\Core\Module\Execution;
use AdzerCMS\Core\Module\Marks;
use AdzerCMS\Core\Module\Operations;
use AdzerCMS\Core\Module\Snapshot;
use AdzerCMS\Core\Module\Sources;
use AdzerCMS\Core\Module\Staging;
use AdzerCMS\Core\Module\Store;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Module\Tree;
use AdzerCMS\Core\Notice\Bounces;
use AdzerCMS\Core\Schema\MigrationOrder;
use DirectoryTree\ImapEngine\MailboxInterface;
use PhpTuf\ComposerStager\API\Core\BeginnerInterface;
use PhpTuf\ComposerStager\API\Process\Service\ComposerProcessRunnerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\Process\ExecutableFinder;
use Twig\Extra\CssInliner\CssInlinerExtension;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()->autowire()->autoconfigure();

    $services->load('AdzerCMS\\Core\\Account\\', '../src/Account/');
    $services->load('AdzerCMS\\Core\\Analytics\\', '../src/Analytics/');
    $services->load('AdzerCMS\\Core\\Attachment\\', '../src/Attachment/');
    $services->load('AdzerCMS\\Core\\Captcha\\', '../src/Captcha/');
    $services->load('AdzerCMS\\Core\\Command\\', '../src/Command/');
    $services->load('AdzerCMS\\Core\\Confirmation\\', '../src/Confirmation/');
    $services->load('AdzerCMS\\Core\\Controller\\', '../src/Controller/');
    $services->load('AdzerCMS\\Core\\Login\\', '../src/Login/');
    $services->alias(Redirects::class, Authorizations::class);
    $services->load('AdzerCMS\\Core\\Notice\\', '../src/Notice/')->exclude('../src/Notice/BotChannel.php');
    $services->set(Bounces::MAILBOX, MailboxInterface::class)->factory([Bounces::class, 'mailbox']);
    $services->load('AdzerCMS\\Core\\Repository\\', '../src/Repository/');
    $services->load('AdzerCMS\\Core\\Security\\', '../src/Security/');
    $services->load('AdzerCMS\\Core\\Seo\\', '../src/Seo/')->exclude('../src/Seo/Head.php');
    $services->load('AdzerCMS\\Core\\Session\\', '../src/Session/');
    $services->load('AdzerCMS\\Core\\Setting\\', '../src/Setting/');
    $services->load('AdzerCMS\\Core\\Text\\', '../src/Text/');
    $services->load('AdzerCMS\\Core\\Ui\\', '../src/Ui/');
    $services->set(Repositories::CLIENT, RetryableHttpClient::class)
        ->args([new Reference('http_client'), (new Definition(GenericRetryStrategy::class))->setArgument('$delayMs', 0), Repositories::RETRIES]);
    $services->set(Repositories::class)->arg('$httpClient', new Reference(Repositories::CLIENT));
    $services->set(Artifacts::class);
    $services->set(Sources::class);
    $services->set(Snapshot::class);
    $services->set(Marks::class)->arg('$httpClient', new Reference(Repositories::CLIENT));
    $services->set(Store::class)->arg('$httpClient', new Reference(Repositories::CLIENT));
    $services->set(Catalog::class);
    $services->set(Tree::class);
    $services->set(Operations::class);
    $services->set(Staging::class);
    $services->set(Execution::class);
    $services->set(Switches::class);
    $services->set(PreviewMark::class);
    $services->set(MigrationOrder::class);
    $services->set(CssInlinerExtension::class);

    $stager = \dirname((string) (new ReflectionClass(BeginnerInterface::class))->getFileName(), 3);
    $services->load('PhpTuf\\ComposerStager\\', $stager.'/*')
        ->exclude([$stager.'/*/*/Value', $stager.'/API/Exception', $stager.'/Internal/Process/Service/ComposerProcessRunner.php']);
    $services->set(ComposerRunner::class);
    $services->alias(ComposerProcessRunnerInterface::class, ComposerRunner::class);
    $services->set(ExecutableFinder::class);
};
