<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Tab;
use AdzerCMS\Core\Entity\Consent;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Setting\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

final class Consents implements Owner
{
    public const REVISION = 'legal.revision';
    public const OFFER_URL = 'legal.offer_url';
    public const PRIVACY_URL = 'legal.privacy_url';
    private const OFFER = 'offer';
    private const PRIVACY = 'privacy';

    public function __construct(
        private readonly Settings $settings,
        private readonly RequestStack $requests,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function settingFields(): array
    {
        $section = t('core.setting.section.legal');

        return [
            new Field(self::REVISION, t('core.setting.legal_revision'), section: $section, tab: Tab::General),
            new Field(self::OFFER_URL, t('core.setting.offer_url'), section: $section, tab: Tab::General),
            new Field(self::PRIVACY_URL, t('core.setting.privacy_url'), section: $section, tab: Tab::General),
        ];
    }

    public function revision(): string
    {
        $revision = trim($this->settings->get(self::REVISION));

        return '' === $revision ? now()->format('Y-m-d') : $revision;
    }

    /** @return array<string, string> */
    public function documents(): array
    {
        return array_filter([
            self::OFFER => trim($this->settings->get(self::OFFER_URL)),
            self::PRIVACY => trim($this->settings->get(self::PRIVACY_URL)),
        ]);
    }

    #[AsTwigFunction('legal', isSafe: ['html'])]
    public function legal(string $document, string $text): string
    {
        $url = $this->documents()[$document] ?? '';
        $name = htmlspecialchars($text, \ENT_QUOTES);

        return '' === $url
            ? $name
            : sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', htmlspecialchars($url, \ENT_QUOTES), $name);
    }

    public function accept(User $user): void
    {
        $this->em->persist(new Consent($user, ConsentKind::Offer, $this->revision(), $this->requests->getCurrentRequest()?->getClientIp()));
        $this->em->flush();
    }
}
