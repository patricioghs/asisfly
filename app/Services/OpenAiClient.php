<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AiProviderRepository;
use RuntimeException;

final class OpenAiClient
{
    public function respond(string $prompt, array $settings, array $context): array
    {
        $apiKey = (new AiProviderRepository())->resolvedApiKey($settings);
        if ($apiKey === '') {
            throw new RuntimeException('No hay API key configurada para OpenAI. Guardala desde Superadmin > IA y tokens o define ' . ($settings['api_key_env'] ?? 'OPENAI_API_KEY') . ' en el .env.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('La extension cURL de PHP no esta habilitada.');
        }

        $input = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt($context),
            ],
        ];

        foreach ($this->historyMessages($context['history'] ?? []) as $message) {
            $input[] = $message;
        }

        $input[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        $payload = [
            'model' => $settings['model'],
            'input' => $input,
            'temperature' => (float) ($settings['temperature'] ?? 0.4),
        ];

        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 45,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            throw new RuntimeException($error !== '' ? $error : 'OpenAI no devolvio respuesta.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('OpenAI devolvio una respuesta no valida.');
        }

        if ($statusCode >= 400) {
            $message = $decoded['error']['message'] ?? 'Error HTTP ' . $statusCode . ' desde OpenAI.';
            throw new RuntimeException((string) $message);
        }

        return [
            'text' => $this->extractText($decoded),
            'prompt_tokens' => (int) ($decoded['usage']['input_tokens'] ?? 0),
            'completion_tokens' => (int) ($decoded['usage']['output_tokens'] ?? 0),
            'request_id' => $decoded['id'] ?? null,
            'raw' => $decoded,
        ];
    }

    private function systemPrompt(array $context): string
    {
        $assistant = $context['assistant'] ?? [];
        $company = $context['company'] ?? [];
        $route = $context['route'] ?? [];
        $memory = $context['memory'] ?? [];
        $aiContext = $context['ai_context'] ?? [];

        return implode("\n", array_filter([
            'Eres AsisFly, un empleado digital multiempresa para Latinoamerica.',
            'Empresa: ' . ($company['name'] ?? 'Empresa'),
            'Pais: ' . ($company['country'] ?? 'LatAm') . '. Moneda: ' . ($company['currency'] ?? 'USD') . '.',
            'Cerebro activo: ' . ($route['name'] ?? 'Cerebro Ejecutivo') . '. Modulo: ' . ($route['module'] ?? 'Direccion') . '.',
            $this->abilityPrompt($aiContext),
            $this->toolPrompt($aiContext),
            $this->planPrompt($aiContext),
            'Nombre configurado: ' . ($assistant['name'] ?? 'AsisFly'),
            'Tono: ' . ($assistant['tone'] ?? 'Cercano y ejecutivo'),
            'Idioma principal: ' . ($assistant['language'] ?? 'Espanol latino'),
            'Reglas de atencion: ' . ($assistant['rules'] ?? 'Pedir aprobacion antes de acciones sensibles.'),
            'Escalar a humano cuando: ' . ($assistant['human_escalation'] ?? 'Riesgo comercial, legal o cliente molesto.'),
            $this->memoryPrompt($memory),
            'Usa el historial reciente para interpretar respuestas cortas como "si", "ok" o "dale".',
            'Responde de forma util, accionable y breve. No inventes datos internos; si falta informacion, indica el supuesto.',
        ]));
    }

    private function historyMessages(array $history): array
    {
        $messages = [];
        foreach (array_slice($history, -8) as $message) {
            $role = (string) ($message['role'] ?? '');
            $content = trim((string) ($message['content'] ?? ''));
            if (!in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }

            $messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return $messages;
    }

    private function abilityPrompt(array $context): string
    {
        $abilities = array_map(
            fn (array $ability): string => (string) ($ability['ability_key'] ?? ''),
            $context['abilities'] ?? []
        );
        $abilities = array_values(array_filter($abilities));

        return $abilities
            ? 'Habilidades activas de esta empresa: ' . implode(', ', $abilities) . '. No menciones ni uses modulos fuera de esta lista.'
            : 'Habilidades activas: no disponibles. Usa comportamiento seguro y no ejecutes herramientas.';
    }

    private function toolPrompt(array $context): string
    {
        $allowed = array_keys($context['tools']['allowed'] ?? []);
        $blocked = array_keys($context['tools']['blocked'] ?? []);

        return implode("\n", array_filter([
            $allowed ? 'Herramientas IA permitidas para este usuario: ' . implode(', ', $allowed) . '.' : 'Herramientas IA permitidas: ninguna.',
            $blocked ? 'Herramientas IA bloqueadas: ' . implode(', ', $blocked) . '. Si el usuario pide una de ellas, indica que no tiene autorizacion.' : null,
        ]));
    }

    private function planPrompt(array $context): string
    {
        $limits = $context['plan_limits'] ?? [];
        if (!$limits) {
            return '';
        }

        return 'Limites del plan: ' . json_encode($limits, JSON_UNESCAPED_UNICODE) . '. Respeta estos limites y no prometas capacidades no incluidas.';
    }

    private function memoryPrompt(array $memory): string
    {
        if (!$memory) {
            return 'Memoria empresarial: no se encontro contexto documental relevante para esta solicitud.';
        }

        $parts = ['Memoria empresarial relevante. Usa este contexto solo si aplica y menciona cuando una respuesta depende de documentos internos:'];
        foreach ($memory as $index => $entry) {
            $parts[] = '[' . ($index + 1) . '] ' . ($entry['title'] ?? 'Documento') . ': ' . ($entry['content'] ?? '');
        }

        return implode("\n", $parts);
    }

    private function extractText(array $response): string
    {
        if (!empty($response['output_text']) && is_string($response['output_text'])) {
            return $response['output_text'];
        }

        $parts = [];
        foreach (($response['output'] ?? []) as $item) {
            foreach (($item['content'] ?? []) as $content) {
                if (!empty($content['text']) && is_string($content['text'])) {
                    $parts[] = $content['text'];
                }
            }
        }

        $text = trim(implode("\n", $parts));
        return $text !== '' ? $text : 'OpenAI respondio, pero no se pudo extraer texto de salida.';
    }
}
