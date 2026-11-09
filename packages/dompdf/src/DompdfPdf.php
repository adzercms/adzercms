<?php

declare(strict_types=1);

namespace AdzerCMS\Dompdf;

use AdzerCMS\Contracts\Ui\Pdf;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

#[AsAlias]
final readonly class DompdfPdf implements Pdf
{
    private const PAPER = 'a4';
    private const PROTOCOLS = ['data://' => ['rules' => []]];

    public function __construct(
        #[Autowire('%kernel.cache_dir%/dompdf')]
        private string $cacheDir,
    ) {
    }

    public function file(string $html): string
    {
        (new Filesystem())->mkdir($this->cacheDir);
        $pdf = new Dompdf(new Options([
            'tempDir' => $this->cacheDir,
            'fontDir' => $this->cacheDir,
            'fontCache' => $this->cacheDir,
            'chroot' => [$this->cacheDir],
            'allowedProtocols' => self::PROTOCOLS,
            'isRemoteEnabled' => false,
            'defaultPaperSize' => self::PAPER,
        ]));
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        return (string) $pdf->output();
    }
}
