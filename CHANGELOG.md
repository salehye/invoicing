# Changelog

All notable changes to `salehye/invoicing` will be documented in this file.

## 1.0.0 - 2025-07-XX

### Initial Release

- Standalone Laravel invoicing package (no subscription dependency)
- Invoice creation with polymorphic billable or standalone (no billable)
- Line items with quantity, pricing, discount, and tax
- Percentage and fixed discounts via `DiscountType` backed enum
- Configurable tax calculation (percentage of subtotal after discount)
- Unique invoice number generation with collision retry (bounded 10 attempts)
- Invoice status lifecycle: `draft → unpaid → paid → refunded`, `canceled`, `overdue`
- `Overdue` status with `canTransitionTo()` enum validation
- `invoicing:mark-overdue` artisan command
- Multi-gateway payments via `GatewayManager` (local, stripe, bank_transfer)
- Stripe gateway with SDK availability check
- Bank transfer with manual admin verification flow
- Payment amount validation (no negative/excessive amounts)
- `$fillable` mass-assignment security (no `$guarded = []`)
- `restrictOnDelete` on invoice FKs (audit trail preservation)
- `foreignId` for `user_id` and `verified_by` columns (indexed, no constrained for portability)
- Custom exception hierarchy: `InvoiceStatusTransitionException`, `PaymentVerificationException`, `GatewayNotFoundException`, `InvalidPaymentAmountException`
- Immutable events with `public readonly` constructor promotion
- `HasInvoices` trait with `MorphMany` return types
- Eloquent relationship return types (`MorphTo`, `BelongsTo`, `HasMany`)
- `readonly` constructor parameters on service classes
- Customizable table names, currency, and user model
- User ID tracking on invoices and payments
- Tenant ID support
- Soft deletes on invoices
- Metadata JSON storage
- Laravel events for all state changes
- `EnsureInvoicePaid` middleware (auto-registered via ServiceProvider for Laravel 11/12/13)
- Custom tax/discount calculator contracts
- Config-driven gateway registration with runtime override
- Laravel 11, 12, and 13 support
- 41 tests, 92 assertions
