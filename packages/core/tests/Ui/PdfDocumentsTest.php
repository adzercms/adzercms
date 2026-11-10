<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui;

use AdzerCMS\Core\Ui\PdfDocuments;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class PdfDocumentsTest extends TestCase
{
    #[Group('ACMS-UI-0146')]
    public function testFileNameComesWithItsAsciiTransliteration(): void
    {
        $name = 'Счёт № 2026-00001.pdf';
        $response = PdfDocuments::response('%PDF-', $name, HeaderUtils::DISPOSITION_ATTACHMENT);

        self::assertSame(PdfDocuments::MIME, $response->headers->get('Content-Type'));
        self::assertSame(
            [[HeaderUtils::DISPOSITION_ATTACHMENT], ['filename', 'Scet No 2026-00001.pdf'], ['filename*', "utf-8''".rawurlencode($name)]],
            HeaderUtils::split((string) $response->headers->get('Content-Disposition'), ';='),
        );
    }
}
