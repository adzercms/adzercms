<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Core\CoreBundle;
use AdzerCMS\Core\Tests\Module\Fixture\Design\Lumen\LumenBundle;
use AdzerCMS\Core\Tests\Module\Fixture\Design\Vista\VistaBundle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Twig\Loader\FilesystemLoader;

final class DesignTemplatesTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function templates(): iterable
    {
        yield 'site frame the design lacks' => ['@Design/layout.html.twig', 'theme layout'];
        yield 'site part the design has' => ['@Design/_header.html.twig', 'design header'];
        yield 'module template the design overrides' => ['@Pages/blocks/text.html.twig', 'design text block'];
        yield 'module template the design keeps' => ['@Pages/blocks/hero.html.twig', 'module hero block'];
        yield 'theme template the design overrides' => ['@Theme/error.html.twig', 'design error page'];
    }

    #[Group('ACMS-UI-0162')]
    #[Group('ACMS-UI-0164')]
    #[DataProvider('templates')]
    public function testDesignTemplatesGoBeforeThemeSiteAndModuleOnes(string $template, string $source): void
    {
        $fixture = __DIR__.'/Fixture/Design';
        $builder = new ContainerBuilder(new ParameterBag([
            'kernel.environment' => 'test',
            'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.bundles' => ['LumenBundle' => LumenBundle::class, 'VistaBundle' => VistaBundle::class],
            'kernel.bundles_metadata' => [
                'LumenBundle' => ['path' => $fixture.'/Lumen', 'namespace' => 'AdzerCMS\\Core\\Tests\\Module\\Fixture\\Design\\Lumen'],
                'VistaBundle' => ['path' => $fixture.'/Vista', 'namespace' => 'AdzerCMS\\Core\\Tests\\Module\\Fixture\\Design\\Vista'],
            ],
        ]));

        $extension = (new CoreBundle())->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($builder);

        $loader = new FilesystemLoader();
        foreach (array_merge(...array_column($builder->getExtensionConfig('twig'), 'paths')) as $path => $namespace) {
            $loader->addPath($path, $namespace);
        }
        $loader->addPath($fixture.'/Pages/templates', 'Pages');

        self::assertSame($source, trim($loader->getSourceContext($template)->getCode()));
    }
}
