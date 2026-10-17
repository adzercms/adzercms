<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

use function Symfony\Component\Translation\t;

final class Images
{
    private const TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/svg+xml' => 'svg',
        'image/webp' => 'webp',
        'image/vnd.microsoft.icon' => 'ico',
    ];
    private const MAX_BYTES = 512000;

    public function __construct(
        private readonly Settings $settings,
        private readonly EntityManagerInterface $em,
        #[Autowire('%adzercms.files_dir%/settings')]
        private readonly string $directory,
    ) {
    }

    public function store(Field $field, File $file): ?Refusal
    {
        $extension = self::TYPES[(string) $file->getMimeType()] ?? null;
        if (null === $extension) {
            return new Refusal(t('core.image.type', ['field' => $field->label]));
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return new Refusal(t('core.image.size', ['field' => $field->label, 'kb' => intdiv(self::MAX_BYTES, 1024)]));
        }

        $this->drop($field);
        $name = $field->name.'.'.$extension;
        (new Filesystem())->copy($file->getPathname(), $this->directory.'/'.$name);
        $this->settings->save($field, $name);
        $this->em->flush();

        return null;
    }

    public function clear(Field $field): void
    {
        $this->drop($field);
        $this->settings->clear($field);
        $this->em->flush();
    }

    public function path(Field $field): ?string
    {
        $name = $this->settings->get($field->name);

        return '' !== $name && is_file($this->directory.'/'.$name) ? $this->directory.'/'.$name : null;
    }

    private function drop(Field $field): void
    {
        $path = $this->path($field);
        if (null !== $path) {
            (new Filesystem())->remove($path);
        }
    }
}
