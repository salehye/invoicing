<?php

namespace Salehye\Invoicing\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return 'test_customers';
    }
}
