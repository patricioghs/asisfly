<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\AITrainingRepository;
use App\Services\AITrainingContextBuilder;
use App\Services\AITrainingDocumentProcessor;

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
            'section' => trim((string) ($_GET['section'] ?? 'summary')),
            'overview' => $overview,
            'questions' => $questions,
            'answersByKey' => $answersByKey,
            'simulation' => $_SESSION['ai_training_simulation'] ?? null,
        ]);
        unset($_SESSION['ai_training_simulation']);
    }

    public function saveOnboarding(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        $questionKey = trim((string) ($_POST['question_key'] ?? ''));
        (new AITrainingRepository())->saveOnboardingAnswer(
            $this->companyId(),
            (int) ($_SESSION['user']['id'] ?? 0),
            $questionKey,
            trim((string) ($_POST['answer'] ?? ''))
        );
        $_SESSION['flash_success'] = 'Respuesta guardada. Puedes continuar cuando quieras.';
        $this->redirect('/ai-training?section=onboarding#question-' . rawurlencode($questionKey));
    }

    public function saveProfile(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        (new AITrainingRepository())->saveProfile($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST);
        $_SESSION['flash_success'] = 'Perfil de empresa actualizado.';
        $this->redirect('/ai-training?section=company');
    }

    public function savePersonality(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        (new AITrainingRepository())->savePersonality($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST);
        $_SESSION['flash_success'] = 'Personalidad del trabajador virtual actualizada.';
        $this->redirect('/ai-training?section=personality');
    }

    public function addProduct(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        (new AITrainingRepository())->addProduct($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST);
        $_SESSION['flash_success'] = 'Producto o servicio agregado al conocimiento.';
        $this->redirect('/ai-training?section=products');
    }

    public function addRule(): void
    {
        $this->requireTrainingAccess('ai_training.rules.manage');
        (new AITrainingRepository())->addRule($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST);
        $_SESSION['flash_success'] = 'Regla de negocio agregada.';
        $this->redirect('/ai-training?section=rules');
    }

    public function addFaq(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        (new AITrainingRepository())->addFaq($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST);
        $_SESSION['flash_success'] = 'Pregunta frecuente guardada.';
        $this->redirect('/ai-training?section=faqs');
    }

    public function addExample(): void
    {
        $this->requireTrainingAccess('ai_training.examples.manage');
        (new AITrainingRepository())->addExample($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST);
        $_SESSION['flash_success'] = 'Ejemplo aprobado guardado.';
        $this->redirect('/ai-training?section=examples');
    }

    public function saveChannel(): void
    {
        $this->requireTrainingAccess('ai_training.settings.manage');
        (new AITrainingRepository())->saveChannelSetting($this->companyId(), $_POST);
        $_SESSION['flash_success'] = 'Modo de operacion por canal actualizado.';
        $this->redirect('/ai-training?section=settings');
    }

    public function uploadDocument(): void
    {
        $this->requireTrainingAccess('ai_training.documents.manage');
        if (empty($_FILES['document']['name'])) {
            $_SESSION['flash_error'] = 'Selecciona un documento para entrenar a AsisFly.';
            $this->redirect('/ai-training?section=documents');
        }

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
        $this->redirect('/ai-training?section=documents');
    }

    public function reprocessDocument(): void
    {
        $this->requireTrainingAccess('ai_training.documents.manage');
        $documentId = (int) ($_POST['document_id'] ?? 0);
        $result = (new AITrainingDocumentProcessor())->processExistingDocument($this->companyId(), $documentId);

        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'] . (!empty($result['chunks']) ? ' Fragmentos: ' . (string) $result['chunks'] . '.' : '');
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

        $_SESSION['ai_training_simulation'] = (new AITrainingContextBuilder())->simulate($this->companyId(), $message, (int) ($_SESSION['user']['id'] ?? 0));
        $this->redirect('/ai-training?section=simulator');
    }

    public function publish(): void
    {
        $this->requireTrainingAccess('ai_training.publish');
        (new AITrainingRepository())->publishProfile($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0));
        $_SESSION['flash_success'] = 'Conocimiento principal publicado. AsisFly ya puede usar esta version como contexto autorizado.';
        $this->redirect('/ai-training?section=summary');
    }

    private function requireTrainingAccess(string $permission): void
    {
        $this->requireAuth();
        $user = $_SESSION['user'] ?? [];
        $permissions = $user['permissions'] ?? [];
        $role = (string) ($user['role'] ?? '');

        if (in_array('*', $permissions, true)
            || in_array($permission, $permissions, true)
            || in_array('assistant.manage', $permissions, true)
            || in_array('documents.manage', $permissions, true)
            || in_array($role, ['Superadmin', 'Dueno de empresa', 'Administrador'], true)
        ) {
            return;
        }

        http_response_code(403);
        $this->view('errors/403', ['title' => 'Sin permiso', 'permission' => $permission]);
        exit;
    }
}
