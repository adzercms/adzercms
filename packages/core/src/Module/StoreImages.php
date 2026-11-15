<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Core\Photo\Photos;

final readonly class StoreImages
{
    private const PLACE = 'store-images';
    private const SIDE = 1920;

    public function __construct(private Photos $photos)
    {
    }

    public function path(string $name): ?string
    {
        return $this->photos->path(self::PLACE, $name);
    }

    public function copied(?StoreDocument $document, ?StoreDocument $held): ?StoreDocument
    {
        $copies = [];
        foreach ($held->entries ?? [] as $entry) {
            $copies += array_filter($entry->images);
        }

        $entries = [];
        foreach ($document->entries ?? [] as $name => $entry) {
            $images = [];
            foreach (array_keys($entry->images) as $url) {
                $images[$url] = $copies[$url] ??= $this->photos->fetched($url, self::PLACE, self::SIDE);
            }
            $entries[$name] = new StoreEntry($entry->type, $entry->demo, $images);
        }

        $names = array_merge(...array_values(array_map(static fn (StoreEntry $entry): array => array_values(array_filter($entry->images)), $entries)), ...[[]]);
        $this->photos->drop(self::PLACE, array_values(array_diff($this->photos->names(self::PLACE), $names)));

        if (null === $document) {
            return null;
        }
        $whole = !array_any($entries, static fn (StoreEntry $entry): bool => \in_array(null, $entry->images, true));

        return new StoreDocument($document->url, $whole ? $document->etag : null, $entries);
    }
}
