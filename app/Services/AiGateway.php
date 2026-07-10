<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AiProviderRepository;
use App\Repositories\MemoryRepository;
use App\Repositories\TenantRepository;
use App\Repositories\SocialRepository;
use App\Repositories\ActionRepository;
use App\Repositories\AutonomyRepository;
use Throwable;

final class AiGateway
{
    public function ask(string $prompt, array $company, array $user): array
    {
        $repo = new TenantRepository();
        $aiRepo = new AiProviderRepository();
        $route = (new BrainRouter())->route($prompt);
        $contextBuilder = new AiContextBuilder();
        $aiContext = $contextBuilder->build($company, $user, $route, $prompt);
        $toolRegistry = new AiToolRegistry();
        $primaryTool = $this->primaryToolForRoute($route, $prompt);
        if ($primaryTool !== null && !$contextBuilder->isToolAllowed($aiContext, $primaryTool)) {
            $blocked = $contextBuilder->blockedTool($aiContext, $primaryTool);
            $reason = (string) ($blocked['reason'] ?? 'No autorizado.');
            $toolRegistry->audit((int) $company['id'], (int) ($user['id'] ?? 0), $primaryTool, 'blocked', $reason, [
                'route' => $route,
                'prompt_preview' => substr($prompt, 0, 180),
            ]);

            return [
                'answer' => $this->unauthorizedToolResponse($primaryTool, $reason),
                'tokens' => $this->estimateTokens($prompt),
                'brain' => $route,
                'status' => 'blocked',
            ];
        }
        if ($primaryTool !== null) {
            $toolRegistry->audit((int) $company['id'], (int) ($user['id'] ?? 0), $primaryTool, 'allowed', null, [
                'route' => $route,
            ]);
        }

        $assistant = $repo->assistant((int) $company['id']);
        $memory = [];
        if ($contextBuilder->isToolAllowed($aiContext, 'memory.search')) {
            $toolRegistry->audit((int) $company['id'], (int) ($user['id'] ?? 0), 'memory.search', 'allowed', null, ['route' => $route['module']]);
            $memory = (new MemoryRepository())->search((int) $company['id'], $prompt, 4);
            $toolRegistry->audit((int) $company['id'], (int) ($user['id'] ?? 0), 'memory.search', 'executed', null, ['hits' => count($memory)]);
        } elseif ($contextBuilder->blockedTool($aiContext, 'memory.search')) {
            $blocked = $contextBuilder->blockedTool($aiContext, 'memory.search');
            $toolRegistry->audit((int) $company['id'], (int) ($user['id'] ?? 0), 'memory.search', 'blocked', (string) ($blocked['reason'] ?? 'No autorizado.'), ['route' => $route['module']]);
        }
        $settings = $aiRepo->settings((int) $company['id']);
        $autonomyRepo = new AutonomyRepository();
        $autonomy = $autonomyRepo->profile((int) $company['id']);
        $estimatedPromptTokens = $this->estimateTokens($prompt) + 420;
        $limit = $aiRepo->canUseAi((int) $company['id'], $estimatedPromptTokens + 700, $settings);
        $provider = $settings['provider'];
        $model = $settings['model'];
        $promptTokens = $estimatedPromptTokens;
        $completionTokens = 180;
        $status = 'fallback';
        $errorMessage = null;
        $requestId = null;

        if (!$limit['allowed']) {
            $status = 'blocked';
            $provider = 'simulated';
            $model = 'asisfly-demo-latam';
            $response = $this->blockedResponse((string) $limit['reason']);
        } else {
            try {
                if ($provider === 'openai') {
                    $real = (new OpenAiClient())->respond($prompt, $settings, [
                        'company' => $company,
                        'assistant' => $assistant,
                        'route' => $route,
                        'memory' => $memory,
                        'ai_context' => $aiContext,
                    ]);
                    $response = $real['text'];
                    $promptTokens = $real['prompt_tokens'] > 0 ? $real['prompt_tokens'] : $estimatedPromptTokens;
                    $completionTokens = $real['completion_tokens'] > 0 ? $real['completion_tokens'] : $this->estimateTokens($response);
                    $requestId = $real['request_id'];
                    $status = 'success';
                } else {
                    $response = $this->responseForRoute($route, $prompt, $company, $assistant, $memory);
                    if ($provider === 'simulated') {
                        $status = 'success';
                    } else {
                        $status = 'fallback';
                        $errorMessage = "Proveedor {$provider} preparado, pero aun no implementado en esta fase.";
                    }
                }
            } catch (Throwable $exception) {
                $status = 'fallback';
                $errorMessage = $exception->getMessage();
                $provider = $settings['fallback_provider'] ?: 'simulated';
                $model = $settings['fallback_model'] ?: 'asisfly-demo-latam';
                $response = $this->fallbackResponse($route, $prompt, $company, $assistant, $errorMessage, $memory);
                $completionTokens = $this->estimateTokens($response);
            }
        }

        $totalTokens = $promptTokens + $completionTokens;
        $cost = $this->estimateCost($provider, $model, $promptTokens, $completionTokens);

        if ($route['module'] === 'Asisti Social' && $contextBuilder->isToolAllowed($aiContext, 'social.generate_campaign')) {
            (new SocialRepository())->generateCampaign((int) $company['id'], [
                'brief' => $prompt,
                'product_focus' => 'Producto con buen margen',
                'season' => 'Temporada actual',
                'quantity' => 10,
                'channels' => ['Instagram', 'Facebook', 'LinkedIn', 'TikTok', 'WhatsApp'],
            ], $company);
            $toolRegistry->audit((int) $company['id'], (int) ($user['id'] ?? 0), 'social.generate_campaign', 'executed', null, [
                'route' => $route['module'],
                'posts' => 10,
            ]);
        }

        if ($contextBuilder->isToolAllowed($aiContext, 'action_center.create_suggestion')) {
            $this->createSuggestedAction((int) $company['id'], (int) ($user['id'] ?? 0), $route, $prompt, $autonomy);
            $toolRegistry->audit((int) $company['id'], (int) ($user['id'] ?? 0), 'action_center.create_suggestion', 'executed', null, [
                'route' => $route['module'],
            ]);
        } elseif ($contextBuilder->blockedTool($aiContext, 'action_center.create_suggestion')) {
            $blocked = $contextBuilder->blockedTool($aiContext, 'action_center.create_suggestion');
            $toolRegistry->audit((int) $company['id'], (int) ($user['id'] ?? 0), 'action_center.create_suggestion', 'blocked', (string) ($blocked['reason'] ?? 'No autorizado.'), [
                'route' => $route['module'],
            ]);
        }
        $autonomyRepo->recordSignal((int) $company['id'], 'suggested');

        $repo->logAiUsage((int) $company['id'], (int) ($user['id'] ?? 0), $route['module'], $provider, $model, $promptTokens, $completionTokens, $cost, [
            'prompt_text' => $prompt,
            'response_text' => $response,
            'status' => $status,
            'error_message' => $errorMessage,
            'request_id' => $requestId,
            'metadata' => [
                'brain' => $route['name'],
                'memory_hits' => count($memory),
                'configured_provider' => $settings['provider'],
                'configured_model' => $settings['model'],
                'autonomy_mode' => $autonomy['mode'],
                'learning_progress' => $autonomy['learning_progress'],
                'allowed_tools' => array_keys($aiContext['tools']['allowed'] ?? []),
                'blocked_tools' => array_keys($aiContext['tools']['blocked'] ?? []),
                'active_abilities' => array_column($aiContext['abilities'] ?? [], 'ability_key'),
            ],
        ]);

        $response .= $this->autonomyNotice($autonomy);

        return ['answer' => $response, 'tokens' => $totalTokens, 'brain' => $route, 'status' => $status];
    }

