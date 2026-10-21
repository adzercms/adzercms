<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Text;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\Text;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Translator;

use function Symfony\Component\Translation\t;

final readonly class Texts
{
    public const FILES = 'adzercms.text_files';

    /** @param array<string, list<string>> $files */
    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire(param: self::FILES)]
        private array $files,
        #[Autowire('%kernel.cache_dir%/translations')]
        private string $cache,
        #[Autowire(service: 'translator.default')]
        private Translator $translator,
    ) {
    }

    public static function domain(string $file): string
    {
        return implode('.', \array_slice(explode('.', basename($file)), 0, -2));
    }

    public function catalogue(string $locale): MessageCatalogue
    {
        return self::load($locale, $this->files[$locale] ?? []);
    }

    /** @param list<string> $files */
    public static function load(string $locale, array $files): MessageCatalogue
    {
        $catalogue = new MessageCatalogue($locale);
        $loader = new XliffFileLoader();
        foreach ($files as $file) {
            $catalogue->addCatalogue($loader->load($file, $locale, self::domain($file)));
        }

        return $catalogue;
    }

    /** @return array<string, array<string, string>> */
    public function stored(string $locale): array
    {
        $stored = [];
        foreach ($this->em->getRepository(Text::class)->findBy(['locale' => $locale]) as $text) {
            $stored[$text->domain][$text->message] = $text->value();
        }

        return $stored;
    }

    public function save(string $locale, string $domain, string $message, string $value): ?Refusal
    {
        $text = $this->em->find(Text::class, ['locale' => $locale, 'domain' => $domain, 'message' => $message]);
        $catalogue = $this->catalogue($locale);

        if ('' === trim($value) || $value === $catalogue->get($message, $domain)) {
            if (null !== $text) {
                $this->em->remove($text);
            }
        } else {
            if ($catalogue->defines($message, $domain.MessageCatalogue::INTL_DOMAIN_SUFFIX)) {
                try {
                    new \MessageFormatter($locale, $value);
                } catch (\IntlException) {
                    return new Refusal(t('core.text.broken'));
                }
            }

            null === $text ? $this->em->persist(new Text($locale, $domain, $message, $value)) : $text->change($value);
        }

        $this->em->flush();
        (new Filesystem())->remove(glob($this->cache.'/catalogue.*') ?: []);
        $this->translator->setFallbackLocales($this->translator->getFallbackLocales());

        return null;
    }
}
