<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\CrmRepository;
use App\Repositories\TenantRepository;

final class CrmController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('crm.manage');
        $currency = $this->currentCompany()['currency'];
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'stage' => trim((string) ($_GET['stage'] ?? '')),
            'temperature' => trim((string) ($_GET['temperature'] ?? '')),
            'customer_id' => (int) ($_GET['customer_id'] ?? 0),
        ];
        $crm = (new CrmRepository())->overview($this->companyId(), $filters, $currency);
        $this->view('crm/index', [
            'title' => 'CRM',
            'filters' => $filters,
            'metrics' => $crm['metrics'],
            'customers' => $crm['customers'] ?: (new TenantRepository())->customers($this->companyId(), $currency),
            'selected' => $crm['selected'],
            'stages' => $crm['stages'],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('crm.manage');
        if (trim($_POST['company'] ?? '') === '' || trim($_POST['contact'] ?? '') === '') {
            $this->redirect('/crm');
        }
        $id = (new CrmRepository())->createCustomer($this->companyId(), (int) $_SESSION['user']['id'], $_POST, $this->currentCompany()['currency']);
        $this->redirect('/crm?customer_id=' . $id);
    }

    public function addNote(): void
    {
        $this->requirePermission('crm.manage');
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        (new CrmRepository())->addNote($this->companyId(), $customerId, (int) $_SESSION['user']['id'], (string) ($_POST['note'] ?? ''));
        $this->redirect('/crm?customer_id=' . $customerId);
    }

    public function addTask(): void
    {
        $this->requirePermission('crm.manage');
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        (new CrmRepository())->addTask($this->companyId(), $customerId, (int) $_SESSION['user']['id'], $_POST);
        $this->redirect('/crm?customer_id=' . $customerId);
    }

    public function completeTask(): void
    {
        $this->requirePermission('crm.manage');
        (new CrmRepository())->completeTask($this->companyId(), (int) ($_POST['task_id'] ?? 0), (int) $_SESSION['user']['id']);
        $this->redirect('/crm?customer_id=' . (int) ($_POST['customer_id'] ?? 0));
    }

    public function updateState(): void
    {
        $this->requirePermission('crm.manage');
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        (new CrmRepository())->updateCustomerState($this->companyId(), $customerId, (int) $_SESSION['user']['id'], $_POST);
        $this->redirect('/crm?customer_id=' . $customerId);
    }

    public function addOpportunity(): void
    {
        $this->requirePermission('crm.manage');
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        (new CrmRepository())->addOpportunity($this->companyId(), $customerId, (int) $_SESSION['user']['id'], $_POST, $this->currentCompany()['currency']);
        $this->redirect('/crm?customer_id=' . $customerId);
    }

    public function automaticFollowups(): void
    {
        $this->requirePermission('crm.manage');
        (new CrmRepository())->createAutomaticFollowups($this->companyId(), (int) $_SESSION['user']['id']);
        $this->redirect('/crm?temperature=cold');
    }
}
