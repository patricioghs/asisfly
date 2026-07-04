<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class IntelligenceController extends Controller
{
    public function reports(): void
    {
        $this->render('Reportes', 'Informes ejecutivos listos para ventas, operaciones, finanzas y atencion al cliente.', [
            ['Ventas mensuales', 'Comparativo por canal, producto y ejecutivo.', 'Listo'],
            ['Clientes frios', 'Oportunidades sin respuesta y recuperacion sugerida.', 'Pendiente'],
            ['Consumo IA', 'Tokens, costo estimado y uso por modulo.', 'Auditable'],
        ]);
    }

    public function analytics(): void
    {
        $this->render('Analytics', 'Analisis de comportamiento, tendencias, conversion y rendimiento comercial.', [
            ['Ventas bajaron 18%', 'Comparado con el mes anterior en categoria seleccionada.', 'Detectado'],
            ['Zona sur compra mas', 'Clientes del sur muestran mayor ticket promedio.', 'Insight'],
            ['Producto con bajo margen', 'Alta rotacion, rentabilidad bajo objetivo.', 'Revisar'],
        ]);
    }

    public function dashboards(): void
    {
        $this->render('Dashboards', 'Paneles visuales para gerencia, ventas, operaciones, social y finanzas.', [
            ['Dashboard ejecutivo', 'Ventas, mensajes, reuniones, tareas y alertas.', 'Activo'],
            ['Dashboard comercial', 'Pipeline, cotizaciones, clientes frios y recuperacion.', 'Activo'],
            ['Dashboard operacional', 'Pedidos, atrasos, compras y avance de proyectos.', 'Proximo'],
        ]);
    }

    public function intelligenceDocuments(): void
    {
        $this->render('Documentos inteligentes', 'Lectura y analisis de documentos para extraer riesgos, resumenes e indicadores.', [
            ['Contratos', 'Resumen de obligaciones, fechas y riesgos.', 'Preparado'],
            ['PDF comerciales', 'Extraccion de condiciones, precios y politicas.', 'Preparado'],
            ['Reportes internos', 'Comparacion automatica de periodos.', 'Preparado'],
        ]);
    }

    public function excel(): void
    {
        $this->render('Excel', 'Analisis de Excel, CSV y planillas para detectar ventas, gastos, inventario y anomalias.', [
            ['Ventas por periodo', 'Comparar mes actual versus anterior.', 'Listo'],
            ['Inventario', 'Productos con quiebre, sobrestock o baja rotacion.', 'Listo'],
            ['Gastos', 'Categorias con aumento inesperado.', 'Listo'],
        ]);
    }

    public function indicators(): void
    {
        $this->render('Indicadores', 'Indicadores comerciales y operativos para observar la salud del negocio.', [
            ['Conversion por canal', 'WhatsApp, Instagram, correo y web.', '8,6%'],
            ['Tiempo de respuesta', 'Promedio de atencion por canal.', '12 min'],
            ['Margen promedio', 'Rentabilidad estimada por linea.', '31%'],
        ]);
    }

    public function kpis(): void
    {
        $this->render('KPIs', 'Objetivos clave para medir ventas, ahorro de tiempo, recuperacion y productividad.', [
            ['Ventas recuperadas', 'Monto estimado por seguimientos sugeridos.', '$2.800.000'],
            ['Horas ahorradas', 'Tareas administrativas asistidas por IA.', '18 h'],
            ['Mensajes respondidos', 'Conversaciones gestionadas por AsisFly.', '432'],
        ]);
    }

    private function render(string $title, string $description, array $cards): void
    {
        $this->requireAuth();
        $this->view('intelligence/show', [
            'title' => $title,
            'description' => $description,
            'cards' => $cards,
        ]);
    }
}
