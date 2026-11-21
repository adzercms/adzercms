<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Photo\Photos;
use AdzerCMS\Core\Setting\Values;
use AdzerCMS\Core\Text\TextValues;
use AdzerCMS\Core\Ui\Once;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

final readonly class BlockForm
{
    public const TYPE = 'type';
    public const VALUE = 'value';
    public const DROP = 'drop';

    private const ADD = 'pages.block';
    private const SUMMARY = ' · ';
    private const DIGEST = 'xxh128';
    private const UPLOAD = 'upload-';

    public function __construct(
        private PageBlockRepository $blocks,
        private EntityManagerInterface $em,
        private Once $once,
        private Pictures $pictures,
        private TextValues $values,
    ) {
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, string|list<array<string, string>>>
     */
    public static function shown(Block $type, array $values): array
    {
        $shown = [];
        foreach ($type->fields() as $field) {
            $value = $values[$field->name] ?? null;
            $shown[$field->name] = $field->type->picture()
                ? (\is_string($value) ? $value : '')
                : Values::form($field, Values::posted($field, $value) ?? '');
        }

        return $shown;
    }

    /** @return array{array<string, string>, list<Refusal>} */
    public function posted(Block $type, Request $request, Album $album): array
    {
        [$values, $uploads, $refusals] = $this->checked($type, $request, $album);

        return [[] === $refusals ? self::placed($values, $uploads, $album) : [], $refusals];
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, string>|Refusal
     */
    public function stored(Block $type, array $values, Album $album): array|Refusal
    {
        $stored = [];
        foreach ($type->fields() as $field) {
            $value = $values[$field->name] ?? '';
            $value = $field->type->picture() ? $value : $this->value($type, $field, $value);
            if ($value instanceof Refusal) {
                return $value;
            }
            foreach (self::named($field, $value) as $name) {
                if (null === $album->path($name)) {
                    return new Refusal(t('pages.refusal.picture', ['field' => $field->label]));
                }
            }
            if ('' !== $value) {
                $stored[$field->name] = $value;
            }
        }

        return $stored;
    }

    /** @return PageBlock|list<Refusal> */
    public function add(Page $page, Block $type, Request $request): PageBlock|array
    {
        [$values, $uploads, $refusals] = $this->checked($type, $request, $this->pictures);
        if ([] !== $refusals) {
            return $refusals;
        }
        $digests = array_map(static fn (UploadedFile $upload): string => (string) hash_file(self::DIGEST, $upload->getPathname()), $uploads);
        $block = $this->once->act(self::ADD, [(int) $page->getId(), $type->key(), strtr(json_encode($values, \JSON_THROW_ON_ERROR), $digests)], function () use ($page, $type, $values, $uploads): PageBlock {
            $block = new PageBlock($page, $type->key(), self::placed($values, $uploads, $this->pictures));
            $block->place(\count($this->blocks->of($page)) + 1);
            $this->em->persist($block);
            $this->em->flush();

            return $block;
        });

        return $block instanceof Refusal ? [$block] : $block;
    }

    /** @return list<Refusal> */
    public function fill(PageBlock $block, Block $type, Request $request): array
    {
        $before = $block->getValues();
        [$values, $refusals] = $this->posted($type, $request, $this->pictures);
        if ([] !== $refusals) {
            return $refusals;
        }

        $block->fill($values);
        $this->em->flush();
        $this->pictures->drop(array_diff(PhotoAlbum::names($before), PhotoAlbum::names($values)));

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
            $pictures = array_fill_keys(self::pictures($field), true);
            $texts = Type::Rows === $field->type
                ? array_map(static fn (array $row): string => (string) array_find(array_diff_key($row, $pictures), static fn (string $cell): bool => '' !== $cell), Values::entries($field, $value))
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

    /** @return array{array<string, string>, array<string, UploadedFile>, list<Refusal>} */
    private function checked(Block $type, Request $request, Album $album): array
    {
        $posted = $request->request->all(self::VALUE);
        $files = $request->files->all(self::VALUE);
        $dropped = $request->request->all(self::DROP);
        $values = [];
        $uploads = [];
        $refusals = [];
        foreach ($type->fields() as $field) {
            if ($field->type->picture()) {
                $kept = isset($dropped[$field->name]) ? '' : self::kept($posted[$field->name] ?? null, $album);
                $value = self::picture($files[$field->name] ?? null, $kept, $uploads, $refusals);
                if ('' !== $value) {
                    $values[$field->name] = $value;
                }

                continue;
            }

            $input = $posted[$field->name] ?? null;
            if (\is_array($input) && [] !== self::pictures($field)) {
                $input = self::cells($field, $input, $files[$field->name] ?? [], $dropped[$field->name] ?? [], $uploads, $refusals, $album);
            }
            $value = $this->value($type, $field, Values::posted($field, $input) ?? '');
            if ($value instanceof Refusal) {
                $refusals[] = $value;
            } elseif ('' !== $value) {
                $values[$field->name] = $value;
            }
        }

        return [$values, $uploads, $refusals];
    }

    private function value(Block $type, Field $field, string $value): string|Refusal
    {
        $value = Values::check($field, $value);

        return \is_string($value) && '' !== $value ? $type->check($field, $value) ?? $this->values->check($field, $value) ?? $value : $value;
    }

    /** @return list<string> */
    private static function named(Field $field, string $value): array
    {
        if ($field->type->picture()) {
            return '' === $value ? [] : [$value];
        }
        $cells = array_flip(self::pictures($field));
        $names = [];
        foreach ([] === $cells ? [] : Values::entries($field, $value) as $row) {
            array_push($names, ...array_values(array_intersect_key($row, $cells)));
        }

        return array_values(array_filter($names, static fn (string $name): bool => '' !== $name));
    }

    /**
     * @param array<string, string>       $values
     * @param array<string, UploadedFile> $uploads
     *
     * @return array<string, string>
     */
    private static function placed(array $values, array $uploads, Album $album): array
    {
        $names = array_map($album->store(...), $uploads);

        return array_map(static fn (string $value): string => strtr($value, $names), $values);
    }

    private static function kept(mixed $name, Album $album): string
    {
        return \is_string($name) && null !== $album->path($name) ? $name : '';
    }

    /** @return list<string> */
    private static function pictures(Field $field): array
    {
        return array_values(array_map(static fn (Field $cell): string => $cell->name, array_filter($field->fields, static fn (Field $cell): bool => $cell->type->picture())));
    }

    /**
     * @param array<string, UploadedFile> $uploads
     * @param list<Refusal>               $refusals
     */
    private static function picture(mixed $upload, string $kept, array &$uploads, array &$refusals): string
    {
        if (!$upload instanceof UploadedFile) {
            return $kept;
        }
        $refusal = Photos::refusal([$upload]);
        if (null !== $refusal) {
            $refusals[] = $refusal;

            return $kept;
        }
        $token = self::UPLOAD.bin2hex(random_bytes(16));
        $uploads[$token] = $upload;

        return $token;
    }

    /**
     * @param array<array-key, mixed>     $rows
     * @param array<array-key, mixed>     $files
     * @param array<array-key, mixed>     $dropped
     * @param array<string, UploadedFile> $uploads
     * @param list<Refusal>               $refusals
     *
     * @return array<array-key, mixed>
     */
    private static function cells(Field $field, array $rows, array $files, array $dropped, array &$uploads, array &$refusals, Album $album): array
    {
        foreach ($rows as $at => $row) {
            if (!\is_array($row)) {
                continue;
            }
            foreach (self::pictures($field) as $cell) {
                $kept = isset($dropped[$at][$cell]) ? '' : self::kept($row[$cell] ?? null, $album);
                $rows[$at][$cell] = self::picture(\is_array($files[$at] ?? null) ? $files[$at][$cell] ?? null : null, $kept, $uploads, $refusals);
            }
        }

        return $rows;
    }
}
