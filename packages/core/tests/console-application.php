<?php

declare(strict_types=1);

use AdzerCMS\Core\Tests\ModuleSuite;
use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

ModuleSuite::environment('test');

return new Application(new Kernel('test', true));
