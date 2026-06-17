<?php

namespace Salehye\Invoicing\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Salehye\Invoicing\Traits\HasInvoices;

class Customer extends Model
{
    use HasInvoices;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'test_customers';
    }
}
