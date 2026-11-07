-- NFC Digital Card: additive production upgrade, 2026-11-02, revision 2.
-- Target: existing MariaDB 10.5+ / MySQL 8 application database selected in phpMyAdmin.
-- Select the application database again before importing, NOT information_schema.
-- Revision 2 changes read-only verification only; it does not reset existing data.
-- Compared against the supplied production schema with 22 recorded App migrations.
-- BACK UP THE DATABASE AND FILES FIRST. Run once during maintenance, without a concurrent deploy.
-- This is NOT a replacement dump. It contains no account, profile, payment or credential data.
-- Do not import the old production dump over the existing database.
-- Alternative: run `php spark migrate -n App -g default` instead of importing this SQL.
-- ALTER TABLE commits implicitly in MariaDB: do not assume a transaction can undo the DDL.

-- Preserve every existing row and locale. A repeated import leaves the column untouched.
-- Use a prepared conditional statement for compatibility with MariaDB 10.5 and MySQL 8.
SET @nfc_card_locale_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'table_data_personal'
      AND COLUMN_NAME = 'card_locale'
);
SET @nfc_card_locale_sql = IF(
    @nfc_card_locale_exists = 0,
    'ALTER TABLE `table_data_personal` ADD COLUMN `card_locale` VARCHAR(5) NULL DEFAULT NULL AFTER `gallery_columns`',
    'SELECT ''card_locale already exists; existing values preserved'' AS upgrade_info'
);
PREPARE nfc_card_locale_upgrade FROM @nfc_card_locale_sql;
EXECUTE nfc_card_locale_upgrade;
DEALLOCATE PREPARE nfc_card_locale_upgrade;
SET @nfc_card_locale_exists = NULL;
SET @nfc_card_locale_sql = NULL;

-- Register the exact CI4 migration identity, only once and only if the expected column exists.
-- Hex encodes the class name so this works with or without NO_BACKSLASH_ESCAPES SQL mode:
-- App\Database\Migrations\AddCardLocaleToProfiles
INSERT INTO `migrations` (`version`, `class`, `group`, `namespace`, `time`, `batch`)
SELECT
    '2026-10-30-090000',
    CONVERT(0x4170705c44617461626173655c4d6967726174696f6e735c416464436172644c6f63616c65546f50726f66696c6573 USING utf8mb4),
    'default',
    'App',
    UNIX_TIMESTAMP(),
    (SELECT COALESCE(MAX(previous.`batch`), 0) + 1 FROM `migrations` AS previous WHERE previous.`group` = 'default')
WHERE NOT EXISTS (
    SELECT 1 FROM `migrations` AS existing
    WHERE REPLACE(existing.`version`, '-', '') = '20261030090000'
      AND existing.`class` = CONVERT(0x4170705c44617461626173655c4d6967726174696f6e735c416464436172644c6f63616c65546f50726f66696c6573 USING utf8mb4)
      AND existing.`group` = 'default'
      AND existing.`namespace` = 'App'
)
AND EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'table_data_personal'
      AND COLUMN_NAME = 'card_locale'
      AND DATA_TYPE = 'varchar'
      AND CHARACTER_MAXIMUM_LENGTH = 5
      AND IS_NULLABLE = 'YES'
);

-- Expected: one row, varchar(5), NULL allowed.
-- SHOW avoids phpMyAdmin switching its result context to information_schema before
-- the next query against the application migrations table.
SHOW COLUMNS FROM `table_data_personal` WHERE `Field` = 'card_locale';

-- Expected: exactly one row for this identity (new batch on first import).
SELECT `version`, `class`, `group`, `namespace`, `batch`
FROM `migrations`
WHERE REPLACE(`version`, '-', '') = '20261030090000'
  AND `class` = CONVERT(0x4170705c44617461626173655c4d6967726174696f6e735c416464436172644c6f63616c65546f50726f66696c6573 USING utf8mb4)
  AND `group` = 'default'
  AND `namespace` = 'App';
