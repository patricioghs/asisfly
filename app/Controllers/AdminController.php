<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\TenantRepository;

final class AdminController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('*');
        $repo = new TenantRepository();
        $this->view('admin/index', [
            'title' => 'Superadmin',
            'companies' => $repo->companies(),
            'usage' => $repo->aiUsage($this->companyId()),
        ]);
    }
}
