<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Repositories\AITrainingRepository;
use App\Services\AITrainingContextBuilder;
use App\Services\AITrainingDocumentProcessor;
use App\Services\AITrainingQuickStartService;
use Throwable;

final class AITrainingController extends Controller
{
    public function index(): void
    {
        $this->show($this->sectionFromRequest());
    }

    public function summary(): void
    {
        $this->show('summary');
    }

    public function onboarding(): void
    {
        $this->show('onboarding');
    }

    public function quickStart(): void
    {
        $this->show('quick-start');
    }

    public function company(): void
    {
        $this->show('company');
    }

    public function products(): void
    {
        $this->show('products');
    }

    public function personality(): void
    {
        $this->show('personality');
    }

    public function rules(): void
    {
        $this->show('rules');
    }

    public function faqs(): void
    {
        $this->show('faqs');
    }

    public function documents(): void
    {
        $this->show('documents');
    }

    public function examples(): void
    {
        $this->show('examples');
    }

    public function corrections(): void
    {
        $this->show('corrections');
    }

    public function routing(): void
    {
        $this->show('routing');
    }

    public function simulator(): void
    {
        $this->show('simulator');
    }

    public function settings(): void
    {
        $this->show('settings');
    }

    public function versions(): void
    {
        $this->show('versions');
    }

    private function show(string $section): void
    {
        $this->requireTrainingAccess('ai_training.view');
        $repo = new AITrainingRepository();
        $overview = $repo->overview($this->companyId(), $this->selectedBrandRouteId());
        $questions = $repo->questions();
        $answersByKey = [];
        foreach ($overview['answers'] ?? [] as $answer) {
            $answersByKey[$answer['question_key']] = $answer;
        }

        $this->view('ai_training/index', [
            'title' => 'Centro de Entrenamiento IA',
            'section' => $section,
            'overview' => $overview,
            'questions' => $questions,
            'answersByKey' => $answersByKey,
            'simulation' => $_SESSION['ai_training_simulation'] ?? null,
            'quickStartDraft' => $repo->quickStartDraft($this->companyId(), 0, (int) ($overview['selectedBrandRouteId'] ?? 0)),
        ]);
        unset($_SESSION['ai_training_simulation']);
    }

