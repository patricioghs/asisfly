<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\ControlRepository;

final class ControlController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'category' => trim((string) ($_GET['category'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'control_id' => (int) ($_GET['control_id'] ?? 0),
        ];
        $repo = new ControlRepository();
        $data = $repo->overview($this->companyId(), $filters);

        $this->view('controls/index', [
            'title' => 'Controles',
            ...$data,
            'filters' => $filters,
            'company' => $this->currentCompany(),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();

        $id = (new ControlRepository())->create(
            $this->companyId(),
            (int) ($_SESSION['user']['id'] ?? 0),
            $_POST,
            (string) ($this->currentCompany()['currency'] ?? 'CLP')
        );
        $_SESSION[$id > 0 ? 'flash_success' : 'flash_error'] = $id > 0 ? 'Control creado. AsisFly ya puede comenzar a ordenarlo.' : 'No se pudo crear el control. Revisa el nombre.';
        $this->redirect('/controls' . ($id > 0 ? '?control_id=' . $id : ''));
    }

    public function addEntry(): void
    {
        $this->requireAuth();

        $controlId = (int) ($_POST['control_id'] ?? 0);
        (new ControlRepository())->addEntry(
            $this->companyId(),
            $controlId,
            (int) ($_SESSION['user']['id'] ?? 0),
            $_POST,
            (string) ($this->currentCompany()['currency'] ?? 'CLP')
        );
        $this->redirect('/controls?control_id=' . $controlId);
    }

    public function transition(): void
    {
        $this->requireAuth();

        $controlId = (int) ($_POST['control_id'] ?? 0);
        (new ControlRepository())->transition(
            $this->companyId(),
            $controlId,
            (int) ($_SESSION['user']['id'] ?? 0),
            (string) ($_POST['status'] ?? 'active')
        );
        $this->redirect('/controls?control_id=' . $controlId);
    }

    public function resolveAlert(): void
    {
        $this->requireAuth();

        (new ControlRepository())->resolveAlert(
            $this->companyId(),
            (int) ($_POST['alert_id'] ?? 0),
            (int) ($_SESSION['user']['id'] ?? 0)
        );
        $this->redirect('/controls?control_id=' . (int) ($_POST['control_id'] ?? 0));
    }
}
