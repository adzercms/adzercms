<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Tab;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Contracts\Ui\Palette;
use AdzerCMS\Contracts\Ui\Scheme;
use AdzerCMS\Core\Account\Theme;
use AdzerCMS\Core\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class Brand implements Owner
{
    public const TITLE = 'brand.title';
    public const LOGO = 'brand.logo';
    public const LOGO_DARK = 'brand.logo_dark';
    public const ICON = 'brand.icon';
    public const THEME = 'brand.theme';
    public const LAYOUT = 'brand.layout';
    public const COLOR = 'brand.color';

    private readonly ?Palette $palette;

    /** @param iterable<Palette> $palettes */
    public function __construct(
        private readonly Settings $settings,
        private readonly Images $images,
        private readonly UrlGeneratorInterface $urls,
        private readonly Security $security,
        #[AutowireIterator(Palette::class)]
        iterable $palettes,
    ) {
        $this->palette = [...$palettes][0] ?? null;
    }

    public function settingFields(): array
    {
        return [
            new Field(self::TITLE, t('core.setting.title'), tab: Tab::Brand),
            self::logoField(),
            self::darkLogoField(),
            self::iconField(),
            new Field(self::THEME, t('core.setting.theme'), Type::Choice, choices: self::themes(), tab: Tab::Brand),
            new Field(self::LAYOUT, t('core.setting.layout'), Type::Choice, choices: self::choices(Layout::cases()), tab: Tab::Brand),
            ...null === $this->palette ? [] : [self::colorField($this->palette)],
        ];
    }

    /** @return array<string, TranslatableInterface> */
    public static function themes(): array
    {
        return self::choices(Theme::cases());
    }

    public function title(): string
    {
        return $this->settings->get(self::TITLE);
    }

    public function logo(?Theme $theme = null): ?string
    {
        return (Theme::Dark === $theme ? $this->image(self::darkLogoField()) : null) ?? $this->image(self::logoField());
    }

    public function icon(): ?string
    {
        return $this->image(self::iconField());
    }

    public function theme(): Theme
    {
        $user = $this->security->getUser();

        return ($user instanceof User ? $user->getTheme() : null) ?? Theme::tryFrom($this->settings->get(self::THEME)) ?? Theme::Light;
    }

    public function layout(): Layout
    {
        return Layout::tryFrom($this->settings->get(self::LAYOUT)) ?? Layout::Side;
    }

    public function color(): ?string
    {
        $picked = null === $this->palette ? null : $this->settings->palette(self::colorField($this->palette));

        return $picked instanceof Scheme ? $picked->color : $picked;
    }

    private function image(Field $field): ?string
    {
        return null === $this->images->path($field)
            ? null
            : $this->urls->generate('setting_image', ['name' => $field->name], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @param list<Theme|Layout> $cases
     *
     * @return array<string, TranslatableInterface>
     */
    private static function choices(array $cases): array
    {
        return array_combine(
            array_map(static fn (Theme|Layout $case): string => $case->value, $cases),
            array_map(static fn (Theme|Layout $case): TranslatableInterface => $case->label(), $cases),
        );
    }

    private static function logoField(): Field
    {
        return new Field(self::LOGO, t('core.setting.logo'), Type::Image, tab: Tab::Brand);
    }

    private static function darkLogoField(): Field
    {
        return new Field(self::LOGO_DARK, t('core.setting.logo_dark'), Type::Image, tab: Tab::Brand);
    }

    private static function iconField(): Field
    {
        return new Field(self::ICON, t('core.setting.icon'), Type::Image, tab: Tab::Brand);
    }

    private static function colorField(Palette $palette): Field
    {
        return new Field(self::COLOR, t('core.setting.brand_color'), Type::Palette, tab: Tab::Brand, schemes: $palette->schemes());
    }
}
