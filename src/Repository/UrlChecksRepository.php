<?php

namespace PageAnalyzer\Repository;

use PDO;
use PageAnalyzer\Entity\UrlCheckResult;

class UrlChecksRepository
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getEntities(int $url_id): array
    {
        $sql = "SELECT * FROM url_checks WHERE url_id = :url_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':url_id', $url_id);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_FUNC, [UrlCheckResult::class, 'fromDataBaseRow']);
        return $result;
    }

    public function findLatestChecks(): array
    {
        $sql = "
        SELECT DISTINCT ON (url_id)
            url_id,
            *
        FROM url_checks
        ORDER BY url_id, id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_FUNC | PDO::FETCH_GROUP, [UrlCheckResult::class, 'fromDataBaseRow']);
        return array_combine(array_keys($result), array_column($result, 0));
    }

    public function find(int $id): ?UrlCheckResult
    {
        $sql = "SELECT * FROM url_checks WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($row = $stmt->fetch()) {
            $checResultKeys = ['status_code' => '', 'h1' => '', 'title' => '', 'description' => ''];
            $checkResult = array_intersect_key($row, $checResultKeys);
            $check = new UrlCheckResult($row['url_id'], $row['created_at'], $checkResult);
            $check->setId($row['id']);

            return $check;
        }

        return null;
    }

    public function save(UrlCheckResult $check): void
    {
        if ($check->exists()) {
            $this->update($check);
        } else {
            $this->create($check);
        }
    }

    private function update(UrlCheckResult $check): void
    {
        $sql = "UPDATE url_checks SET status_code = :status_code,
        h1 = :h1, title = :title, description = :description WHERE id = :id";
        $stmt = $this->conn->prepare($sql);

        $id = $check->getId();
        $status_code = $check->getStatusCode();
        $h1 = $check->getH1();
        $title = $check->getTitle();
        $description = $check->getDescription();

        $stmt->bindParam(':status_code', $status_code);
        $stmt->bindParam(':h1', $h1);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }

    private function create(UrlCheckResult $check): void
    {
        $sql = "INSERT INTO url_checks (url_id, status_code, h1, title, description, created_at)
        VALUES (:url_id, :status_code, :h1, :title, :description, :created_at)";
        $stmt = $this->conn->prepare($sql);

        $url_id = $check->getUrlId();
        $status_code = $check->getStatusCode();
        $h1 = $check->getH1();
        $title = $check->getTitle();
        $description = $check->getDescription();
        $created_at = $check->getCreatedAt();

        $stmt->bindParam(':url_id', $url_id);
        $stmt->bindParam(':status_code', $status_code);
        $stmt->bindParam(':h1', $h1);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':created_at', $created_at);
        $stmt->execute();

        $id = (int) $this->conn->lastInsertId();
        $check->setId($id);
    }
}
