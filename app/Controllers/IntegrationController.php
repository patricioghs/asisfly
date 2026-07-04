<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\AiProviderRepository;
use App\Repositories\OmnichannelRepository;
use App\Repositories\TenantRepository;
use App\Services\OpenAiClient;
use Throwable;

final class IntegrationController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->view('integrations/index', [
            'title' => 'Integraciones',
            'integrations' => (new TenantRepository())->integrations($this->companyId()),
            'aiSettings' => (new AiProviderRepository())->settings($this->companyId()),
        ]);
    }

    public function saveAi(): void
    {
        $this->requireAuth();
        (new AiProviderRepository())->save($this->companyId(), $_POST);
        $_SESSION['flash_success'] = 'Configuracion IA guardada.';
        $this->redirect('/integrations');
    }

    public function testAi(): void
    {
        $this->requireAuth();

        $repo = new AiProviderRepository();
        $settings = $repo->settings($this->companyId());
        if (($settings['provider'] ?? '') !== 'openai') {
            $_SESSION['flash_error'] = 'Selecciona OpenAI como proveedor principal antes de probar la conexion.';
            $this->redirect('/integrations');
        }

        try {
            $tenant = new TenantRepository();
            $result = (new OpenAiClient())->respond('Responde exactamente: OpenAI conectado con AsisFly.', $settings, [
                'company' => $this->currentCompany(),
                'assistant' => $tenant->assistant($this->companyId()),
                'route' => ['name' => 'Prueba de integracion', 'module' => 'Integraciones'],
                'memory' => [],
            ]);

            $promptTokens = (int) ($result['prompt_tokens'] ?? 0);
            $completionTokens = (int) ($result['completion_tokens'] ?? 0);
            $tenant->logAiUsage($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), 'Integraciones', 'openai', (string) $settings['model'], $promptTokens, $completionTokens, $this->estimateOpenAiCost((string) $settings['model'], $promptTokens, $completionTokens), [
                'prompt_text' => 'Prueba de conexion OpenAI',
                'response_text' => (string) ($result['text'] ?? ''),
                'status' => 'success',
                'request_id' => $result['request_id'] ?? null,
                'metadata' => ['test' => true],
            ]);

            $_SESSION['flash_success'] = 'OpenAI conectado correctamente. Respuesta: ' . trim((string) ($result['text'] ?? 'OK'));
        } catch (Throwable $exception) {
            $_SESSION['flash_error'] = 'No se pudo conectar con OpenAI: ' . $exception->getMessage();
        }

        $this->redirect('/integrations');
    }

    private function estimateOpenAiCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $rates = [
            'gpt-4.1-mini' => [0.0004, 0.0016],
            'gpt-4.1' => [0.0020, 0.0080],
            'gpt-4o-mini' => [0.00015, 0.0006],
            'gpt-4o' => [0.0025, 0.0100],
        ];
        [$inputPer1k, $outputPer1k] = $rates[$model] ?? [0.0010, 0.0040];

        return ($promptTokens / 1000 * $inputPer1k) + ($completionTokens / 1000 * $outputPer1k);
    }

    public function accounts(): void
    {
        $this->requireAuth();
        $repo = new OmnichannelRepository();
        $this->view('integrations/accounts', [
            'title' => 'Cuentas conectadas',
            'accounts' => $repo->accounts($this->companyId()),
            'metrics' => $repo->accountMetrics($this->companyId()),
            'users' => $repo->companyUsers($this->companyId()),
        ]);
    }

    public function saveAccount(): void
    {
        $this->requireAuth();
        (new OmnichannelRepository())->saveAccount($this->companyId(), $_POST);
        $_SESSION['flash_success'] = 'Cuenta conectada creada en modo prueba.';
        $this->redirect('/integrations/accounts');
    }

    public function updateAccount(): void
    {
        $this->requireAuth();
        (new OmnichannelRepository())->updateAccount($this->companyId(), (int) ($_POST['account_id'] ?? 0), $_POST);
        $_SESSION['flash_success'] = 'Cuenta conectada actualizada.';
        $this->redirect('/integrations/accounts');
    }

    public function saveAccountCredentials(): void
    {
        $this->requireAuth();

        try {
            (new OmnichannelRepository())->saveCredentials($this->companyId(), (int) ($_POST['account_id'] ?? 0), $_POST);
            $_SESSION['flash_success'] = 'Credenciales guardadas de forma cifrada.';
        } catch (Throwable $exception) {
            $_SESSION['flash_error'] = 'No se pudieron guardar las credenciales: ' . $exception->getMessage();
        }

        $this->redirect('/integrations/accounts');
    }

    public function testAccountCredentials(): void
    {
        $this->requireAuth();

        $result = (new OmnichannelRepository())->testCredentials($this->companyId(), (int) ($_POST['account_id'] ?? 0));
        if ($result['ok']) {
            $_SESSION['flash_success'] = 'Conexion probada: ' . $result['message'];
        } else {
            $_SESSION['flash_error'] = 'No se pudo conectar: ' . $result['message'];
        }

        $this->redirect('/integrations/accounts');
    }
}
