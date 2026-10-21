<?php

declare(strict_types=1);

namespace AdzerCMS\Underlay\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'underlay_mark')]
class Mark
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $ticketId = 0;

    public function getId(): ?int
    {
        return $this->id;
    }
}
