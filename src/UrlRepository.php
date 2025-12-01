<?php

namespace PageAnalyzer;

use PDO;

class UrlRepository
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getEntities(): array
    {
        $sql = "
        SELECT DISTINCT ON (url_id)
            url_id,
            id,
            status_code,
            h1,
            title,
            description,
            created_at
        FROM url_checks 
        ORDER BY url_id, id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $checks = $stmt->fetchAll(PDO::FETCH_GROUP);

        $sql = "SELECT id, name, created_at FROM urls ORDER BY id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $checkResultKeys = ['status_code' => '', 'h1' => '', 'title' => '', 'description' => ''];
        return $stmt->fetchAll(PDO::FETCH_FUNC, function ($id, $name, $created_at) use ($checks, $checkResultKeys) {
            $url = new Url($name, $created_at);
            $url->setId($id);

            if ($checks[$id] !== null) {
                $checkRow = $checks[$id][0];
                $checkResult = array_intersect_key($checkRow, $checkResultKeys);

                $check = new UrlCheckResult($id, $checkRow['created_at'], $checkResult);
                $check->setId($checkRow['id']);

                $url->setLastCheck($check);
            }

            return $url;
        });
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