    private function sectionFromRequest(): string
    {
        foreach (['REQUEST_URI', 'PATH_INFO', 'ORIG_PATH_INFO', 'REDIRECT_URL'] as $key) {
            $value = (string) ($_SERVER[$key] ?? '');
            $path = parse_url($value, PHP_URL_PATH) ?: $value;
            if (preg_match('#(?:^|/)ai-training/([a-z-]+)(?:/)?$#', trim($path, '/'), $matches)) {
                return trim((string) $matches[1]);
            }
            if (preg_match('~(?:^|/)ai-training/([a-z-]+)(?:[?#].*)?$~', $value, $matches)) {
                return trim((string) $matches[1]);
            }
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
            $this->redirect($this->sectionUrl('onboarding'));
        }

        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->saveOnboardingAnswer($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $questionKey, $answer, (int) ($_POST['brand_route_id'] ?? 0)),
            'Respuesta guardada. Puedes continuar cuando quieras.'
        );
        $this->redirect($this->sectionUrl('onboarding', (int) ($_POST['brand_route_id'] ?? 0)) . '#question-' . rawurlencode($questionKey));
    }

    public function generateQuickStart(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        $brief = trim((string) ($_POST['business_brief'] ?? ''));
        $requestedBrandRouteId = (int) ($_POST['brand_route_id'] ?? 0);
        $repo = new AITrainingRepository();
        $overview = $repo->overview($this->companyId(), $requestedBrandRouteId);
        $brandRouteId = (int) ($overview['selectedBrandRouteId'] ?? 0);

        try {
            $draft = (new AITrainingQuickStartService())->createDraft(
                $this->companyId(),
                (int) ($_SESSION['user']['id'] ?? 0),
                $this->currentCompany(),
                $brandRouteId,
                $brief
            );
            $_SESSION['flash_success'] = (($draft['source'] ?? '') === 'openai'
                ? 'AsisFly preparó un borrador con IA. Revísalo antes de aplicarlo.'
                : 'AsisFly preparó un borrador inicial. Puedes completarlo y aplicarlo cuando esté correcto.');
        } catch (Throwable $exception) {
            $_SESSION['flash_error'] = 'No se pudo preparar el borrador: ' . $exception->getMessage();
        }

        $this->redirect($this->sectionUrl('quick-start', $brandRouteId));
    }

    public function applyQuickStart(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        $draftId = (int) ($_POST['draft_id'] ?? 0);
        $brandRouteId = (int) ($_POST['brand_route_id'] ?? 0);
        try {
            $counts = (new AITrainingRepository())->applyQuickStartDraft($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $draftId);
            $_SESSION['flash_success'] = 'Borrador aplicado: perfil, personalidad, ' . $counts['products'] . ' productos, ' . $counts['rules'] . ' reglas y ' . $counts['faqs'] . ' FAQs. AsisFly seguirá en modo supervisado hasta que configures la autonomía por canal.';
        } catch (Throwable $exception) {
            $_SESSION['flash_error'] = 'No se pudo aplicar el borrador: ' . $exception->getMessage();
        }
        $this->redirect($this->sectionUrl('quick-start', $brandRouteId));
    }

    public function saveProfile(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->saveProfile($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST),
            'Perfil de empresa actualizado.'
        );
        $this->redirect($this->sectionUrl('company', (int) ($_POST['brand_route_id'] ?? 0)));
    }

    public function savePersonality(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->savePersonality($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST),
            'Personalidad del trabajador virtual actualizada.'
        );
        $this->redirect($this->sectionUrl('personality', (int) ($_POST['brand_route_id'] ?? 0)));
    }

    public function addProduct(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        if (trim((string) ($_POST['name'] ?? '')) === '') {
            $_SESSION['flash_error'] = 'Ingresa el nombre del producto o servicio.';
            $this->redirect($this->sectionUrl('products', (int) ($_POST['brand_route_id'] ?? 0)));
        }
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->addProduct($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST),
            'Producto o servicio agregado al conocimiento.'
        );
        $this->redirect($this->sectionUrl('products', (int) ($_POST['brand_route_id'] ?? 0)));
    }

    public function addRule(): void
    {
        $this->requireTrainingAccess('ai_training.rules.manage');
        if (trim((string) ($_POST['name'] ?? '')) === '') {
            $_SESSION['flash_error'] = 'Ingresa el nombre de la regla.';
            $this->redirect($this->sectionUrl('rules', (int) ($_POST['brand_route_id'] ?? 0)));
        }
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->addRule($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST),
            'Regla de negocio agregada.'
        );
        $this->redirect($this->sectionUrl('rules', (int) ($_POST['brand_route_id'] ?? 0)));
    }

    public function addFaq(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        if (trim((string) ($_POST['question'] ?? '')) === '' || trim((string) ($_POST['approved_answer'] ?? '')) === '') {
            $_SESSION['flash_error'] = 'Ingresa la pregunta y la respuesta aprobada.';
            $this->redirect($this->sectionUrl('faqs', (int) ($_POST['brand_route_id'] ?? 0)));
        }
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->addFaq($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST),
            'Pregunta frecuente guardada.'
        );
        $this->redirect($this->sectionUrl('faqs', (int) ($_POST['brand_route_id'] ?? 0)));
    }

    public function addExample(): void
    {
        $this->requireTrainingAccess('ai_training.examples.manage');
        if (trim((string) ($_POST['customer_message'] ?? '')) === '' || trim((string) ($_POST['ideal_response'] ?? '')) === '') {
            $_SESSION['flash_error'] = 'Ingresa el mensaje del cliente y la respuesta ideal.';
            $this->redirect($this->sectionUrl('examples', (int) ($_POST['brand_route_id'] ?? 0)));
        }
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->addExample($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST),
            'Ejemplo aprobado guardado.'
        );
        $this->redirect($this->sectionUrl('examples', (int) ($_POST['brand_route_id'] ?? 0)));
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

    public function addBrandRoute(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        if (trim((string) ($_POST['brand_name'] ?? '')) === '') {
            $_SESSION['flash_error'] = 'Ingresa el nombre de la marca o empresa.';
            $this->redirect('/ai-training?section=routing');
        }

        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->addBrandRoute($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST),
            'Marca agregada al enrutador. En esta fase solo se usara como configuracion visible.'
        );
        $this->redirect('/ai-training?section=routing');
    }

    public function saveRoutingSettings(): void
    {
        $this->requireTrainingAccess('ai_training.edit');
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->saveRoutingSettings($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST),
            'Configuracion de enrutamiento guardada.'
        );
        $this->redirect('/ai-training?section=routing');
    }

    public function uploadDocument(): void
    {
        $this->requireTrainingAccess('ai_training.documents.manage');
        if (empty($_FILES['document']['name'])) {
            $_SESSION['flash_error'] = 'Selecciona un documento para entrenar a AsisFly.';
            $this->redirect($this->sectionUrl('documents', (int) ($_POST['brand_route_id'] ?? 0)));
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
        $this->redirect($this->sectionUrl('documents', (int) ($_POST['brand_route_id'] ?? 0)));
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
            $this->redirect($this->sectionUrl('simulator', (int) ($_POST['brand_route_id'] ?? 0)));
        }

        try {
            $_SESSION['ai_training_simulation'] = (new AITrainingContextBuilder())->simulate($this->companyId(), $message, (int) ($_SESSION['user']['id'] ?? 0));
        } catch (Throwable $exception) {
            $_SESSION['flash_error'] = 'No se pudo generar la simulacion: ' . $exception->getMessage();
        }
        $this->redirect($this->sectionUrl('simulator', (int) ($_POST['brand_route_id'] ?? 0)));
    }

    public function publish(): void
    {
        $this->requireTrainingAccess('ai_training.publish');
        $this->runTrainingAction(
            fn (): mixed => (new AITrainingRepository())->publishProfile($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), (int) ($_POST['brand_route_id'] ?? 0)),
            'Conocimiento principal publicado. AsisFly ya puede usar esta version como contexto autorizado.'
        );
        $this->redirect($this->sectionUrl('summary', (int) ($_POST['brand_route_id'] ?? 0)));
    }

    private function selectedBrandRouteId(): int
    {
        return max(0, (int) ($_GET['brand_route_id'] ?? $_POST['brand_route_id'] ?? 0));
    }

    private function sectionUrl(string $section, int $brandRouteId = 0): string
    {
        $query = ['section' => $section];
        if ($brandRouteId > 0) {
            $query['brand_route_id'] = $brandRouteId;
        }

        return '/ai-training?' . http_build_query($query);
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
