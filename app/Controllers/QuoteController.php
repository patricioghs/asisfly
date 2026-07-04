<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\QuoteRepository;

final class QuoteController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('quotes.manage');
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];
        $data = (new QuoteRepository())->dashboard($this->companyId(), $this->currentCompany()['currency'], $filters);
        $this->view('quotes/index', [
            'title' => 'Cotizaciones',
            'filters' => $filters,
            ...$data,
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('quotes.manage');
        if (trim($_POST['client'] ?? '') === '' && (int) ($_POST['customer_id'] ?? 0) <= 0) {
            $this->redirect('/quotes');
        }
        $quoteId = (new QuoteRepository())->createQuote($this->companyId(), (int) $_SESSION['user']['id'], $_POST, $this->currentCompany());
        $this->redirect('/quotes?created=' . $quoteId);
    }

    public function product(): void
    {
        $this->requirePermission('quotes.manage');
        (new QuoteRepository())->createProduct($this->companyId(), $_POST, $this->currentCompany()['currency']);
        $this->redirect('/quotes');
    }

    public function pdf(): void
    {
        $this->requirePermission('quotes.manage');
        $path = (new QuoteRepository())->pdfPath($this->companyId(), (int) ($_GET['id'] ?? 0));
        if (!$path) {
            http_response_code(404);
            echo 'PDF no encontrado';
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        readfile($path);
    }

    public function send(): void
    {
        $this->requirePermission('quotes.manage');
        (new QuoteRepository())->requestSend($this->companyId(), (int) ($_POST['quote_id'] ?? 0), (int) $_SESSION['user']['id'], (string) ($_POST['channel'] ?? 'Email'));
        $this->redirect('/quotes');
    }

    public function status(): void
    {
        $this->requirePermission('quotes.manage');
        (new QuoteRepository())->transition($this->companyId(), (int) ($_POST['quote_id'] ?? 0), (int) $_SESSION['user']['id'], (string) ($_POST['status'] ?? ''));
        $this->redirect('/quotes');
    }
}
