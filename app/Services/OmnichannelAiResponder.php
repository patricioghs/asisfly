<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\AiProviderRepository;
use App\Repositories\MemoryRepository;
use App\Repositories\TenantRepository;
use Throwable;

final class OmnichannelAiResponder
{
    public function draft(int $companyId, array $account, array $message, array $decision, array $history = [], int $userId = 0): array
    {
        $fallback = $this->fallbackDraft($account, $message);
        $settingsRepo = new AiProviderRepository();
        $tenantRepo = new TenantRepository();
        $settings = $settingsRepo->settings($companyId);
        $company = $this->company($companyId);
        $assistant = $tenantRepo->assistant($companyId);
        $route = $this->routeForAccount($account);
        $brandRoute = is_array($message['brand_route'] ?? null) ? $message['brand_route'] : [];
        if ($brandRoute) {
            $route['brand_route'] = $brandRoute;
        }
        $prompt = $this->prompt($account, $message, $decision, $history);
        $estimatedPromptTokens = $this->estimateTokens($prompt) + 680;
        $limit = $settingsRepo->canUseAi($companyId, $estimatedPromptTokens + 450, $settings);
        $provider = (string) ($settings['provider'] ?? 'simulated');
        $model = (string) ($settings['model'] ?? 'asisfly-demo-latam');
        $promptTokens = $estimatedPromptTokens;
        $completionTokens = $this->estimateTokens($fallback);
        $status = 'fallback';
        $errorMessage = null;
        $requestId = null;
        $draft = $fallback;

        $systemUser = [
            'id' => 0,
            'role' => 'Sistema',
            'permissions' => ['chat.use', 'documents.manage'],
        ];
        $contextBuilder = new AiContextBuilder();
        $aiContext = $contextBuilder->build($company, $systemUser, $route, $prompt);
        $toolRegistry = new AiToolRegistry();
        $explicitTools = $toolRegistry->resolveTools($companyId, $systemUser, ['omnichannel.assist', 'memory.search']);
        $aiContext['tools']['allowed'] = array_merge($aiContext['tools']['allowed'] ?? [], $explicitTools['allowed']);
        $aiContext['tools']['blocked'] = array_merge($aiContext['tools']['blocked'] ?? [], $explicitTools['blocked']);
        $trainingBuilder = new AITrainingContextBuilder();
        $trainingContext = $trainingBuilder->build($companyId, $prompt, $history);
        $trainingPrompt = $trainingBuilder->renderForPrompt($trainingContext);
        $knowledgeSources = $trainingContext['knowledge'] ?: ($trainingContext['documents'] ?? []);
        $knowledge = new KnowledgeRetrievalService();
        $contextLogId = $knowledge->logContext($companyId, $userId, 'Omnicanal', $prompt, $knowledgeSources, $trainingPrompt);
        $memory = $contextBuilder->isToolAllowed($aiContext, 'memory.search')
            ? ($trainingContext['documents'] ?: (new MemoryRepository())->search($companyId, $prompt, 4))
            : [];

        if (!$contextBuilder->isToolAllowed($aiContext, 'omnichannel.assist')) {
            $blocked = $contextBuilder->blockedTool($aiContext, 'omnichannel.assist');
            $errorMessage = (string) ($blocked['reason'] ?? 'Omnicanal no autorizado para esta empresa.');
            $toolRegistry->audit($companyId, null, 'omnichannel.assist', 'blocked', $errorMessage, [
                'account_id' => $account['id'] ?? null,
                'channel' => $account['channel'] ?? null,
            ]);
        } elseif (!$limit['allowed']) {
            $errorMessage = (string) $limit['reason'];
        } elseif ($provider === 'openai') {
            try {
                $toolRegistry->audit($companyId, null, 'omnichannel.assist', 'allowed', null, [
                    'account_id' => $account['id'] ?? null,
                    'channel' => $account['channel'] ?? null,
                ]);
                $real = (new OpenAiClient())->respond($prompt, $settings, [
                    'company' => $company,
                    'assistant' => $assistant,
                    'route' => $route,
                    'memory' => $memory,
                    'ai_context' => $aiContext,
                    'training_context' => $trainingPrompt,
                    'history' => $this->historyForOpenAi($history),
                ]);
                $draft = $this->cleanDraft((string) $real['text'], $assistant, $fallback);
                $promptTokens = (int) ($real['prompt_tokens'] ?: $estimatedPromptTokens);
                $completionTokens = (int) ($real['completion_tokens'] ?: $this->estimateTokens($draft));
                $requestId = $real['request_id'] ?? null;
                $status = 'success';
                $toolRegistry->audit($companyId, null, 'omnichannel.assist', 'executed', null, [
                    'account_id' => $account['id'] ?? null,
                    'channel' => $account['channel'] ?? null,
                    'memory_hits' => count($memory),
                ]);
            } catch (Throwable $exception) {
                $errorMessage = $exception->getMessage();
                $provider = (string) ($settings['fallback_provider'] ?: 'simulated');
                $model = (string) ($settings['fallback_model'] ?: 'asisfly-demo-latam');
                $toolRegistry->audit($companyId, null, 'omnichannel.assist', 'fallback', $errorMessage, [
                    'account_id' => $account['id'] ?? null,
                    'channel' => $account['channel'] ?? null,
                ]);
            }
        } else {
            $errorMessage = 'Proveedor IA simulado o no implementado para omnicanal.';
        }

        $cost = $this->estimateCost($provider, $model, $promptTokens, $completionTokens);
        $tenantRepo->logAiUsage($companyId, null, 'Omnicanal', $provider, $model, $promptTokens, $completionTokens, $cost, [
            'prompt_text' => $prompt,
            'response_text' => $draft,
            'status' => $status,
            'error_message' => $errorMessage,
            'request_id' => $requestId,
            'metadata' => [
                'channel' => $account['channel'] ?? null,
                'account_id' => $account['id'] ?? null,
                'brand_route' => $brandRoute,
                'decision' => $decision,
                'memory_hits' => count($memory),
                'knowledge_hits' => count($knowledgeSources),
                'context_log_id' => $contextLogId,
            ],
        ]);

        $generatedResponseId = $knowledge->recordGeneratedResponse($companyId, $userId, $contextLogId, [
            'module' => 'Omnicanal',
            'channel' => (string) ($account['channel'] ?? 'omnichannel'),
            'customer_message' => (string) ($message['body'] ?? ''),
            'generated_response' => $draft,
            'confidence' => (int) ($decision['confidence'] ?? 0),
            'sources' => [
                'documents' => count($trainingContext['documents'] ?? []),
                'knowledge' => count($trainingContext['knowledge'] ?? []),
                'faqs' => count($trainingContext['faqs'] ?? []),
                'products' => count($trainingContext['products'] ?? []),
                'examples' => count($trainingContext['examples'] ?? []),
                'context_log_id' => $contextLogId,
            ],
            'status' => 'draft',
        ]);

        return [
            'body' => $draft,
            'status' => $status,
            'provider' => $provider,
            'model' => $model,
            'error' => $errorMessage,
            'memory_hits' => count($memory),
            'knowledge_hits' => count($knowledgeSources),
            'context_log_id' => $contextLogId,
            'generated_response_id' => $generatedResponseId,
            'brand_route' => $brandRoute,
            'confidence' => (int) ($decision['confidence'] ?? 0),
        ];
    }

