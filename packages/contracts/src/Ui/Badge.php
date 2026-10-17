<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class Badge implements Badged
{
    private function __construct(
        private TranslatableInterface $label,
        private string $kind,
    ) {
    }

    public static function good(TranslatableInterface $label): self
    {
        return new self($label, 'success');
    }

    public static function warn(TranslatableInterface $label): self
    {
        return new self($label, 'warning');
    }

    public static function bad(TranslatableInterface $label): self
    {
        return new self($label, 'danger');
    }

    public static function idle(TranslatableInterface $label): self
    {
        return new self($label, 'secondary');
    }

    public function label(): TranslatableInterface
    {
        return $this->label;
    }

    public function badge(): string
    {
        return $this->kind;
    }
}
