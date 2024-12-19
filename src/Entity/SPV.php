<?php

namespace App\Entity;

use App\Entity\Traits\UuidTrait;
use App\Repository\SPVRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SPVRepository::class)]
class SPV
{
    use UuidTrait;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $DisplayName = null;

    #[ORM\Column]
    private ?bool $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $CS = null;

    #[
        ORM\OneToMany(
            mappedBy: 'spv',
            targetEntity: Disponibilite::class,
            cascade: ['persist', 'remove']
        )
    ]
    private Collection $disponibilites;

    public function __construct()
    {
        $this->disponibilites = new ArrayCollection();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->DisplayName;
    }

    public function setDisplayName(?string $DisplayName): static
    {
        $this->DisplayName = $DisplayName;

        return $this;
    }

    public function isStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCS(): ?string
    {
        return $this->CS;
    }

    public function setCS(?string $CS): static
    {
        $this->CS = $CS;

        return $this;
    }

    /**
     * @return Collection|Disponibilite[]
     */
    public function getDisponibilites(): Collection
    {
        return $this->disponibilites;
    }

    public function addDisponibilite(Disponibilite $disponibilite): self
    {
        if (!$this->disponibilites->contains($disponibilite)) {
            $this->disponibilites[] = $disponibilite;
            $disponibilite->setSpv($this);
        }

        return $this;
    }

    public function removeDisponibilite(Disponibilite $disponibilite): self
    {
        if ($this->disponibilites->removeElement($disponibilite)) {
            // Set the owning side to null
            if ($disponibilite->getSpv() === $this) {
                $disponibilite->setSpv(null);
            }
        }

        return $this;
    }
}
