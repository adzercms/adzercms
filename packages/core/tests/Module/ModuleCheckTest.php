<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Core\Tests\ModuleCheck;
use AdzerCMS\Core\Tests\ModuleSuite;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ModuleCheckTest extends TestCase
{
    #[Group('ACMS-CORE-0956')]
    public function testAuditTakesPackagesTheModuleAloneAddsToInstance(): void
    {
        $added = (new ModuleCheck(ModuleSuite::root(), 'adzercms/opotopo'))->added();

        self::assertContains('adzercms/opotopo', $added);
        self::assertContains('opotopo/seller-api', $added, 'a requirement of the module only');
        self::assertNotContains('adzercms/core', $added, 'a requirement the instance has without the module');
        self::assertNotContains('symfony/http-client', $added, 'a requirement the instance has without the module');
    }

    #[Group('ACMS-CORE-0956')]
    public function testModuleGoesThroughChecksInOrder(): void
    {
        $check = new Process([\PHP_BINARY, 'vendor/bin/adzercms-module-check', 'adzercms/sms-pilot'], ModuleSuite::root(), timeout: null);
        $check->run();

        self::assertSame(0, $check->getExitCode(), $check->getOutput().$check->getErrorOutput());
        preg_match_all('/^== (\S+)$/m', $check->getOutput(), $checks);
        self::assertSame([ModuleCheck::BUILD, ModuleCheck::AUDIT, ModuleCheck::PHPSTAN, ModuleCheck::PSALM], $checks[1]);
        self::assertStringEndsWith("module check passed\n", $check->getOutput());
    }
}
