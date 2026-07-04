<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\AutonomyRepository;
use App\Repositories\ControlRepository;
use App\Services\AiGateway;

final class ChatController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('chat.use');
        $this->view('chat/index', [
            'title' => 'Chat IA',
            'messages' => $_SESSION['chat'] ?? [],
            'autonomy' => (new AutonomyRepository())->profile($this->companyId()),
        ]);
    }

    public function ask(): void
    {
        $this->requirePermission('chat.use');
        $prompt = trim($_POST['prompt'] ?? '');
        if (strlen($prompt) > 4000) {
            $_SESSION['chat'][] = ['role' => 'assistant', 'content' => 'La solicitud es demasiado larga para esta fase. Resume la tarea y vuelve a intentarlo.'];
            $this->redirect('/chat');
        }
        if ($prompt !== '') {
            $_SESSION['chat'][] = ['role' => 'user', 'content' => $prompt];
            $control = $this->tryCreateControlFromPrompt($prompt);
            if ($control) {
                $_SESSION['chat'][] = $control;
            } else {
                $result = (new AiGateway())->ask($prompt, $this->currentCompany(), $_SESSION['user']);
                $_SESSION['chat'][] = [
                    'role' => 'assistant',
                    'content' => $result['answer'],
                    'brain' => $result['brain']['name'] ?? 'Cerebro Ejecutivo',
                    'module' => $result['brain']['module'] ?? 'Direccion',
                    'status' => $result['status'] ?? 'success',
                ];
            }
        }
        $this->redirect('/chat');
    }

    private function tryCreateControlFromPrompt(string $prompt): ?array
    {
        $normalized = strtolower($prompt);
        $looksLikeControl = str_contains($normalized, 'control de')
            || str_contains($normalized, 'controlar')
            || str_contains($normalized, 'llevaremos el control')
            || str_contains($normalized, 'llevar el control');

        if (!$looksLikeControl) {
            return null;
        }

        $category = 'custom';
        $name = 'Control operativo';
        if (str_contains($normalized, 'gasto')) {
            $name = 'Control de gastos';
            $category = 'financial';
        } elseif (str_contains($normalized, 'pago')) {
            $name = 'Control de pagos pendientes';
            $category = 'administrative';
        } elseif (str_contains($normalized, 'inventario') || str_contains($normalized, 'stock')) {
            $name = 'Control de inventario';
            $category = 'inventory';
        } elseif (str_contains($normalized, 'cliente')) {
            $name = 'Control de clientes';
            $category = 'commercial';
        } elseif (str_contains($normalized, 'obra') || str_contains($normalized, 'operacion')) {
            $name = 'Control operacional';
            $category = 'operations';
        }

        $id = (new ControlRepository())->create($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), [
            'name' => $name,
            'category' => $category,
            'frequency' => 'weekly',
            'source_type' => 'mixed',
            'objective' => 'Proceso creado desde el chat para ordenar datos, generar alertas, tareas y reportes sobre: ' . $prompt,
        ], (string) ($this->currentCompany()['currency'] ?? 'CLP'));

        if ($id <= 0) {
            return null;
        }

        return [
            'role' => 'assistant',
            'content' => 'Perfecto. Cree "' . $name . '" como un control vivo de la empresa. Ya puedes cargar entradas, revisar reglas y ver alertas en /controls?control_id=' . $id,
            'brain' => 'Cerebro Ejecutivo',
            'module' => 'Controles',
            'status' => 'created',
        ];
    }
}
