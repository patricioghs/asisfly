<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\TenantRepository;

final class AssistantController extends Controller
{
    public function edit(): void
    {
        $this->requirePermission('assistant.manage');
        $this->view('assistant/edit', ['title' => 'Asistente', 'assistant' => (new TenantRepository())->assistant($this->companyId())]);
    }

    public function update(): void
    {
        $this->requirePermission('assistant.manage');
        (new TenantRepository())->saveAssistant($this->companyId(), $_POST);
        $this->redirect('/assistant');
    }
}
