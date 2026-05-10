-- Migration: add per-registration payment history JSON backup
ALTER TABLE trip_registrations
  ADD COLUMN payment_history TEXT DEFAULT '[]' COMMENT 'JSON array of payment events for this registration, including deposits and payments' AFTER notes;

UPDATE trip_registrations
  SET payment_history = '[]'
  WHERE payment_history IS NULL;
