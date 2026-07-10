<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\MemoryRepository;
use Throwable;

final class AITrainingDocumentProcessor
{
    private const MAX_BYTES = 15728640;
    private const EXTENSIONS = ['pdf', 'docx', 'xlsx', 'csv', 'txt'];

    public function processUpload(int $companyId, int $userId, array $file, array $metadata = []): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'message' => 'El esquema de conocimiento no esta instalado.', 'source_id' => 0, 'document_id' => 0, 'chunks' => 0];
        }

        $validation = $this->validate($file);
        if (!$validation['ok']) {
            return ['ok' => false, 'message' => $validation['message'], 'source_id' => 0, 'document_id' => 0, 'chunks' => 0];
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $file['name']);
        $relativePath = 'uploads/company_' . $companyId . '/' . date('YmdHis') . '_' . $safeName;
        $absoluteDir = dirname(__DIR__, 2) . '/storage/uploads/company_' . $companyId;
        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0775, true);
        }
        $absolutePath = dirname(__DIR__, 2) . '/storage/' . $relativePath;

        if (is_uploaded_file((string) $file['tmp_name'])) {
            move_uploaded_file((string) $file['tmp_name'], $absolutePath);
        } elseif (is_file((string) $file['tmp_name'])) {
            copy((string) $file['tmp_name'], $absolutePath);
        } else {
            return ['ok' => false, 'message' => 'No se pudo leer el archivo temporal.', 'source_id' => 0, 'document_id' => 0, 'chunks' => 0];
        }

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();

            $pdo->prepare(
                'INSERT INTO documents (company_id, uploaded_by, original_name, mime_type, storage_path, document_type, processing_status, summary)
                 VALUES (:company_id, :uploaded_by, :original_name, :mime_type, :storage_path, :document_type, "processing", :summary)'
            )->execute([
                'company_id' => $companyId,
                'uploaded_by' => $userId,
                'original_name' => (string) $file['name'],
                'mime_type' => $file['type'] ?? null,
                'storage_path' => $relativePath,
                'document_type' => (string) ($metadata['category'] ?? 'Entrenamiento IA'),
                'summary' => 'Fuente cargada desde Centro de Entrenamiento IA.',
            ]);
            $documentId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO ai_knowledge_sources (company_id, document_id, source_type, name, category, tags, mime_type, file_size, version, status, uploaded_by)
                 VALUES (:company_id, :document_id, "document", :name, :category, :tags, :mime_type, :file_size, 1, "processing", :uploaded_by)'
            )->execute([
                'company_id' => $companyId,
                'document_id' => $documentId,
                'name' => (string) $file['name'],
                'category' => trim((string) ($metadata['category'] ?? 'Entrenamiento IA')),
                'tags' => trim((string) ($metadata['tags'] ?? '')),
                'mime_type' => $file['type'] ?? null,
                'file_size' => (int) ($file['size'] ?? 0),
                'uploaded_by' => $userId ?: null,
            ]);
            $sourceId = (int) $pdo->lastInsertId();

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'message' => $exception->getMessage(), 'source_id' => 0, 'document_id' => 0, 'chunks' => 0];
        }

        return $this->processSource($companyId, $sourceId, $documentId, $absolutePath, (string) $file['name'], $file['type'] ?? null);
    }

    public function processExistingDocument(int $companyId, int $documentId): array
    {
        if (!$this->ready() || $documentId <= 0) {
            return ['ok' => false, 'message' => 'Documento invalido.', 'source_id' => 0, 'document_id' => $documentId, 'chunks' => 0];
        }

        $statement = Database::connection()->prepare('SELECT * FROM documents WHERE company_id = :company_id AND id = :id LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'id' => $documentId]);
        $document = $statement->fetch(\PDO::FETCH_ASSOC);
        if (!$document) {
            return ['ok' => false, 'message' => 'Documento no encontrado para esta empresa.', 'source_id' => 0, 'document_id' => $documentId, 'chunks' => 0];
        }

        $sourceId = $this->ensureSourceForDocument($companyId, $document);
        $absolutePath = dirname(__DIR__, 2) . '/storage/' . (string) $document['storage_path'];

        return $this->processSource($companyId, $sourceId, $documentId, $absolutePath, (string) $document['original_name'], $document['mime_type'] ?? null);
    }

    private function processSource(int $companyId, int $sourceId, int $documentId, string $absolutePath, string $originalName, ?string $mimeType): array
    {
        $extraction = (new DocumentTextExtractor())->extract($absolutePath, $originalName, $mimeType);
        $text = (string) ($extraction['text'] ?? '');
        $chunks = $this->chunk($text);
        $status = $chunks ? 'ready' : 'failed';
        $summary = (string) ($extraction['summary'] ?? '');

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM ai_knowledge_chunks WHERE company_id = :company_id AND source_id = :source_id')
                ->execute(['company_id' => $companyId, 'source_id' => $sourceId]);

            if ($chunks) {
                $insert = $pdo->prepare(
                    'INSERT INTO ai_knowledge_chunks (company_id, source_id, document_id, chunk_index, title, content, content_hash, token_estimate, retrieval_weight, embedding_provider, status)
                     VALUES (:company_id, :source_id, :document_id, :chunk_index, :title, :content, :content_hash, :token_estimate, :retrieval_weight, "text-index-v1", "ready")'
                );
                foreach ($chunks as $index => $chunk) {
                    $insert->execute([
                        'company_id' => $companyId,
                        'source_id' => $sourceId,
                        'document_id' => $documentId,
                        'chunk_index' => $index,
                        'title' => $originalName . ' / fragmento ' . ($index + 1),
                        'content' => $chunk,
                        'content_hash' => hash('sha256', $chunk),
                        'token_estimate' => max(1, (int) ceil(strlen($chunk) / 4)),
                        'retrieval_weight' => $index === 0 ? 1.2 : 1.0,
                    ]);
                }
            }

            $pdo->prepare(
                'UPDATE ai_knowledge_sources
                 SET status = :status, extracted_text = :extracted_text, summary = :summary, error_message = :error_message, processed_at = CURRENT_TIMESTAMP
                 WHERE company_id = :company_id AND id = :id'
            )->execute([
                'status' => $status,
                'extracted_text' => $text !== '' ? $text : null,
                'summary' => $summary . ($chunks ? ' Fragmentos de entrenamiento: ' . count($chunks) . '.' : ' No se generaron fragmentos.'),
                'error_message' => $chunks ? null : 'No se pudo extraer texto suficiente para fragmentar.',
                'company_id' => $companyId,
                'id' => $sourceId,
            ]);

            $pdo->prepare('UPDATE documents SET processing_status = :status, summary = :summary WHERE company_id = :company_id AND id = :id')
                ->execute([
                    'status' => $chunks ? 'processed' : 'failed',
                    'summary' => $summary . ($chunks ? ' Fragmentos de entrenamiento: ' . count($chunks) . '.' : ' No se generaron fragmentos.'),
                    'company_id' => $companyId,
                    'id' => $documentId,
                ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->markFailed($companyId, $sourceId, $documentId, $exception->getMessage());
            return ['ok' => false, 'message' => $exception->getMessage(), 'source_id' => $sourceId, 'document_id' => $documentId, 'chunks' => 0];
        }

        (new MemoryRepository())->indexDocument($companyId, $documentId, $originalName, $text);

        return [
            'ok' => $chunks !== [],
            'message' => $chunks ? 'Documento procesado e indexado para entrenamiento.' : 'Archivo guardado, pero no se pudo extraer texto.',
            'source_id' => $sourceId,
            'document_id' => $documentId,
            'chunks' => count($chunks),
        ];
    }

    private function ensureSourceForDocument(int $companyId, array $document): int
    {
        $statement = Database::connection()->prepare('SELECT id FROM ai_knowledge_sources WHERE company_id = :company_id AND document_id = :document_id LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'document_id' => (int) $document['id']]);
        $sourceId = (int) $statement->fetchColumn();
        if ($sourceId > 0) {
            return $sourceId;
        }

        Database::connection()->prepare(
            'INSERT INTO ai_knowledge_sources (company_id, document_id, source_type, name, category, mime_type, status, uploaded_by)
             VALUES (:company_id, :document_id, "document", :name, :category, :mime_type, "pending", :uploaded_by)'
        )->execute([
            'company_id' => $companyId,
            'document_id' => (int) $document['id'],
            'name' => (string) $document['original_name'],
            'category' => (string) ($document['document_type'] ?? 'Entrenamiento IA'),
            'mime_type' => $document['mime_type'] ?? null,
            'uploaded_by' => $document['uploaded_by'] ?? null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function validate(array $file): array
    {
        if (empty($file['name']) || empty($file['tmp_name'])) {
            return ['ok' => false, 'message' => 'Selecciona un archivo valido.'];
        }
        if (!empty($file['error'])) {
            return ['ok' => false, 'message' => 'El archivo no se cargo correctamente. Codigo: ' . (string) $file['error']];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'message' => 'El archivo supera el limite de 15 MB para esta fase.'];
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONS, true)) {
            return ['ok' => false, 'message' => 'Formato no permitido. Usa PDF, DOCX, XLSX, CSV o TXT.'];
        }

        return ['ok' => true, 'message' => 'ok'];
    }

    private function chunk(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $chunks = [];
        $length = strlen($content);
        $size = 2800;
        $overlap = 280;
        for ($offset = 0; $offset < $length; $offset += ($size - $overlap)) {
            $chunk = trim(substr($content, $offset, $size));
            if (strlen($chunk) >= 40) {
                $chunks[] = $chunk;
            }
        }

        return $chunks;
    }

    private function markFailed(int $companyId, int $sourceId, int $documentId, string $message): void
    {
        try {
            Database::connection()->prepare('UPDATE ai_knowledge_sources SET status = "failed", error_message = :error WHERE company_id = :company_id AND id = :id')
                ->execute(['error' => $message, 'company_id' => $companyId, 'id' => $sourceId]);
            Database::connection()->prepare('UPDATE documents SET processing_status = "failed", summary = :summary WHERE company_id = :company_id AND id = :id')
                ->execute(['summary' => $message, 'company_id' => $companyId, 'id' => $documentId]);
        } catch (Throwable) {
            // Processing errors are reported by the caller.
        }
    }

    private function ready(): bool
    {
        try {
            foreach (['documents', 'ai_knowledge_sources', 'ai_knowledge_chunks'] as $table) {
                Database::connection()->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
