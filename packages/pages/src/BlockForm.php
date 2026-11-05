<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Photo\Photos;
use AdzerCMS\Core\Setting\Values;
use AdzerCMS\Core\Ui\Once;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final readonly class BlockForm
{
    public const TYPE = 'type';
    public const VALUE = 'value';
    public const DROP = 'drop';

    private const ADD = 'pages.block';
    private const SUMMARY = ' · ';
    private const DIGEST = 'xxh128';

    public function __construct(
        private PageBlockRepository $blocks,
        private EntityManagerInterface $em,
        private Once $once,
        private Pictures $pictures,
    ) {
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, string|list<array<string, string>>>
     */
    public static function shown(Block $type, array $values, ?PageBlock $block): array
    {
        $shown = [];
        foreach ($type->fields() as $field) {
            $shown[$field->name] = $field->type->picture()
                ? $block?->getValues()[$field->name] ?? ''
                : Values::form($field, Values::posted($field, $values[$field->name] ?? null) ?? '');
        }

        return $shown;
    }

    /** @return PageBlock|list<Refusal> */
    public function add(Page $page, Block $type, Request $request): PageBlock|array
    {
        [$values, $uploads, $refusals] = self::checked($type, $request, []);
        if ([] !== $refusals) {
            return $refusals;
        }
        $digests = array_map(static fn (UploadedFile $upload): string => (string) hash_file(self::DIGEST, $upload->getPathname()), array_values($uploads));
        if (!$this->once->fresh(self::ADD, [(int) $page->getId(), $type->key(), json_encode($values, \JSON_THROW_ON_ERROR), ...$digests])) {
            return [new Refusal(Once::repeat())];
        }

        $block = new PageBlock($page, $type->key(), [...$values, ...array_map($this->pictures->store(...), $uploads)]);
        $block->place(\count($this->blocks->of($page)) + 1);
        $this->em->persist($block);
        $this->em->flush();

        return $block;
    }

    /** @return list<Refusal> */
    public function fill(PageBlock $block, Block $type, Request $request): array
    {
        $before = $block->getValues();
        [$values, $uploads, $refusals] = self::checked($type, $request, $before);
        if ([] !== $refusals) {
            return $refusals;
        }

        $values = [...$values, ...array_map($this->pictures->store(...), $uploads)];
        $block->fill($values);
        $this->em->flush();
        $this->pictures->drop(array_diff($before, $values));

        return [];
    }

    public function drop(PageBlock $block): void
    {
        $values = $block->getValues();
        $this->em->remove($block);
        $this->em->flush();
        $this->pictures->drop($values);
    }

    /** @param list<int> $moved */
    public function arrange(Page $page, array $moved): void
    {
        Order::place($this->blocks->of($page), $moved);
        $this->em->flush();
    }

    public static function summary(Block $type, PageBlock $block): string
    {
        $parts = [];
        foreach ($type->fields() as $field) {
            if ($field->type->picture()) {
                continue;
            }
            $value = $block->getValues()[$field->name] ?? '';
            $texts = Type::Rows === $field->type
                ? array_map(static fn (array $row): string => (string) array_find($row, static fn (string $cell): bool => '' !== $cell), Values::entries($field, $value))
                : [$value];
            foreach ($texts as $text) {
                $text = trim((string) preg_replace('/\s+/u', ' ', $text));
                if ('' !== $text) {
                    $parts[] = $text;
                }
            }
        }

        return implode(self::SUMMARY, $parts);
    }

    /**
     * @param array<string, string> $stored
     *
     * @return array{array<string, string>, array<string, UploadedFile>, list<Refusal>}
     */
    private static function checked(Block $type, Request $request, array $stored): array
    {
        $posted = $request->request->all(self::VALUE);
        $files = $request->files->all(self::VALUE);
        $dropped = $request->request->all(self::DROP);
        $values = [];
        $uploads = [];
        $refusals = [];
        foreach ($type->fields() as $field) {
            if ($field->type->picture()) {
                $upload = $files[$field->name] ?? null;
                $refusal = $upload instanceof UploadedFile ? Photos::refusal([$upload]) : null;
                if (null !== $refusal) {
                    $refusals[] = $refusal;
                } elseif ($upload instanceof UploadedFile) {
                    $uploads[$field->name] = $upload;
                } elseif (isset($stored[$field->name]) && !isset($dropped[$field->name])) {
                    $values[$field->name] = $stored[$field->name];
                }

                continue;
            }

            $value = Values::check($field, Values::posted($field, $posted[$field->name] ?? null) ?? '');
            if (\is_string($value) && '' !== $value) {
                $value = $type->check($field, $value) ?? $value;
            }
            if ($value instanceof Refusal) {
                $refusals[] = $value;
            } elseif ('' !== $value) {
                $values[$field->name] = $value;
            }
        }

        return [$values, $uploads, $refusals];
    }
}