    private function responseForRoute(array $route, string $prompt, array $company, array $assistant, array $memory = []): string
    {
        $shortPrompt = strlen($prompt) > 90 ? substr($prompt, 0, 87) . '...' : $prompt;
        $name = $assistant['name'] ?? 'AsisFly';
        $companyName = $company['name'];

        if ($route['module'] === 'Asisti Social') {
            return "{$name} activo el {$route['name']} para {$companyName}. Cree 10 borradores en Asisti Social con texto del post, imagen sugerida, hashtags, horario recomendado, canal, objetivo, CTA y estado draft. Puedes revisarlos en el calendario editorial antes de aprobar o programar.";
        }

        $memoryText = $memory
            ? ' Encontre ' . count($memory) . ' fragmentos relevantes en la memoria empresarial: ' . implode(' | ', array_map(fn (array $item): string => $item['title'], $memory)) . '. Contexto clave: ' . $this->memoryPreview($memory)
            : ' No encontre contexto documental relevante en la memoria empresarial.';

        return "{$name} enruto la solicitud al {$route['name']}. Entendi: \"{$shortPrompt}\".{$memoryText} En esta fase preparo la ejecucion, separo datos por empresa, registro consumo IA y mantengo aprobacion humana para acciones sensibles.";
    }

    private function primaryToolForRoute(array $route, string $prompt): ?string
    {
        $module = (string) ($route['module'] ?? '');
        $text = strtolower($prompt);

        return match ($module) {
            'Asisti Social' => 'social.generate_campaign',
            'Comercial' => str_contains($text, 'cotizacion') || str_contains($text, 'cotizaciones') ? 'quotes.assist' : 'crm.assist',
            'Analitica' => 'intelligence.analyze',
            'Administracion' => 'documents.assist',
            'Operaciones' => 'omnichannel.assist',
            default => null,
        };
    }

