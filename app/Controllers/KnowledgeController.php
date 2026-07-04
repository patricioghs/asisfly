<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class KnowledgeController extends Controller
{
    public function base(): void
    {
        $this->render('Base de conocimiento', 'Repositorio privado de preguntas frecuentes, politicas, procesos y respuestas oficiales por empresa.', [
            ['Preguntas frecuentes', 'Respuestas aprobadas para canales comerciales.', '12 entradas'],
            ['Politicas comerciales', 'Garantias, cambios, despacho y condiciones.', '8 reglas'],
            ['Procesos internos', 'Pasos operativos que AsisFly debe seguir.', '5 procesos'],
        ]);
    }

    public function catalogs(): void
    {
        $this->render('Catalogos', 'Productos, servicios, precios, margenes, stock y recomendaciones comerciales para vender mejor.', [
            ['Catalogo principal', 'Productos activos con precio y margen.', 'Activo'],
            ['Servicios', 'Implementaciones, soporte y planes.', 'Activo'],
            ['Promociones', 'Campanas por temporada y objetivos.', 'Borrador'],
        ]);
    }

    public function manuals(): void
    {
        $this->render('Manuales', 'Manuales de marca, operacion, ventas y atencion para entrenar respuestas consistentes.', [
            ['Manual de ventas', 'Objeciones, seguimiento y cierre.', 'Cargado'],
            ['Manual de atencion', 'Tono, escalamiento y SLA.', 'Cargado'],
            ['Manual operativo', 'Pedidos, despacho y postventa.', 'Pendiente'],
        ]);
    }

    public function training(): void
    {
        $this->render('Entrenamiento', 'Centro para subir fuentes, validar conocimiento y revisar como AsisFly aprende de cada negocio.', [
            ['Documentos procesados', 'Fuentes listas para memoria empresarial.', '24'],
            ['Fragmentos indexados', 'Bloques disponibles para busqueda.', '168'],
            ['Revision humana', 'Contenido pendiente de aprobacion.', '3'],
        ]);
    }

    private function render(string $title, string $description, array $cards): void
    {
        $this->requireAuth();
        $this->view('knowledge/show', [
            'title' => $title,
            'description' => $description,
            'cards' => $cards,
        ]);
    }
}
