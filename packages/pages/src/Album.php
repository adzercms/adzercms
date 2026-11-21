<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface Album
{
    public function store(UploadedFile $picture): string;

    public function path(string $name): ?string;

    /** @return array{src: string, width: int, height: int}|null */
    public function shown(string $name): ?array;

    /** @return list<array{src: string, name: string}> */
    public function listed(): array;

    /** @return array{src: string, name: string} */
    public function entry(string $name): array;

    /** @param array<array-key, string> $values */
    public function drop(array $values): void;
}
