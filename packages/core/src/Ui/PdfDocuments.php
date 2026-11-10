<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Ui\Pdf;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class PdfDocuments
{
    public const MIME = 'application/pdf';

    private const ASCII = 'Any-Latin; Latin-ASCII; [^ -~] Remove';
    private const UNSAFE = ['%', '/', '\\'];

    public function __construct(
        private Environment $twig,
        private ?Pdf $pdf = null,
    ) {
    }

    public function available(): bool
    {
        return null !== $this->pdf;
    }

    /** @param array<string, mixed> $context */
    public function file(string $template, array $context): string
    {
        $pdf = $this->pdf ?? throw new \LogicException('no module of the PDF document kind is selected');

        return $pdf->file($this->twig->render($template, $context));
    }

    public static function response(string $file, string $name, string $disposition = HeaderUtils::DISPOSITION_INLINE): Response
    {
        $response = new Response($file, Response::HTTP_OK, ['Content-Type' => self::MIME]);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            $disposition,
            $name,
            str_replace(self::UNSAFE, '-', (string) transliterator_transliterate(self::ASCII, $name)),
        ));

        return $response;
    }
}
