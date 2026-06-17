<?php

namespace Salehye\Invoicing\Facades;

use Illuminate\Support\Facades\Facade;
use Salehye\Invoicing\Services\InvoiceManager;

class Invoicing extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return InvoiceManager::class;
    }
}
