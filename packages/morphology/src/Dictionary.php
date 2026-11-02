<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology;

use AdzerCMS\Morphology\Dawg\Dawg;
use AdzerCMS\Morphology\Dawg\RecordDawg;

final readonly class Dictionary
{
    public RecordDawg $words;
    /** @var array<int, RecordDawg> */
    public array $predictions;
    public Dawg $probabilities;
    /** @var list<string> */
    public array $prefixes;
    public int $maxSuffixLength;
    /** @var list<Tag> */
    private array $tags;
    /** @var list<string> */
    private array $suffixes;
    private string $paradigms;
    /** @var list<int> */
    private array $starts;
    /** @var list<int> */
    private array $sizes;

    public function __construct(string $path)
    {
        /** @var array{compile_options: array{paradigm_prefixes: list<string>, max_suffix_length: int}, gramtab_formats: array{'opencorpora-int': string}} $meta */
        $meta = array_column(self::json($path.'/meta.json'), 1, 0);
        $this->prefixes = $meta['compile_options']['paradigm_prefixes'];
        $this->maxSuffixLength = $meta['compile_options']['max_suffix_length'];
        /** @var list<string> $tags */
        $tags = self::json($path.'/'.$meta['gramtab_formats']['opencorpora-int']);
        $this->tags = array_map(static fn (string $tag): Tag => new Tag($tag), $tags);
        /** @var list<string> $suffixes */
        $suffixes = self::json($path.'/suffixes.json');
        $this->suffixes = $suffixes;

        $this->paradigms = self::read($path.'/paradigms.array');
        $starts = $sizes = [];
        for ($i = 0, $count = $this->uint16(0), $offset = 2; $i < $count; ++$i) {
            $length = $this->uint16($offset);
            $starts[] = $offset + 2;
            $sizes[] = intdiv($length, 3);
            $offset += 2 + 2 * $length;
        }
        $this->starts = $starts;
        $this->sizes = $sizes;

        $this->words = new RecordDawg(Dawg::load($path.'/words.dawg'), 'nparadigm/nform');
        $predictions = [];
        foreach (array_keys($this->prefixes) as $id) {
            $predictions[$id] = new RecordDawg(Dawg::load($path.'/prediction-suffixes-'.$id.'.dawg'), 'Ncount/nparadigm/nform');
        }
        $this->predictions = $predictions;
        $this->probabilities = Dawg::load($path.'/p_t_given_w.intdawg');
    }

    public function tag(int $paradigm, int $form): Tag
    {
        return $this->tags[$this->entry($paradigm, $this->sizes[$paradigm] + $form)];
    }

    public function normalForm(int $paradigm, int $form, string $word): string
    {
        if (0 === $form) {
            return $word;
        }
        $prefix = $this->prefixes[$this->entry($paradigm, 2 * $this->sizes[$paradigm] + $form)];
        $suffix = $this->suffixes[$this->entry($paradigm, $form)];
        $stem = mb_substr($word, mb_strlen($prefix), '' === $suffix ? null : -mb_strlen($suffix));

        return $this->prefixes[$this->entry($paradigm, 2 * $this->sizes[$paradigm])].$stem.$this->suffixes[$this->entry($paradigm, 0)];
    }

    private function entry(int $paradigm, int $position): int
    {
        return $this->uint16($this->starts[$paradigm] + 2 * $position);
    }

    private function uint16(int $offset): int
    {
        /** @var array{1: int} $value */
        $value = unpack('v', $this->paradigms, $offset);

        return $value[1];
    }

    private static function read(string $path): string
    {
        $data = file_get_contents($path);
        if (false === $data) {
            throw new \RuntimeException(\sprintf('Cannot read the dictionary file %s', $path));
        }

        return $data;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function json(string $path): array
    {
        $data = json_decode(self::read($path), true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($data)) {
            throw new \RuntimeException(\sprintf('The dictionary file %s is not a JSON array', $path));
        }

        return $data;
    }
}
