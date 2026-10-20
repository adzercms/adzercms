<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Composer\Artifacts;
use AdzerCMS\Core\Composer\Repositories;
use AdzerCMS\Core\Composer\Source;
use AdzerCMS\Core\Entity\ModuleSource;
use AdzerCMS\Core\Repository\ModuleSourceRepository;
use AdzerCMS\Core\Setting\Secrets;
use AdzerCMS\Core\Ui\WebLink;
use Doctrine\ORM\EntityManagerInterface;

use function Symfony\Component\Translation\t;

final readonly class Sources
{
    public const BASE = [Repositories::PACKAGIST];

    private const ENTRY = 'adzercms-';
    private const ZIP = self::ENTRY.'zip';
    private const OWN = self::ENTRY.'source-';
    private const AUTH = 'COMPOSER_AUTH';

    public function __construct(
        private ModuleSourceRepository $sources,
        private EntityManagerInterface $em,
        private Secrets $secrets,
        private Artifacts $artifacts,
    ) {
    }

    /** @return list<ModuleSource> */
    public function own(): array
    {
        return $this->sources->ordered();
    }

    /** @return list<Source> */
    public function repositories(): array
    {
        return [
            ...array_map(fn (ModuleSource $source): Source => new Source(
                $source->getUrl(),
                $source->getLogin(),
                null === $source->getTokenCipher() ? null : $this->secrets->decrypt($source->getTokenCipher()),
            ), $this->own()),
            ...array_map(static fn (string $url): Source => new Source($url), self::BASE),
        ];
    }

    public function add(string $url): ?Refusal
    {
        $url = rtrim(trim($url), '/');
        if (!WebLink::valid($url) || \strlen($url) > ModuleSource::URL) {
            return new Refusal(t('core.module.source.url', ['max' => ModuleSource::URL]));
        }
        $own = $this->own();
        if (\in_array($url, [...self::BASE, ...array_map(static fn (ModuleSource $source): string => $source->getUrl(), $own)], true)) {
            return new Refusal(t('core.module.source.taken', ['url' => $url]));
        }

        $source = new ModuleSource($url);
        $kin = array_find($own, static fn (ModuleSource $held): bool => Source::origin($held->getUrl()) === Source::origin($url));
        if (null !== $kin) {
            $source->grant($kin->getLogin(), $kin->getTokenCipher());
        }
        $this->em->persist($source);
        $this->em->flush();

        return null;
    }

    public function grant(ModuleSource $source, string $login, string $token): void
    {
        $cipher = '' === $token ? $source->getTokenCipher() : $this->secrets->encrypt($token);
        foreach ($this->kin($source) as $kin) {
            $kin->grant('' === trim($login) ? null : trim($login), $cipher);
        }
        $this->em->flush();
    }

    public function revoke(ModuleSource $source): void
    {
        foreach ($this->kin($source) as $kin) {
            $kin->grant(null, null);
        }
        $this->em->flush();
    }

    public function drop(ModuleSource $source): void
    {
        $this->em->remove($source);
        $this->em->flush();
    }

    /** @return array<string, string> */
    public function environment(): array
    {
        $auth = array_replace_recursive([], ...array_map(static fn (Source $source): array => $source->auth(), $this->repositories()));

        return [] === $auth ? [] : [self::AUTH => json_encode($auth, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES)];
    }

    /** @return array<string, array{type: string, url: string}> */
    public function entries(): array
    {
        $entries = [] === $this->artifacts->archives() ? [] : [self::ZIP => ['type' => Artifacts::REPOSITORY, 'url' => $this->artifacts->directory]];
        foreach ($this->own() as $source) {
            $entries[self::OWN.$source->getId()] = ['type' => 'composer', 'url' => $source->getUrl()];
        }

        return $entries;
    }

    public static function entry(string $name): bool
    {
        return str_starts_with($name, self::ENTRY);
    }

    public function fingerprint(): string
    {
        return hash('xxh128', serialize([
            array_map(static fn (ModuleSource $source): array => [$source->getUrl(), $source->getLogin(), $source->getTokenCipher()], $this->own()),
            array_map(static fn (array $archive): array => [$archive['path'], filemtime($archive['path'])], $this->artifacts->archives()),
        ]));
    }

    /** @return list<ModuleSource> */
    private function kin(ModuleSource $source): array
    {
        return array_values(array_filter($this->own(), static fn (ModuleSource $held): bool => Source::origin($held->getUrl()) === Source::origin($source->getUrl())));
    }
}
