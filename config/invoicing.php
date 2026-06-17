<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency used when creating invoices. Can be overridden
    | per invoice.
    |
    */
    'currency' => env('INVOICING_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Invoice Number Format
    |--------------------------------------------------------------------------
    |
    | The format used for generating invoice numbers.
    | Available placeholders: {prefix}, {year}, {month}, {sequence}
    |
    */
    'invoice_number_format' => '{prefix}-{year}-{sequence}',
    'invoice_number_prefix' => 'INV',
    'invoice_number_sequence_length' => 4,

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | The gateway used when no specific gateway is provided.
    | Must be a registered gateway name (built-in: 'local', 'stripe', 'bank_transfer').
    |
    */
    'default_gateway' => env('INVOICING_GATEWAY', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for each payment gateway.
    | Built-in gateways ('local', 'stripe', 'bank_transfer') are auto-registered.
    | To add a custom gateway, set the 'driver' key to its class name:
    |
    |   'mygateway' => [
    |       'driver' => \App\Gateways\MyGateway::class,
    |       'api_key' => env('MYGATEWAY_API_KEY'),
    |   ],
    |
    | Note: The 'stripe' gateway requires the stripe/stripe-php Composer package.
    | It will throw a RuntimeException if the SDK is not installed.
    |
    */
    'gateways' => [
        'local' => [
            'auto_succeed' => true,
        ],

        'stripe' => [
            'api_key' => env('STRIPE_API_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],

        'bank_transfer' => [
            'bank_details' => [
                'bank_name' => env('BANK_NAME'),
                'account_name' => env('BANK_ACCOUNT_NAME'),
                'account_number' => env('BANK_ACCOUNT_NUMBER'),
                'iban' => env('BANK_IBAN'),
                'swift_code' => env('BANK_SWIFT_CODE'),
            ],
            'instructions' => env('BANK_TRANSFER_INSTRUCTIONS', 'Transfer the amount to the bank account and upload proof of payment.'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Rate
    |--------------------------------------------------------------------------
    |
    | Default tax rate applied to invoices when no specific tax is provided.
    | Applied as a percentage of the subtotal (after discount).
    |
    */
    'default_tax_rate' => 0,

    /*
    |--------------------------------------------------------------------------
    | Overdue Threshold
    |--------------------------------------------------------------------------
    |
    | Number of days after the due date before an invoice is considered overdue.
    | Set to 0 to mark invoices as overdue immediately when past due_at.
    | The invoicing:mark-overdue artisan command uses this value.
    |
    */
    'overdue_threshold_days' => 0,

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Custom table names for the invoicing entities. Change these if you need
    | to avoid conflicts with existing tables.
    |
    */
    'table_names' => [
        'invoices' => 'invoices',
        'invoice_lines' => 'invoice_lines',
        'payments' => 'payments',
    ],

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The model used for the user() relationship on Invoice and Payment.
    | Defaults to App\Models\User. Change this if your app uses a different
    | model or namespace for users.
    |
    */
    'user_model' => \App\Models\User::class,
];
