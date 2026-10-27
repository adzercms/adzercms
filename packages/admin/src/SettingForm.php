<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Contracts\Ui\Badge;
use AdzerCMS\Contracts\Ui\Badged;
use AdzerCMS\Contracts\Ui\Scheme;
use AdzerCMS\Core\Setting\Images;
use AdzerCMS\Core\Setting\Keychain;
use AdzerCMS\Core\Setting\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class SettingForm
{
    private const DROPPED = [Type::Image, Type::Container, Type::Palette];

    /** @var list<TranslatableInterface> */
    private array $faults = [];

    public function __construct(
        private readonly Settings $settings,
        private readonly Images $images,
        private readonly Keychain $keychain,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return list<TranslatableInterface> */
    public function faults(): array
    {
        return $this->faults;
    }

    /** @param list<Field> $fields */
    public function save(array $fields, Request $request): bool
    {
        $this->faults = [];
        $dropped = $request->request->all('drop');
        $values = $request->request->all('value');
        $passwords = $request->request->all('pass');
        $files = $request->files->all('file');
        $carded = self::carded($fields);

        foreach ($fields as $field) {
            if (\in_array($field->name, $carded, true)) {
                continue;
            }

            if (isset($dropped[$field->name])) {
                match ($field->type) {
                    Type::Image => $this->images->clear($field),
                    Type::Container => $this->keychain->clear($field),
                    default => $this->settings->clear($field),
                };

                continue;
            }

            if (Type::Image !== $field->type && Type::Container !== $field->type) {
                $value = $values[$field->name] ?? null;
                $this->store($field, \is_string($value) ? $value : null);

                continue;
            }

            $upload = $files[$field->name] ?? null;
            if (!$upload instanceof UploadedFile || !$upload->isValid()) {
                continue;
            }

            $password = $passwords[$field->name] ?? '';
            $refusal = Type::Image === $field->type
                ? $this->images->store($field, $upload)
                : $this->keychain->store($field, $upload, \is_string($password) ? $password : '');
            if (null !== $refusal) {
                $this->faults[] = $refusal->reason;
            }
        }

        $this->em->flush();

        return [] === $this->faults;
    }

    public function store(Field $field, ?string $value): bool
    {
        if (null === $value) {
            return true;
        }

        $refusal = $this->settings->save($field, $value);
        if (null !== $refusal) {
            $this->faults[] = $refusal->reason;

            return false;
        }

        return true;
    }

    /**
     * @param list<Field> $fields
     *
     * @return list<array{section: ?TranslatableInterface, fields: list<Field>, credentials: array<string, Badged>, stored: list<string>}>
     */
    public function sections(array $fields): array
    {
        $labels = [null];
        $grouped = [[]];
        $credentials = [[]];
        $stored = [[]];
        $carded = self::carded($fields);

        foreach ($fields as $field) {
            $at = array_search($field->section, $labels, false);
            if (false === $at) {
                $labels[] = $field->section;
                $at = array_key_last($labels);
                $grouped[$at] = [];
                $credentials[$at] = [];
                $stored[$at] = [];
            }

            if (!\in_array($field->name, $carded, true)) {
                $grouped[$at][] = $field;
                if (\in_array($field->type, self::DROPPED, true) && '' !== $this->settings->get($field->name)) {
                    $stored[$at][] = $field->name;
                }
            } elseif (null !== $field->group && !isset($credentials[$at][$field->group])) {
                $credentials[$at][$field->group] = $this->filled(self::group($fields, $field->group))
                    ? Badge::good(t('admin.setting.set'))
                    : Badge::idle(t('admin.setting.unset'));
            }
        }

        return array_values(array_filter(array_map(
            static fn (?TranslatableInterface $section, array $fields, array $badges, array $kept): array => ['section' => $section, 'fields' => $fields, 'credentials' => $badges, 'stored' => $kept],
            $labels,
            $grouped,
            $credentials,
            $stored,
        ), static fn (array $section): bool => [] !== $section['fields'] || [] !== $section['credentials']));
    }

    /**
     * @param list<Field> $fields
     *
     * @return array<string, string>
     */
    public function values(array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $picked = Type::Palette === $field->type ? $this->settings->palette($field) : null;
            $values[$field->name] = match (true) {
                Type::Secret === $field->type => '',
                $picked instanceof Scheme => $picked->key,
                \is_string($picked) => $picked,
                default => $this->settings->get($field->name),
            };
        }

        return $values;
    }

    /** @param list<Field> $fields */
    public function filled(array $fields): bool
    {
        foreach ($fields as $field) {
            if ('' !== $this->settings->get($field->name)) {
                return true;
            }
        }

        return false;
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function clear(Field $field): void
    {
        $this->settings->clear($field);
    }

    /**
     * @param list<Field> $fields
     *
     * @return list<Field>
     */
    private static function group(array $fields, string $group): array
    {
        return array_values(array_filter($fields, static fn (Field $field): bool => $group === $field->group));
    }

    /**
     * @param list<Field> $fields
     *
     * @return list<string>
     */
    private static function carded(array $fields): array
    {
        $names = [];

        foreach ($fields as $field) {
            if (null === $field->group) {
                continue;
            }
            $group = self::group($fields, $field->group);
            if ([] !== array_filter($group, static fn (Field $member): bool => Type::Secret === $member->type)) {
                $names[] = $field->name;
            }
        }

        return $names;
    }
}
