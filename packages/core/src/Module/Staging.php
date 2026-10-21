<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use PhpTuf\ComposerStager\API\Core\BeginnerInterface;
use PhpTuf\ComposerStager\API\Core\CleanerInterface;
use PhpTuf\ComposerStager\API\Core\CommitterInterface;
use PhpTuf\ComposerStager\API\Core\StagerInterface;
use PhpTuf\ComposerStager\API\Exception\ExceptionInterface;
use PhpTuf\ComposerStager\API\Path\Factory\PathFactoryInterface;
use PhpTuf\ComposerStager\API\Path\Factory\PathListFactoryInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Process\Service\ComposerProcessRunnerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

final readonly class Staging
{
    private const STAGE = 'var/stage';
    private const EXCLUDED = ['var', '.env.local', 'public/assets', 'assets/vendor'];
    private const OPTIONS = ['--no-scripts', '--no-interaction', '--no-progress'];
    private const ENTRY_OPTIONS = ['--no-plugins', '--no-interaction'];
    private const MANIFEST = 'composer.json';

    public function __construct(
        private Tree $tree,
        private BeginnerInterface $beginner,
        private StagerInterface $stager,
        private CommitterInterface $committer,
        private CleanerInterface $cleaner,
        private ComposerProcessRunnerInterface $composer,
        private Sources $sources,
        private PathFactoryInterface $paths,
        private PathListFactoryInterface $lists,
        #[Autowire('%adzercms.files_dir%')]
        private string $files,
    ) {
    }

    /**
     * @param list<string>          $command
     * @param array<string, string> $environment
     */
    public function run(array $command, array $environment): ?string
    {
        $active = $this->paths->create($this->tree->root);
        $stage = $this->paths->create(self::STAGE, $active);
        $excluded = $this->lists->create(...self::EXCLUDED, ...Path::isBasePath($this->tree->root, $this->files) ? [Path::makeRelative($this->files, $this->tree->root)] : []);

        try {
            $this->clean($active, $stage);
            $this->beginner->begin($active, $stage, $excluded, timeout: Tree::TIMEOUT);
            $this->enter($stage);
            $this->stager->stage([...$command, ...self::OPTIONS], $active, $stage, timeout: Tree::TIMEOUT);
            $refused = $this->tree->refresh($environment, function () use ($stage, $active, $excluded): void {
                $this->committer->commit($stage, $active, $excluded, timeout: Tree::TIMEOUT);
            });
            $this->clean($active, $stage);
        } catch (ExceptionInterface $failure) {
            $this->clean($active, $stage);

            return $failure->getMessage();
        }

        return $refused;
    }

    private function enter(PathInterface $stage): void
    {
        $manifest = json_decode((string) file_get_contents($stage->absolute().'/'.self::MANIFEST), true);
        $repositories = \is_array($manifest) && \is_array($manifest['repositories'] ?? null) ? $manifest['repositories'] : [];
        foreach ($repositories as $key => $repository) {
            $name = \is_string($key) ? $key : (\is_array($repository) ? $repository['name'] ?? null : null);
            if (\is_string($name) && Sources::entry($name)) {
                $this->composer->run(['repo', 'remove', $name, ...self::ENTRY_OPTIONS], $stage, timeout: Tree::TIMEOUT);
            }
        }
        foreach ($this->sources->entries() as $name => $entry) {
            $this->composer->run(['repo', 'add', $name, json_encode($entry, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES), '--append', ...self::ENTRY_OPTIONS], $stage, timeout: Tree::TIMEOUT);
        }
    }

    private function clean(PathInterface $active, PathInterface $stage): void
    {
        if (is_dir($stage->absolute())) {
            $this->cleaner->clean($active, $stage, timeout: Tree::TIMEOUT);
        }
    }
}
