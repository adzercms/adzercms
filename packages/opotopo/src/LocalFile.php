<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use Opotopo\SellerApi\Files\FileSource;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Mime\MimeTypes;

final readonly class LocalFile implements FileSource
{
    private const SOURCE = 'file:';
    private const UNKNOWN = 'application/octet-stream';

    private string $path;

    public function __construct(
        private string $root,
        private string $relative,
    ) {
        $this->path = Path::join($root, $relative);
    }

    public function source(): string
    {
        return self::SOURCE.$this->relative;
    }

    public function exists(): bool
    {
        return Path::isBasePath($this->root, $this->path) && is_file($this->path);
    }

    public function name(): string
    {
        return basename($this->path);
    }

    public function size(): int
    {
        return (int) filesize($this->path);
    }

    public function modifiedAt(): int
    {
        return (int) filemtime($this->path);
    }

    public function mimeType(): string
    {
        return MimeTypes::getDefault()->guessMimeType($this->path) ?? self::UNKNOWN;
    }

    public function stream()
    {
        return fopen($this->path, 'r') ?: throw new \RuntimeException(\sprintf('file %s is not readable', $this->path));
    }
}
