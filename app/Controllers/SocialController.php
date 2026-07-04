<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\SocialRepository;

final class SocialController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('chat.use');
        $repo = new SocialRepository();
        $month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? (string) $_GET['month'] : date('Y-m');

        $this->view('social/index', [
            'title' => 'Asisti Social',
            'posts' => $repo->posts($this->companyId(), $month),
            'insights' => $repo->insights($this->companyId(), $this->currentCompany()),
            'metrics' => $repo->metrics($this->companyId()),
            'channels' => $repo->channels(),
            'templates' => $repo->templates($this->companyId()),
            'campaigns' => $repo->campaigns($this->companyId()),
            'performance' => $repo->performance($this->companyId()),
            'month' => $month,
        ]);
    }

    public function generate(): void
    {
        $this->requirePermission('chat.use');
        if ((int) ($_POST['quantity'] ?? 0) < 1) {
            $this->redirect('/social');
        }
        (new SocialRepository())->generateCampaign($this->companyId(), $_POST, $this->currentCompany());
        $this->redirect('/social');
    }

    public function status(): void
    {
        $this->requirePermission('chat.use');
        (new SocialRepository())->updatePostStatus($this->companyId(), (int) ($_POST['post_id'] ?? 0), (string) ($_POST['status'] ?? ''));
        $this->redirect('/social?month=' . date('Y-m'));
    }
}
