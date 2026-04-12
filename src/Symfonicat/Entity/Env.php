<?php

namespace Symfonicat\Entity;

use Symfonicat\Repository\EnvRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnvRepository::class)]
#[ORM\Table(name: 'symfonicat_env')]
class Env
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = trim($id);

        return $this;
    }

    public function __toString(): string
    {
        return $this->id ?? '';
    }
}
