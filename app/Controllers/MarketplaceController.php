<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\MarketplaceService;

final class MarketplaceController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->ensureCanManageMarketplace();

        $service = new MarketplaceService();
        $items = Database::available() ? $service->items($this->companyId()) : [];
        if (!$this->isSuperadmin()) {
            $items = array_values(array_filter($items, fn (array $item): bool => ($item['ability_key'] ?? '') !== 'core_superadmin'));
        }

        $this->view('marketplace/index', [
            'title' => 'Marketplace',
            'items' => $items,
            'summary' => $this->summary($items),
            'canManage' => $this->canManageMarketplace(),
        ]);
    }

    public function activate(): void
    {
        $this->requireAuth();
        $this->ensureCanManageMarketplace();

        $abilityKey = trim((string) ($_POST['ability_key'] ?? ''));
        $result = (new MarketplaceService())->activate($this->companyId(), $abilityKey, (int) ($_SESSION['user']['id'] ?? 0));

        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'];
        $this->redirect('/marketplace');
    }

    public function disable(): void
    {
        $this->requireAuth();
        $this->ensureCanManageMarketplace();

        $abilityKey = trim((string) ($_POST['ability_key'] ?? ''));
        $result = (new MarketplaceService())->disable($this->companyId(), $abilityKey, (int) ($_SESSION['user']['id'] ?? 0));

        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'];
        $this->redirect('/marketplace');
    }

    private function ensureCanManageMarketplace(): void
    {
        if ($this->canManageMarketplace()) {
            return;
        }

        http_response_code(403);
        $this->view('errors/403', ['title' => 'Sin permiso', 'permission' => 'marketplace.manage']);
        exit;
    }

    private function canManageMarketplace(): bool
    {
        $role = (string) ($_SESSION['user']['role'] ?? '');
        $permissions = $_SESSION['user']['permissions'] ?? [];

        return $role === 'Dueno de empresa' || $this->isSuperadmin() || in_array('*', $permissions, true);
    }

    private function isSuperadmin(): bool
    {
        $role = (string) ($_SESSION['user']['role'] ?? '');
        $permissions = $_SESSION['user']['permissions'] ?? [];

        return $role === 'Superadmin' || in_array('*', $permissions, true);
    }

    private function summary(array $items): array
    {
        $summary = ['total' => count($items), 'active' => 0, 'disabled' => 0, 'available' => 0];
        foreach ($items as $item) {
            $status = $item['tenant_status'] ?? null;
            if ($status === 'active') {
                $summary['active']++;
            } elseif ($status === 'disabled') {
                $summary['disabled']++;
            } else {
                $summary['available']++;
            }
        }

        return $summary;
    }
}
