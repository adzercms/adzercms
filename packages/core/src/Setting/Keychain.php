<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Symfony\Component\Translation\t;

final readonly class Keychain
{
    private const CERTIFICATE = '-cert.pem';
    private const KEY = '-key.pem';

    public function __construct(
        private Settings $settings,
        private EntityManagerInterface $em,
        #[Autowire('%adzercms.files_dir%/keys')]
        private string $directory,
    ) {
    }

    /** @return array{local_cert: string, local_pk: string}|null */
    public function client(Field $field): ?array
    {
        $certificate = $this->directory.'/'.$field->name.self::CERTIFICATE;
        $key = $this->directory.'/'.$field->name.self::KEY;

        return '' !== $this->settings->get($field->name) && is_file($certificate) && is_file($key) ? ['local_cert' => $certificate, 'local_pk' => $key] : null;
    }

    public function store(Field $field, File $file, string $password): ?Refusal
    {
        $parts = [];
        if (!openssl_pkcs12_read((string) file_get_contents($file->getPathname()), $parts, $password)) {
            return new Refusal(t('core.keychain.unreadable', ['field' => $field->label]));
        }

        $filesystem = new Filesystem();
        $filesystem->mkdir($this->directory, 0o700);
        foreach ([self::CERTIFICATE => $parts['cert'].implode('', $parts['extracerts'] ?? []), self::KEY => $parts['pkey']] as $suffix => $pem) {
            $filesystem->dumpFile($this->directory.'/'.$field->name.$suffix, $pem);
            $filesystem->chmod($this->directory.'/'.$field->name.$suffix, 0o600);
        }
        $this->settings->save($field, $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename());
        $this->em->flush();

        return null;
    }

    public function clear(Field $field): void
    {
        (new Filesystem())->remove([$this->directory.'/'.$field->name.self::CERTIFICATE, $this->directory.'/'.$field->name.self::KEY]);
        $this->settings->clear($field);
        $this->em->flush();
    }
}
