<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class BrandRoutingDetector
{
    public function detect(int $companyId, array $message): array
    {
        $settings = $this->settings($companyId);
        $routes = $this->activeRoutes($companyId);
        $text = $this->messageText($message);

        if ($text === '' || !$routes) {
            return $this->emptyDetection('No hay suficiente texto o rutas de marca configuradas.', $settings);
        }

        $ranked = [];
        foreach ($routes as $route) {
            $score = 0;
            $matched = [];

            foreach ($this->weightedTerms($route) as $term => $weight) {
                if ($term !== '' && $this->contains($text, $term)) {
                    $score += $weight;
                    $matched[] = $term;
                }
            }

            if ($score > 0) {
                $score += min(6, max(0, (int) ($route['priority'] ?? 0)) / 20);
                $ranked[] = [
                    'route' => $route,
                    'confidence' => min(96, (int) round($score)),
                    'matched_terms' => array_values(array_unique($matched)),
                ];
            }
        }

        if (!$ranked) {
            return $this->emptyDetection('No se encontraron coincidencias claras con las marcas configuradas.', $settings);
        }

        usort($ranked, fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);
        $best = $ranked[0];
        $route = $best['route'];
        $confidence = (int) $best['confidence'];
        $threshold = (int) ($settings['min_confidence'] ?? 65);
        $status = $confidence >= $threshold ? 'suggested' : 'uncertain';

        return [
            'route_id' => (int) $route['id'],
            'brand_name' => (string) $route['brand_name'],
            'target_company_name' => (string) $route['target_company_name'],
            'confidence' => $confidence,
            'status' => $status,
            'reason' => $status === 'suggested'
                ? 'Coincidencia pasiva por palabras y contexto del mensaje.'
                : 'Coincidencia encontrada, pero bajo el umbral configurado.',
            'matched_terms' => $best['matched_terms'],
            'ambiguous_action' => (string) ($settings['ambiguous_action'] ?? 'ask_customer'),
            'min_confidence' => $threshold,
        ];
    }

    public function record(int $companyId, ?int $conversationId, ?int $messageId, array $detection): void
    {
        try {
            Database::connection()->prepare(
                'INSERT INTO ai_brand_route_detections
                    (company_id, conversation_id, message_id, brand_route_id, brand_name, target_company_name, confidence, status, reason, matched_terms_json, source)
                 VALUES
                    (:company_id, :conversation_id, :message_id, :brand_route_id, :brand_name, :target_company_name, :confidence, :status, :reason, :matched_terms_json, "passive")'
            )->execute([
                'company_id' => $companyId,
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'brand_route_id' => !empty($detection['route_id']) ? (int) $detection['route_id'] : null,
                'brand_name' => $detection['brand_name'] ?? null,
                'target_company_name' => $detection['target_company_name'] ?? null,
                'confidence' => max(0, min(100, (int) ($detection['confidence'] ?? 0))),
                'status' => in_array(($detection['status'] ?? ''), ['suggested', 'uncertain', 'none'], true) ? $detection['status'] : 'none',
                'reason' => (string) ($detection['reason'] ?? ''),
                'matched_terms_json' => json_encode($detection['matched_terms'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable) {
            // Passive routing must never interrupt message reception.
        }
    }

    public function activeRoutes(int $companyId): array
    {
        try {
            $statement = Database::connection()->prepare(
                'SELECT id, brand_name, target_company_name, description, keywords, products_services, typical_phrases, channels, priority, status
                 FROM ai_brand_routes
                 WHERE company_id = :company_id AND status = "active"
                 ORDER BY priority DESC, brand_name'
            );
            $statement->execute(['company_id' => $companyId]);
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    public function latestForConversation(int $companyId, int $conversationId): ?array
    {
        if ($conversationId <= 0) {
            return null;
        }

        try {
            $statement = Database::connection()->prepare(
                'SELECT d.*, r.brand_name AS configured_brand_name, r.target_company_name AS configured_target_company_name
                 FROM ai_brand_route_detections d
                 LEFT JOIN ai_brand_routes r ON r.id = d.brand_route_id AND r.company_id = d.company_id
                 WHERE d.company_id = :company_id AND d.conversation_id = :conversation_id
                 ORDER BY CASE WHEN d.source = "manual" THEN 0 ELSE 1 END, d.id DESC
                 LIMIT 1'
            );
            $statement->execute(['company_id' => $companyId, 'conversation_id' => $conversationId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            $terms = json_decode((string) ($row['matched_terms_json'] ?? '[]'), true);

            return [
                'id' => (int) ($row['id'] ?? 0),
                'route_id' => !empty($row['brand_route_id']) ? (int) $row['brand_route_id'] : null,
                'brand_name' => (string) (($row['configured_brand_name'] ?? '') ?: ($row['brand_name'] ?? '')),
                'target_company_name' => (string) (($row['configured_target_company_name'] ?? '') ?: ($row['target_company_name'] ?? '')),
                'confidence' => (int) ($row['confidence'] ?? 0),
                'status' => (string) ($row['status'] ?? 'none'),
                'reason' => (string) ($row['reason'] ?? ''),
                'matched_terms' => is_array($terms) ? $terms : [],
                'source' => (string) ($row['source'] ?? 'passive'),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function confirmRoute(int $companyId, int $conversationId, int $routeId, int $userId): array
    {
        $route = $this->routeById($companyId, $routeId);
        if (!$route) {
            return ['ok' => false, 'message' => 'La marca seleccionada no existe o no pertenece a esta empresa.'];
        }

        $previous = $this->latestForConversation($companyId, $conversationId);
        $status = !empty($previous['route_id']) && (int) $previous['route_id'] !== $routeId ? 'corrected' : 'confirmed';
        $reason = $status === 'corrected'
            ? 'Marca corregida manualmente desde Centro de Supervision.'
            : 'Marca confirmada manualmente desde Centro de Supervision.';

        try {
            Database::connection()->prepare(
                'INSERT INTO ai_brand_route_detections
                    (company_id, conversation_id, message_id, brand_route_id, brand_name, target_company_name, confidence, status, reason, matched_terms_json, source)
                 VALUES
                    (:company_id, :conversation_id, NULL, :brand_route_id, :brand_name, :target_company_name, 100, :status, :reason, :matched_terms_json, "manual")'
            )->execute([
                'company_id' => $companyId,
                'conversation_id' => $conversationId,
                'brand_route_id' => $routeId,
                'brand_name' => (string) $route['brand_name'],
                'target_company_name' => (string) $route['target_company_name'],
                'status' => $status,
                'reason' => $reason . ' Usuario #' . $userId . '.',
                'matched_terms_json' => json_encode(['manual'], JSON_UNESCAPED_UNICODE),
            ]);

            return ['ok' => true, 'message' => $status === 'corrected' ? 'Marca corregida para esta conversacion.' : 'Marca confirmada para esta conversacion.'];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'message' => 'No se pudo guardar la marca: ' . $exception->getMessage()];
        }
    }

    private function routeById(int $companyId, int $routeId): ?array
    {
        if ($routeId <= 0) {
            return null;
        }

        try {
            $statement = Database::connection()->prepare('SELECT * FROM ai_brand_routes WHERE company_id = :company_id AND id = :id AND status = "active" LIMIT 1');
            $statement->execute(['company_id' => $companyId, 'id' => $routeId]);
            $route = $statement->fetch(PDO::FETCH_ASSOC);
            return $route ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function settings(int $companyId): array
    {
        try {
            $statement = Database::connection()->prepare('SELECT ambiguous_action, min_confidence FROM ai_routing_settings WHERE company_id = :company_id LIMIT 1');
            $statement->execute(['company_id' => $companyId]);
            $settings = $statement->fetch(PDO::FETCH_ASSOC);
            return $settings ?: ['ambiguous_action' => 'ask_customer', 'min_confidence' => 65];
        } catch (\Throwable) {
            return ['ambiguous_action' => 'ask_customer', 'min_confidence' => 65];
        }
    }

    private function emptyDetection(string $reason, array $settings): array
    {
        return [
            'route_id' => null,
            'brand_name' => null,
            'target_company_name' => null,
            'confidence' => 0,
            'status' => 'none',
            'reason' => $reason,
            'matched_terms' => [],
            'ambiguous_action' => (string) ($settings['ambiguous_action'] ?? 'ask_customer'),
            'min_confidence' => (int) ($settings['min_confidence'] ?? 65),
        ];
    }

    private function messageText(array $message): string
    {
        return trim(implode(' ', array_filter([
            $message['subject'] ?? '',
            $message['body'] ?? '',
            $message['from'] ?? '',
            $message['sender_name'] ?? '',
            $message['customer_name'] ?? '',
            $message['customer_handle'] ?? '',
        ])));
    }

    private function weightedTerms(array $route): array
    {
        $terms = [];
        $this->addTerm($terms, (string) ($route['brand_name'] ?? ''), 35);
        $this->addTerm($terms, (string) ($route['target_company_name'] ?? ''), 28);

        foreach ($this->splitTerms((string) ($route['keywords'] ?? '')) as $term) {
            $this->addTerm($terms, $term, 10);
        }

        foreach ($this->splitTerms((string) ($route['products_services'] ?? '')) as $term) {
            $this->addTerm($terms, $term, 7);
        }

        foreach ($this->splitTerms((string) ($route['typical_phrases'] ?? '')) as $term) {
            $this->addTerm($terms, $term, 12);
        }

        foreach (array_slice($this->splitTerms((string) ($route['description'] ?? '')), 0, 8) as $term) {
            $this->addTerm($terms, $term, 4);
        }

        return $terms;
    }

    private function addTerm(array &$terms, string $term, int $weight): void
    {
        $term = trim($term);
        if ($term === '' || strlen($term) < 3) {
            return;
        }

        $terms[$term] = max($terms[$term] ?? 0, $weight);
    }

    private function splitTerms(string $value): array
    {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }

        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,;|]+/', $value) ?: [])));
    }

    private function contains(string $text, string $term): bool
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $term = function_exists('mb_strtolower') ? mb_strtolower($term, 'UTF-8') : strtolower($term);

        return str_contains($text, $term);
    }
}
