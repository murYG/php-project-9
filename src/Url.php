<?php

namespace Url;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\TransferException;

class Url
{
    private ?int $id = null;
    private ?string $name = null;
    private ?string $created_at = null;
    private ?UrlCheck $last_check = null;

    public function __construct(string $name, ?string $created_at = null)
    {
        $this->name = $name;
        $this->created_at = $created_at ?? Carbon::now();
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

    public function setLastCheck(UrlCheck $check): void
    {
        $this->last_check = $check;
    }

    public function exists(): bool
    {
        return !is_null($this->getId());
    }

    public function check(UrlCheckRepository $repo): void
    {
        $client = new Client(['base_uri' => $this->getName()]);

        try {
            $response = $client->request('GET', '');
        } catch (ClientException $e) {
            $response = $e->getResponse();
        } catch (TransferException $e) {
            throw new \RuntimeException("Произошла ошибка при проверке, не удалось подключиться");
        }

        $check = new UrlCheck($this->getId(), null, ['status_code' => $response->getStatusCode()]);
        $repo->save($check);
        $this->setLastCheck($check);
    }

    public function getCheckList(UrlCheckRepository $repo): array
    {
        return $repo->getEntities($this->getId());
    }
}
