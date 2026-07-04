<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\AiProviderRepository;
use App\Repositories\OmnichannelRepository;
use App\Repositories\TenantRepository;

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
        $this->redirect('/integrations');
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
}
