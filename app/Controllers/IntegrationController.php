<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\AiProviderRepository;
use App\Repositories\OmnichannelRepository;
use App\Repositories\TenantRepository;
use App\Services\OpenAiClient;
use App\Services\WhatsAppControlService;
use App\Services\WhatsAppEmbeddedSignup;
use Throwable;

final class IntegrationController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $omnichannel = new OmnichannelRepository();
        $this->view('integrations/index', [
            'title' => 'Integraciones',
            'aiSettings' => (new AiProviderRepository())->settings($this->companyId()),
            'canManageAiEngine' => in_array('*', $_SESSION['user']['permissions'] ?? [], true),
            'accounts' => $omnichannel->accounts($this->companyId()),
            'accountMetrics' => $omnichannel->accountMetrics($this->companyId()),
            'whatsappSignup' => (new WhatsAppEmbeddedSignup())->status(),
        ]);
    }

    public function saveAi(): void
    {
        $this->requireAuth();
        if (!in_array('*', $_SESSION['user']['permissions'] ?? [], true)) {
            $_SESSION['flash_error'] = 'La configuracion tecnica del motor IA es administrada por AsisFly.';
            $this->redirect('/integrations');
        }

        (new AiProviderRepository())->save($this->companyId(), $_POST);
        $_SESSION['flash_success'] = 'Configuracion IA guardada.';
        $this->redirect('/integrations');
    }

    public function testAi(): void
    {
        $this->requireAuth();
        if (!in_array('*', $_SESSION['user']['permissions'] ?? [], true)) {
            $_SESSION['flash_error'] = 'La prueba tecnica de OpenAI esta reservada para Superadmin.';
            $this->redirect('/integrations');
        }

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
            'whatsappSignup' => (new WhatsAppEmbeddedSignup())->status(),
        ]);
    }

    public function whatsappControl(): void
    {
        $this->requirePermission('company.manage');
        $control = new WhatsAppControlService();
        $this->view('integrations/whatsapp_control', [
            'title' => 'Control por WhatsApp',
            'controllers' => $control->controllers($this->companyId()),
            'users' => $control->users($this->companyId()),
            'accounts' => $control->whatsappAccounts($this->companyId()),
            'resources' => $control->resources($this->companyId()),
            'commands' => $control->commands($this->companyId()),
        ]);
    }

    public function saveWhatsAppController(): void
    {
        $this->requirePermission('company.manage');
        $result = (new WhatsAppControlService())->saveController($this->companyId(), $_POST);
        $_SESSION[!empty($result['ok']) ? 'flash_success' : 'flash_error'] = (string) $result['message'];
        $this->redirect('/integrations/whatsapp-control');
    }

    public function disableWhatsAppController(): void
    {
        $this->requirePermission('company.manage');
        (new WhatsAppControlService())->disableController($this->companyId(), (int) ($_POST['controller_id'] ?? 0));
        $_SESSION['flash_success'] = 'Acceso por WhatsApp desactivado.';
        $this->redirect('/integrations/whatsapp-control');
    }

    public function whatsappConnect(): void
    {
        $this->requireAuth();

        $signup = new WhatsAppEmbeddedSignup();
        $config = $signup->publicConfig();
        if (empty($config['configured'])) {
            $_SESSION['flash_error'] = 'AsisFly aun no tiene configurado Meta Embedded Signup. Pide al Superadmin configurar Meta App ID y Configuration ID.';
            $this->redirect('/integrations/accounts');
        }

        $this->view('integrations/whatsapp_connect', [
            'title' => 'Conectar WhatsApp',
            'config' => $config,
        ]);
    }

    public function whatsappCallback(): void
    {
        $this->requireAuth();
        $_SESSION['flash_success'] = 'Meta devolvio la autorizacion. Si el numero no aparece conectado, vuelve a finalizar el flujo desde la ventana de conexion.';
        $this->redirect('/integrations/accounts');
    }

    public function whatsappEmbeddedResult(): void
    {
        $this->requireAuth();

        $payload = json_decode((string) ($_POST['signup_payload'] ?? ''), true);
        $payload = is_array($payload) ? $payload : [];
        $data = $this->extractWhatsAppSignupData($payload);
        $data['raw_signup'] = $payload;

        if (($data['phone_number_id'] ?? '') === '' && ($data['display_phone_number'] ?? '') === '') {
            $_SESSION['flash_error'] = 'Meta no entrego un numero WhatsApp valido. Revisa que el flujo haya finalizado correctamente.';
            $this->redirect('/integrations/whatsapp/connect');
        }

        $accountId = (new OmnichannelRepository())->saveWhatsAppEmbeddedAccount($this->companyId(), $data);
        if ($accountId > 0) {
            $_SESSION['flash_success'] = 'WhatsApp conectado a AsisFly. Ya puede recibir mensajes y trabajar con supervision IA.';
        } else {
            $_SESSION['flash_error'] = 'No se pudo crear la cuenta WhatsApp conectada.';
        }

        $this->redirect('/integrations/accounts');
    }

    public function saveAccount(): void
    {
        $this->requireAuth();
        (new OmnichannelRepository())->saveAccount($this->companyId(), $_POST);
        $_SESSION['flash_success'] = 'Cuenta conectada creada en modo prueba.';
        $this->redirect('/integrations/accounts');
    }

    private function extractWhatsAppSignupData(array $payload): array
    {
        $flat = $this->flattenArray($payload);
        $pick = function (array $keys) use ($flat): string {
            foreach ($keys as $key) {
                foreach ($flat as $path => $value) {
                    if (str_ends_with($path, $key) && is_scalar($value) && trim((string) $value) !== '') {
                        return trim((string) $value);
                    }
                }
            }

            return '';
        };

        return [
            'waba_id' => $pick(['waba_id', 'whatsapp_business_account_id', 'business_account_id']),
            'phone_number_id' => $pick(['phone_number_id']),
            'display_phone_number' => $pick(['display_phone_number', 'phone_number', 'number']),
            'business_name' => $pick(['business_name', 'verified_name', 'name']),
        ];
    }

    private function flattenArray(array $value, string $prefix = ''): array
    {
        $flat = [];
        foreach ($value as $key => $item) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;
            if (is_array($item)) {
                $flat += $this->flattenArray($item, $path);
            } else {
                $flat[$path] = $item;
            }
        }

        return $flat;
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

    public function syncAccountEmail(): void
    {
        $this->requireAuth();

        try {
            $result = (new OmnichannelRepository())->syncEmailAccount($this->companyId(), (int) ($_POST['account_id'] ?? 0));
            if ($result['ok']) {
                $_SESSION['flash_success'] = $result['message'];
                $this->redirect('/inbox');
            }

            $_SESSION['flash_error'] = $result['message'];
        } catch (Throwable $exception) {
            $_SESSION['flash_error'] = 'No se pudo sincronizar la cuenta: ' . $exception->getMessage();
        }

        $this->redirect('/integrations/accounts');
    }
}
