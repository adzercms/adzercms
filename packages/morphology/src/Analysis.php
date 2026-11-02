<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology;

final readonly class Analysis
{
    public function __construct(
        public string $word,
        public Tag $tag,
        public string $normalForm,
        public float $score,
        public ?int $paradigm,
    ) {
    }

    public function scored(float $score): self
    {
        return new self($this->word, $this->tag, $this->normalForm, $score, $this->paradigm);
    }

    public function prefixed(string $prefix, float $multiplier): self
    {
        return new self($prefix.$this->word, $this->tag, $prefix.$this->normalForm, $this->score * $multiplier, $this->paradigm);
    }
}
