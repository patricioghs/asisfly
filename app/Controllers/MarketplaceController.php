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
            $items = array_values(array_filter($items, fn (array $item): bool => ($item['resolved_ability_key'] ?? '') !== 'core_superadmin'));
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

        $abilityKey = $this->abilityIdentifierFromRequest();
        $result = (new MarketplaceService())->activate($this->companyId(), $abilityKey, (int) ($_SESSION['user']['id'] ?? 0));
        $this->logMarketplaceAction('activate', $abilityKey, $result);

        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'];
        $this->redirect('/marketplace');
    }

    public function disable(): void
    {
        $this->requireAuth();
        $this->ensureCanManageMarketplace();

        $abilityKey = $this->abilityIdentifierFromRequest();
        $result = (new MarketplaceService())->disable($this->companyId(), $abilityKey, (int) ($_SESSION['user']['id'] ?? 0));
        $this->logMarketplaceAction('disable', $abilityKey, $result);

        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['message'];
        $this->redirect('/marketplace');
    }

    private function abilityIdentifierFromRequest(): string
    {
        $abilityKey = trim((string) ($_POST['ability_key'] ?? ''));
        if ($abilityKey !== '') {
            return $abilityKey;
        }

        return trim((string) ($_POST['ability_id'] ?? ''));
    }

    private function logMarketplaceAction(string $action, string $identifier, array $result): void
    {
        try {
            $dir = dirname(__DIR__, 2) . '/logs';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            file_put_contents(
                $dir . '/marketplace.log',
                json_encode([
                    'at' => date('c'),
                    'action' => $action,
                    'company_id' => $this->companyId(),
                    'user_id' => $_SESSION['user']['id'] ?? null,
                    'identifier' => $identifier,
                    'post_ability_key' => $_POST['ability_key'] ?? null,
                    'post_ability_id' => $_POST['ability_id'] ?? null,
                    'ok' => $result['ok'] ?? false,
                    'message' => $result['message'] ?? null,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable) {
            // El log no debe interrumpir la operacion del Marketplace.
        }
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
