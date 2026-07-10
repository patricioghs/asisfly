<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

final class KnowledgeRetrievalService
{
    public function search(int $companyId, string $query, int $limit = 6): array
    {
        if ($companyId <= 0 || trim($query) === '' || !$this->ready()) {
            return [];
        }

        $terms = $this->keywords($query);
        if (!$terms) {
            return [];
        }

        $booleanQuery = implode(' ', array_map(fn (string $term): string => $term . '*', $terms));
        $limit = max(1, min(12, $limit));

        try {
            $statement = Database::connection()->prepare(
                'SELECT c.id, c.source_id, c.document_id, c.title, c.content, c.token_estimate,
                        s.name AS source_name, s.category, s.tags,
                        MATCH(c.title, c.content) AGAINST (:query IN BOOLEAN MODE) AS score
                 FROM ai_knowledge_chunks c
                 INNER JOIN ai_knowledge_sources s ON s.id = c.source_id AND s.company_id = c.company_id
                 WHERE c.company_id = :company_id
                   AND c.status = "ready"
                   AND s.status = "ready"
                   AND MATCH(c.title, c.content) AGAINST (:query IN BOOLEAN MODE)
                 ORDER BY score DESC, c.retrieval_weight DESC, c.id DESC
                 LIMIT ' . $limit
            );
            $statement->execute(['company_id' => $companyId, 'query' => $booleanQuery]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                return $this->format($rows);
            }
        } catch (Throwable) {
            // Fallback below keeps training usable if FULLTEXT is unavailable.
        }

        $where = [];
        $params = ['company_id' => $companyId];
        foreach (array_slice($terms, 0, 6) as $index => $term) {
            $key = 'term' . $index;
            $where[] = "(c.title LIKE :{$key} OR c.content LIKE :{$key} OR s.name LIKE :{$key} OR s.tags LIKE :{$key})";
            $params[$key] = '%' . $term . '%';
        }

        $statement = Database::connection()->prepare(
            'SELECT c.id, c.source_id, c.document_id, c.title, c.content, c.token_estimate,
                    s.name AS source_name, s.category, s.tags, 0 AS score
             FROM ai_knowledge_chunks c
             INNER JOIN ai_knowledge_sources s ON s.id = c.source_id AND s.company_id = c.company_id
             WHERE c.company_id = :company_id
               AND c.status = "ready"
               AND s.status = "ready"
               AND (' . implode(' OR ', $where) . ')
             ORDER BY c.retrieval_weight DESC, c.id DESC
             LIMIT ' . $limit
        );
        $statement->execute($params);

        return $this->format($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function logContext(int $companyId, int $userId, string $module, string $query, array $sources, string $context): int
    {
        if (!$this->ready()) {
            return 0;
        }

        try {
            Database::connection()->prepare(
                'INSERT INTO ai_context_logs (company_id, user_id, module, query_text, context_summary, sources_json, token_estimate)
                 VALUES (:company_id, :user_id, :module, :query_text, :context_summary, :sources_json, :token_estimate)'
            )->execute([
                'company_id' => $companyId,
                'user_id' => $userId ?: null,
                'module' => $module,
                'query_text' => substr($query, 0, 5000),
                'context_summary' => substr($context, 0, 5000),
                'sources_json' => json_encode($this->sourceTrace($sources), JSON_UNESCAPED_UNICODE),
                'token_estimate' => max(1, (int) ceil(strlen($context) / 4)),
            ]);

            return (int) Database::connection()->lastInsertId();
        } catch (Throwable) {
            return 0;
        }
    }

    public function recordGeneratedResponse(int $companyId, int $userId, int $contextLogId, array $data): int
    {
        if (!$this->ready() || trim((string) ($data['generated_response'] ?? '')) === '') {
            return 0;
        }

        try {
            Database::connection()->prepare(
                'INSERT INTO ai_generated_responses (company_id, user_id, context_log_id, module, channel, customer_message, generated_response, confidence, sources_json, status)
                 VALUES (:company_id, :user_id, :context_log_id, :module, :channel, :customer_message, :generated_response, :confidence, :sources_json, :status)'
            )->execute([
                'company_id' => $companyId,
                'user_id' => $userId ?: null,
                'context_log_id' => $contextLogId ?: null,
                'module' => (string) ($data['module'] ?? 'Entrenamiento IA'),
                'channel' => $data['channel'] ?? null,
                'customer_message' => $data['customer_message'] ?? null,
                'generated_response' => (string) $data['generated_response'],
                'confidence' => isset($data['confidence']) ? (int) $data['confidence'] : null,
                'sources_json' => json_encode($data['sources'] ?? [], JSON_UNESCAPED_UNICODE),
                'status' => (string) ($data['status'] ?? 'draft'),
            ]);

            return (int) Database::connection()->lastInsertId();
        } catch (Throwable) {
            return 0;
        }
    }

    private function format(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'source_id' => (int) $row['source_id'],
            'document_id' => (int) ($row['document_id'] ?? 0),
            'title' => (string) $row['title'],
            'content' => strlen((string) $row['content']) > 950 ? substr((string) $row['content'], 0, 947) . '...' : (string) $row['content'],
            'score' => (float) ($row['score'] ?? 0),
            'token_estimate' => (int) ($row['token_estimate'] ?? 0),
            'source_name' => (string) ($row['source_name'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'tags' => (string) ($row['tags'] ?? ''),
        ], $rows);
    }

    private function sourceTrace(array $sources): array
    {
        return array_map(fn (array $source): array => [
            'chunk_id' => $source['id'] ?? null,
            'source_id' => $source['source_id'] ?? null,
            'document_id' => $source['document_id'] ?? null,
            'title' => $source['title'] ?? null,
            'source_name' => $source['source_name'] ?? null,
            'score' => $source['score'] ?? null,
        ], $sources);
    }

    private function keywords(string $query): array
    {
        $query = strtolower(preg_replace('/[^a-zA-Z0-9 ]/', ' ', $this->normalize($query)) ?? $query);
        $parts = preg_split('/\s+/', $query) ?: [];
        $stop = ['para', 'como', 'donde', 'cuando', 'este', 'esta', 'estos', 'estas', 'sobre', 'busca', 'buscar', 'documento', 'documentos', 'dime', 'mis', 'los', 'las', 'una', 'uno', 'del', 'que'];

        return array_values(array_unique(array_filter($parts, fn (string $term): bool => strlen($term) >= 3 && !in_array($term, $stop, true))));
    }

    private function normalize(string $value): string
    {
        if (!function_exists('iconv')) {
            return $value;
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return is_string($ascii) ? $ascii : $value;
    }

    private function ready(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM ai_knowledge_chunks LIMIT 1');
            Database::connection()->query('SELECT 1 FROM ai_knowledge_sources LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
