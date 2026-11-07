-- NFC production upgrade: read-only verification, revision 2, 2026-11-02.
-- First click the actual application database in phpMyAdmin, NOT information_schema.
-- Expected: current_database is your application DB, one card_locale column,
-- and exactly one matching migration row. This file changes no data or schema.
SELECT DATABASE() AS current_database;

SHOW COLUMNS FROM `table_data_personal` WHERE `Field` = 'card_locale';

SELECT `version`, `class`, `group`, `namespace`, `batch`
FROM `migrations`
WHERE REPLACE(`version`, '-', '') = '20261030090000'
  AND `class` = CONVERT(0x4170705c44617461626173655c4d6967726174696f6e735c416464436172644c6f63616c65546f50726f66696c6573 USING utf8mb4)
  AND `group` = 'default'
  AND `namespace` = 'App';
