<?php

declare(strict_types=1);

namespace App\Services;

final class BrainRouter
{
    public function route(string $prompt): array
    {
        $text = strtolower($this->normalize($prompt));

        $routes = [
            'social' => [
                'brain' => 'commercial',
                'name' => 'Cerebro Comercial + Asisti Social',
                'module' => 'Asisti Social',
                'keywords' => ['publicacion', 'publicaciones', 'caption', 'copy', 'copies', 'instagram', 'facebook', 'linkedin', 'tiktok', 'hashtag', 'campana', 'contenido', 'calendario de contenido', 'post'],
            ],
            'analytical' => [
                'brain' => 'analytical',
                'name' => 'Cerebro Analitico',
                'module' => 'Analitica',
                'keywords' => ['excel', 'csv', 'ventas bajaron', 'gastos', 'inventario', 'margen', 'perdidas', 'analiza', 'reporte', 'base de datos', 'crm'],
            ],
            'administrative' => [
                'brain' => 'administrative',
                'name' => 'Cerebro Administrativo',
                'module' => 'Administracion',
                'keywords' => ['agenda', 'calendario', 'tarea', 'tareas', 'pago', 'pagos', 'contrato', 'documento', 'correo', 'organiza', 'recordar'],
            ],
            'operational' => [
                'brain' => 'operational',
                'name' => 'Cerebro Operacional',
                'module' => 'Operaciones',
                'keywords' => ['tilo', 'obra', 'despacho', 'pedido', 'carrito', 'materiales', 'orden de compra', 'presupuesto', 'avance'],
            ],
            'executive' => [
                'brain' => 'executive',
                'name' => 'Cerebro Ejecutivo',
                'module' => 'Direccion',
                'keywords' => ['buenos dias', 'resumen', 'gerente', 'prioridades', 'riesgos', 'margen bajo', 'recomiendo llamar', 'decision'],
            ],
            'commercial' => [
                'brain' => 'commercial',
                'name' => 'Cerebro Comercial',
                'module' => 'Comercial',
                'keywords' => ['whatsapp', 'cotizacion', 'cotizaciones', 'cliente frio', 'clientes frios', 'seguimiento', 'recuperar ventas', 'vender', 'oportunidad', 'reunion'],
            ],
        ];

        foreach ($routes as $route) {
            foreach ($route['keywords'] as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $route;
                }
            }
        }

        return [
            'brain' => 'executive',
            'name' => 'Cerebro Ejecutivo',
            'module' => 'Direccion',
            'keywords' => [],
        ];
    }

    private function normalize(string $value): string
    {
        $map = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n'];
        return strtr($value, $map);
    }
}
