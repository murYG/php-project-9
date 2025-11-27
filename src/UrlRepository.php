<?php

namespace Url;

class UrlRepository
{
    private \PDO $conn;

    public function __construct(\PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getEntities(): array
    {
        $sql = "
        SELECT 
            urls.*,
            checks.url_id,
            checks.id as last_check_id,
            checks.status_code,
            checks.h1,
            checks.title,
            checks.description,
            checks.created_at as check_created_at
        FROM 
            urls 
            LEFT JOIN (
                SELECT 
                    url_checks.*
                FROM 
                    url_checks
                    INNER JOIN (SELECT url_id, MAX(id) as last_check_id FROM url_checks GROUP BY url_id) as last_check
                    ON last_check.url_id = url_checks.url_id
                    AND last_check.last_check_id = url_checks.id                
            ) as checks
            ON checks.url_id = urls.id
        ORDER BY 
            urls.created_at DESC";
        $stmt = $this->conn->query($sql);

        $urls = [];
        while ($row = $stmt->fetch()) {
            if ($row['last_check_id'] !== null) {
                $checkData = array_combine(
                    ['status_code', 'h1', 'title', 'description'],
                    [$row['status_code'], $row['h1'], $row['title'], $row['description']]
                );
                $check = new UrlCheck($row['url_id'], $checkData, $row['check_created_at']);
                $check->setId($row['last_check_id']);
            } else {
                $check = null;
            }

            $url = new Url($row['name'], $row['created_at'], $check);
            $url->setId($row['id']);
            $urls[] = $url;
        }

        return $urls;
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
