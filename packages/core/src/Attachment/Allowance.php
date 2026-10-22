<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Attachment;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Ui\Split;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Symfony\Component\Translation\t;

final readonly class Allowance
{
    private const MEGABYTE = 1024 * 1024;

    /** @param list<string> $types */
    public function __construct(public int $megabytes, public array $types)
    {
    }

    public static function configured(Settings $settings, string $megabytes, string $types, self $default): self
    {
        $named = Split::items($settings->get($types));

        return new self(
            max(1, $settings->int($megabytes, $default->megabytes)),
            [] === $named ? $default->types : array_map(strtolower(...), $named),
        );
    }

    public function accept(): string
    {
        return implode(',', array_map(static fn (string $type): string => '.'.$type, $this->types));
    }

    public function refusal(UploadedFile $file): ?Refusal
    {
        if (!$file->isValid()) {
            return \in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)
                ? $this->oversize()
                : new Refusal(t('core.attachment.upload'));
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!\in_array($extension, $this->types, true)) {
            return new Refusal(t('core.attachment.type', ['extension' => $extension]));
        }

        return $file->getSize() > $this->megabytes * self::MEGABYTE ? $this->oversize() : null;
    }

    private function oversize(): Refusal
    {
        return new Refusal(t('core.attachment.oversize', ['megabytes' => $this->megabytes]));
    }
}
