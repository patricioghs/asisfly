<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\AutonomyRepository;
use App\Repositories\TenantRepository;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $repo = new TenantRepository();
        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'company' => $this->currentCompany(),
            'metrics' => $repo->dashboard($this->companyId()),
            'alerts' => $repo->alerts($this->companyId()),
            'usage' => $repo->aiUsage($this->companyId()),
            'autonomy' => (new AutonomyRepository())->profile($this->companyId()),
        ]);
    }
}
