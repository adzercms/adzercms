<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Rule;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Contracts\Ui\Scheme;
use AdzerCMS\Core\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ResetInterface;

use function Symfony\Component\Translation\t;

final class Settings implements ResetInterface
{
    private const SECRET = 'secret:';
    private const TIME = '/^([01]?\d|2[0-3]):([0-5]\d)$/';
    private const COLOR = '/^#[0-9a-f]{6}$/i';

    /** @var array<string, string>|null */
    private ?array $stored = null;

    /** @param iterable<Rule> $rules */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Secrets $secrets,
        #[AutowireIterator(Rule::class)]
        private readonly iterable $rules,
    ) {
    }

    public function get(string $name): string
    {
        $value = $this->stored()[$name] ?? '';

        return str_starts_with($value, self::SECRET) ? $this->secrets->decrypt(substr($value, \strlen(self::SECRET))) : $value;
    }

    public function int(string $name, int $default): int
    {
        $value = $this->get($name);

        return '' === $value ? $default : (int) $value;
    }

    public function minutes(string $name): ?int
    {
        return self::time($this->get($name));
    }

    public function on(string $name): bool
    {
        return Toggle::On === Toggle::tryFrom($this->get($name));
    }

    public function moment(string $name): ?\DateTimeImmutable
    {
        $value = $this->get($name);
        $moment = '' === $value ? false : \DateTimeImmutable::createFromFormat(\DATE_ATOM, $value);

        return false === $moment ? null : $moment;
    }

    public function palette(Field $field): Scheme|string
    {
        $value = $this->get($field->name);

        return self::scheme($field, $value) ?? (1 === preg_match(self::COLOR, $value) ? $value : $field->schemes[0] ?? '');
    }

    public function stamp(Field $field, \DateTimeImmutable $moment): void
    {
        $this->save($field, $moment->format(\DATE_ATOM));
    }

    public function save(Field $field, string $value): ?Refusal
    {
        $value = trim($value);
        $secret = Type::Secret === $field->type;
        if ('' === $value) {
            if (!$secret) {
                $this->clear($field);
            }

            return null;
        }

        if (Type::Time === $field->type) {
            $minutes = self::time($value);
            if (null === $minutes) {
                return new Refusal(t('core.setting.time', ['field' => $field->label]));
            }
            $value = \sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        if (Type::Toggle === $field->type && null === Toggle::tryFrom($value)) {
            return new Refusal(t('core.setting.toggle', ['field' => $field->label]));
        }

        if (Type::Choice === $field->type && !isset($field->choices[$value])) {
            return new Refusal(t('core.setting.choice', ['field' => $field->label]));
        }

        if (Type::Color === $field->type || (Type::Palette === $field->type && null === self::scheme($field, $value))) {
            if (1 !== preg_match(self::COLOR, $value)) {
                return new Refusal(t(Type::Color === $field->type ? 'core.setting.color' : 'core.setting.choice', ['field' => $field->label]));
            }
            $value = strtolower($value);
        }

        if (Type::Decimal === $field->type) {
            $value = str_replace(',', '.', $value);
            if (!is_numeric($value)) {
                return new Refusal(t('core.setting.decimal', ['field' => $field->label]));
            }
        }

        foreach ($this->rules as $rule) {
            $refusal = $rule->check($field, $value);
            if (null !== $refusal) {
                return $refusal;
            }
        }

        $stored = $secret ? self::SECRET.$this->secrets->encrypt($value) : $value;
        $setting = $this->em->find(Setting::class, $field->name);
        null === $setting ? $this->em->persist(new Setting($field->name, $stored)) : $setting->setValue($stored);

        $this->stored();
        $this->stored[$field->name] = $stored;

        return null;
    }

    public function clear(Field $field): void
    {
        $setting = $this->em->find(Setting::class, $field->name);
        if (null !== $setting) {
            $this->em->remove($setting);
        }

        $this->stored();
        unset($this->stored[$field->name]);
    }

    public function reset(): void
    {
        $this->stored = null;
    }

    private static function time(string $value): ?int
    {
        return 1 === preg_match(self::TIME, $value, $time) ? (int) $time[1] * 60 + (int) $time[2] : null;
    }

    private static function scheme(Field $field, string $key): ?Scheme
    {
        return array_find($field->schemes, static fn (Scheme $scheme): bool => $scheme->key === $key);
    }

    /** @return array<string, string> */
    private function stored(): array
    {
        return $this->stored ??= $this->em->getConnection()->fetchAllKeyValue(\sprintf('SELECT name, value FROM %s', Setting::TABLE));
    }
}
