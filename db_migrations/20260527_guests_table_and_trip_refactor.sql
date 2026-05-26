-- Migration: Create dedicated guests table and refactor trip_registrations
-- Purpose: Move guest identity data out of trip_registrations into a proper guests table
-- Date: 2026-05-27

-- ─── Step 1: Create the guests table ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `guests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `church_id` INT NOT NULL COMMENT 'Which church added this guest',
  `name` VARCHAR(255) NOT NULL COMMENT 'Full name of the guest child',
  `phone` VARCHAR(20) DEFAULT NULL COMMENT 'Phone number (guardian/child)',
  `guardian_name` VARCHAR(255) DEFAULT NULL COMMENT 'Name of parent/guardian',
  `class` VARCHAR(100) DEFAULT NULL COMMENT 'Class/grade if known',
  `gender` ENUM('male','female') DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT DEFAULT NULL COMMENT 'Uncle ID who added the guest',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_guests_church (`church_id`),
  INDEX idx_guests_name (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Step 2: Add registration_type + guest_id to trip_registrations ────────────
-- (Note: If 'registration_type' already exists from a previous step, skip the first ALTER/INDEX statement and run the rest)

-- 2a. Run this only if 'registration_type' is NOT already present:
-- ALTER TABLE `trip_registrations` ADD COLUMN `registration_type` ENUM('student','other_church_student','guest') DEFAULT 'student' COMMENT 'Type of registration';
-- CREATE INDEX idx_trip_reg_type ON trip_registrations(registration_type);

-- 2b. Run these to add guest_id and modify student_id to be nullable:
ALTER TABLE `trip_registrations` ADD COLUMN `guest_id` INT DEFAULT NULL COMMENT 'FK to guests table (for guest registrations)';
ALTER TABLE `trip_registrations` MODIFY `student_id` INT DEFAULT NULL COMMENT 'NULL for guest registrations';

CREATE INDEX idx_trip_reg_guest ON trip_registrations(guest_id);

-- ─── Step 3: Migrate existing inline guest data (if old schema was used) ──────
-- This moves any guest_* data from trip_registrations into the new guests table
-- and sets guest_id on the registration row.

-- 3a. Insert unique guests from existing inline data
INSERT INTO `guests` (`church_id`, `name`, `phone`, `guardian_name`, `class`, `gender`, `notes`, `created_at`)
SELECT DISTINCT
    t.church_id,
    tr.guest_name,
    tr.guest_phone,
    tr.guest_guardian_name,
    tr.guest_class,
    tr.guest_gender,
    tr.notes,
    tr.created_at
FROM `trip_registrations` tr
JOIN `trips` t ON tr.trip_id = t.id
WHERE tr.registration_type = 'guest'
  AND tr.guest_name IS NOT NULL
  AND tr.guest_id IS NULL;

-- 3b. Link trip_registrations to the newly created guests
UPDATE `trip_registrations` tr
JOIN `trips` t ON tr.trip_id = t.id
JOIN `guests` g ON g.name = tr.guest_name 
    AND g.church_id = t.church_id
    AND COALESCE(g.phone, '') = COALESCE(tr.guest_phone, '')
SET tr.guest_id = g.id
WHERE tr.registration_type = 'guest'
  AND tr.guest_id IS NULL
  AND tr.guest_name IS NOT NULL;

-- ─── Step 4: Drop old inline guest columns ────────────────────────────────────
-- Only run after confirming migration above succeeded
-- ALTER TABLE `trip_registrations`
--   DROP COLUMN `guest_name`,
--   DROP COLUMN `guest_phone`,
--   DROP COLUMN `guest_guardian_name`,
--   DROP COLUMN `guest_class`,
--   DROP COLUMN `guest_gender`,
--   DROP COLUMN `display_name`;

-- NOTE: The PHP ensureGuestsTable() function handles all of the above automatically.
-- This SQL file is for documentation and manual execution if needed.

-- ─── Registration Types Documentation ─────────────────────────────────────────
--
-- REGISTRATION TYPES:
-- 1. 'student' - Standard student from current church
--    - student_id: REQUIRED
--    - guest_id: NULL
--
-- 2. 'other_church_student' - Student from collaborating church
--    - student_id: REQUIRED (their student ID)
--    - guest_id: NULL
--
-- 3. 'guest' - Guest child (stored in guests table)
--    - student_id: NULL
--    - guest_id: REQUIRED (FK to guests table)