    private function unauthorizedToolResponse(string $toolKey, string $reason): string
    {
        return "No tengo autorizacion para usar la herramienta {$toolKey} en esta empresa. {$reason} Puedes pedir al Dueno de empresa o Superadmin que active la habilidad correspondiente en Marketplace o ajuste tus permisos.";
    }

    private function memoryPreview(array $memory): string
    {
        $content = trim(implode(' ', array_map(fn (array $item): string => (string) ($item['content'] ?? ''), array_slice($memory, 0, 2))));
        return strlen($content) > 320 ? substr($content, 0, 317) . '...' : $content;
    }

    private function fallbackResponse(array $route, string $prompt, array $company, array $assistant, string $error, array $memory = []): string
    {
        return $this->responseForRoute($route, $prompt, $company, $assistant, $memory)
            . " Nota tecnica: el proveedor IA real no estuvo disponible y AsisFly uso fallback seguro. Motivo registrado: {$error}";
    }

    private function blockedResponse(string $reason): string
    {
        return "No ejecute esta solicitud porque el control de consumo IA la bloqueo: {$reason} Puedes revisar limites, plan y proveedor en Integraciones > Motor IA.";
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

    private function createSuggestedAction(int $companyId, int $userId, array $route, string $prompt, array $autonomy): void
    {
        $action = match ($route['module']) {
            'Asisti Social' => [
                'title' => 'Aprobar borradores de Asisti Social',
                'description' => 'Se generaron 10 publicaciones en borrador. Revisa texto, imagen sugerida, hashtags, horario, objetivo y CTA antes de programar.',
                'action_type' => 'approve_social_campaign',
                'priority' => 'high',
                'risk_level' => 'medium',
                'payload' => ['prompt' => $prompt, 'posts' => 10, 'next_step' => 'Aprobar o programar publicaciones'],
            ],
            'Comercial' => [
                'title' => 'Aprobar accion comercial sugerida',
                'description' => 'AsisFly preparo una accion comercial que puede impactar clientes, cotizaciones o seguimiento.',
                'action_type' => 'approve_commercial_action',
                'priority' => 'high',
                'risk_level' => 'medium',
                'payload' => ['prompt' => $prompt],
            ],
            default => [
                'title' => 'Revisar accion sugerida por AsisFly',
                'description' => 'AsisFly preparo una accion y espera confirmacion humana antes de ejecutarla.',
                'action_type' => 'review_ai_action',
                'priority' => 'medium',
                'risk_level' => 'low',
                'payload' => ['prompt' => $prompt],
            ],
        };
        $riskLevel = $action['risk_level'] ?? 'medium';
        $requiresApproval = (new AutonomyRepository())->requiresApproval($autonomy, $riskLevel);

        (new ActionRepository())->create($companyId, $userId ?: null, [
            ...$action,
            'module' => $route['module'],
            'brain' => $route['name'],
            'requires_approval' => $requiresApproval,
            'payload' => [
                ...($action['payload'] ?? []),
                'learning_progress' => $autonomy['learning_progress'],
                'autonomy_mode' => $autonomy['label'],
                'approval_policy' => $requiresApproval ? 'Requiere aprobacion humana' : 'Puede ejecutarse segun reglas de bajo riesgo',
            ],
        ]);
    }

    private function autonomyNotice(array $autonomy): string
    {
        if (empty($autonomy['requires_all_approval'])) {
            return "\n\nModo AsisFly: {$autonomy['label']} ({$autonomy['learning_progress']}%). Revisare las reglas de aprobacion antes de ejecutar acciones.";
        }

        return "\n\nModo AsisFly: {$autonomy['label']} ({$autonomy['learning_progress']}%). Estoy aprendiendo como funciona esta empresa; todo queda como sugerencia y requiere aprobacion, modificacion o comentario humano para mejorar.";
    }
}
