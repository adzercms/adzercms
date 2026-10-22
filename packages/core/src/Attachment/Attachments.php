<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Attachment;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\StoredFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class Attachments
{
    private const NAME_BYTES = 16;
    private const UNKNOWN = 'application/octet-stream';

    public function __construct(
        #[Autowire('%adzercms.files_dir%')]
        private string $directory,
    ) {
    }

    /**
     * @param array<mixed>                   $files
     * @param class-string<AttachmentRecord> $record
     *
     * @return list<StoredFile>|Refusal
     */
    public function receive(array $files, Allowance $allowance, string $record): array|Refusal
    {
        $uploads = array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile));

        foreach ($uploads as $file) {
            $refusal = $allowance->refusal($file);
            if (null !== $refusal) {
                return $refusal;
            }
        }

        return array_map(fn (UploadedFile $file): StoredFile => $this->keep($file, $record::folder()), $uploads);
    }

    public function path(AttachmentRecord $attachment): string
    {
        return $this->folder($attachment::folder()).'/'.$attachment->getFile()->getStoredName();
    }

    public function response(AttachmentRecord $attachment): BinaryFileResponse
    {
        $path = $this->path($attachment);
        if (!is_file($path)) {
            throw new NotFoundHttpException();
        }

        $file = $attachment->getFile();
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $file->getMime());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(
            $file->isInline() ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $file->getName(),
        );

        return $response;
    }

    private function keep(UploadedFile $file, string $folder): StoredFile
    {
        $kept = new StoredFile(
            mb_substr($file->getClientOriginalName(), 0, StoredFile::NAME_MAX),
            bin2hex(random_bytes(self::NAME_BYTES)).'.'.strtolower($file->getClientOriginalExtension()),
            $file->getMimeType() ?? self::UNKNOWN,
            (int) $file->getSize(),
        );

        $file->move($this->folder($folder), $kept->getStoredName());

        return $kept;
    }

    private function folder(string $folder): string
    {
        return $this->directory.'/'.$folder;
    }
}
