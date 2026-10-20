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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

final readonly class Staging
{
    private const STAGE = 'var/stage';
    private const EXCLUDED = ['var', '.env.local', 'public/assets', 'assets/vendor'];
    private const OPTIONS = ['--no-scripts', '--no-interaction', '--no-progress'];

    public function __construct(
        private Tree $tree,
        private BeginnerInterface $beginner,
        private StagerInterface $stager,
        private CommitterInterface $committer,
        private CleanerInterface $cleaner,
        private PathFactoryInterface $paths,
        private PathListFactoryInterface $lists,
        #[Autowire('%adzercms.files_dir%')]
        private string $files,
    ) {
    }

    /** @param list<string> $command */
    public function run(array $command): ?string
    {
        $active = $this->paths->create($this->tree->root);
        $stage = $this->paths->create(self::STAGE, $active);
        $excluded = $this->lists->create(...self::EXCLUDED, ...Path::isBasePath($this->tree->root, $this->files) ? [Path::makeRelative($this->files, $this->tree->root)] : []);

        try {
            $this->clean($active, $stage);
            $this->beginner->begin($active, $stage, $excluded, timeout: Tree::TIMEOUT);
            $this->stager->stage([...$command, ...self::OPTIONS], $active, $stage, timeout: Tree::TIMEOUT);
            $this->committer->commit($stage, $active, $excluded, timeout: Tree::TIMEOUT);
            $this->clean($active, $stage);
        } catch (ExceptionInterface $failure) {
            $this->clean($active, $stage);

            return $failure->getMessage();
        }

        return null;
    }

    private function clean(PathInterface $active, PathInterface $stage): void
    {
        if (is_dir($stage->absolute())) {
            $this->cleaner->clean($active, $stage, timeout: Tree::TIMEOUT);
        }
    }
}
