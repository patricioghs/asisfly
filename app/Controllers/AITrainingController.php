<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Repositories\AITrainingRepository;
use App\Services\AITrainingContextBuilder;
use App\Services\AITrainingDocumentProcessor;
use Throwable;

final class AITrainingController extends Controller
{
    public function index(): void
    {
        $this->requireTrainingAccess('ai_training.view');
        $repo = new AITrainingRepository();
        $overview = $repo->overview($this->companyId());
        $questions = $repo->questions();
        $answersByKey = [];
        foreach ($overview['answers'] ?? [] as $answer) {
            $answersByKey[$answer['question_key']] = $answer;
        }

        $this->view('ai_training/index', [
            'title' => 'Centro de Entrenamiento IA',
            'section' => $this->sectionFromRequest(),
            'overview' => $overview,
            'questions' => $questions,
            'answersByKey' => $answersByKey,
            'simulation' => $_SESSION['ai_training_simulation'] ?? null,
        ]);
        unset($_SESSION['ai_training_simulation']);
    }

    private function sectionFromRequest(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if (preg_match('#/ai-training/([a-z-]+)$#', $path, $matches)) {
            return trim((string) $matches[1]);
        }

        return trim((string) ($_GET['section'] ?? 'summary'));
    }

    public function saveOnboarding(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        $questionKey = trim((string) ($_POST['question_key'] ?? ''));
        $answer = trim((string) ($_POST['answer'] ?? ''));
        if ($questionKey === '') {
            $_SESSION['flash_error'] = 'No se pudo identificar la pregunta de entrenamiento.';
            $this->redirect('/ai-training?section=onboarding');
        }

        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->saveOnboardingAnswer($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $questionKey, $answer),
            'Respuesta guardada. Puedes continuar cuando quieras.'
        );
        $this->redirect('/ai-training?section=onboarding#question-' . rawurlencode($questionKey));
    }

    public function saveProfile(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->saveProfile($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST),
            'Perfil de empresa actualizado.'
        );
        $this->redirect('/ai-training?section=company');
    }

    public function savePersonality(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->savePersonality($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST),
            'Personalidad del trabajador virtual actualizada.'
        );
        $this->redirect('/ai-training?section=personality');
    }

    public function addProduct(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        if (trim((string) ($_POST['name'] ?? '')) === '') {
            $_SESSION['flash_error'] = 'Ingresa el nombre del producto o servicio.';
            $this->redirect('/ai-training?section=products');
        }
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->addProduct($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST),
            'Producto o servicio agregado al conocimiento.'
        );
        $this->redirect('/ai-training?section=products');
    }

    public function addRule(): void
    {
        $this->requireTrainingAccess('ai_training.rules.manage');
        if (trim((string) ($_POST['name'] ?? '')) === '') {
            $_SESSION['flash_error'] = 'Ingresa el nombre de la regla.';
            $this->redirect('/ai-training?section=rules');
        }
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->addRule($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST),
            'Regla de negocio agregada.'
        );
        $this->redirect('/ai-training?section=rules');
    }

    public function addFaq(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        if (trim((string) ($_POST['question'] ?? '')) === '' || trim((string) ($_POST['approved_answer'] ?? '')) === '') {
            $_SESSION['flash_error'] = 'Ingresa la pregunta y la respuesta aprobada.';
            $this->redirect('/ai-training?section=faqs');
        }
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->addFaq($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST),
            'Pregunta frecuente guardada.'
        );
        $this->redirect('/ai-training?section=faqs');
    }

    public function addExample(): void
    {
        $this->requireTrainingAccess('ai_training.examples.manage');
        if (trim((string) ($_POST['customer_message'] ?? '')) === '' || trim((string) ($_POST['ideal_response'] ?? '')) === '') {
            $_SESSION['flash_error'] = 'Ingresa el mensaje del cliente y la respuesta ideal.';
            $this->redirect('/ai-training?section=examples');
        }
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->addExample($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST),
            'Ejemplo aprobado guardado.'
        );
        $this->redirect('/ai-training?section=examples');
    }

    public function saveChannel(): void
    {
        $this->requireTrainingAccess('ai_training.settings.manage');
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->saveChannelSetting($this->companyId(), $_POST),
            'Modo de operacion por canal actualizado.'
        );
        $this->redirect('/ai-training?section=settings');
    }

    public function uploadDocument(): void
    {
        $this->requireTrainingAccess('ai_training.documents.manage');
        if (empty($_FILES['document']['name'])) {
            $_SESSION['flash_error'] = 'Selecciona un documento para entrenar a AsisFly.';
            $this->redirect('/ai-training?section=documents');
        }

        try {
            $result = (new AITrainingDocumentProcessor())->processUpload(
                $this->companyId(),
                (int) ($_SESSION['user']['id'] ?? 0),
                $_FILES['document'],
                [
                    'category' => $_POST['category'] ?? 'Entrenamiento IA',
                    'tags' => $_POST['tags'] ?? '',
                ]
            );
            $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'] . (!empty($result['chunks']) ? ' Fragmentos: ' . (string) $result['chunks'] . '.' : '');
        } catch (Throwable $exception) {
            $_SESSION['flash_error'] = 'No se pudo procesar el documento: ' . $exception->getMessage();
        }
        $this->redirect('/ai-training?section=documents');
    }

    public function reprocessDocument(): void
    {
        $this->requireTrainingAccess('ai_training.documents.manage');
        $documentId = (int) ($_POST['document_id'] ?? 0);
        try {
            $result = (new AITrainingDocumentProcessor())->processExistingDocument($this->companyId(), $documentId);
            $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'] . (!empty($result['chunks']) ? ' Fragmentos: ' . (string) $result['chunks'] . '.' : '');
        } catch (Throwable $exception) {
            $_SESSION['flash_error'] = 'No se pudo reprocesar el documento: ' . $exception->getMessage();
        }
        $this->redirect('/ai-training?section=documents');
    }

    public function simulate(): void
    {
        $this->requireTrainingAccess('ai_training.simulator.use');
        $message = trim((string) ($_POST['customer_message'] ?? ''));
        if ($message === '') {
            $_SESSION['flash_error'] = 'Escribe un mensaje de cliente para practicar.';
            $this->redirect('/ai-training?section=simulator');
        }

        try {
            $_SESSION['ai_training_simulation'] = (new AITrainingContextBuilder())->simulate($this->companyId(), $message, (int) ($_SESSION['user']['id'] ?? 0));
        } catch (Throwable $exception) {
            $_SESSION['flash_error'] = 'No se pudo generar la simulacion: ' . $exception->getMessage();
        }
        $this->redirect('/ai-training?section=simulator');
    }

    public function publish(): void
    {
        $this->requireTrainingAccess('ai_training.publish');
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->publishProfile($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0)),
            'Conocimiento principal publicado. AsisFly ya puede usar esta version como contexto autorizado.'
        );
        $this->redirect('/ai-training?section=summary');
    }

    private function runTrainingAction(callable $action, string $successMessage): void
    {
        try {
            $action();
            $_SESSION['flash_success'] = $successMessage;
        } catch (Throwable $exception) {
            $_SESSION['flash_error'] = 'No se pudo guardar el entrenamiento: ' . $exception->getMessage();
        }
    }

    private function requireTrainingAccess(string $permission): void
    {
        $this->requireAuth();
        $this->refreshSessionPermissions();
        $user = $_SESSION['user'] ?? [];
        $permissions = $user['permissions'] ?? [];
        $role = $this->normalizeRole((string) ($user['role'] ?? ''));

        if (in_array('*', $permissions, true)
            || in_array($permission, $permissions, true)
            || in_array('assistant.manage', $permissions, true)
            || in_array('documents.manage', $permissions, true)
            || str_contains(strtolower((string) ($user['role'] ?? '')), 'due')
            || in_array($role, ['superadmin', 'dueno de empresa', 'administrador'], true)
        ) {
            return;
        }

        http_response_code(403);
        $this->view('errors/403', ['title' => 'Sin permiso', 'permission' => $permission]);
        exit;
    }

    private function refreshSessionPermissions(): void
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0 || !Database::available()) {
            return;
        }

        try {
            $statement = Database::connection()->prepare('SELECT r.name AS role_name, r.permissions_json FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1');
            $statement->execute(['id' => $userId]);
            $row = $statement->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return;
            }

            $_SESSION['user']['role'] = (string) $row['role_name'];
            $_SESSION['user']['permissions'] = json_decode((string) ($row['permissions_json'] ?? '[]'), true) ?: [];
        } catch (Throwable) {
            // Keep current session permissions if refresh fails.
        }
    }

    private function normalizeRole(string $role): string
    {
        $role = strtolower(trim($role));
        if (function_exists('iconv')) {
            $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $role);
            if (is_string($normalized) && $normalized !== '') {
                $role = $normalized;
            }
        }
        $role = preg_replace('/[^a-z0-9 ]+/', '', $role) ?: $role;
        $role = preg_replace('/\s+/', ' ', $role) ?: $role;

        return $role;
    }
}
