<?php

declare(strict_types=1);

namespace AdzerCMS\Dompdf\Tests;

use AdzerCMS\Dompdf\DompdfPdf;
use PHPUnit\Framework\TestCase;

final class DompdfPdfTest extends TestCase
{
    public function testSheetOfTheFileComesFromThePageRuleOfTheMarkup(): void
    {
        $file = (new DompdfPdf(sys_get_temp_dir().'/dompdf-'.getmypid()))->file(
            '<html><head><style>@page { size: A5 landscape; margin: 10mm; }</style></head><body><p>Счёт № 1</p></body></html>',
        );

        self::assertStringStartsWith('%PDF-', $file);
        self::assertMatchesRegularExpression('~/MediaBox \[0\.0+ 0\.0+ 595\.2\d* 419\.5\d*\]~', $file);
    }
}
