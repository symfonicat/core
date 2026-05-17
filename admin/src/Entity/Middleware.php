<?php

namespace Symfonicat\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfonicat\Repository\MiddlewareRepository;

#[ORM\Entity(repositoryClass: MiddlewareRepository::class)]
#[ORM\Table(name: 'symfonicat_middleware')]
#[ORM\UniqueConstraint(name: 'uniq_symfonicat_middleware_class', columns: ['class'])]
class Middleware
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    private string $class = '';

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = trim($id);

        return $this;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function setClass(string $class): static
    {
        $this->class = trim($class);

        return $this;
    }
}
