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
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ResetInterface;

final class Settings implements ResetInterface
{
    private const SECRET = 'secret:';

    /** @var array<string, string>|null */
    private ?array $stored = null;

    /** @param iterable<Rule> $rules */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Secrets $secrets,
        #[AutowireIterator(Rule::class)]
        private readonly iterable $rules,
        private readonly EventDispatcherInterface $events,
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

    /** @return list<string> */
    public function lines(string $name): array
    {
        $value = $this->get($name);

        return '' === $value ? [] : explode("\n", $value);
    }

    /** @return list<array<string, string>> */
    public function entries(Field $field): array
    {
        return Values::entries($field, $this->get($field->name));
    }

    public function minutes(string $name): ?int
    {
        return Values::minutes($this->get($name));
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

        return Values::scheme($field, $value) ?? Values::color($value) ?? $field->schemes[0] ?? '';
    }

    public function stamp(Field $field, \DateTimeImmutable $moment): void
    {
        $this->save($field, $moment->format(\DATE_ATOM));
    }

    public function save(Field $field, string $value): ?Refusal
    {
        $value = Values::check($field, $value);
        if ($value instanceof Refusal) {
            return $value;
        }

        $secret = Type::Secret === $field->type;
        if ('' === $value) {
            if (!$secret) {
                $this->clear($field);
            }

            return null;
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
        $this->events->dispatch(new SettingSaved($field->name));

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
        $this->events->dispatch(new SettingSaved($field->name));
    }

    /** @return array<string, string> */
    public function rows(): array
    {
        return $this->stored();
    }

    /**
     * @param array<string, string> $rows
     *
     * @return list<string>
     */
    public function restore(array $rows): array
    {
        $damaged = array_keys(array_filter($rows, fn (string $value): bool => !$this->decrypted($value)));
        if ([] !== $damaged) {
            return $damaged;
        }

        $this->stored = [...$this->stored(), ...$rows];
        foreach ($rows as $name => $value) {
            $setting = $this->em->find(Setting::class, $name);
            null === $setting ? $this->em->persist(new Setting($name, $value)) : $setting->setValue($value);
            $this->events->dispatch(new SettingSaved($name));
        }

        return [];
    }

    public function reset(): void
    {
        $this->stored = null;
    }

    private function decrypted(string $value): bool
    {
        if (!str_starts_with($value, self::SECRET)) {
            return true;
        }

        try {
            $this->secrets->decrypt(substr($value, \strlen(self::SECRET)));
        } catch (\RuntimeException|\SodiumException) {
            return false;
        }

        return true;
    }

    /** @return array<string, string> */
    private function stored(): array
    {
        return $this->stored ??= $this->em->getConnection()->fetchAllKeyValue(\sprintf('SELECT name, value FROM %s', Setting::TABLE));
    }
}
