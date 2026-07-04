<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Repositories\AuthRepository;
use App\Repositories\AiProviderRepository;
use App\Repositories\TenantRepository;
use PDOException;
use Throwable;

final class SuperadminController extends Controller
{
    public function companies(): void
    {
        $this->requirePermission('*');
        $plans = Database::available() ? Database::connection()->query('SELECT name FROM plans ORDER BY monthly_price')->fetchAll() : [];
        $this->view('superadmin/show', [
            'title' => 'Empresas',
            'description' => 'Control global de empresas, planes, usuarios, estado y consumo.',
            'cards' => (new TenantRepository())->companies(),
            'plans' => $plans,
            'type' => 'companies',
        ]);
    }

    public function storeCompany(): void
    {
        $this->requirePermission('*');

        $input = [
            'company' => trim((string) ($_POST['company'] ?? '')),
            'name' => trim((string) ($_POST['owner_name'] ?? '')),
            'email' => trim((string) ($_POST['owner_email'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'country' => trim((string) ($_POST['country'] ?? 'Chile')),
            'currency' => strtoupper(trim((string) ($_POST['currency'] ?? 'CLP'))),
            'timezone' => trim((string) ($_POST['timezone'] ?? 'America/Santiago')),
            'locale' => trim((string) ($_POST['locale'] ?? 'es_CL')),
            'plan' => trim((string) ($_POST['plan'] ?? 'Starter')),
        ];

        if ($input['company'] === '' || $input['name'] === '' || !filter_var($input['email'], FILTER_VALIDATE_EMAIL) || strlen($input['password']) < 8) {
            $_SESSION['flash_error'] = 'Completa empresa, dueno, email valido y contrasena de al menos 8 caracteres.';
            $this->redirect('/admin/companies');
        }

        try {
            if (Database::available()) {
                (new AuthRepository())->createCompanyOwner($input);
            }
            $_SESSION['flash_success'] = 'Empresa creada con dueno inicial.';
        } catch (PDOException) {
            $_SESSION['flash_error'] = 'No se pudo crear la empresa. Revisa si el correo ya existe.';
        }

        $this->redirect('/admin/companies');
    }

    public function plans(): void
    {
        $this->requirePermission('*');
        $plans = Database::available() ? Database::connection()->query('SELECT name, monthly_price, limits_json FROM plans ORDER BY monthly_price')->fetchAll() : [];
        $this->view('superadmin/show', [
            'title' => 'Planes',
            'description' => 'Planes comerciales, limites y estructura de suscripcion.',
            'cards' => $plans,
            'type' => 'plans',
        ]);
    }

    public function aiTokens(): void
    {
        $this->requirePermission('*');
        $aiRepo = new AiProviderRepository();
        $this->view('superadmin/show', [
            'title' => 'IA y tokens',
            'description' => 'Claves API administradas por Superadmin, consumo global de IA, modelos, costos estimados y limites por empresa.',
            'cards' => (new TenantRepository())->aiUsage($this->companyId()),
            'credentials' => $aiRepo->companyCredentialStatus(),
            'type' => 'usage',
        ]);
    }

    public function saveOpenAiKey(): void
    {
        $this->requirePermission('*');

        $companyId = (int) ($_POST['company_id'] ?? 0);
        $apiKey = trim((string) ($_POST['openai_api_key'] ?? ''));

        if ($companyId < 1 || $apiKey === '' || !str_starts_with($apiKey, 'sk-')) {
            $_SESSION['flash_error'] = 'Selecciona empresa e ingresa una API key valida de OpenAI.';
            $this->redirect('/admin/ai-tokens');
        }

        try {
            (new AiProviderRepository())->saveManagedApiKey($companyId, $apiKey);
            $_SESSION['flash_success'] = 'Clave OpenAI guardada de forma cifrada para la empresa seleccionada.';
        } catch (Throwable $exception) {
            $_SESSION['flash_error'] = 'No se pudo guardar la clave: ' . $exception->getMessage();
        }

        $this->redirect('/admin/ai-tokens');
    }

    public function deleteOpenAiKey(): void
    {
        $this->requirePermission('*');

        $companyId = (int) ($_POST['company_id'] ?? 0);
        if ($companyId < 1) {
            $_SESSION['flash_error'] = 'Selecciona una empresa valida.';
            $this->redirect('/admin/ai-tokens');
        }

        (new AiProviderRepository())->forgetManagedApiKey($companyId);
        $_SESSION['flash_success'] = 'Clave administrada eliminada. Si existe OPENAI_API_KEY en .env, quedara como respaldo.';
        $this->redirect('/admin/ai-tokens');
    }

    public function auditLogs(): void
    {
        $this->requirePermission('*');
        $this->view('superadmin/show', [
            'title' => 'Auditoria y logs',
            'description' => 'Eventos de seguridad, acciones IA, aprobaciones y actividad critica.',
            'cards' => [
                ['event' => 'login_success', 'module' => 'Auth', 'status' => 'ok'],
                ['event' => 'action_approved', 'module' => 'Acciones', 'status' => 'auditado'],
                ['event' => 'ai_fallback', 'module' => 'IA', 'status' => 'registrado'],
            ],
            'type' => 'logs',
        ]);
    }

    public function systemStatus(): void
    {
        $this->requirePermission('*');
        $this->view('superadmin/show', [
            'title' => 'Estado del sistema',
            'description' => 'Salud de aplicacion, base de datos, sesiones, almacenamiento y conectores.',
            'cards' => [
                ['service' => 'Aplicacion PHP', 'status' => 'Operativa', 'detail' => PHP_VERSION],
                ['service' => 'Base de datos', 'status' => Database::available() ? 'Conectada' : 'No disponible', 'detail' => 'MySQL'],
                ['service' => 'Sesiones', 'status' => 'Operativas', 'detail' => 'storage/sessions'],
                ['service' => 'Conectores', 'status' => 'Sandbox', 'detail' => 'Listos para APIs reales'],
            ],
            'type' => 'status',
        ]);
    }
}
