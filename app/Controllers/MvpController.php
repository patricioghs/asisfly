<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class MvpController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $items = [
            ['area' => 'Acceso', 'item' => 'Login, registro y recuperacion de contrasena', 'status' => 'ready', 'next' => 'Agregar CSRF y rate limit'],
            ['area' => 'Multiempresa', 'item' => 'Datos separados por company_id', 'status' => 'ready', 'next' => 'Auditar consultas nuevas'],
            ['area' => 'Cerebros', 'item' => 'Comercial, Administrativo, Analitico, Operacional y Ejecutivo', 'status' => 'ready', 'next' => 'Afinar prompts por rubro'],
            ['area' => 'Chat IA', 'item' => 'Enrutamiento por cerebro y registro de consumo', 'status' => 'ready', 'next' => 'Conectar proveedor IA real'],
            ['area' => 'Acciones', 'item' => 'Aprobacion humana con ejecucion y auditoria', 'status' => 'ready', 'next' => 'Notificaciones'],
            ['area' => 'Omnicanal', 'item' => 'Bandeja WhatsApp, Instagram, Messenger y Email simulada', 'status' => 'ready', 'next' => 'Conectar APIs reales'],
            ['area' => 'Asisti Social', 'item' => 'Calendario, captions, hashtags, CTA y programacion simulada', 'status' => 'ready', 'next' => 'Publicacion real'],
            ['area' => 'CRM', 'item' => 'Clientes y pipeline basico', 'status' => 'ready', 'next' => 'Tareas y oportunidades avanzadas'],
            ['area' => 'Cotizaciones', 'item' => 'Borradores y calculo basico', 'status' => 'partial', 'next' => 'PDF profesional y envio'],
            ['area' => 'Documentos', 'item' => 'Carga y memoria documental inicial', 'status' => 'partial', 'next' => 'Extraccion de texto y busqueda semantica'],
            ['area' => 'Produccion', 'item' => 'Instalador y migraciones', 'status' => 'partial', 'next' => 'Deploy VPS, SSL, backups'],
            ['area' => 'Venta', 'item' => 'Demo funcional para pilotos', 'status' => 'partial', 'next' => 'Landing, precios y onboarding comercial'],
        ];

        $ready = count(array_filter($items, fn (array $item) => $item['status'] === 'ready'));
        $score = (int) round($ready / count($items) * 100);

        $this->view('mvp/index', [
            'title' => 'MVP Vendible',
            'items' => $items,
            'score' => $score,
        ]);
    }
}
