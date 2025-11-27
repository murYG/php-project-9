<?php

namespace Url;

class UrlCheckRepository
{
    private \PDO $conn;

    public function __construct(\PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getEntities(int $url_id): array
    {
        $sql = "SELECT * FROM url_checks WHERE url_id = :url_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':url_id', $url_id);
        $stmt->execute();

        $checks = [];
        while ($row = $stmt->fetch()) {
            $checkData = array_combine(
                ['status_code', 'h1', 'title', 'description'],
                [$row['status_code'], $row['h1'], $row['title'], $row['description']]
            );
            $check = new UrlCheck($row['url_id'], $checkData, $row['created_at']);
            $check->setId($row['id']);

            $checks[] = $check;
        }

        return $checks;
    }

    public function find(int $id): ?UrlCheck
    {
        $sql = "SELECT * FROM url_checks WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($row = $stmt->fetch()) {
            $checkData = array_combine(
                ['status_code', 'h1', 'title', 'description'],
                [$row['status_code'], $row['h1'], $row['title'], $row['description']]
            );
            $check = new UrlCheck($row['url_id'], $checkData, $row['created_at']);
            $check->setId($row['id']);

            return $check;
        }

        return null;
    }

    public function save(UrlCheck $check): void
    {
        if ($check->exists()) {
            $this->update($check);
        } else {
            $this->create($check);
        }
    }

    private function update(UrlCheck $check): void
    {
        $sql = "
		UPDATE 
			url_checks 
		SET 
			status_code = :status_code, h1 = :h1, title = :title, description = :description 
		WHERE 
			id = :id";
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

    private function create(UrlCheck $check): void
    {
        $sql = "
		INSERT INTO 
			url_checks (url_id, status_code, h1, title, description, created_at) 
		VALUES 
			(:url_id, :status_code, :h1, :title, :description, :created_at)";
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
