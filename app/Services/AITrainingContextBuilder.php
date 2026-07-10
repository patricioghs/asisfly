<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AITrainingRepository;
use App\Repositories\MemoryRepository;

final class AITrainingContextBuilder
{
    public function __construct(
        private ?AITrainingRepository $training = null,
        private ?MemoryRepository $memory = null,
        private ?KnowledgeRetrievalService $knowledge = null
    ) {
        $this->training ??= new AITrainingRepository();
        $this->memory ??= new MemoryRepository();
        $this->knowledge ??= new KnowledgeRetrievalService();
    }

    public function build(int $companyId, string $query = '', array $conversationHistory = [], array $customerMemory = []): array
    {
        $training = $this->training->trainingContext($companyId, $query, 5);
        $knowledge = $this->knowledge->search($companyId, $query, 6);
        $documents = $knowledge ?: $this->memory->search($companyId, $query, 4);

        return [
            'profile' => $training['profile'] ?? [],
            'personality' => $training['personality'] ?? [],
            'rules' => $training['rules'] ?? [],
            'restrictions' => $training['restrictions'] ?? [],
            'products' => $training['products'] ?? [],
            'faqs' => $training['faqs'] ?? [],
            'examples' => $training['examples'] ?? [],
            'documents' => $documents,
            'knowledge' => $knowledge,
            'channels' => $training['channels'] ?? [],
            'prompt' => $training['prompt'] ?? [],
            'conversation_history' => array_slice($conversationHistory, -8),
            'customer_memory' => $customerMemory,
            'token_budget' => [
                'max_context_chars' => 9000,
                'strategy' => 'critical_rules_restrictions_conversation_products_faqs_documents_examples_profile',
            ],
        ];
    }

    public function renderForPrompt(array $context): string
    {
        $profile = $context['profile'] ?? [];
        $personality = $context['personality'] ?? [];

        $sections = [
            'Empresa' => [
                'Nombre: ' . ($profile['company_name'] ?? 'Empresa'),
                'Descripcion: ' . ($profile['description'] ?? ''),
                'Rubro: ' . ($profile['industry'] ?? ''),
                'Objetivo: ' . ($profile['primary_objective'] ?? 'Atender y ayudar a clientes'),
            ],
            'Estilo' => [
                'Tono: ' . ($personality['tone'] ?? 'professional'),
                'Idioma: ' . ($personality['primary_language'] ?? 'Espanol latino'),
                'Extension: ' . ($personality['response_length'] ?? 'medium'),
                'Persuasion: ' . ($personality['persuasion_level'] ?? 'medium'),
            ],
            'Reglas criticas' => $this->mapRows($context['rules'] ?? [], ['name', 'condition_text', 'action_text']),
            'Restricciones' => $this->mapRows($context['restrictions'] ?? [], ['name', 'description', 'value_text']),
            'Productos o servicios' => $this->mapRows($context['products'] ?? [], ['name', 'description', 'price_type', 'delivery_time']),
            'Preguntas frecuentes' => $this->mapRows($context['faqs'] ?? [], ['question', 'approved_answer']),
            'Documentos relevantes' => $this->mapRows($context['documents'] ?? [], ['title', 'content']),
            'Ejemplos aprobados' => $this->mapRows($context['examples'] ?? [], ['customer_message', 'ideal_response']),
        ];

        $text = [];
        foreach ($sections as $title => $lines) {
            $lines = array_values(array_filter(array_map('trim', $lines)));
            if (!$lines) {
                continue;
            }
            $text[] = $title . ":\n- " . implode("\n- ", $lines);
        }

        return substr(implode("\n\n", $text), 0, (int) ($context['token_budget']['max_context_chars'] ?? 9000));
    }

    public function simulate(int $companyId, string $customerMessage, int $userId = 0): array
    {
        $context = $this->build($companyId, $customerMessage);
        $profile = $context['profile'] ?? [];
        $personality = $context['personality'] ?? [];
        $faqs = $context['faqs'] ?? [];
        $products = $context['products'] ?? [];
        $examples = $context['examples'] ?? [];
        $documents = $context['documents'] ?? [];

        $answer = 'Gracias por escribirnos.';
        if (!empty($faqs[0]['approved_answer'])) {
            $answer = (string) $faqs[0]['approved_answer'];
        } elseif (!empty($examples[0]['ideal_response'])) {
            $answer = (string) $examples[0]['ideal_response'];
        } elseif (!empty($products[0]['name'])) {
            $answer = 'Te puedo ayudar con ' . $products[0]['name'] . '. Para responder correctamente necesito confirmar los antecedentes necesarios y disponibilidad antes de comprometer precio, stock o plazos.';
        } elseif (!empty($documents[0]['content'])) {
            $answer = 'Segun la informacion cargada, puedo orientarte con esto. Para evitar errores, confirmare los datos clave antes de entregar precios, plazos o condiciones.';
        }

        $greeting = !empty($personality['greeting_style']) ? (string) $personality['greeting_style'] : 'Hola,';
        $closing = !empty($personality['closing_style']) ? (string) $personality['closing_style'] : 'Quedo atento/a.';
        $companyName = (string) ($profile['company_name'] ?? 'la empresa');

        $contextPreview = $this->renderForPrompt($context);
        $result = [
            'message' => $customerMessage,
            'answer' => trim($greeting . "\n\n" . $answer . "\n\n" . $closing),
            'confidence' => $this->confidence($context),
            'sources' => [
                'faqs' => count($faqs),
                'products' => count($products),
                'examples' => count($examples),
                'documents' => count($documents),
                'company' => $companyName,
            ],
            'context_preview' => $contextPreview,
        ];

        $contextLogId = $this->knowledge->logContext($companyId, $userId, 'Entrenamiento IA', $customerMessage, $documents, $contextPreview);
        $generatedId = $this->knowledge->recordGeneratedResponse($companyId, $userId, $contextLogId, [
            'module' => 'Entrenamiento IA',
            'channel' => 'simulator',
            'customer_message' => $customerMessage,
            'generated_response' => $result['answer'],
            'confidence' => $result['confidence'],
            'sources' => $result['sources'],
            'status' => 'draft',
        ]);
        $result['context_log_id'] = $contextLogId;
        $result['generated_response_id'] = $generatedId;

        return $result;
    }

    private function confidence(array $context): int
    {
        $score = 35;
        $score += !empty($context['profile']) ? 10 : 0;
        $score += !empty($context['personality']) ? 10 : 0;
        $score += min(15, count($context['rules'] ?? []) * 5);
        $score += min(15, count($context['faqs'] ?? []) * 5);
        $score += min(10, count($context['examples'] ?? []) * 5);
        $score += min(5, count($context['documents'] ?? []) * 2);

        return min(95, $score);
    }

    private function mapRows(array $rows, array $fields): array
    {
        return array_map(function (array $row) use ($fields): string {
            $parts = [];
            foreach ($fields as $field) {
                $value = trim((string) ($row[$field] ?? ''));
                if ($value !== '') {
                    $parts[] = $value;
                }
            }
            return implode(' | ', $parts);
        }, $rows);
    }
}
