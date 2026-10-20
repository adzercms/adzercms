<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    AdzerCMS\Core\CoreBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelayBundle::class => ['test' => true],
    AdzerCMS\Core\Tests\Notice\Fixture\Beacon\BeaconBundle::class => ['test' => true],
    AdzerCMS\Core\Tests\Notice\Fixture\Chime\ChimeBundle::class => ['test' => true],
    AdzerCMS\Core\Tests\Login\Fixture\Latch\LatchBundle::class => ['test' => true],
    AdzerCMS\Core\Tests\Login\Fixture\Wicket\WicketBundle::class => ['test' => true],
    AdzerCMS\Core\Tests\Analytics\Fixture\Tally\TallyBundle::class => ['test' => true],
];
