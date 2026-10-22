<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Attachment;

use AdzerCMS\Core\Entity\StoredFile;

interface AttachmentRecord
{
    public static function folder(): string;

    public function getFile(): StoredFile;
}
