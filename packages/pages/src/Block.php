<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Translation\TranslatableInterface;

#[AutoconfigureTag]
interface Block
{
    public const TITLE = 'title';

    public function key(): string;

    public function label(): TranslatableInterface;

    /** @return list<Field> */
    public function fields(): array;

    public function template(): string;

    /**
     * @param array<string, string> $values
     *
     * @return array<string, mixed>
     */
    public function data(array $values): array;

    /**
     * @param array<string, string> $values
     *
     * @return array<string, mixed>|null
     */
    public function schema(array $values): ?array;

    /** @return list<class-string> */
    public function sources(): array;

    public function check(Field $field, string $value): ?Refusal;
}
