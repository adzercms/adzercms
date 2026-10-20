<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Opotopo\SellerApi\Files\FileTarget;
use Opotopo\SellerApi\Files\StoredFile;

#[ORM\Entity]
#[ORM\Table(name: 'opotopo_file')]
#[ORM\UniqueConstraint(name: 'uniq_opotopo_file_target', columns: ['source_hash', 'gallery', 'category_id'])]
#[ORM\UniqueConstraint(name: 'uniq_opotopo_file_file_id', columns: ['file_id'])]
class File
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $source;

    #[ORM\Column(length: 64)]
    private string $sourceHash;

    #[ORM\Column(length: 32)]
    private string $gallery;

    #[ORM\Column(type: Types::GUID)]
    private string $categoryId;

    #[ORM\Column(type: Types::BIGINT)]
    private int $size;

    #[ORM\Column(type: Types::BIGINT)]
    private int $modifiedAt;

    #[ORM\Column(length: 64)]
    private string $checksum;

    #[ORM\Column(type: Types::GUID)]
    private string $fileId;

    public function __construct(FileTarget $target, StoredFile $file)
    {
        $this->sourceHash = $target->sourceHash;
        $this->gallery = $target->gallery;
        $this->categoryId = $target->categoryId;
        $this->source = $file->source;
        $this->size = $file->size;
        $this->modifiedAt = $file->modifiedAt;
        $this->checksum = $file->checksum;
        $this->fileId = $file->fileId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
