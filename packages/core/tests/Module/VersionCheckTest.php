<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Core\Composer\Repositories;
use AdzerCMS\Core\Module\Marks;
use AdzerCMS\Core\Tests\Container;
use AdzerCMS\Core\Tests\VersionCheck;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class VersionCheckTest extends KernelTestCase
{
    use CatalogAnswers;
    use Container;

    protected function setUp(): void
    {
        $this->answerCatalog();
        $this->listed(['acme/till', 'acme/shop', 'acme/kit', 'acme/draft']);
        $this->packages['acme/till'] = [self::manifest('acme/till', '1.2.0'), self::manifest('acme/till', '1.1.0')];
        $this->offer('acme/shop', '1.0.0');
        $this->offer('acme/kit', '2.0.0', 'library');
        $this->branch('acme/draft', '1.0.x-dev');
    }

    #[Group('ACMS-US-0039')]
    #[Group('ACMS-CORE-0957')]
    public function testPublishedVersionWithoutRecordGoesToCheck(): void
    {
        $this->mark('acme/shop', self::commit('acme/shop', '1.0.0'));
        $this->mark('acme/till', self::commit('acme/till', '1.1.0'));

        self::assertSame([
            ['package' => 'acme/draft', 'version' => '1.0.x-dev', 'require' => '1.0.x-dev#'.self::commit('acme/draft', 'dev-main'), 'reference' => self::commit('acme/draft', 'dev-main')],
            ['package' => 'acme/till', 'version' => '1.2.0', 'require' => '1.2.0', 'reference' => self::commit('acme/till', '1.2.0')],
        ], $this->check()->pending($this->registry ?? []));
    }

    #[Group('ACMS-US-0039')]
    #[Group('ACMS-CORE-0958')]
    public function testOutcomeGoesToRegistryWhileVersionIsPublished(): void
    {
        $this->mark('acme/till', self::commit('acme/till', '1.1.0'));
        $this->mark('acme/till', self::commit('acme/till', '0.9.0'));
        $this->mark('acme/gone', self::commit('acme/gone', '1.0.0'));
        $check = $this->check();
        $pending = $check->pending($this->registry ?? []);
        $outcomes = [['package' => 'acme/till', 'outcome' => 'phpstan'], ['package' => 'acme/shop', 'outcome' => Marks::PASSED]];

        self::assertEquals([
            'acme/shop' => [self::commit('acme/shop', '1.0.0') => ['version' => '1.0.0', 'outcome' => Marks::PASSED]],
            'acme/till' => [
                self::commit('acme/till', '1.1.0') => ['version' => '1.0.0', 'outcome' => Marks::PASSED],
                self::commit('acme/till', '1.2.0') => ['version' => '1.2.0', 'outcome' => 'phpstan'],
            ],
        ], $check->record($this->registry ?? [], $pending, $outcomes));

        $this->root = null;
        $this->expectExceptionObject(new \RuntimeException(\sprintf('%s is unavailable', Repositories::PACKAGIST)));
        $check->record($this->registry ?? [], $pending, $outcomes);
    }

    private function check(): VersionCheck
    {
        return new VersionCheck(self::service(Repositories::class));
    }
}
