<?php

declare(strict_types=1);

namespace App\Models;

abstract class Model
{
    protected string $table;
    protected string $companyColumn = 'company_id';

    public function table(): string
    {
        return $this->table;
    }
}