    private function prompt(array $account, array $message, array $decision, array $history): string
    {
        $recent = [];
        foreach (array_slice($history, -6) as $item) {
            $recent[] = '[' . ($item['direction'] ?? 'msg') . '] ' . ($item['sender_name'] ?? '-') . ': ' . trim((string) ($item['body'] ?? ''));
        }

        return implode("\n", array_filter([
            'Redacta SOLO el cuerpo de una respuesta omnicanal para el cliente.',
            'No incluyas asunto, markdown, analisis interno ni explicaciones.',
            'Canal: ' . ($account['channel'] ?? 'Omnicanal') . '. Cuenta: ' . ($account['display_name'] ?? 'Cuenta conectada') . '.',
            'Cliente: ' . ($message['customer_name'] ?? 'Cliente') . ' <' . ($message['customer_handle'] ?? '-') . '>.',
            'Asunto: ' . ($message['subject'] ?? 'Nueva conversacion') . '.',
            'Mensaje recibido: ' . ($message['body'] ?? ''),
            'Decision IA: riesgo ' . ($decision['risk'] ?? 'medium') . ', confianza ' . ($decision['confidence'] ?? 0) . '%, modo ' . ($decision['mode'] ?? 'approval_required') . '.',
            $this->brandRoutePrompt($message['brand_route'] ?? null),
            $recent ? 'Historial reciente:' . "\n" . implode("\n", $recent) : null,
            'Objetivo: responder de forma util, breve y comercialmente segura. Si falta informacion, pedir el dato exacto y no inventar precios, stock, plazos ni descuentos.',
        ]));
    }

