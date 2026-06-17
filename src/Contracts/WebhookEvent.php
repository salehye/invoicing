<?php

namespace Salehye\Invoicing\Contracts;

interface WebhookEvent
{
    public function getInvoiceId(): int;

    public function getStatus(): string;

    public function getTransactionId(): ?string;

    public function getRawPayload(): array;
}
