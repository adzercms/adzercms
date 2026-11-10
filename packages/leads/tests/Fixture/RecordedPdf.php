<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Tests\Fixture;

use AdzerCMS\Contracts\Ui\Pdf;

final class RecordedPdf implements Pdf
{
    public string $markup = '';

    public function __construct(private readonly Pdf $inner)
    {
    }

    public function file(string $html): string
    {
        $this->markup = $html;

        return $this->inner->file($html);
    }
}
