<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\AutonomyRepository;
use App\Repositories\BrainRepository;

final class BrainController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('chat.use');
        $repo = new BrainRepository();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'channel' => trim((string) ($_GET['channel'] ?? '')),
            'account_id' => trim((string) ($_GET['account_id'] ?? '')),
        ];

        $this->view('brains/index', [
            'title' => 'Cerebros IA',
            'filters' => $filters,
            'brains' => $repo->all($this->companyId(), $filters),
            'accountsByBrain' => $repo->accountsByBrain($this->companyId()),
            'accounts' => $repo->accounts($this->companyId()),
            'channels' => $repo->channels($this->companyId()),
            'morningBrief' => $repo->morningBrief($this->currentCompany()),
            'autonomy' => (new AutonomyRepository())->profile($this->companyId()),
        ]);
    }
}
