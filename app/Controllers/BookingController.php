<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\BookingRepository;

final class BookingController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('chat.use');
        $repo = new BookingRepository();
        $this->view('bookings/index', [
            'title' => 'Agenda y reservas',
            ...$repo->overview($this->companyId(), $this->currentCompany(), (string) ($_GET['date'] ?? '')),
        ]);
    }

    public function saveSettings(): void
    {
        $this->requirePermission('company.manage');
        (new BookingRepository())->saveSettings($this->companyId(), $this->currentCompany(), $_POST);
        $_SESSION['flash_success'] = 'Configuracion de reservas actualizada.';
        $this->redirect('/bookings');
    }

    public function addResource(): void
    {
        $this->requirePermission('chat.use');
        $ok = (new BookingRepository())->addResource($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST);
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok ? 'Responsable agregado a la agenda.' : 'Indica un nombre para el responsable.';
        $this->redirect('/bookings');
    }

    public function addRule(): void
    {
        $this->requirePermission('chat.use');
        $resourceId = (int) ($_POST['resource_id'] ?? 0);
        $ok = (new BookingRepository())->saveRule($this->companyId(), $resourceId, $_POST);
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok ? 'Horario disponible agregado.' : 'Revisa el dia y el rango horario.';
        $this->redirect('/bookings?resource_id=' . $resourceId);
    }

    public function deleteRule(): void
    {
        $this->requirePermission('chat.use');
        (new BookingRepository())->deleteRule($this->companyId(), (int) ($_POST['rule_id'] ?? 0));
        $this->redirect('/bookings?resource_id=' . (int) ($_POST['resource_id'] ?? 0));
    }

    public function addBlock(): void
    {
        $this->requirePermission('chat.use');
        $resourceId = (int) ($_POST['resource_id'] ?? 0);
        $ok = (new BookingRepository())->addBlock($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST, (string) ($this->currentCompany()['timezone'] ?? 'America/Santiago'));
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok ? 'Bloqueo agregado a la agenda.' : 'Revisa responsable, fecha y horario del bloqueo.';
        $this->redirect('/bookings?resource_id=' . $resourceId);
    }

    public function create(): void
    {
        $this->requirePermission('chat.use');
        $result = (new BookingRepository())->createInternal($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $_POST, $this->currentCompany());
        $_SESSION[!empty($result['ok']) ? 'flash_success' : 'flash_error'] = (string) $result['message'];
        $this->redirect('/bookings?resource_id=' . (int) ($_POST['resource_id'] ?? 0));
    }

    public function status(): void
    {
        $this->requirePermission('chat.use');
        (new BookingRepository())->updateStatus($this->companyId(), (int) ($_POST['appointment_id'] ?? 0), (string) ($_POST['status'] ?? ''));
        $this->redirect('/bookings?resource_id=' . (int) ($_POST['resource_id'] ?? 0));
    }

    public function reserve(): void
    {
        $token = trim((string) ($_GET['space'] ?? ''));
        $space = $token !== '' ? (new BookingRepository())->publicSpace($token, (string) ($_GET['date'] ?? ''), (int) ($_GET['resource_id'] ?? 0)) : null;
        http_response_code($space ? 200 : 404);
        require dirname(__DIR__, 2) . '/views/bookings/public.php';
    }

    public function reserveStore(): void
    {
        $token = trim((string) ($_POST['space'] ?? ''));
        $result = $token !== '' ? (new BookingRepository())->createPublic($token, $_POST) : ['ok' => false, 'message' => 'Enlace de reserva invalido.'];
        $query = ['space' => $token, 'date' => (string) ($_POST['date'] ?? ''), 'resource_id' => (int) ($_POST['resource_id'] ?? 0)];
        $_SESSION[!empty($result['ok']) ? 'booking_success' : 'booking_error'] = (string) $result['message'];
        $this->redirect('/reserve?' . http_build_query($query));
    }
}
