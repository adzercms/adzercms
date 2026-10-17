<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;

final readonly class Installed
{
    public Kind $kind;

    /** @var list<Kind> */
    public array $kinds;

    /**
     * @param class-string<Module> $bundle
     * @param list<string>         $requires
     * @param list<string>         $implementations
     * @param list<string>         $develops
     */
    public function __construct(
        public string $package,
        public string $key,
        public string $bundle,
        public array $requires,
        public array $implementations,
        public array $develops,
    ) {
        $this->kind = $bundle::kind();
        $this->kinds = $bundle::kinds();
    }
}
