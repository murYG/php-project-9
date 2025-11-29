<?php

namespace Url;

use Carbon\Carbon;

class UrlCheck
{
    private ?int $id = null;
    private ?int $url_id = null;
    private ?int $status_code = null;
    private ?string $h1 = null;
    private ?string $title = null;
    private ?string $description = null;
    private ?string $created_at = null;

    public function __construct(int $url_id, ?string $created_at = null, array $checkResult = [])
    {
        $this->url_id = $url_id;
        $this->created_at = $created_at ?? Carbon::now('Europe/Moscow');

        foreach ($checkResult as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrlId(): ?int
    {
        return $this->url_id;
    }

    public function getStatusCode(): ?string
    {
        return $this->status_code;
    }

    public function getH1(): ?string
    {
        return $this->h1;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
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
