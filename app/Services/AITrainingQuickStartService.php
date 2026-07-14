<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AITrainingRepository;
use App\Repositories\AiProviderRepository;
use App\Repositories\TenantRepository;
use Throwable;

final class AITrainingQuickStartService
{
    public function createDraft(int $companyId, int $userId, array $company, int $brandRouteId, string $brief): array
    {
        $brief = trim($brief);
        if ($this->length($brief) < 30) {
            throw new \RuntimeException('Cuéntale un poco más a AsisFly. Con unas dos o tres frases ya puede preparar un buen borrador.');
        }

        $provider = new AiProviderRepository();
        $settings = $provider->settings($companyId);
        $analysis = null;
        $source = 'fallback';
        $error = null;
        $promptTokens = $this->estimateTokens($brief) + 430;
        $completionTokens = 0;
        $model = (string) ($settings['model'] ?? 'asisfly-demo-latam');
        $providerName = (string) ($settings['provider'] ?? 'simulated');
        $requestId = null;

        $canUse = $provider->canUseAi($companyId, $promptTokens + 850, $settings);
        if ($canUse['allowed'] && $providerName === 'openai') {
            try {
                $response = (new OpenAiClient())->respond($this->prompt($brief), $settings, [
                    'company' => $company,
                    'assistant' => [],
                    'route' => ['name' => 'Entrenamiento empresarial', 'module' => 'Entrenamiento IA'],
                    'ai_context' => [],
                    'history' => [],
                ]);
                $analysis = $this->decodeAnalysis((string) $response['text']);
                $source = 'openai';
                $promptTokens = (int) ($response['prompt_tokens'] ?: $promptTokens);
                $completionTokens = (int) ($response['completion_tokens'] ?: $this->estimateTokens((string) $response['text']));
                $requestId = $response['request_id'] ?? null;
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        } elseif (!$canUse['allowed']) {
            $error = (string) ($canUse['reason'] ?? 'El motor IA no está disponible para esta empresa.');
        } elseif ($providerName !== 'openai') {
            $error = 'OpenAI no está seleccionado para esta empresa; se preparó un borrador inicial.';
        }

        $analysis ??= $this->fallbackAnalysis($company, $brief);
        $completionTokens = max(1, $completionTokens ?: $this->estimateTokens(json_encode($analysis, JSON_UNESCAPED_UNICODE) ?: ''));
        $logStatus = $source === 'openai' ? 'success' : 'fallback';
        $loggedProvider = $source === 'openai' ? 'openai' : 'simulated';
        $loggedModel = $source === 'openai' ? $model : 'asisfly-training-fallback';

        (new TenantRepository())->logAiUsage($companyId, $userId, 'Entrenamiento IA', $loggedProvider, $loggedModel, $promptTokens, $completionTokens, $this->estimateCost($loggedProvider, $loggedModel, $promptTokens, $completionTokens), [
            'prompt_text' => $brief,
            'response_text' => json_encode($analysis, JSON_UNESCAPED_UNICODE),
            'status' => $logStatus,
            'error_message' => $error,
            'request_id' => $requestId,
            'metadata' => ['feature' => 'quick_start', 'brand_route_id' => $brandRouteId, 'source' => $source],
        ]);

        return (new AITrainingRepository())->storeQuickStartDraft($companyId, $userId, $brandRouteId, $brief, $analysis, $source, $error);
    }

    private function prompt(string $brief): string
    {
        return <<<PROMPT
Actúa como analista de onboarding para AsisFly. A partir del relato de una empresa, crea un borrador de conocimiento empresarial. No inventes precios, horarios, políticas, correos ni datos de contacto. Cuando falten datos, deja texto vacío y agrégalos a suggested_questions.

Devuelve SOLO JSON válido, sin Markdown ni texto adicional, con esta forma exacta:
{
  "profile": {"company_name":"","description":"","industry":"","main_offering":"","customer_type":"","value_proposition":"","differentiators":"","business_hours":"","locations":"","website":"","contact_details":"","primary_objective":""},
  "personality": {"tone":"professional","allow_emojis":false,"response_length":"medium","greeting_style":"","closing_style":"","use_customer_name":true,"persuasion_level":"medium","primary_language":"Espanol latino","preferred_phrases":"","forbidden_phrases":""},
  "products": [{"item_type":"service","name":"","category":"","description":"","price_type":"quote_required","delivery_time":"","requirements":""}],
  "rules": [{"name":"","description":"","condition_text":"","action_text":"","priority":"high","channel":"all","escalation_role":""}],
  "faqs": [{"question":"","approved_answer":"","category":"","channel":"all","priority":"medium"}],
  "suggested_questions": ["..."]
}

Reglas: máximo 5 productos, 5 reglas, 5 FAQs y 5 preguntas. Las FAQs solo si puedes redactar una respuesta segura usando el relato. Las reglas deben priorizar solicitar aprobación o derivar a humano cuando falten datos sensibles.

Relato de la empresa:
{$brief}
PROMPT;
    }

    private function decodeAnalysis(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text) ?? $text;
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false) {
            $text = substr($text, $start, $end - $start + 1);
        }
        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OpenAI no devolvió un borrador de entrenamiento válido.');
        }

        return $this->normalizeAnalysis($decoded);
    }

    private function fallbackAnalysis(array $company, string $brief): array
    {
        return $this->normalizeAnalysis([
            'profile' => [
                'company_name' => (string) ($company['name'] ?? ''),
                'description' => $brief,
                'primary_objective' => 'Responder consultas y apoyar la operación con aprobación humana.',
            ],
            'personality' => [
                'tone' => 'professional',
                'allow_emojis' => false,
                'response_length' => 'medium',
                'use_customer_name' => true,
                'persuasion_level' => 'medium',
                'primary_language' => 'Espanol latino',
            ],
            'products' => [],
            'rules' => [[
                'name' => 'Confirmar información no definida',
                'description' => 'No inventar precios, stock, plazos ni disponibilidad cuando el relato no los indique.',
                'condition_text' => 'Cuando un cliente solicite información no confirmada.',
                'action_text' => 'Solicitar el dato faltante o derivar a una persona responsable.',
                'priority' => 'high',
                'channel' => 'all',
                'escalation_role' => 'Responsable de la empresa',
            ]],
            'faqs' => [],
            'suggested_questions' => [
                '¿Qué consultas frecuentes quieres que AsisFly pueda responder primero?',
                '¿Qué datos debe pedir antes de cotizar o agendar?',
                '¿En qué casos debe derivar a una persona?',
            ],
        ]);
    }

    private function normalizeAnalysis(array $analysis): array
    {
        $profileKeys = ['company_name', 'description', 'industry', 'main_offering', 'customer_type', 'value_proposition', 'differentiators', 'business_hours', 'locations', 'website', 'contact_details', 'primary_objective'];
        $personalityDefaults = ['tone' => 'professional', 'allow_emojis' => false, 'response_length' => 'medium', 'greeting_style' => '', 'closing_style' => '', 'use_customer_name' => true, 'persuasion_level' => 'medium', 'primary_language' => 'Espanol latino', 'preferred_phrases' => '', 'forbidden_phrases' => ''];
        $profile = [];
        foreach ($profileKeys as $key) {
            $profile[$key] = $this->text($analysis['profile'][$key] ?? '', 5000);
        }
        $personality = $personalityDefaults;
        foreach (array_keys($personalityDefaults) as $key) {
            $value = $analysis['personality'][$key] ?? $personalityDefaults[$key];
            $personality[$key] = is_bool($personalityDefaults[$key]) ? (bool) $value : $this->text($value, 5000);
        }
        $personality['tone'] = in_array($personality['tone'], ['formal', 'professional', 'close', 'technical', 'commercial'], true) ? $personality['tone'] : 'professional';
        $personality['response_length'] = in_array($personality['response_length'], ['short', 'medium', 'detailed'], true) ? $personality['response_length'] : 'medium';
        $personality['persuasion_level'] = in_array($personality['persuasion_level'], ['low', 'medium', 'high'], true) ? $personality['persuasion_level'] : 'medium';

        return [
            'profile' => $profile,
            'personality' => $personality,
            'products' => $this->rows($analysis['products'] ?? [], ['item_type', 'name', 'category', 'description', 'price_type', 'delivery_time', 'requirements'], 5),
            'rules' => $this->rows($analysis['rules'] ?? [], ['name', 'description', 'condition_text', 'action_text', 'priority', 'channel', 'escalation_role'], 5),
            'faqs' => $this->rows($analysis['faqs'] ?? [], ['question', 'approved_answer', 'category', 'channel', 'priority'], 5),
            'suggested_questions' => array_values(array_filter(array_map(fn (mixed $question): string => $this->text($question, 300), array_slice((array) ($analysis['suggested_questions'] ?? []), 0, 5)))),
        ];
    }

    private function rows(mixed $rows, array $keys, int $limit): array
    {
        $result = [];
        foreach (array_slice(is_array($rows) ? $rows : [], 0, $limit) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [];
            foreach ($keys as $key) {
                $item[$key] = $this->text($row[$key] ?? '', 5000);
            }
            if (trim((string) ($item['name'] ?? $item['question'] ?? '')) !== '') {
                $result[] = $item;
            }
        }
        return $result;
    }

    private function text(mixed $value, int $limit): string
    {
        $value = trim((string) $value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $limit, 'UTF-8') : substr($value, 0, $limit);
    }

    private function length(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }

    private function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(strlen($text) / 4));
    }

    private function estimateCost(string $provider, string $model, int $input, int $output): float
    {
        if ($provider !== 'openai') {
            return ($input + $output) / 1000 * 0.002;
        }
        $rates = $model === 'gpt-4.1-mini' ? [0.0004, 0.0016] : [0.0010, 0.0040];
        return ($input / 1000 * $rates[0]) + ($output / 1000 * $rates[1]);
    }
}
