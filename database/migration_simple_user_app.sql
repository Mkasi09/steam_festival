USE steam_festival;

ALTER TABLE schools
  MODIFY district_id INT UNSIGNED NULL,
  MODIFY circuit_id INT UNSIGNED NULL;

ALTER TABLE learners
  ADD COLUMN IF NOT EXISTS race VARCHAR(60) NULL AFTER last_name;

DELETE FROM districts WHERE name = 'Example District';
