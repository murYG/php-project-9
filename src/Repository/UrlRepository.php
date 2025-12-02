<?php

namespace PageAnalyzer\Repository;

use PDO;
use PageAnalyzer\Entity\Url;

class UrlRepository
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getEntities(): array
    {
        $sql = "SELECT id, name, created_at FROM urls ORDER BY id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_FUNC, function ($id, $name, $created_at) {
            $url = new Url($name, $created_at);
            $url->setId($id);

            return $url;
        });

        return $result;
    }

    public function getById(int $id): ?Url
    {
        $sql = "SELECT * FROM urls WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($row = $stmt->fetch()) {
            $url = new Url($row['name'], $row['created_at']);
            $url->setId($row['id']);

            return $url;
        }

        return null;
    }

    public function getByName(string $name): ?Url
    {
        $sql = "SELECT * FROM urls WHERE name = :name";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->execute();

        if ($row = $stmt->fetch()) {
            $url = new Url($row['name'], $row['created_at']);
            $url->setId($row['id']);

            return $url;
        }

        return null;
    }

    public function save(Url $url): void
    {
        if ($url->exists()) {
            $this->update($url);
        } else {
            $this->create($url);
        }
    }

    private function update(Url $url): void
    {
        $sql = "UPDATE urls SET name = :name WHERE id = :id";
        $stmt = $this->conn->prepare($sql);

        $id = $url->getId();
        $name = $url->getName();

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }

    private function create(Url $url): void
    {
        $sql = "INSERT INTO urls (name, created_at) VALUES (:name, :created_at)";
        $stmt = $this->conn->prepare($sql);

        $name = $url->getName();
        $created_at = $url->getCreatedAt();

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':created_at', $created_at);
        $stmt->execute();

        $id = (int) $this->conn->lastInsertId();
        $url->setId($id);
    }
}
