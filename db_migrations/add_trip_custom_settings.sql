ALTER TABLE `trips`
  ADD COLUMN `has_points_game` TINYINT(1) DEFAULT 0,
  ADD COLUMN `custom_fields` TEXT DEFAULT NULL;

ALTER TABLE `trip_registrations`
  ADD COLUMN `custom_data` TEXT DEFAULT NULL;
