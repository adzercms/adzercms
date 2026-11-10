<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Ui\PdfDocuments;
use AdzerCMS\Leads\Entity\Lead;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Clock\now;

final readonly class Offer
{
    private const TEMPLATE = '@Leads/offer.html.twig';

    public function __construct(
        private PdfDocuments $documents,
        private Brand $brand,
        private Subjects $subjects,
        private LeadSettings $settings,
        private TranslatorInterface $translator,
    ) {
    }

    public function available(): bool
    {
        return $this->documents->available();
    }

    public function response(Lead $lead): Response
    {
        $today = now()->setTime(0, 0);

        return PdfDocuments::response($this->documents->file(self::TEMPLATE, [
            'lead' => $lead,
            'date' => $today,
            'until' => $today->modify(\sprintf('+%d days', $this->settings->offerDays())),
            'details' => $this->subjects->details($lead),
            'logo' => $this->brand->paperLogo(),
            'title' => $this->brand->title(),
            'titled' => $this->brand->titled(),
        ]), $this->translator->trans('leads.offer.file', ['number' => $lead->getId()]));
    }
}
