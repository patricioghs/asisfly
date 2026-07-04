<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Repositories\AuthRepository;
use PDOException;
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
        $roles = [];
        if (Database::available()) {
            $statement = Database::connection()->prepare('SELECT u.name, u.email, u.status, u.created_at, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.company_id = :company_id ORDER BY u.id DESC LIMIT 50');
            $statement->execute(['company_id' => $this->companyId()]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            $roles = Database::connection()->query("SELECT id, name FROM roles WHERE name <> 'Superadmin' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->view('company/users', [
            'title' => 'Usuarios',
            'users' => $rows,
            'roles' => $roles,
        ]);
    }

    public function storeUser(): void
    {
        $this->requirePermission('users.manage');

        $input = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'role_id' => (int) ($_POST['role_id'] ?? 0),
            'status' => in_array($_POST['status'] ?? 'active', ['active', 'invited', 'disabled'], true) ? $_POST['status'] : 'active',
            'locale' => $_SESSION['company']['locale'] ?? 'es_CL',
            'timezone' => $_SESSION['company']['timezone'] ?? 'America/Santiago',
        ];

        if ($input['name'] === '' || !filter_var($input['email'], FILTER_VALIDATE_EMAIL) || strlen($input['password']) < 8) {
            $_SESSION['flash_error'] = 'Ingresa nombre, email valido y contrasena de al menos 8 caracteres.';
            $this->redirect('/users');
        }

        try {
            if (Database::available()) {
                (new AuthRepository())->createUser($this->companyId(), $input);
            }
            $_SESSION['flash_success'] = 'Usuario creado correctamente.';
        } catch (PDOException) {
            $_SESSION['flash_error'] = 'No se pudo crear el usuario. Revisa si el correo ya existe.';
        }

        $this->redirect('/users');
    }

    public function roles(): void
    {
        $this->requireAuth();

        $roles = [];
        if (Database::available()) {
            $roles = Database::connection()->query('SELECT name, permissions_json FROM roles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
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
