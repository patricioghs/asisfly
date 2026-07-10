<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\InboxRepository;
use App\Repositories\OmnichannelRepository;

final class InboxController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('chat.use');
        $repo = new InboxRepository();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'channel' => trim((string) ($_GET['channel'] ?? '')),
            'account_id' => trim((string) ($_GET['account_id'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'priority' => trim((string) ($_GET['priority'] ?? '')),
            'ai_state' => trim((string) ($_GET['ai_state'] ?? '')),
            'intervention' => trim((string) ($_GET['intervention'] ?? '')),
        ];

        $this->view('inbox/index', [
            'title' => 'Centro de Supervision',
            'filters' => $filters,
            'conversations' => $repo->conversations($this->companyId(), $filters),
            'selected' => $repo->selectedConversation($this->companyId(), (int) ($_GET['id'] ?? 0), $filters),
            'metrics' => $repo->metrics($this->companyId()),
            'accounts' => (new OmnichannelRepository())->accounts($this->companyId()),
        ]);
    }

    public function suggest(): void
    {
        $this->requirePermission('chat.use');
        (new InboxRepository())->suggestReply($this->companyId(), (int) ($_POST['conversation_id'] ?? 0), (int) $_SESSION['user']['id']);
        $this->redirect('/inbox?id=' . (int) ($_POST['conversation_id'] ?? 0));
    }

    public function saveDraft(): void
    {
        $this->requirePermission('chat.use');
        $conversationId = (int) ($_POST['conversation_id'] ?? 0);
        (new InboxRepository())->saveDraft($this->companyId(), $conversationId, (int) $_SESSION['user']['id'], (string) ($_SESSION['user']['name'] ?? 'Usuario'), (string) ($_POST['body'] ?? ''));
        $this->redirect('/inbox?id=' . $conversationId);
    }

    public function sendDraft(): void
    {
        $this->requirePermission('chat.use');
        $conversationId = (int) ($_POST['conversation_id'] ?? 0);
        $result = (new InboxRepository())->sendDraft($this->companyId(), $conversationId, (int) $_SESSION['user']['id']);
        $_SESSION[!empty($result['ok']) ? 'flash_success' : 'flash_error'] = (string) ($result['message'] ?? 'No se pudo completar el envio.');
        $this->redirect('/inbox?id=' . $conversationId);
    }

    public function assignHuman(): void
    {
        $this->requirePermission('chat.use');
        (new InboxRepository())->assignHuman($this->companyId(), (int) ($_POST['conversation_id'] ?? 0), (int) $_SESSION['user']['id']);
        $this->redirect('/inbox?id=' . (int) ($_POST['conversation_id'] ?? 0));
    }

    public function status(): void
    {
        $this->requirePermission('chat.use');
        $conversationId = (int) ($_POST['conversation_id'] ?? 0);
        (new InboxRepository())->updateStatus($this->companyId(), $conversationId, (string) ($_POST['status'] ?? ''));
        $this->redirect('/inbox?id=' . $conversationId);
    }
}
