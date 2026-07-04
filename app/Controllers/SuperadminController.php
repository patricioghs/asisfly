<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Repositories\TenantRepository;

final class SuperadminController extends Controller
{
    public function companies(): void
    {
        $this->requirePermission('*');
        $this->view('superadmin/show', [
            'title' => 'Empresas',
            'description' => 'Control global de empresas, planes, usuarios, estado y consumo.',
            'cards' => (new TenantRepository())->companies(),
            'type' => 'companies',
        ]);
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
        $this->view('superadmin/show', [
            'title' => 'IA y tokens',
            'description' => 'Consumo global de IA, modelos, costos estimados y limites por empresa.',
            'cards' => (new TenantRepository())->aiUsage($this->companyId()),
            'type' => 'usage',
        ]);
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
