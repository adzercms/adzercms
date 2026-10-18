<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Text;

use AdzerCMS\Core\Entity\Text;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;

#[AutoconfigureTag('translation.loader', ['alias' => self::FORMAT])]
final readonly class TextLoader implements LoaderInterface
{
    public const FORMAT = 'texts';

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function load(mixed $resource, string $locale, string $domain = 'messages'): MessageCatalogue
    {
        $catalogue = new MessageCatalogue($locale);
        $stored = str_ends_with($domain, MessageCatalogue::INTL_DOMAIN_SUFFIX) ? substr($domain, 0, -\strlen(MessageCatalogue::INTL_DOMAIN_SUFFIX)) : $domain;
        try {
            $texts = $this->em->getRepository(Text::class)->findBy(['locale' => $locale, 'domain' => $stored]);
        } catch (ConnectionException|TableNotFoundException) {
            return $catalogue;
        }

        foreach ($texts as $text) {
            $catalogue->set($text->message, $text->value(), $domain);
        }

        return $catalogue;
    }
}
