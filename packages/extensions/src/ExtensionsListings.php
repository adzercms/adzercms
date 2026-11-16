<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use AdzerCMS\Extensions\Repository\ExtensionRepository;
use AdzerCMS\Opotopo\Catalog as OpotopoCatalog;
use AdzerCMS\Opotopo\Records;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ExtensionsListings implements OpotopoCatalog
{
    private const NAME = 'extensions';
    private const CATEGORY = '00000000-0000-0000-0032-00000000000b';
    private const MANAGER = 'COMPOSER';
    private const OFFER = 'PRODUCT';
    private const FREE = '0';

    public function __construct(
        private ExtensionRepository $extensions,
        private Records $records,
        private TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function mapping(): array
    {
        return [
            'fields' => ['sku' => 'name', 'title' => 'title', 'description' => 'description', 'offerType' => 'offer'],
            'category' => 'category',
            'package' => ['manager' => 'manager', 'name' => 'name', 'git' => 'git'],
            'variants' => [['name' => $this->translator->trans('extensions.license'), 'price' => 'price']],
        ];
    }

    public function records(): iterable
    {
        foreach ($this->extensions->operated() as $extension) {
            yield $this->records->record($extension->getName(), [
                'name' => $extension->getName(),
                'title' => $extension->getTitle()->trans($this->translator),
                'description' => $extension->getDescription()?->trans($this->translator),
                'offer' => self::OFFER,
                'category' => self::CATEGORY,
                'manager' => self::MANAGER,
                'git' => $extension->getSource(),
                'price' => $extension->getPrice() ?? self::FREE,
            ]);
        }
    }
}
