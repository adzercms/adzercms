<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

final class QrImage
{
    private const SIZE = 260;

    public function dataUri(string $payload): string
    {
        return (new Builder(writer: new SvgWriter(), data: $payload, size: self::SIZE, margin: 0))->build()->getDataUri();
    }
}
