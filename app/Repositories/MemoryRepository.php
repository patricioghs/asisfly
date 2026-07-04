<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class MemoryRepository
{
    public function indexDocument(int $companyId, int $documentId, string $title, string $content): int
    {
        if (!Database::available() || trim($content) === '') {
            return 0;
        }

        $pdo = Database::connection();
        $delete = $pdo->prepare('DELETE FROM memory_entries WHERE company_id = :company_id AND source_type = "document" AND source_id = :source_id');
        $delete->execute(['company_id' => $companyId, 'source_id' => $documentId]);

        $chunks = $this->chunk($content);
        $insert = $pdo->prepare('INSERT INTO memory_entries (company_id, source_type, source_id, title, content, embedding_provider) VALUES (:company_id, "document", :source_id, :title, :content, :embedding_provider)');

        foreach ($chunks as $index => $chunk) {
            $insert->execute([
                'company_id' => $companyId,
                'source_id' => $documentId,
                'title' => $title . ' / parte ' . ($index + 1),
                'content' => $chunk,
                'embedding_provider' => 'text-index-v1',
            ]);
        }

        return count($chunks);
    }

    public function search(int $companyId, string $query, int $limit = 5): array
    {
        if (!Database::available() || trim($query) === '') {
            return [];
        }

        $terms = $this->keywords($query);
        if (!$terms) {
            return [];
        }

        $booleanQuery = implode(' ', array_map(fn (string $term): string => $term . '*', $terms));
        $pdo = Database::connection();

        try {
            $statement = $pdo->prepare('SELECT title, content, MATCH(title, content) AGAINST (:query IN BOOLEAN MODE) AS score FROM memory_entries WHERE company_id = :company_id AND MATCH(title, content) AGAINST (:query IN BOOLEAN MODE) ORDER BY score DESC, id DESC LIMIT ' . max(1, $limit));
            $statement->execute(['company_id' => $companyId, 'query' => $booleanQuery]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                return $this->format($rows);
            }
        } catch (\Throwable) {
            // MySQL FULLTEXT can fail on very small terms; LIKE fallback keeps the MVP useful.
        }

        $where = [];
        $params = ['company_id' => $companyId];
        foreach (array_slice($terms, 0, 6) as $index => $term) {
            $key = 'term' . $index;
            $where[] = "(title LIKE :{$key} OR content LIKE :{$key})";
            $params[$key] = '%' . $term . '%';
        }
        $statement = $pdo->prepare('SELECT title, content, 0 AS score FROM memory_entries WHERE company_id = :company_id AND (' . implode(' OR ', $where) . ') ORDER BY id DESC LIMIT ' . max(1, $limit));
        $statement->execute($params);

        return $this->format($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function stats(int $companyId): array
    {
        if (!Database::available()) {
            return ['entries' => 0, 'sources' => 0];
        }

        $statement = Database::connection()->prepare('SELECT COUNT(*) AS entries, COUNT(DISTINCT source_id) AS sources FROM memory_entries WHERE company_id = :company_id');
        $statement->execute(['company_id' => $companyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: ['entries' => 0, 'sources' => 0];

        return ['entries' => (int) $row['entries'], 'sources' => (int) $row['sources']];
    }

    private function chunk(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $chunks = [];
        $length = strlen($content);
        $size = 3500;
        $overlap = 350;
        for ($offset = 0; $offset < $length; $offset += ($size - $overlap)) {
            $chunk = trim(substr($content, $offset, $size));
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
        }

        return $chunks;
    }

    private function keywords(string $query): array
    {
        $query = strtolower(preg_replace('/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ ]/u', ' ', $query) ?? $query);
        $parts = preg_split('/\s+/', $query) ?: [];
        $stop = ['para', 'como', 'donde', 'cuando', 'este', 'esta', 'estos', 'estas', 'sobre', 'busca', 'buscar', 'documento', 'documentos', 'AsisFly', 'dime', 'mis', 'los', 'las', 'una', 'uno', 'del'];

        return array_values(array_unique(array_filter($parts, fn (string $term): bool => strlen($term) >= 3 && !in_array($term, $stop, true))));
    }

    private function format(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'title' => $row['title'],
            'content' => strlen($row['content']) > 900 ? substr($row['content'], 0, 897) . '...' : $row['content'],
            'score' => (float) ($row['score'] ?? 0),
        ], $rows);
    }
}
