<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class Tree
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $root,
    ) {
    }

    public function versioned(): bool
    {
        for ($directory = $this->root;; $directory = \dirname($directory)) {
            if (file_exists($directory.'/.git')) {
                return true;
            }
            if (\dirname($directory) === $directory) {
                return false;
            }
        }
    }
}
