<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class AITrainingRepository
{
    public function overview(int $companyId): array
    {
        if (!$this->ready()) {
            return $this->fallback();
        }

        $session = $this->session($companyId, 0);
        $profile = $this->profile($companyId);
        $personality = $this->personality($companyId);

        return [
            'session' => $session,
            'profile' => $profile,
            'personality' => $personality,
            'answers' => $this->answers($companyId, (int) ($session['id'] ?? 0)),
            'metrics' => $this->metrics($companyId),
            'products' => $this->products($companyId, 8),
            'rules' => $this->rules($companyId, 8),
            'faqs' => $this->faqs($companyId, 8),
            'examples' => $this->examples($companyId, 8),
            'autonomy' => (new AutonomyRepository())->profile($companyId),
            'channels' => $this->channelSettings($companyId),
            'prompt' => $this->activePrompt(),
            'knowledgeSources' => $this->knowledgeSources($companyId, 12),
            'knowledgeStats' => $this->knowledgeStats($companyId),
            'knowledgeQuery' => trim((string) ($_GET['knowledge_q'] ?? '')),
            'knowledgeResults' => $this->knowledgeResults($companyId, trim((string) ($_GET['knowledge_q'] ?? '')), 8),
            'responseReviews' => $this->responseReviews($companyId, 12),
        ];
    }

    public function session(int $companyId, int $userId): array
    {
        if (!$this->ready()) {
            return ['id' => 0, 'status' => 'draft', 'progress_percent' => 0, 'current_step' => 'welcome'];
        }

        $statement = Database::connection()->prepare('SELECT * FROM ai_onboarding_sessions WHERE company_id = :company_id ORDER BY id DESC LIMIT 1');
        $statement->execute(['company_id' => $companyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        Database::connection()->prepare('INSERT INTO ai_onboarding_sessions (company_id, user_id, status, current_step, progress_percent) VALUES (:company_id, :user_id, "draft", "welcome", 0)')
            ->execute(['company_id' => $companyId, 'user_id' => $userId ?: null]);

        return $this->session($companyId, $userId);
    }

    public function saveOnboardingAnswer(int $companyId, int $userId, string $questionKey, string $answer): void
    {
        $this->ensureReady();

        $questions = $this->questions();
        if (!isset($questions[$questionKey])) {
            throw new RuntimeException('La pregunta de entrenamiento no existe.');
        }

        $session = $this->session($companyId, $userId);
        $question = $questions[$questionKey];
        Database::connection()->prepare(
            'INSERT INTO ai_onboarding_answers (company_id, session_id, question_key, question_label, answer, is_optional, answered_by, answered_at)
             VALUES (:company_id, :session_id, :question_key, :question_label, :answer, :is_optional, :answered_by, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE answer = VALUES(answer), answered_by = VALUES(answered_by), answered_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP'
        )->execute([
            'company_id' => $companyId,
            'session_id' => (int) $session['id'],
            'question_key' => $questionKey,
            'question_label' => $question['label'],
            'answer' => trim($answer),
            'is_optional' => !empty($question['optional']) ? 1 : 0,
            'answered_by' => $userId ?: null,
        ]);

        $this->syncProfileFromAnswers($companyId, $userId);
        $this->refreshSessionProgress($companyId, (int) $session['id']);
    }

    public function saveProfile(int $companyId, int $userId, array $input): void
    {
        $this->ensureReady();

        Database::connection()->prepare(
            'INSERT INTO ai_company_profiles
             (company_id, status, company_name, description, industry, main_offering, customer_type, value_proposition, differentiators, business_hours, locations, website, contact_details, primary_objective, created_by, updated_by)
             VALUES (:company_id, :status, :company_name, :description, :industry, :main_offering, :customer_type, :value_proposition, :differentiators, :business_hours, :locations, :website, :contact_details, :primary_objective, :user_id, :user_id)
             ON DUPLICATE KEY UPDATE status = VALUES(status), company_name = VALUES(company_name), description = VALUES(description), industry = VALUES(industry), main_offering = VALUES(main_offering), customer_type = VALUES(customer_type), value_proposition = VALUES(value_proposition), differentiators = VALUES(differentiators), business_hours = VALUES(business_hours), locations = VALUES(locations), website = VALUES(website), contact_details = VALUES(contact_details), primary_objective = VALUES(primary_objective), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
        )->execute([
            'company_id' => $companyId,
            'status' => $this->status($input['status'] ?? 'draft'),
            'company_name' => $this->text($input, 'company_name', 180),
            'description' => $this->text($input, 'description', 5000),
            'industry' => $this->text($input, 'industry', 160),
            'main_offering' => $this->text($input, 'main_offering', 5000),
            'customer_type' => $this->text($input, 'customer_type', 5000),
            'value_proposition' => $this->text($input, 'value_proposition', 5000),
            'differentiators' => $this->text($input, 'differentiators', 5000),
            'business_hours' => $this->text($input, 'business_hours', 220),
            'locations' => $this->text($input, 'locations', 5000),
            'website' => $this->text($input, 'website', 220),
            'contact_details' => $this->text($input, 'contact_details', 5000),
            'primary_objective' => $this->text($input, 'primary_objective', 220),
            'user_id' => $userId ?: null,
        ]);
    }

    public function publishProfile(int $companyId, int $userId): void
    {
        $this->ensureReady();

        Database::connection()->prepare('UPDATE ai_company_profiles SET status = "published", published_at = CURRENT_TIMESTAMP, updated_by = :user_id WHERE company_id = :company_id')
            ->execute(['company_id' => $companyId, 'user_id' => $userId ?: null]);
        Database::connection()->prepare('UPDATE ai_personalities SET status = "published", updated_by = :user_id WHERE company_id = :company_id')
            ->execute(['company_id' => $companyId, 'user_id' => $userId ?: null]);
        Database::connection()->prepare('UPDATE ai_onboarding_sessions SET status = "published", activated_at = CURRENT_TIMESTAMP WHERE company_id = :company_id ORDER BY id DESC LIMIT 1')
            ->execute(['company_id' => $companyId]);
    }

    public function savePersonality(int $companyId, int $userId, array $input): void
    {
        $this->ensureReady();

        Database::connection()->prepare(
            'INSERT INTO ai_personalities
             (company_id, status, tone, allow_emojis, response_length, greeting_style, closing_style, use_customer_name, persuasion_level, primary_language, preferred_phrases, forbidden_phrases, good_examples, bad_examples, created_by, updated_by)
             VALUES (:company_id, :status, :tone, :allow_emojis, :response_length, :greeting_style, :closing_style, :use_customer_name, :persuasion_level, :primary_language, :preferred_phrases, :forbidden_phrases, :good_examples, :bad_examples, :user_id, :user_id)
             ON DUPLICATE KEY UPDATE status = VALUES(status), tone = VALUES(tone), allow_emojis = VALUES(allow_emojis), response_length = VALUES(response_length), greeting_style = VALUES(greeting_style), closing_style = VALUES(closing_style), use_customer_name = VALUES(use_customer_name), persuasion_level = VALUES(persuasion_level), primary_language = VALUES(primary_language), preferred_phrases = VALUES(preferred_phrases), forbidden_phrases = VALUES(forbidden_phrases), good_examples = VALUES(good_examples), bad_examples = VALUES(bad_examples), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
        )->execute([
            'company_id' => $companyId,
            'status' => $this->status($input['status'] ?? 'draft'),
            'tone' => $this->allowed($input['tone'] ?? 'professional', ['formal', 'professional', 'close', 'technical', 'commercial'], 'professional'),
            'allow_emojis' => !empty($input['allow_emojis']) ? 1 : 0,
            'response_length' => $this->allowed($input['response_length'] ?? 'medium', ['short', 'medium', 'detailed'], 'medium'),
            'greeting_style' => $this->text($input, 'greeting_style', 180),
            'closing_style' => $this->text($input, 'closing_style', 180),
            'use_customer_name' => !empty($input['use_customer_name']) ? 1 : 0,
            'persuasion_level' => $this->allowed($input['persuasion_level'] ?? 'medium', ['low', 'medium', 'high'], 'medium'),
            'primary_language' => $this->text($input, 'primary_language', 80) ?: 'Espanol latino',
            'preferred_phrases' => $this->text($input, 'preferred_phrases', 5000),
            'forbidden_phrases' => $this->text($input, 'forbidden_phrases', 5000),
            'good_examples' => $this->text($input, 'good_examples', 5000),
            'bad_examples' => $this->text($input, 'bad_examples', 5000),
            'user_id' => $userId ?: null,
        ]);
    }

    public function addProduct(int $companyId, int $userId, array $input): void
    {
        $this->ensureReady();
        if (trim((string) ($input['name'] ?? '')) === '') {
            throw new RuntimeException('El nombre del producto o servicio es obligatorio.');
        }

        Database::connection()->prepare(
            'INSERT INTO ai_products (company_id, item_type, name, category, description, price_type, price_from, price_to, currency, delivery_time, requirements, stock, warranty, restrictions, faqs, status, created_by, updated_by)
             VALUES (:company_id, :item_type, :name, :category, :description, :price_type, :price_from, :price_to, :currency, :delivery_time, :requirements, :stock, :warranty, :restrictions, :faqs, :status, :user_id, :user_id)'
        )->execute([
            'company_id' => $companyId,
            'item_type' => $this->allowed($input['item_type'] ?? 'service', ['product', 'service'], 'service'),
            'name' => $this->text($input, 'name', 180),
            'category' => $this->text($input, 'category', 120),
            'description' => $this->text($input, 'description', 5000),
            'price_type' => $this->allowed($input['price_type'] ?? 'quote_required', ['fixed', 'range', 'quote_required'], 'quote_required'),
            'price_from' => $this->decimal($input['price_from'] ?? null),
            'price_to' => $this->decimal($input['price_to'] ?? null),
            'currency' => strtoupper($this->text($input, 'currency', 3) ?: 'CLP'),
            'delivery_time' => $this->text($input, 'delivery_time', 160),
            'requirements' => $this->text($input, 'requirements', 5000),
            'stock' => $this->text($input, 'stock', 120),
            'warranty' => $this->text($input, 'warranty', 5000),
            'restrictions' => $this->text($input, 'restrictions', 5000),
            'faqs' => $this->text($input, 'faqs', 5000),
            'status' => $this->allowed($input['status'] ?? 'active', ['active', 'inactive'], 'active'),
            'user_id' => $userId ?: null,
        ]);
    }

    public function addRule(int $companyId, int $userId, array $input): void
    {
        $this->ensureReady();
        if (trim((string) ($input['name'] ?? '')) === '') {
            throw new RuntimeException('El nombre de la regla es obligatorio.');
        }

        Database::connection()->prepare(
            'INSERT INTO ai_business_rules (company_id, name, description, condition_text, action_text, priority, channel, escalation_role, effective_until, status, is_active, created_by, updated_by)
             VALUES (:company_id, :name, :description, :condition_text, :action_text, :priority, :channel, :escalation_role, :effective_until, :status, :is_active, :user_id, :user_id)'
        )->execute([
            'company_id' => $companyId,
            'name' => $this->text($input, 'name', 180),
            'description' => $this->text($input, 'description', 5000),
            'condition_text' => $this->text($input, 'condition_text', 5000),
            'action_text' => $this->text($input, 'action_text', 5000),
            'priority' => $this->allowed($input['priority'] ?? 'medium', ['low', 'medium', 'high', 'critical'], 'medium'),
            'channel' => $this->text($input, 'channel', 60) ?: 'all',
            'escalation_role' => $this->text($input, 'escalation_role', 120),
            'effective_until' => $this->date($input['effective_until'] ?? null),
            'status' => $this->status($input['status'] ?? 'published'),
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'user_id' => $userId ?: null,
        ]);
    }

    public function addFaq(int $companyId, int $userId, array $input): void
    {
        $this->ensureReady();
        if (trim((string) ($input['question'] ?? '')) === '' || trim((string) ($input['approved_answer'] ?? '')) === '') {
            throw new RuntimeException('La pregunta y respuesta aprobada son obligatorias.');
        }

        $status = $this->status($input['status'] ?? 'published');
        Database::connection()->prepare(
            'INSERT INTO ai_faqs (company_id, question, variants, approved_answer, category, tags, channel, priority, source, status, created_by, approved_by, approved_at)
             VALUES (:company_id, :question, :variants, :approved_answer, :category, :tags, :channel, :priority, :source, :status, :created_by, :approved_by, :approved_at)'
        )->execute([
            'company_id' => $companyId,
            'question' => $this->text($input, 'question', 5000),
            'variants' => $this->text($input, 'variants', 5000),
            'approved_answer' => $this->text($input, 'approved_answer', 5000),
            'category' => $this->text($input, 'category', 120),
            'tags' => $this->text($input, 'tags', 255),
            'channel' => $this->text($input, 'channel', 60) ?: 'all',
            'priority' => $this->allowed($input['priority'] ?? 'medium', ['low', 'medium', 'high', 'critical'], 'medium'),
            'source' => $this->text($input, 'source', 160) ?: 'Centro de Entrenamiento IA',
            'status' => $status,
            'created_by' => $userId ?: null,
            'approved_by' => $status === 'published' ? ($userId ?: null) : null,
            'approved_at' => $status === 'published' ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public function addExample(int $companyId, int $userId, array $input): void
    {
        $this->ensureReady();
        if (trim((string) ($input['customer_message'] ?? '')) === '' || trim((string) ($input['ideal_response'] ?? '')) === '') {
            throw new RuntimeException('El mensaje del cliente y la respuesta ideal son obligatorios.');
        }

        $status = $this->status($input['status'] ?? 'published');
        Database::connection()->prepare(
            'INSERT INTO ai_conversation_examples (company_id, customer_message, ideal_response, channel, category, intent, tags, product_service, expected_result, status, approved_by, approved_at, created_by)
             VALUES (:company_id, :customer_message, :ideal_response, :channel, :category, :intent, :tags, :product_service, :expected_result, :status, :approved_by, :approved_at, :created_by)'
        )->execute([
            'company_id' => $companyId,
            'customer_message' => $this->text($input, 'customer_message', 5000),
            'ideal_response' => $this->text($input, 'ideal_response', 5000),
            'channel' => $this->text($input, 'channel', 60) ?: 'all',
            'category' => $this->text($input, 'category', 120),
            'intent' => $this->text($input, 'intent', 120),
            'tags' => $this->text($input, 'tags', 255),
            'product_service' => $this->text($input, 'product_service', 180),
            'expected_result' => $this->text($input, 'expected_result', 180),
            'status' => $status,
            'approved_by' => $status === 'published' ? ($userId ?: null) : null,
            'approved_at' => $status === 'published' ? date('Y-m-d H:i:s') : null,
            'created_by' => $userId ?: null,
        ]);
    }

    public function saveChannelSetting(int $companyId, array $input): void
    {
        $this->ensureReady();

        $channel = $this->text($input, 'channel', 60) ?: 'all';
        Database::connection()->prepare(
            'INSERT INTO ai_channel_settings (company_id, channel, mode, min_confidence, require_approval_for_sensitive, auto_reply_schedule, status)
             VALUES (:company_id, :channel, :mode, :min_confidence, :require_approval_for_sensitive, :auto_reply_schedule, :status)
             ON DUPLICATE KEY UPDATE mode = VALUES(mode), min_confidence = VALUES(min_confidence), require_approval_for_sensitive = VALUES(require_approval_for_sensitive), auto_reply_schedule = VALUES(auto_reply_schedule), status = VALUES(status), updated_at = CURRENT_TIMESTAMP'
        )->execute([
            'company_id' => $companyId,
            'channel' => $channel,
            'mode' => $this->allowed($input['mode'] ?? 'manual', ['manual', 'assisted', 'automatic'], 'manual'),
            'min_confidence' => max(0, min(100, (int) ($input['min_confidence'] ?? 75))),
            'require_approval_for_sensitive' => !empty($input['require_approval_for_sensitive']) ? 1 : 0,
            'auto_reply_schedule' => $this->text($input, 'auto_reply_schedule', 180),
            'status' => $this->allowed($input['status'] ?? 'active', ['active', 'inactive'], 'active'),
        ]);
    }

    public function trainingContext(int $companyId, string $query = '', int $limit = 5): array
    {
        if (!$this->ready()) {
            return [];
        }

        return [
            'profile' => $this->profile($companyId, true),
            'personality' => $this->personality($companyId, true),
            'rules' => $this->publishedRows('ai_business_rules', $companyId, 'FIELD(priority, "critical", "high", "medium", "low"), id DESC', $limit),
            'restrictions' => $this->publishedRows('ai_restrictions', $companyId, 'id DESC', $limit),
            'products' => $this->searchRows('ai_products', $companyId, $query, 'name, description', $limit),
            'faqs' => $this->searchRows('ai_faqs', $companyId, $query, 'question, variants, approved_answer', $limit),
            'examples' => $this->searchRows('ai_conversation_examples', $companyId, $query, 'customer_message, ideal_response', $limit),
            'reviews' => $this->reviewExamples($companyId, $limit),
            'knowledge' => $this->knowledgeResults($companyId, $query, $limit),
            'channels' => $this->channelSettings($companyId),
            'prompt' => $this->activePrompt(),
        ];
    }

    public function responseReviews(int $companyId, int $limit = 20): array
    {
        if (!$this->reviewsReady()) {
            return [];
        }

        return $this->rows(
            'SELECT r.*, u.name AS reviewer_name
             FROM ai_response_reviews r
             LEFT JOIN users u ON u.id = r.reviewed_by
             WHERE r.company_id = :company_id
             ORDER BY r.reviewed_at DESC, r.id DESC
             LIMIT ' . max(1, min(50, $limit)),
            ['company_id' => $companyId]
        );
    }

    public function knowledgeSources(int $companyId, int $limit = 12): array
    {
        if (!$this->knowledgeReady()) {
            return [];
        }

        return $this->rows(
            'SELECT s.*, COUNT(c.id) AS chunks
             FROM ai_knowledge_sources s
             LEFT JOIN ai_knowledge_chunks c ON c.company_id = s.company_id AND c.source_id = s.id
             WHERE s.company_id = :company_id
             GROUP BY s.id
             ORDER BY FIELD(s.status, "processing", "pending", "ready", "failed", "inactive"), s.id DESC
             LIMIT ' . max(1, $limit),
            ['company_id' => $companyId]
        );
    }

    public function knowledgeStats(int $companyId): array
    {
        if (!$this->knowledgeReady()) {
            return ['sources' => 0, 'ready' => 0, 'failed' => 0, 'chunks' => 0];
        }

        $sources = $this->scalar('SELECT COUNT(*) FROM ai_knowledge_sources WHERE company_id = :company_id', ['company_id' => $companyId]);
        $ready = $this->scalar('SELECT COUNT(*) FROM ai_knowledge_sources WHERE company_id = :company_id AND status = "ready"', ['company_id' => $companyId]);
        $failed = $this->scalar('SELECT COUNT(*) FROM ai_knowledge_sources WHERE company_id = :company_id AND status = "failed"', ['company_id' => $companyId]);
        $chunks = $this->scalar('SELECT COUNT(*) FROM ai_knowledge_chunks WHERE company_id = :company_id AND status = "ready"', ['company_id' => $companyId]);

        return ['sources' => $sources, 'ready' => $ready, 'failed' => $failed, 'chunks' => $chunks];
    }

    public function knowledgeResults(int $companyId, string $query, int $limit = 8): array
    {
        if (!$this->knowledgeReady() || trim($query) === '') {
            return [];
        }

        return (new \App\Services\KnowledgeRetrievalService())->search($companyId, $query, $limit);
    }

    public function questions(): array
    {
        return [
            'company_name' => ['label' => 'Nombre de la empresa', 'section' => 'Informacion general'],
            'description' => ['label' => 'Describe brevemente que hace la empresa', 'section' => 'Informacion general'],
            'industry' => ['label' => 'Actividad o rubro principal', 'section' => 'Informacion general'],
            'main_offering' => ['label' => 'Productos o servicios principales', 'section' => 'Informacion general'],
            'customer_type' => ['label' => 'Tipo de clientes que atienden', 'section' => 'Informacion general'],
            'value_proposition' => ['label' => 'Propuesta de valor', 'section' => 'Informacion general'],
            'differentiators' => ['label' => 'Diferenciadores frente a la competencia', 'section' => 'Informacion general', 'optional' => true],
            'business_hours' => ['label' => 'Horarios de atencion', 'section' => 'Informacion general'],
            'locations' => ['label' => 'Ubicaciones o sucursales', 'section' => 'Informacion general', 'optional' => true],
            'website' => ['label' => 'Sitio web', 'section' => 'Informacion general', 'optional' => true],
            'contact_details' => ['label' => 'Datos de contacto oficiales', 'section' => 'Informacion general'],
            'sales_process' => ['label' => 'Como llega normalmente un cliente y que pasos sigue la venta', 'section' => 'Proceso comercial'],
            'quote_requirements' => ['label' => 'Que informacion se solicita antes de cotizar', 'section' => 'Proceso comercial'],
            'human_escalation' => ['label' => 'Cuando se debe derivar a un humano', 'section' => 'Proceso comercial'],
            'ai_goal' => ['label' => 'Que accion debe intentar lograr AsisFly', 'section' => 'Proceso comercial'],
        ];
    }

    private function syncProfileFromAnswers(int $companyId, int $userId): void
    {
        $session = $this->session($companyId, $userId);
        $answers = $this->answers($companyId, (int) $session['id']);
        $map = [];
        foreach ($answers as $answer) {
            $map[$answer['question_key']] = $answer['answer'];
        }

        $this->saveProfile($companyId, $userId, [
            'status' => 'draft',
            'company_name' => $map['company_name'] ?? '',
            'description' => $map['description'] ?? '',
            'industry' => $map['industry'] ?? '',
            'main_offering' => $map['main_offering'] ?? '',
            'customer_type' => $map['customer_type'] ?? '',
            'value_proposition' => $map['value_proposition'] ?? '',
            'differentiators' => $map['differentiators'] ?? '',
            'business_hours' => $map['business_hours'] ?? '',
            'locations' => $map['locations'] ?? '',
            'website' => $map['website'] ?? '',
            'contact_details' => $map['contact_details'] ?? '',
            'primary_objective' => $map['ai_goal'] ?? '',
        ]);
    }

    private function refreshSessionProgress(int $companyId, int $sessionId): void
    {
        $total = count(array_filter($this->questions(), fn (array $question): bool => empty($question['optional'])));
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM ai_onboarding_answers WHERE company_id = :company_id AND session_id = :session_id AND is_optional = FALSE AND answer IS NOT NULL AND answer <> ""');
        $statement->execute(['company_id' => $companyId, 'session_id' => $sessionId]);
        $answered = (int) $statement->fetchColumn();
        $progress = $total > 0 ? (int) floor(($answered / $total) * 100) : 0;
        Database::connection()->prepare('UPDATE ai_onboarding_sessions SET progress_percent = :progress, current_step = :current_step WHERE company_id = :company_id AND id = :id')
            ->execute([
                'progress' => min(100, $progress),
                'current_step' => $progress >= 100 ? 'summary' : 'interview',
                'company_id' => $companyId,
                'id' => $sessionId,
            ]);
    }

    private function metrics(int $companyId): array
    {
        $session = $this->session($companyId, 0);
        $answers = $this->scalar('SELECT COUNT(*) FROM ai_onboarding_answers WHERE company_id = :company_id AND session_id = :session_id AND answer IS NOT NULL AND answer <> ""', ['company_id' => $companyId, 'session_id' => (int) $session['id']]);
        $products = $this->scalar('SELECT COUNT(*) FROM ai_products WHERE company_id = :company_id AND status = "active"', ['company_id' => $companyId]);
        $rules = $this->scalar('SELECT COUNT(*) FROM ai_business_rules WHERE company_id = :company_id AND status = "published" AND is_active = TRUE', ['company_id' => $companyId]);
        $faqs = $this->scalar('SELECT COUNT(*) FROM ai_faqs WHERE company_id = :company_id AND status = "published"', ['company_id' => $companyId]);
        $examples = $this->scalar('SELECT COUNT(*) FROM ai_conversation_examples WHERE company_id = :company_id AND status = "published"', ['company_id' => $companyId]);
        $reviews = $this->reviewMetrics($companyId);
        $documents = $this->documentMetrics($companyId);
        $readinessItems = [
            !empty($this->profile($companyId)['description']),
            $products > 0,
            !empty($this->personality($companyId)['primary_language']),
            $rules > 0,
            $faqs > 0,
            $examples > 0,
        ];
        $ready = count(array_filter($readinessItems));

        return [
            ['label' => 'Preguntas respondidas', 'value' => (string) $answers, 'hint' => 'Entrevista inicial'],
            ['label' => 'Productos/servicios', 'value' => (string) $products, 'hint' => 'Activos'],
            ['label' => 'Reglas activas', 'value' => (string) $rules, 'hint' => 'Publicadas'],
            ['label' => 'FAQs aprobadas', 'value' => (string) $faqs, 'hint' => 'Listas para contexto'],
            ['label' => 'Documentos procesados', 'value' => (string) $documents['processed'], 'hint' => $documents['failed'] . ' con errores'],
            ['label' => 'Ejemplos aprobados', 'value' => (string) $examples, 'hint' => 'Correcciones reutilizables'],
            ['label' => 'Aprobadas sin cambios', 'value' => (string) $reviews['approved'], 'hint' => 'Respuestas IA'],
            ['label' => 'Preparacion', 'value' => (string) floor(($ready / count($readinessItems)) * 100) . '%', 'hint' => "{$ready}/" . count($readinessItems) . ' criterios'],
        ];
    }

    private function answers(int $companyId, int $sessionId): array
    {
        if ($sessionId <= 0) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT * FROM ai_onboarding_answers WHERE company_id = :company_id AND session_id = :session_id ORDER BY id');
        $statement->execute(['company_id' => $companyId, 'session_id' => $sessionId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function profile(int $companyId, bool $publishedOnly = false): array
    {
        $where = 'company_id = :company_id';
        if ($publishedOnly) {
            $where .= ' AND status = "published"';
        }
        $statement = Database::connection()->prepare("SELECT * FROM ai_company_profiles WHERE {$where} LIMIT 1");
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function personality(int $companyId, bool $publishedOnly = false): array
    {
        $where = 'company_id = :company_id';
        if ($publishedOnly) {
            $where .= ' AND status = "published"';
        }
        $statement = Database::connection()->prepare("SELECT * FROM ai_personalities WHERE {$where} LIMIT 1");
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function products(int $companyId, int $limit): array
    {
        return $this->rows('SELECT * FROM ai_products WHERE company_id = :company_id ORDER BY FIELD(status, "active", "inactive"), id DESC LIMIT ' . max(1, $limit), ['company_id' => $companyId]);
    }

    private function rules(int $companyId, int $limit): array
    {
        return $this->rows('SELECT * FROM ai_business_rules WHERE company_id = :company_id ORDER BY FIELD(priority, "critical", "high", "medium", "low"), id DESC LIMIT ' . max(1, $limit), ['company_id' => $companyId]);
    }

    private function faqs(int $companyId, int $limit): array
    {
        return $this->rows('SELECT * FROM ai_faqs WHERE company_id = :company_id ORDER BY FIELD(priority, "critical", "high", "medium", "low"), id DESC LIMIT ' . max(1, $limit), ['company_id' => $companyId]);
    }

    private function examples(int $companyId, int $limit): array
    {
        return $this->rows('SELECT * FROM ai_conversation_examples WHERE company_id = :company_id ORDER BY id DESC LIMIT ' . max(1, $limit), ['company_id' => $companyId]);
    }

    private function channelSettings(int $companyId): array
    {
        return $this->rows('SELECT * FROM ai_channel_settings WHERE company_id = :company_id ORDER BY FIELD(channel, "whatsapp", "email", "instagram", "facebook", "all"), channel', ['company_id' => $companyId]);
    }

    private function activePrompt(): array
    {
        $statement = Database::connection()->query('SELECT t.template_key, t.name, v.version, v.body, v.activated_at FROM ai_prompt_templates t INNER JOIN ai_prompt_versions v ON v.template_id = t.id WHERE t.template_key = "tenant_worker_base" AND v.status = "active" ORDER BY v.id DESC LIMIT 1');
        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function publishedRows(string $table, int $companyId, string $order, int $limit): array
    {
        return $this->rows("SELECT * FROM {$table} WHERE company_id = :company_id AND status = \"published\" AND (is_active = TRUE OR is_active IS NULL) ORDER BY {$order} LIMIT " . max(1, $limit), ['company_id' => $companyId]);
    }

    private function searchRows(string $table, int $companyId, string $query, string $columns, int $limit): array
    {
        $params = ['company_id' => $companyId];
        $where = 'company_id = :company_id';
        if (in_array($table, ['ai_faqs', 'ai_conversation_examples'], true)) {
            $where .= ' AND status = "published"';
        }
        if ($table === 'ai_products') {
            $where .= ' AND status = "active"';
        }
        $terms = array_values(array_filter(preg_split('/\s+/', strtolower($query)) ?: [], fn (string $term): bool => strlen($term) >= 3));
        if ($terms) {
            $parts = [];
            foreach (array_slice($terms, 0, 4) as $index => $term) {
                $key = 'q' . $index;
                $likeParts = [];
                foreach (explode(',', $columns) as $column) {
                    $likeParts[] = trim($column) . " LIKE :{$key}";
                }
                $parts[] = '(' . implode(' OR ', $likeParts) . ')';
                $params[$key] = '%' . $term . '%';
            }
            $where .= ' AND (' . implode(' OR ', $parts) . ')';
        }

        return $this->rows("SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT " . max(1, $limit), $params);
    }

    private function reviewMetrics(int $companyId): array
    {
        if (!$this->reviewsReady()) {
            return ['approved' => 0, 'edited' => 0, 'rejected' => 0];
        }

        $rows = $this->rows('SELECT result, COUNT(*) AS total FROM ai_response_reviews WHERE company_id = :company_id GROUP BY result', ['company_id' => $companyId]);
        $metrics = ['approved' => 0, 'edited' => 0, 'rejected' => 0];
        foreach ($rows as $row) {
            $metrics[(string) $row['result']] = (int) $row['total'];
        }
        return $metrics;
    }

    private function documentMetrics(int $companyId): array
    {
        try {
            $rows = $this->rows('SELECT processing_status, COUNT(*) AS total FROM documents WHERE company_id = :company_id GROUP BY processing_status', ['company_id' => $companyId]);
        } catch (Throwable) {
            return ['processed' => 0, 'failed' => 0];
        }
        $metrics = ['processed' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            if ((string) $row['processing_status'] === 'processed') {
                $metrics['processed'] = (int) $row['total'];
            }
            if ((string) $row['processing_status'] === 'failed') {
                $metrics['failed'] = (int) $row['total'];
            }
        }
        return $metrics;
    }

    private function reviewExamples(int $companyId, int $limit): array
    {
        if (!$this->reviewsReady()) {
            return [];
        }

        return $this->rows(
            'SELECT customer_message, ai_response, final_response, result, difference_summary, channel, intent
             FROM ai_response_reviews
             WHERE company_id = :company_id
               AND result IN ("approved", "edited")
               AND final_response IS NOT NULL
               AND final_response <> ""
             ORDER BY reviewed_at DESC, id DESC
             LIMIT ' . max(1, $limit),
            ['company_id' => $companyId]
        );
    }

    private function rows(string $sql, array $params = []): array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function scalar(string $sql, array $params): int
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    private function ready(): bool
    {
        try {
            foreach (['ai_company_profiles', 'ai_onboarding_sessions', 'ai_personalities', 'ai_business_rules', 'ai_products', 'ai_faqs', 'ai_conversation_examples'] as $table) {
                Database::connection()->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function ensureReady(): void
    {
        if (!$this->ready()) {
            throw new RuntimeException('El esquema de Entrenamiento IA no esta instalado. Ejecuta database/upgrade_abilities.php.');
        }
    }

    private function fallback(): array
    {
        return [
            'session' => ['id' => 0, 'status' => 'draft', 'progress_percent' => 0],
            'profile' => [],
            'personality' => [],
            'answers' => [],
            'metrics' => [],
            'products' => [],
            'rules' => [],
            'faqs' => [],
            'examples' => [],
            'responseReviews' => [],
            'autonomy' => ['learning_progress' => 0, 'label' => 'Aprendizaje supervisado', 'description' => 'Modo seguro inicial.'],
            'channels' => [],
            'prompt' => [],
            'knowledgeSources' => [],
            'knowledgeStats' => ['sources' => 0, 'ready' => 0, 'failed' => 0, 'chunks' => 0],
            'knowledgeQuery' => '',
            'knowledgeResults' => [],
        ];
    }

    private function knowledgeReady(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM ai_knowledge_sources LIMIT 1');
            Database::connection()->query('SELECT 1 FROM ai_knowledge_chunks LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function reviewsReady(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM ai_response_reviews LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function text(array $input, string $key, int $max): string
    {
        return substr(trim((string) ($input[$key] ?? '')), 0, $max);
    }

    private function allowed(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function status(string $value): string
    {
        return $this->allowed($value, ['draft', 'review', 'published', 'archived'], 'draft');
    }

    private function decimal(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        return (float) $value;
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
