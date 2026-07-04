<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\ActionRepository;
use App\Repositories\AutonomyRepository;

final class ActionController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $repo = new ActionRepository();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? 'pending')),
            'module' => trim((string) ($_GET['module'] ?? '')),
            'risk_level' => trim((string) ($_GET['risk_level'] ?? '')),
        ];

        $this->view('actions/index', [
            'title' => 'Centro de Acciones',
            'actions' => $repo->all($this->companyId(), $filters),
            'metrics' => $repo->metrics($this->companyId()),
            'filters' => $filters,
            'modules' => $repo->modules($this->companyId()),
            'autonomy' => (new AutonomyRepository())->profile($this->companyId()),
            'company' => $this->currentCompany(),
        ]);
    }

    public function approve(): void
    {
        $this->requireAuth();
        (new ActionRepository())->transition($this->companyId(), (int) ($_POST['id'] ?? 0), 'approved', (int) $_SESSION['user']['id'], $_SESSION['user']);
        $this->redirectToActions();
    }

    public function reject(): void
    {
        $this->requireAuth();
        (new ActionRepository())->transition($this->companyId(), (int) ($_POST['id'] ?? 0), 'rejected', (int) $_SESSION['user']['id'], $_SESSION['user']);
        $this->redirectToActions();
    }

    public function execute(): void
    {
        $this->requireAuth();
        (new ActionRepository())->transition($this->companyId(), (int) ($_POST['id'] ?? 0), 'executed', (int) $_SESSION['user']['id'], $_SESSION['user']);
        $this->redirectToActions();
    }

    public function fail(): void
    {
        $this->requireAuth();
        (new ActionRepository())->transition($this->companyId(), (int) ($_POST['id'] ?? 0), 'failed', (int) $_SESSION['user']['id'], $_SESSION['user']);
        $this->redirectToActions();
    }

    public function retry(): void
    {
        $this->requireAuth();
        (new ActionRepository())->retry($this->companyId(), (int) ($_POST['id'] ?? 0), (int) $_SESSION['user']['id']);
        $this->redirectToActions();
    }

    public function comment(): void
    {
        $this->requireAuth();
        (new ActionRepository())->addComment($this->companyId(), (int) ($_POST['id'] ?? 0), (int) $_SESSION['user']['id'], (string) ($_POST['comment'] ?? ''));
        $this->redirectToActions();
    }

    private function redirectToActions(): void
    {
        $query = http_build_query(array_filter([
            'q' => $_POST['filter_q'] ?? null,
            'status' => $_POST['filter_status'] ?? null,
            'module' => $_POST['filter_module'] ?? null,
            'risk_level' => $_POST['filter_risk_level'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        $this->redirect('/actions' . ($query ? '?' . $query : ''));
    }
}
