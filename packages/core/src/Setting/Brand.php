<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Account\Theme;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class Brand implements Owner
{
    public const TITLE = 'brand.title';
    public const LOGO = 'brand.logo';
    public const ICON = 'brand.icon';
    public const THEME = 'brand.theme';

    public function __construct(
        private readonly Settings $settings,
        private readonly Images $images,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function settingFields(): array
    {
        return [
            new Field(self::TITLE, t('core.setting.title')),
            self::logoField(),
            self::iconField(),
            new Field(self::THEME, t('core.setting.theme'), Type::Choice, choices: self::themes()),
        ];
    }

    /** @return array<string, TranslatableInterface> */
    public static function themes(): array
    {
        return array_combine(
            array_column(Theme::cases(), 'value'),
            array_map(static fn (Theme $theme): TranslatableInterface => $theme->label(), Theme::cases()),
        );
    }

    public function title(): string
    {
        return $this->settings->get(self::TITLE);
    }

    public function logo(): ?string
    {
        return $this->image(self::logoField());
    }

    public function icon(): ?string
    {
        return $this->image(self::iconField());
    }

    public function theme(?Theme $chosen = null): Theme
    {
        return $chosen ?? Theme::tryFrom($this->settings->get(self::THEME)) ?? Theme::Light;
    }

    private function image(Field $field): ?string
    {
        return null === $this->images->path($field)
            ? null
            : $this->urls->generate('setting_image', ['name' => $field->name], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private static function logoField(): Field
    {
        return new Field(self::LOGO, t('core.setting.logo'), Type::Image);
    }

    private static function iconField(): Field
    {
        return new Field(self::ICON, t('core.setting.icon'), Type::Image);
    }
}
