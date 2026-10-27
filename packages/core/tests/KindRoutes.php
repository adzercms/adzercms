<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Ui\AdminEntry;
use AdzerCMS\Contracts\Ui\CabinetEntry;
use AdzerCMS\Core\Module\Registry;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use Twig\Token;

final readonly class KindRoutes
{
    public function __construct(private Registry $registry)
    {
    }

    /** @return list<string> */
    public function crossed(string $directory): array
    {
        $manifest = json_decode((string) file_get_contents($directory.'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        $own = \is_array($manifest) && \is_string($manifest['name'] ?? null) ? $manifest['name'] : '';
        $loader = new AttributeDirectoryLoader(new FileLocator(), new AttributeRouteControllerLoader());
        $owners = [];
        foreach ($this->registry->kinds() as $kind) {
            foreach (CoreKind::Feature === $kind ? [] : $this->registry->ofKind($kind) as $module) {
                $controllers = Registry::path($module->package).'/src/Controller';
                if ($module->package !== $own && is_dir($controllers)) {
                    foreach (array_keys($loader->load($controllers)?->all() ?? []) as $name) {
                        $owners[$name] = $module->package;
                    }
                }
            }
        }

        $entries = array_map(static fn (string $class): string|false => realpath((string) (new \ReflectionClass($class))->getFileName()), [AdminEntry::class, CabinetEntry::class]);
        $crossed = [];
        foreach (self::literals($directory) as [$file, $line, $literal]) {
            if (isset($owners[$literal]) && !\in_array(realpath($file), $entries, true)) {
                $crossed[] = \sprintf('%s:%d: route %s of kind module %s goes through an entry of the interface contract', $file, $line, $literal, $owners[$literal]);
            }
        }

        return $crossed;
    }

    /** @return iterable<array{string, int, string}> */
    private static function literals(string $directory): iterable
    {
        foreach (self::files($directory.'/src', '*.php') as $file) {
            foreach (\PhpToken::tokenize((string) file_get_contents($file)) as $token) {
                if (\T_CONSTANT_ENCAPSED_STRING === $token->id) {
                    yield [$file, $token->line, substr($token->text, 1, -1)];
                }
            }
        }

        $twig = new Environment(new ArrayLoader());
        foreach (self::files($directory.'/templates', '*.twig') as $file) {
            $stream = $twig->tokenize(new Source((string) file_get_contents($file), $file, $file));
            while (!$stream->isEOF()) {
                $token = $stream->next();
                if ($token->test(Token::STRING_TYPE)) {
                    yield [$file, $token->getLine(), (string) $token->getValue()];
                }
            }
        }
    }

    /** @return list<string> */
    private static function files(string $directory, string $pattern): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        $files = [];
        foreach (Finder::create()->files()->in($directory)->name($pattern)->sortByName() as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }
}
