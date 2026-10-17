<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Core\CoreBundle;
use AdzerCMS\Core\Tests\Module\Fixture\Aurora\AuroraBundle;
use AdzerCMS\Core\Tests\Module\Fixture\DrumBundle;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class KindTemplatesTest extends TestCase
{
    #[Group('ACMS-CORE-0187')]
    public function testTemplatesOfSingleKindModuleAnswerToNamespaceOfKind(): void
    {
        $fixture = __DIR__.'/Fixture';
        $builder = new ContainerBuilder(new ParameterBag([
            'kernel.environment' => 'test',
            'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.bundles' => ['Aurora' => AuroraBundle::class, 'Drum' => DrumBundle::class],
            'kernel.bundles_metadata' => [
                'Aurora' => ['path' => $fixture.'/Aurora', 'namespace' => 'AdzerCMS\\Core\\Tests\\Module\\Fixture\\Aurora'],
                'Drum' => ['path' => $fixture.'/Aurora', 'namespace' => 'AdzerCMS\\Core\\Tests\\Module\\Fixture'],
            ],
        ]));

        $extension = (new CoreBundle())->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($builder);

        self::assertSame(
            [$fixture.'/Aurora/templates' => 'Theme'],
            array_merge(...array_column($builder->getExtensionConfig('twig'), 'paths')),
            'a module of a listed kind keeps its own namespace only',
        );
    }
}
