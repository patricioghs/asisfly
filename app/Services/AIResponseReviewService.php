<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

final class AIResponseReviewService
{
    public function record(int $companyId, array $data): void
    {
        if ($companyId <= 0 || !$this->ready()) {
            return;
        }

        $aiResponse = trim((string) ($data['ai_response'] ?? ''));
        $finalResponse = trim((string) ($data['final_response'] ?? ''));
        if ($aiResponse === '' && $finalResponse === '') {
            return;
        }

        $result = (string) ($data['result'] ?? 'approved');
        if (!in_array($result, ['approved', 'edited', 'rejected'], true)) {
            $result = 'approved';
        }

        try {
            Database::connection()->prepare(
                'INSERT INTO ai_response_reviews
                 (company_id, conversation_id, message_id, customer_message, ai_response, final_response, reviewed_by, result, difference_summary, correction_reason, channel, intent, confidence, sources_json)
                 VALUES (:company_id, :conversation_id, :message_id, :customer_message, :ai_response, :final_response, :reviewed_by, :result, :difference_summary, :correction_reason, :channel, :intent, :confidence, :sources_json)'
            )->execute([
                'company_id' => $companyId,
                'conversation_id' => !empty($data['conversation_id']) ? (int) $data['conversation_id'] : null,
                'message_id' => !empty($data['message_id']) ? (int) $data['message_id'] : null,
                'customer_message' => $this->short($data['customer_message'] ?? null, 8000),
                'ai_response' => $this->short($aiResponse, 8000),
                'final_response' => $this->short($finalResponse, 8000),
                'reviewed_by' => !empty($data['reviewed_by']) ? (int) $data['reviewed_by'] : null,
                'result' => $result,
                'difference_summary' => $this->differenceSummary($aiResponse, $finalResponse, $result),
                'correction_reason' => $this->short($data['correction_reason'] ?? null, 1000),
                'channel' => $this->short($data['channel'] ?? null, 60),
                'intent' => $this->short($data['intent'] ?? null, 120),
                'confidence' => isset($data['confidence']) ? max(0, min(100, (int) $data['confidence'])) : null,
                'sources_json' => json_encode($data['sources'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);

            if (!empty($data['generated_response_id'])) {
                $this->markGeneratedResponse($companyId, (int) $data['generated_response_id'], $result === 'approved' ? 'approved' : $result);
            }
        } catch (Throwable) {
            // Training feedback should never block the human workflow.
        }
    }

    public function markGeneratedResponse(int $companyId, int $generatedResponseId, string $status): void
    {
        if ($generatedResponseId <= 0 || !$this->generatedReady()) {
            return;
        }

        $status = in_array($status, ['draft', 'approved', 'edited', 'rejected', 'sent'], true) ? $status : 'draft';

        try {
            Database::connection()->prepare('UPDATE ai_generated_responses SET status = :status WHERE company_id = :company_id AND id = :id')->execute([
                'status' => $status,
                'company_id' => $companyId,
                'id' => $generatedResponseId,
            ]);
        } catch (Throwable) {
            // Best effort only.
        }
    }

    private function differenceSummary(string $aiResponse, string $finalResponse, string $result): string
    {
        if ($result === 'rejected') {
            return 'Respuesta IA rechazada por el equipo.';
        }

        if ($aiResponse === $finalResponse) {
            return 'Aprobada sin cambios.';
        }

        $delta = abs(strlen($finalResponse) - strlen($aiResponse));
        return 'Editada por humano antes de enviar. Diferencia aproximada: ' . $delta . ' caracteres.';
    }

    private function short(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return strlen($value) > $max ? substr($value, 0, $max) : $value;
    }

    private function ready(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM ai_response_reviews LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function generatedReady(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM ai_generated_responses LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
