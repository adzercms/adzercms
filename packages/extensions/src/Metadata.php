<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use AdzerCMS\Core\Composer\Lookup;
use AdzerCMS\Core\Composer\Repositories;
use AdzerCMS\Extensions\Entity\Extension;
use Doctrine\ORM\EntityManagerInterface;

final readonly class Metadata
{
    public function __construct(
        private Repositories $repositories,
        private ExtensionsSettings $settings,
        private EntityManagerInterface $em,
    ) {
    }

    /** @param list<string> $names */
    public function lookup(array $names): Lookup
    {
        return $this->repositories->lookup($this->settings->repositories(), $names);
    }

    /** @param list<Extension> $extensions */
    public function refresh(array $extensions): Lookup
    {
        $lookup = $this->lookup(array_map(static fn (Extension $extension): string => $extension->getName(), $extensions));

        foreach ($extensions as $extension) {
            if ($lookup->decided($extension->getName())) {
                $extension->know($lookup->packages[$extension->getName()]);
            }
        }

        $this->em->flush();

        return $lookup;
    }
}
