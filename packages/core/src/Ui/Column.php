<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class Column
{
    public const DAY = 'd.m.Y';
    public const TIME = 'H:i';

    private static ?TranslatorInterface $translator = null;

    /** @param list<string> $fields */
    private function __construct(
        public readonly string $key,
        public readonly ?TranslatableInterface $label,
        public readonly array $fields,
        public readonly ?\Closure $value,
        public readonly bool $secondary = false,
    ) {
    }

    public static function plain(string $key, ?TranslatableInterface $label = null): self
    {
        return new self($key, $label, [], null);
    }

    public static function of(string $key, TranslatableInterface $label, string ...$fields): self
    {
        return new self($key, $label, array_values($fields), null);
    }

    public static function live(string $key, TranslatableInterface $label, \Closure $value): self
    {
        return new self($key, $label, [], $value);
    }

    public function with(\Closure $value): self
    {
        return new self($this->key, $this->label, $this->fields, $value, $this->secondary);
    }

    public function aside(): self
    {
        return new self($this->key, $this->label, $this->fields, $this->value, true);
    }

    public function text(mixed $row): string
    {
        return null === $this->value ? '' : self::scalar(($this->value)($row));
    }

    public static function translateWith(TranslatorInterface $translator): void
    {
        self::$translator = $translator;
    }

    public static function scalar(mixed $value): string
    {
        return match (true) {
            null === $value => '',
            $value instanceof TranslatableInterface => null === self::$translator ? '' : $value->trans(self::$translator),
            \is_bool($value) => $value ? '1' : '',
            $value instanceof \DateTimeInterface => $value->format(self::DAY.' '.self::TIME),
            \is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
