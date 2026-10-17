<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Setting\Images;
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
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument('Setting key; lists all settings without it')] ?string $name = null,
        #[Argument('Value, a file path for an image; an empty value removes the setting')] ?string $value = null,
        #[Option('Read the value from stdin, the only way for a secret')] bool $stdin = false,
    ): int {
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
            $io->error(\sprintf('setting "%s" does not exist, available: %s', $name, implode(', ', array_keys($fields))));

            return Command::INVALID;
        }

        $secret = Type::Secret === $field->type;
        if ($secret && null !== $value) {
            $io->error(\sprintf('secret "%s" is accepted only from stdin: --stdin', $name));

            return Command::INVALID;
        }

        if ($stdin) {
            $value = rtrim(Body::of($input), "\r\n");
        }

        if (null === $value) {
            $io->writeln($secret ? $this->shown($field) : $this->settings->get($name));

            return Command::SUCCESS;
        }

        $refusal = $this->write($field, $value);
        if (null !== $refusal) {
            $io->error($refusal->reason->trans($this->translator));

            return Command::INVALID;
        }

        $io->success(\sprintf('%s: %s', $name, $this->shown($field)));

        return Command::SUCCESS;
    }

    private function write(Field $field, string $value): ?Refusal
    {
        if (Type::Image !== $field->type) {
            $refusal = $this->settings->save($field, $value);
            $this->em->flush();

            return $refusal;
        }

        if ('' === $value) {
            $this->images->clear($field);

            return null;
        }

        return is_file($value) ? $this->images->store($field, new File($value)) : new Refusal(t('core.setting.no_file', ['path' => $value]));
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
