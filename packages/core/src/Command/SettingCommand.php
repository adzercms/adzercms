<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Setting\Images;
use AdzerCMS\Core\Setting\Keychain;
use AdzerCMS\Core\Setting\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

#[AsCommand(name: 'adzercms:setting', description: 'Instance settings: list, value and change by key')]
final class SettingCommand
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly Settings $settings,
        private readonly Images $images,
        private readonly Keychain $keychain,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument('Setting key; lists all settings without it')] ?string $name = null,
        #[Argument('Value, a file path for an image or a PKCS#12 container; an empty value removes the setting')] ?string $value = null,
        #[Option('Read the value from stdin, the only way for a secret; a container takes its password from stdin')] bool $stdin = false,
        #[Option('Print all stored rows as JSON, secrets encrypted by the instance key')] bool $export = false,
        #[Option('Write stored rows as JSON from stdin over the present ones')] bool $import = false,
    ): int {
        if ($export || $import) {
            return null === $name && !$stdin && !($export && $import) ? $this->carry($input, $io, $import) : $this->refuse($io, 'export and import go alone');
        }

        $fields = $this->catalog->fields();

        if (null === $name) {
            $io->table(['Key', 'Setting', 'Value'], array_map(
                fn (Field $field): array => [$field->name, $field->label->trans($this->translator), $this->shown($field)],
                array_values($fields),
            ));

            return Command::SUCCESS;
        }

        $field = $fields[$name] ?? null;
        if (null === $field) {
            return $this->refuse($io, \sprintf('setting "%s" does not exist, available: %s', $name, implode(', ', array_keys($fields))));
        }

        $secret = Type::Secret === $field->type;
        if ($secret && null !== $value) {
            return $this->refuse($io, \sprintf('secret "%s" is accepted only from stdin: --stdin', $name));
        }

        $container = Type::Container === $field->type;
        if ($container && '' !== (string) $value && !$stdin) {
            return $this->refuse($io, \sprintf('container "%s" password is accepted only from stdin: --stdin', $name));
        }

        $piped = $stdin ? rtrim(Body::of($input), "\r\n") : '';
        if ($stdin && !$container) {
            $value = $piped;
        }

        if (null === $value) {
            $io->writeln($secret ? $this->shown($field) : $this->settings->get($name));

            return Command::SUCCESS;
        }

        $refusal = $this->write($field, $value, $piped);
        if (null !== $refusal) {
            return $this->refuse($io, $refusal->reason->trans($this->translator));
        }

        $io->success(\sprintf('%s: %s', $name, $this->shown($field)));

        return Command::SUCCESS;
    }

    private function carry(InputInterface $input, SymfonyStyle $io, bool $import): int
    {
        if (!$import) {
            $io->writeln(json_encode($this->settings->rows(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        try {
            $rows = json_decode(Body::of($input), true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $failure) {
            return $this->refuse($io, 'stdin is no JSON: '.$failure->getMessage());
        }

        if (!\is_array($rows) || array_is_list($rows) && [] !== $rows || [] !== array_filter($rows, static fn (mixed $value): bool => !\is_string($value))) {
            return $this->refuse($io, 'stdin is no JSON object of string values');
        }

        /** @var array<string, string> $rows */
        $damaged = $this->settings->restore($rows);
        if ([] !== $damaged) {
            return $this->refuse($io, 'secrets not decrypted by the instance key: '.implode(', ', $damaged));
        }

        $this->em->flush();
        $io->success(\sprintf('%d settings written', \count($rows)));

        return Command::SUCCESS;
    }

    private function refuse(SymfonyStyle $io, string $reason): int
    {
        $io->error($reason);

        return Command::INVALID;
    }

    private function write(Field $field, string $value, string $password): ?Refusal
    {
        if (Type::Image !== $field->type && Type::Container !== $field->type) {
            $refusal = $this->settings->save($field, $value);
            $this->em->flush();

            return $refusal;
        }

        if ('' === $value) {
            Type::Image === $field->type ? $this->images->clear($field) : $this->keychain->clear($field);

            return null;
        }

        if (!is_file($value)) {
            return new Refusal(t('core.setting.no_file', ['path' => $value]));
        }

        return Type::Image === $field->type ? $this->images->store($field, new File($value)) : $this->keychain->store($field, new File($value), $password);
    }

    private function shown(Field $field): string
    {
        $value = $this->settings->get($field->name);

        return match (true) {
            '' === $value => '—',
            Type::Secret === $field->type => 'set',
            default => $value,
        };
    }
}
