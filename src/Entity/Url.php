<?php

namespace PageAnalyzer\Entity;

use Carbon\Carbon;

class Url
{
    private ?int $id = null;
    private string $name;
    private string $created_at;

    public function __construct(string $name, ?string $created_at = null)
    {
        $this->name = $name;
        $this->created_at = $created_at ?? Carbon::now('Europe/Moscow');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getCreatedAt(): ?string
    {
        return $this->created_at;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function exists(): bool
    {
        return !is_null($this->getId());
    }
}