    private function brandRoutePrompt(mixed $brandRoute): ?string
    {
        if (!is_array($brandRoute) || empty($brandRoute['brand_name'])) {
            return null;
        }

        return implode("\n", array_filter([
            'Marca/negocio confirmado o sugerido para esta conversacion:',
            '- Marca: ' . (string) $brandRoute['brand_name'],
            !empty($brandRoute['target_company_name']) ? '- Empresa/linea: ' . (string) $brandRoute['target_company_name'] : null,
            '- Confianza: ' . (string) ($brandRoute['confidence'] ?? 0) . '%',
            !empty($brandRoute['status']) ? '- Estado de enrutamiento: ' . (string) $brandRoute['status'] : null,
            'Usa este contexto para adaptar tono, productos, agenda y respuesta. Si la marca no esta confirmada o el mensaje es ambiguo, pide aclaracion antes de asumir.',
        ]));
    }

    private function fallbackDraft(array $account, array $message): string
    {
        $name = trim((string) ($message['customer_name'] ?? ''));
        $greeting = $name !== '' ? 'Hola ' . $name . ',' : 'Hola,';
        $body = trim((string) ($message['body'] ?? ''));
        $summary = strlen($body) > 180 ? substr($body, 0, 177) . '...' : $body;
        $channel = (string) ($account['channel'] ?? 'canal');

        return $greeting . "\n\nGracias por escribirnos. Recibi tu mensaje por {$channel}: \"" . $summary . "\".\n\nTe ayudo con esto. Para darte una respuesta correcta, revisare la informacion de la empresa y te confirmare el siguiente paso a la brevedad.\n\nSaludos,\nAsisFly";
    }

    private function cleanDraft(string $draft, array $assistant, string $fallback): string
    {
        $draft = trim(strip_tags($draft));
        $draft = preg_replace('/^\s*(asunto|subject)\s*:.+$/im', '', $draft) ?? $draft;
        $draft = trim($draft);
        if ($draft === '') {
            return $fallback;
        }

        $signature = trim((string) ($assistant['signature'] ?? ''));
        if ($signature !== '' && !str_contains($draft, $signature)) {
            $draft .= "\n\n" . $signature;
        }

        return strlen($draft) > 3000 ? substr($draft, 0, 3000) : $draft;
    }

    private function historyForOpenAi(array $history): array
    {
        return array_map(fn (array $item): array => [
            'role' => ($item['direction'] ?? '') === 'outbound' ? 'assistant' : 'user',
            'content' => trim((string) ($item['body'] ?? '')),
        ], array_values(array_filter($history, fn (array $item): bool => trim((string) ($item['body'] ?? '')) !== '')));
    }

    private function routeForAccount(array $account): array
    {
        return match ((string) ($account['brain_key'] ?? 'commercial')) {
            'administrative' => ['brain' => 'administrative', 'name' => 'Cerebro Administrativo', 'module' => 'Administracion'],
            'analytical' => ['brain' => 'analytical', 'name' => 'Cerebro Analitico', 'module' => 'Analitica'],
            'operational' => ['brain' => 'operational', 'name' => 'Cerebro Operacional', 'module' => 'Operaciones'],
            'executive' => ['brain' => 'executive', 'name' => 'Cerebro Ejecutivo', 'module' => 'Direccion'],
            default => ['brain' => 'commercial', 'name' => 'Cerebro Comercial', 'module' => 'Comercial'],
        };
    }

    private function company(int $companyId): array
    {
        if (!Database::available()) {
            return ['id' => $companyId, 'name' => 'Empresa', 'country' => 'LatAm', 'currency' => 'USD'];
        }

        try {
            $statement = Database::connection()->prepare('SELECT c.*, p.name AS plan FROM companies c LEFT JOIN plans p ON p.id = c.plan_id WHERE c.id = :id LIMIT 1');
            $statement->execute(['id' => $companyId]);
            $company = $statement->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            $company = [];
        }

        return [
            'id' => $companyId,
            'name' => $company['name'] ?? 'Empresa',
            'country' => $company['country'] ?? 'LatAm',
            'currency' => $company['currency'] ?? 'USD',
            'timezone' => $company['timezone'] ?? 'America/Santiago',
            'plan' => $company['plan'] ?? 'Starter',
        ];
    }

    private function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(strlen($text) / 4));
    }

    private function estimateCost(string $provider, string $model, int $promptTokens, int $completionTokens): float
    {
        if ($provider !== 'openai') {
            return ($promptTokens + $completionTokens) / 1000 * 0.002;
        }

        $rates = [
            'gpt-4.1-mini' => [0.0004, 0.0016],
            'gpt-4.1' => [0.0020, 0.0080],
            'gpt-4o-mini' => [0.00015, 0.0006],
            'gpt-4o' => [0.0025, 0.0100],
        ];
        [$inputPer1k, $outputPer1k] = $rates[$model] ?? [0.0010, 0.0040];

        return ($promptTokens / 1000 * $inputPer1k) + ($completionTokens / 1000 * $outputPer1k);
    }
}
