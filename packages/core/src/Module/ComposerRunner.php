<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use PhpTuf\ComposerStager\API\Finder\Service\ExecutableFinderInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Process\Factory\ProcessFactoryInterface;
use PhpTuf\ComposerStager\API\Process\Service\ComposerProcessRunnerInterface;
use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;

final readonly class ComposerRunner implements ComposerProcessRunnerInterface
{
    public function __construct(
        private ExecutableFinderInterface $executables,
        private ProcessFactoryInterface $processes,
    ) {
    }

    public function run(
        array $command,
        ?PathInterface $cwd = null,
        array $env = [],
        ?OutputCallbackInterface $callback = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void {
        $process = $this->processes->create([\PHP_BINARY, $this->executables->find('composer'), ...$command], $cwd, $env);
        $process->setTimeout($timeout);
        $process->mustRun($callback);
    }
}
