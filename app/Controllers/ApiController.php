<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class ApiController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $this->view('integrations/apis', [
            'title' => 'APIs',
            'apis' => [
                ['REST API', 'Endpoints para CRM, cotizaciones, documentos, bandeja y acciones.', 'Preparada'],
                ['Webhooks', 'Recepcion de mensajes omnicanal y eventos externos.', 'Activa'],
                ['API Keys', 'Claves cifradas por empresa para proveedores externos.', 'Sandbox'],
                ['Jobs IA', 'Cola futura para documentos, analisis y automatizaciones.', 'Proximo'],
            ],
        ]);
    }
}
