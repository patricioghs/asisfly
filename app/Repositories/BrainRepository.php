<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class BrainRepository
{
    public function all(int $companyId, array $filters = []): array
    {
        if (!$this->databaseReady()) {
            return $this->filterBrains($this->defaults(), $filters, []);
        }

        $statement = Database::connection()->prepare('SELECT * FROM ai_brains WHERE company_id = :company_id ORDER BY sort_order, id');
        $statement->execute(['company_id' => $companyId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return $this->filterBrains($this->defaults(), $filters, $this->accountsByBrain($companyId));
        }

        $brains = array_map(fn (array $row) => [
            'key' => $row['brain_key'],
            'name' => $row['name'],
            'mission' => $row['mission'],
            'status' => $row['status'],
            'primary_metric' => $row['primary_metric'],
            'channels' => json_decode($row['channels_json'] ?? '[]', true) ?: [],
            'actions' => json_decode($row['actions_json'] ?? '[]', true) ?: [],
            'signals' => json_decode($row['signals_json'] ?? '[]', true) ?: [],
            'examples' => json_decode($row['examples_json'] ?? '[]', true) ?: [],
            'connectors' => json_decode($row['connectors_json'] ?? '[]', true) ?: [],
        ], $rows);

        return $this->filterBrains($brains, $filters, $this->accountsByBrain($companyId));
    }

    public function accountsByBrain(int $companyId): array
    {
        if (!$this->hasOmnichannelAccounts()) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT id, display_name, channel, provider, status, brain_key FROM omnichannel_accounts WHERE company_id = :company_id ORDER BY channel, display_name');
        $statement->execute(['company_id' => $companyId]);
        $grouped = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $account) {
            $grouped[$account['brain_key'] ?: 'commercial'][] = $account;
        }

        return $grouped;
    }

    public function accounts(int $companyId): array
    {
        if (!$this->hasOmnichannelAccounts()) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT id, display_name, channel, provider, brain_key FROM omnichannel_accounts WHERE company_id = :company_id ORDER BY display_name');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function channels(int $companyId): array
    {
        if (!$this->hasOmnichannelAccounts()) {
            return ['WhatsApp', 'Instagram', 'Messenger', 'Email'];
        }

        $statement = Database::connection()->prepare('SELECT DISTINCT channel FROM omnichannel_accounts WHERE company_id = :company_id ORDER BY channel');
        $statement->execute(['company_id' => $companyId]);
        return array_map(fn (array $row): string => (string) $row['channel'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function morningBrief(array $company): array
    {
        return [
            'Buenos dias. Vendiste ' . ($company['currency'] ?? 'CLP') . ' 2.800.000.',
            'Se respondieron 86 mensajes y se cerraron 14 ventas.',
            'Hay 3 clientes importantes esperando respuesta y 2 cotizaciones vencen hoy.',
            'Una operacion aparece atrasada y el margen bajo 3 puntos.',
            'Recomiendo llamar a Constructora X antes del mediodia.',
        ];
    }

    private function databaseReady(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM ai_brains LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasOmnichannelAccounts(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM omnichannel_accounts LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function filterBrains(array $brains, array $filters, array $accountsByBrain): array
    {
        $q = strtolower(trim((string) ($filters['q'] ?? '')));
        $status = trim((string) ($filters['status'] ?? ''));
        $channel = trim((string) ($filters['channel'] ?? ''));
        $accountId = (int) ($filters['account_id'] ?? 0);

        return array_values(array_filter($brains, function (array $brain) use ($q, $status, $channel, $accountId, $accountsByBrain): bool {
            if ($status !== '' && ($brain['status'] ?? '') !== $status) {
                return false;
            }

            if ($q !== '') {
                $haystack = strtolower(implode(' ', [
                    $brain['name'] ?? '',
                    $brain['mission'] ?? '',
                    $brain['primary_metric'] ?? '',
                    implode(' ', $brain['actions'] ?? []),
                    implode(' ', $brain['signals'] ?? []),
                ]));
                if (!str_contains($haystack, $q)) {
                    return false;
                }
            }

            if ($channel !== '' && !in_array($channel, $brain['channels'] ?? [], true)) {
                $accounts = $accountsByBrain[$brain['key']] ?? [];
                $hasChannel = (bool) array_filter($accounts, fn (array $account): bool => ($account['channel'] ?? '') === $channel);
                if (!$hasChannel) {
                    return false;
                }
            }

            if ($accountId > 0) {
                $accounts = $accountsByBrain[$brain['key']] ?? [];
                $hasAccount = (bool) array_filter($accounts, fn (array $account): bool => (int) $account['id'] === $accountId);
                if (!$hasAccount) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function defaults(): array
    {
        return [
            [
                'key' => 'commercial',
                'name' => 'Cerebro Comercial',
                'mission' => 'Vender mas, recuperar oportunidades y responder conversaciones comerciales con aprobacion cuando corresponda.',
                'status' => 'active',
                'primary_metric' => 'Ventas recuperadas',
                'channels' => ['WhatsApp', 'Instagram', 'Messenger', 'Correo', 'CRM', 'Cotizaciones'],
                'actions' => ['Responder mensajes', 'Crear cotizaciones', 'Hacer seguimiento', 'Detectar clientes frios', 'Recuperar ventas', 'Agendar reuniones', 'Recomendar productos'],
                'signals' => ['Clientes que pidieron informacion y no compraron', 'Cotizaciones por vencer', 'Carritos abandonados', 'Mensajes sin respuesta'],
                'examples' => ['Hay 8 clientes que pidieron informacion y nunca compraron.', 'Recomiendo ofrecer el producto X al cliente Y por historial de compra.'],
                'connectors' => ['whatsapp_business', 'instagram', 'facebook', 'gmail', 'outlook'],
            ],
            [
                'key' => 'administrative',
                'name' => 'Cerebro Administrativo',
                'mission' => 'Ahorrar tiempo organizando correos, agenda, tareas, pagos, documentos y busqueda interna.',
                'status' => 'active',
                'primary_metric' => 'Horas ahorradas',
                'channels' => ['Correo', 'Calendario', 'Documentos', 'Tareas', 'Pagos'],
                'actions' => ['Revisar correos', 'Organizar agenda', 'Crear tareas', 'Recordar pagos', 'Reordenar documentos', 'Buscar contratos', 'Generar informes'],
                'signals' => ['Pagos por vencer', 'Reuniones sin preparacion', 'Contratos solicitados', 'Correos acumulados'],
                'examples' => ['Tienes 4 pagos pendientes esta semana.', 'Encontre el contrato marco solicitado en documentos legales.'],
                'connectors' => ['gmail', 'outlook', 'google_calendar', 'documents'],
            ],
            [
                'key' => 'analytical',
                'name' => 'Cerebro Analitico',
                'mission' => 'Analizar datos de ventas, gastos, inventario, CRM, Excel, CSV y bases de datos para encontrar causas y oportunidades.',
                'status' => 'active',
                'primary_metric' => 'Insights accionables',
                'channels' => ['Excel', 'CSV', 'Base de datos', 'Ventas', 'Gastos', 'Inventario', 'CRM'],
                'actions' => ['Comparar periodos', 'Detectar anomalias', 'Analizar margenes', 'Segmentar clientes', 'Crear reportes', 'Recomendar decisiones'],
                'signals' => ['Ventas bajaron 18%', 'Clientes del sur compran 40% mas', 'Producto con perdida', 'Inventario detenido'],
                'examples' => ['Las ventas bajaron un 18% respecto al mes pasado.', 'Este producto genera mas perdidas por descuentos y devoluciones.'],
                'connectors' => ['spreadsheets', 'database', 'crm', 'reports'],
            ],
            [
                'key' => 'operational',
                'name' => 'Cerebro Operacional',
                'mission' => 'Conectar plataformas operativas como Tilo u Obra OK para ejecutar pedidos, despachos, promociones, obras y compras.',
                'status' => 'sandbox',
                'primary_metric' => 'Procesos resueltos',
                'channels' => ['Tilo', 'Obra OK', 'Pedidos', 'Despachos', 'Promociones', 'Ordenes de compra'],
                'actions' => ['Confirmar despachos', 'Responder pedidos nuevos', 'Detectar carritos abandonados', 'Monitorear avance de obras', 'Alertar sobre costos', 'Avisar falta de materiales'],
                'signals' => ['Pedido nuevo', 'Carrito abandonado', 'Obra al 62%', 'Presupuesto superado', '3 ordenes pendientes'],
                'examples' => ['Llego un pedido nuevo: confirma despacho.', 'La obra lleva 62% de avance y el presupuesto supero el costo esperado.'],
                'connectors' => ['tilo', 'obra_ok', 'whatsapp_business', 'inventory'],
            ],
            [
                'key' => 'executive',
                'name' => 'Cerebro Ejecutivo',
                'mission' => 'Actuar como gerente diario: resumir negocio, priorizar riesgos, recomendar llamadas y mostrar decisiones clave.',
                'status' => 'active',
                'primary_metric' => 'Decisiones priorizadas',
                'channels' => ['Dashboard', 'Reportes', 'CRM', 'Ventas', 'Operaciones', 'Finanzas'],
                'actions' => ['Crear resumen diario', 'Priorizar clientes', 'Detectar riesgos', 'Recomendar acciones', 'Comparar margen', 'Alertar vencimientos'],
                'signals' => ['Ventas del dia', 'Mensajes respondidos', 'Ventas cerradas', 'Clientes esperando', 'Cotizaciones por vencer', 'Margen bajo'],
                'examples' => ['Buenos dias, vendiste 2.8 millones y cerraste 14 ventas.', 'Recomiendo llamar a Constructora X hoy.'],
                'connectors' => ['dashboard', 'crm', 'reports', 'ai_usage_logs'],
            ],
        ];
    }
}
