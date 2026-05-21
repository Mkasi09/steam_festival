-- Migration: Remove phone and email columns from learners
-- Date: 2026-05-18
-- Notes: Drops `phone` and `email` from the `learners` table.
-- Backup your database before running this migration.

-- Recommended run command:
-- mysql -u root -p steam_festival < database/migrations/20260518_remove_learner_contact.sql

ALTER TABLE `learners`
  DROP COLUMN IF EXISTS `phone`,
  DROP COLUMN IF EXISTS `email`;
