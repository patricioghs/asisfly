<?php

declare(strict_types=1);

namespace App\Services;

final class DemoRepository
{
    public function dashboard(): array
    {
        return [
            ['label' => 'Correos revisados', 'value' => 48, 'trend' => '+12%'],
            ['label' => 'Mensajes respondidos', 'value' => 126, 'trend' => '+18%'],
            ['label' => 'Reuniones agendadas', 'value' => 9, 'trend' => '+3'],
            ['label' => 'Tareas creadas', 'value' => 21, 'trend' => '+7'],
            ['label' => 'Clientes contactados', 'value' => 34, 'trend' => '+11'],
            ['label' => 'Oportunidades detectadas', 'value' => 14, 'trend' => '+5'],
            ['label' => 'Documentos analizados', 'value' => 17, 'trend' => '+4'],
            ['label' => 'Consumo IA', 'value' => '82k tokens', 'trend' => 'USD 1.94'],
        ];
    }

    public function alerts(): array
    {
        return [
            '3 correos comerciales requieren aprobacion humana antes de responder.',
            '2 clientes llevan mas de 72 horas sin seguimiento.',
            'La integracion WhatsApp esta en modo sandbox.',
        ];
    }

    public function customers(): array
    {
        return $_SESSION['customers'] ?? [
            ['name' => 'Comercial Pacifico', 'contact' => 'Valentina Rojas', 'stage' => 'Negociacion', 'value' => '$2.450.000'],
            ['name' => 'Grupo Norte', 'contact' => 'Diego Morales', 'stage' => 'Propuesta enviada', 'value' => '$980.000'],
            ['name' => 'Clinica Aurora', 'contact' => 'Mariana Silva', 'stage' => 'Descubrimiento', 'value' => '$4.100.000'],
        ];
    }

    public function quotes(): array
    {
        return $_SESSION['quotes'] ?? [
            ['client' => 'Comercial Pacifico', 'items' => 'Implementacion AsisFly Pro', 'total' => '$2.450.000', 'status' => 'Borrador'],
            ['client' => 'Grupo Norte', 'items' => 'Automatizacion CRM', 'total' => '$980.000', 'status' => 'Enviada'],
        ];
    }

    public function integrations(): array
    {
        return [
            ['provider' => 'gmail', 'name' => 'Gmail', 'status' => 'Simulado', 'scope' => 'Correos, borradores, etiquetas'],
            ['provider' => 'outlook', 'name' => 'Outlook', 'status' => 'Simulado', 'scope' => 'Correos y calendario Microsoft'],
            ['provider' => 'google_calendar', 'name' => 'Google Calendar', 'status' => 'Simulado', 'scope' => 'Disponibilidad y eventos'],
            ['provider' => 'whatsapp_business', 'name' => 'WhatsApp Business API', 'status' => 'Sandbox', 'scope' => 'Mensajes y derivacion humana'],
            ['provider' => 'instagram', 'name' => 'Instagram', 'status' => 'Simulado', 'scope' => 'DM, FAQ y oportunidades'],
            ['provider' => 'facebook', 'name' => 'Facebook Messenger', 'status' => 'Simulado', 'scope' => 'Mensajeria y CRM'],
            ['provider' => 'telegram', 'name' => 'Telegram', 'status' => 'Simulado', 'scope' => 'Soporte conversacional'],
        ];
    }

    public function aiUsage(): array
    {
        return $_SESSION['ai_usage'] ?? [
            ['provider' => 'simulated', 'model' => 'asisfly-demo-latam', 'tokens' => 12400, 'cost' => 'USD 0.28', 'module' => 'Chat'],
            ['provider' => 'simulated', 'model' => 'asisfly-demo-latam', 'tokens' => 32800, 'cost' => 'USD 0.74', 'module' => 'Documentos'],
        ];
    }
}
