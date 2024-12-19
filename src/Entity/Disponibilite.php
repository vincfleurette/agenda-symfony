<?php

// src/Entity/Disponibilite.php

namespace App\Entity;

use App\Entity\Traits\UuidTrait;
use App\Enum\DisponibiliteType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Disponibilite
{
    use UuidTrait;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $date;

    #[ORM\Column(type: 'string', enumType: DisponibiliteType::class)]
    private DisponibiliteType $type;

    #[ORM\ManyToOne(targetEntity: SPV::class, inversedBy: 'disponibilites')]
    #[
        ORM\JoinColumn(
            name: 'spv_uuid',
            referencedColumnName: 'uuid',
            nullable: false
        )
    ]
    private SPV $spv;

    // Getters et setters

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getType(): DisponibiliteType
    {
        return $this->type;
    }

    public function setType(DisponibiliteType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSpv(): SPV
    {
        return $this->spv;
    }

    public function setSpv(SPV $spv): self
    {
        $this->spv = $spv;

        return $this;
    }
}
