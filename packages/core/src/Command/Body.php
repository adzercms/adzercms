<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;

final class Body
{
    public static function of(InputInterface $input): string
    {
        $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;

        return (string) stream_get_contents($stream ?? \STDIN);
    }
}
