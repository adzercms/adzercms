<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Photo\Photos;
use Doctrine\ORM\EntityManagerInterface;
use enshrined\svgSanitize\Sanitizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

use function Symfony\Component\Translation\t;

final class Images
{
    public const TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/svg+xml' => 'svg',
        'image/webp' => 'webp',
        'image/vnd.microsoft.icon' => 'ico',
    ];
    private const MAX_BYTES = 512000;
    private const PLACE = 'settings';
    private const SIDE = 1920;
    private const THEMED = '/currentColor|var\(--/i';

    private readonly string $directory;

    public function __construct(
        private readonly Settings $settings,
        private readonly EntityManagerInterface $em,
        private readonly Photos $photos,
        #[Autowire('%adzercms.files_dir%')]
        string $files,
    ) {
        $this->directory = $files.'/'.self::PLACE;
    }

    public function store(Field $field, File $file): ?Refusal
    {
        $refusal = Type::Photo === $field->type ? Photos::refusal([$file]) : self::refusal($field, $file);
        if (null !== $refusal) {
            return $refusal;
        }

        $this->drop($field);
        if (Type::Photo === $field->type) {
            $name = $this->photos->store($file, self::PLACE, self::SIDE);
        } else {
            $name = $field->name.'.'.self::TYPES[(string) $file->getMimeType()];
            (new Filesystem())->copy($file->getPathname(), $this->directory.'/'.$name);
        }
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

    public function themedSvg(Field $field): ?string
    {
        $path = $this->path($field);
        $source = null !== $path && str_ends_with($path, '.svg') ? (string) file_get_contents($path) : '';
        if (1 !== preg_match(self::THEMED, $source)) {
            return null;
        }

        $sanitizer = new Sanitizer();
        $sanitizer->removeXMLTag(true);
        $sanitizer->removeRemoteReferences(true);

        return $sanitizer->sanitize($source) ?: null;
    }

    private static function refusal(Field $field, File $file): ?Refusal
    {
        if (!isset(self::TYPES[(string) $file->getMimeType()])) {
            return new Refusal(t('core.image.type', ['field' => $field->label]));
        }

        return $file->getSize() > self::MAX_BYTES ? new Refusal(t('core.image.size', ['field' => $field->label, 'kb' => intdiv(self::MAX_BYTES, 1024)])) : null;
    }

    private function drop(Field $field): void
    {
        $path = $this->path($field);
        if (null !== $path) {
            (new Filesystem())->remove($path);
        }
    }
}
