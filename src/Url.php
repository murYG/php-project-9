<?php

namespace Url;

use Carbon\Carbon;

class Url
{
    private ?int $id = null;
    private ?string $name = null;
    private ?string $created_at = null;
    private ?UrlCheck $last_check = null;

    public function __construct(string $name, ?string $created_at = null, ?UrlCheck $last_check = null)
    {
        $this->name = $name;
        $this->created_at = $created_at ?? Carbon::now();
        $this->last_check = $last_check;
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

    public function getLastCheck(): ?UrlCheck
    {
        return $this->last_check;
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
