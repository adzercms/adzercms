<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Core\Ui\WebLink;
use AdzerCMS\Extensions\Entity\Extension;
use AdzerCMS\Extensions\Entity\ExtensionAuthor;
use AdzerCMS\Extensions\Repository\ExtensionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;

use function Symfony\Component\Translation\t;

final readonly class Catalog
{
    public const PACKAGE = '{^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$}';

    public function __construct(
        private EntityManagerInterface $em,
        private ExtensionRepository $extensions,
        private Metadata $metadata,
    ) {
    }

    /** @param InputBag<string> $input */
    public function save(Extension $extension, InputBag $input): ?Refusal
    {
        $entry = $this->read($extension, $input);
        if ($entry instanceof Refusal) {
            return $entry;
        }

        $price = trim($input->getString('price'));
        $amount = '' === $price ? null : Money::parse($price);
        if ('' !== $price && (null === $amount || !Money::isPositive($amount) || Money::compare($amount, Extension::PRICE) > 0)) {
            return new Refusal(t('extensions.refusal.price', ['max' => Money::rubles(Extension::PRICE)]));
        }

        $extension->describe($entry['name'], $entry['type'], $amount, $entry['demo']);
        $this->keep($extension);
        $this->metadata->refresh([$extension]);

        return null;
    }

    /** @param InputBag<string> $input */
    public function submit(Extension $extension, InputBag $input): ?Refusal
    {
        $author = $extension->getAuthor() ?? throw new \LogicException('an operator entry goes by save');
        $entry = $this->read($extension, $input);
        if ($entry instanceof Refusal) {
            return $entry;
        }

        $lookup = $this->metadata->lookup([$entry['name']]);
        if (!$lookup->decided($entry['name'])) {
            return new Refusal(t('extensions.refusal.unavailable', ['name' => $entry['name']]));
        }

        $package = $lookup->packages[$entry['name']];
        if ($author->getKey() !== $package?->author) {
            return new Refusal(t('extensions.refusal.key', ['name' => $entry['name'], 'key' => $author->getKey()]));
        }

        $extension->describe($entry['name'], $entry['type'], null, $entry['demo'])->know($package)->revise();
        $this->keep($extension);
        $this->em->flush();

        return null;
    }

    public function author(User $user): ExtensionAuthor
    {
        $author = $this->em->find(ExtensionAuthor::class, $user->getId());
        if (null === $author) {
            $author = new ExtensionAuthor($user);
            $this->em->persist($author);
            $this->em->flush();
        }

        return $author;
    }

    /**
     * @param InputBag<string> $input
     *
     * @return array{name: string, type: ExtensionType, demo: ?string}|Refusal
     */
    private function read(Extension $extension, InputBag $input): array|Refusal
    {
        $name = strtolower(trim($input->getString('name')));
        if (1 !== preg_match(self::PACKAGE, $name)) {
            return new Refusal(t('extensions.refusal.name'));
        }

        $same = $this->extensions->named($name);
        if (null !== $same && $same !== $extension) {
            return new Refusal(t('extensions.refusal.taken', ['name' => $name]));
        }

        $type = ExtensionType::tryFrom($input->getString('type'));
        if (null === $type) {
            return new Refusal(t('extensions.refusal.type'));
        }

        $demo = trim($input->getString('demo'));
        if ('' !== $demo && !WebLink::valid($demo)) {
            return new Refusal(t('extensions.refusal.demo'));
        }

        return ['name' => $name, 'type' => $type, 'demo' => '' === $demo ? null : $demo];
    }

    private function keep(Extension $extension): void
    {
        if (null === $extension->getId()) {
            $extension->place($this->extensions->last() + 1);
            $this->em->persist($extension);
        }
    }
}
