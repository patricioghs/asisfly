<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\BillingRepository;

final class BillingController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('billing.manage');
        $this->view('billing/index', [
            'title' => 'Facturacion',
            ...(new BillingRepository())->overview($this->companyId()),
        ]);
    }

    public function changePlan(): void
    {
        $this->requirePermission('billing.manage');
        (new BillingRepository())->changePlan($this->companyId(), (int) $_SESSION['user']['id'], (string) ($_POST['plan'] ?? 'Starter'));
        $_SESSION['company']['plan'] = (string) ($_POST['plan'] ?? ($_SESSION['company']['plan'] ?? 'Starter'));
        $this->redirect('/billing');
    }
}
