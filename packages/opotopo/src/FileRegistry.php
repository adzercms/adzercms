<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use AdzerCMS\Opotopo\Entity\File;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Opotopo\SellerApi\Files\FileStore;
use Opotopo\SellerApi\Files\FileTarget;
use Opotopo\SellerApi\Files\StoredFile;

final readonly class FileRegistry implements FileStore
{
    private const TARGET = 'f.sourceHash = :hash AND f.gallery = :gallery AND f.categoryId = :category';

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(FileTarget $target): ?StoredFile
    {
        /** @var array{source: string, size: int|string, modifiedAt: int|string, checksum: string, fileId: string}|null $row */
        $row = self::targeted($this->em->createQuery(\sprintf('SELECT f.source, f.size, f.modifiedAt, f.checksum, f.fileId FROM %s f WHERE %s', File::class, self::TARGET)), $target)
            ->getOneOrNullResult();

        return null === $row ? null : new StoredFile($row['source'], (int) $row['size'], (int) $row['modifiedAt'], $row['checksum'], $row['fileId']);
    }

    public function save(FileTarget $target, StoredFile $file): void
    {
        $updated = self::targeted($this->em->createQuery(\sprintf('UPDATE %s f SET f.source = :source, f.size = :size, f.modifiedAt = :modified, f.checksum = :checksum, f.fileId = :file WHERE %s', File::class, self::TARGET)), $target)
            ->setParameter('source', $file->source)
            ->setParameter('size', $file->size)
            ->setParameter('modified', $file->modifiedAt)
            ->setParameter('checksum', $file->checksum)
            ->setParameter('file', $file->fileId)
            ->execute();

        if (0 === $updated && null === $this->find($target)) {
            $entry = new File($target, $file);
            $this->em->persist($entry);
            $this->em->flush();
            $this->em->detach($entry);
        }
    }

    /** @param list<string> $fileIds */
    public function forget(array $fileIds): void
    {
        $this->em->createQuery(\sprintf('DELETE FROM %s f WHERE f.fileId IN (:files)', File::class))
            ->setParameter('files', $fileIds, ArrayParameterType::STRING)
            ->execute();
    }

    private static function targeted(Query $query, FileTarget $target): Query
    {
        return $query
            ->setParameter('hash', $target->sourceHash)
            ->setParameter('gallery', $target->gallery)
            ->setParameter('category', $target->categoryId);
    }
}
