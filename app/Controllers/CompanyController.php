<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class CompanyController extends Controller
{
    public function profile(): void
    {
        $this->requireAuth();
        $company = $this->currentCompany();

        $this->view('company/show', [
            'title' => 'Empresa',
            'heading' => 'Empresa',
            'description' => 'Datos generales del espacio de trabajo, pais, moneda, zona horaria y plan activo.',
            'cards' => [
                ['Nombre', $company['name'] ?? 'Empresa'],
                ['Pais', $company['country'] ?? 'LatAm'],
                ['Moneda', $company['currency'] ?? 'USD'],
                ['Plan', $company['plan'] ?? 'Starter'],
            ],
        ]);
    }

    public function users(): void
    {
        $this->requireAuth();

        $rows = [];
        if (Database::available()) {
            $statement = Database::connection()->prepare('SELECT u.name, u.email, u.status, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.company_id = :company_id ORDER BY u.id DESC LIMIT 50');
            $statement->execute(['company_id' => $this->companyId()]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->view('company/users', [
            'title' => 'Usuarios',
            'users' => $rows,
        ]);
    }

    public function roles(): void
    {
        $this->requireAuth();

        $roles = [];
        if (Database::available()) {
            $roles = Database::connection()->query('SELECT name, scope, permissions_json FROM roles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->view('company/roles', [
            'title' => 'Roles',
            'roles' => $roles,
        ]);
    }

    public function settings(): void
    {
        $this->requireAuth();
        $this->view('company/show', [
            'title' => 'Configuracion',
            'heading' => 'Configuracion',
            'description' => 'Preferencias generales del espacio: idioma, seguridad, aprobaciones, zona horaria y datos regionales.',
            'cards' => [
                ['Idioma principal', 'Espanol latino'],
                ['Aprobacion humana', 'Activa para acciones sensibles'],
                ['Zona horaria', $this->currentCompany()['timezone'] ?? 'America/Santiago'],
                ['Separacion multiempresa', 'company_id obligatorio'],
            ],
        ]);
    }
}
