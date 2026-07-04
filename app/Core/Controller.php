<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\PermissionService;

abstract class Controller
{
    protected function view(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $view = dirname(__DIR__, 2) . '/views/' . $template . '.php';
        require dirname(__DIR__, 2) . '/views/layouts/app.php';
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . \url($path));
        exit;
    }

    protected function requireAuth(): void
    {
        if (empty($_SESSION['user'])) {
            $this->redirect('/login');
        }
    }

    protected function requirePermission(string $permission): void
    {
        $this->requireAuth();
        if (!(new PermissionService())->allows($_SESSION['user'], $permission)) {
            http_response_code(403);
            $this->view('errors/403', ['title' => 'Sin permiso', 'permission' => $permission]);
            exit;
        }
    }

    protected function currentCompany(): array
    {
        return $_SESSION['company'] ?? [
            'id' => 1,
            'name' => 'Andes Demo SpA',
            'country' => 'Chile',
            'currency' => 'CLP',
            'timezone' => 'America/Santiago',
            'plan' => 'Business',
        ];
    }

    protected function companyId(): int
    {
        return (int) ($this->currentCompany()['id'] ?? 0);
    }
}
