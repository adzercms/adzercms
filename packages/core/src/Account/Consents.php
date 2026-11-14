<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Page;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Entity\Consent;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Setting\Values;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

final class Consents implements Owner
{
    public const EDITIONS = 'legal.editions';
    public const OFFER_URL = 'legal.offer_url';
    public const PRIVACY_URL = 'legal.privacy_url';
    public const EFFECTIVE = 'effective';
    public const PUBLISHED = 'published';
    private const OFFER = 'offer';
    private const PRIVACY = 'privacy';

    public function __construct(
        private readonly Settings $settings,
        private readonly RequestStack $requests,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function settingFields(): array
    {
        return [
            self::editionsField(),
            new Field(self::OFFER_URL, t('core.setting.offer_url'), section: self::section(), page: Page::General),
            new Field(self::PRIVACY_URL, t('core.setting.privacy_url'), section: self::section(), page: Page::General),
        ];
    }

    public function revision(): string
    {
        $today = now()->format(Values::DATE);

        return self::current($this->editions(), $today)[self::EFFECTIVE] ?? $today;
    }

    public function edition(): string
    {
        $today = now()->format(Values::DATE);
        $editions = $this->editions();
        $current = self::current($editions, $today);
        $upcoming = array_find($editions, static fn (array $edition): bool => $edition[self::EFFECTIVE] > $today && $edition[self::PUBLISHED] <= $today);

        return implode(' ', array_filter([
            null === $current ? '' : $this->translator->trans('core.legal.edition', ['effective' => Values::day($current[self::EFFECTIVE])]),
            null === $upcoming ? '' : $this->translator->trans('core.legal.upcoming', [
                'published' => Values::day($upcoming[self::PUBLISHED]),
                'effective' => Values::day($upcoming[self::EFFECTIVE]),
            ]),
        ]));
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

    /** @return list<array{effective: string, published: string}> */
    private function editions(): array
    {
        $editions = [];
        foreach (Values::entries(self::editionsField(), $this->settings->get(self::EDITIONS)) as $row) {
            if (null !== Values::day($row[self::EFFECTIVE])) {
                $editions[] = [self::EFFECTIVE => $row[self::EFFECTIVE], self::PUBLISHED => '' === $row[self::PUBLISHED] ? $row[self::EFFECTIVE] : $row[self::PUBLISHED]];
            }
        }
        usort($editions, static fn (array $one, array $other): int => $one[self::EFFECTIVE] <=> $other[self::EFFECTIVE]);

        return $editions;
    }

    /**
     * @param list<array{effective: string, published: string}> $editions
     *
     * @return array{effective: string, published: string}|null
     */
    private static function current(array $editions, string $today): ?array
    {
        return array_find(array_reverse($editions), static fn (array $edition): bool => $edition[self::EFFECTIVE] <= $today);
    }

    private static function editionsField(): Field
    {
        return new Field(self::EDITIONS, t('core.setting.legal_editions'), Type::Rows, section: self::section(), page: Page::General, fields: [
            new Field(self::EFFECTIVE, t('core.setting.legal_effective'), Type::Date, required: true),
            new Field(self::PUBLISHED, t('core.setting.legal_published'), Type::Date),
        ]);
    }

    private static function section(): TranslatableInterface
    {
        return t('core.setting.section.legal');
    }
}
