-- Migration: Add payment_method column to payments table
-- Date: <?= date('Y-m-d H:i:s') ?>

-- SQLite doesn't support ENUM, so we'll use CHECK constraint
-- Valid values: 'mobile_number', 'bank_transfer'

-- Add the payment_method column with a default value
ALTER TABLE payments ADD COLUMN payment_method VARCHAR(20) DEFAULT 'mobile_number' CHECK(payment_method IN ('mobile_number', 'bank_transfer'));

-- Update existing records to have 'mobile_number' as the payment method
-- This assumes existing records are from mobile SMS payments
UPDATE payments SET payment_method = 'mobile_number' WHERE payment_method IS NULL;

-- Create an index on payment_method for better query performance
CREATE INDEX IF NOT EXISTS idx_payments_payment_method ON payments(payment_method);