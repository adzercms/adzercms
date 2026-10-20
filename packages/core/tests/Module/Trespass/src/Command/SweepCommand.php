<?php

declare(strict_types=1);

namespace AdzerCMS\Trespass\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'adzercms:sweep')]
final class SweepCommand extends Command
{
}
