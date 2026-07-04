<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\BillingRepository;
use App\Repositories\MemoryRepository;
use App\Repositories\TenantRepository;

final class DocumentController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('documents.manage');
        $memory = new MemoryRepository();
        $query = trim((string) ($_GET['q'] ?? ''));
        $this->view('documents/index', [
            'title' => 'Documentos',
            'documents' => (new TenantRepository())->documents($this->companyId()),
            'memoryStats' => $memory->stats($this->companyId()),
            'memoryQuery' => $query,
            'memoryResults' => $query !== '' ? $memory->search($this->companyId(), $query, 6) : [],
        ]);
    }

    public function upload(): void
    {
        $this->requirePermission('documents.manage');
        if (!empty($_FILES['document']['name'])) {
            $limit = (new BillingRepository())->canUse($this->companyId(), 'documents');
            if (!$limit['allowed']) {
                $_SESSION['flash_error'] = $limit['reason'] . ' Actualiza tu plan en Facturacion.';
                $this->redirect('/documents');
            }
            (new TenantRepository())->addDocument($this->companyId(), (int) $_SESSION['user']['id'], $_FILES['document'], $_POST['type'] ?? 'Entrenamiento');
        }
        $this->redirect('/documents');
    }
}
