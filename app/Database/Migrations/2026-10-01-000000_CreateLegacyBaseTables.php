<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * The five original tables predate CodeIgniter migrations. Create their ORIGINAL
 * shape here; subsequent migrations add the columns present in today's dump.
 * Do not import the production dump into a fresh database before migrating.
 */
class CreateLegacyBaseTables extends Migration
{
    public function up()
    {
        $tables   = ['table_user', 'table_data_personal', 'sosial_media', 'table_multiple_images', 'templates'];
        $existing = array_filter($tables, function (string $table): bool {
            $result = $this->db->query(
                'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table],
            );

            return $result !== false && $result->getNumRows() > 0;
        });

        // A restored dump already contains the final schema and migration history.
        // Never replay the original column changes on top of that schema.
        if (count($existing) === count($tables)) {
            return;
        }

        if ($existing !== []) {
            throw new RuntimeException('Only some legacy tables exist. Restore a complete backup or use an empty database; no tables were created.');
        }

        $this->db->query(<<<'SQL'
            CREATE TABLE `table_user` (
              `id_user` int NOT NULL AUTO_INCREMENT,
              `username` varchar(255) NOT NULL,
              `password` varchar(255) NOT NULL,
              `email` varchar(255) NOT NULL,
              `registration_date` date NOT NULL,
              `expired_date` date NOT NULL,
              `access_level` varchar(64) NOT NULL,
              `reset_token` varchar(100) DEFAULT NULL,
              `reset_expired_at` date DEFAULT NULL,
              PRIMARY KEY (`id_user`),
              UNIQUE KEY `username` (`username`),
              UNIQUE KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE `table_data_personal` (
              `id_data` int NOT NULL AUTO_INCREMENT,
              `id_user` int NOT NULL,
              `nama` varchar(255) DEFAULT NULL,
              `company_name` text,
              `no_hp` text,
              `email` varchar(255) DEFAULT NULL,
              `no_whatsapp` varchar(20) DEFAULT NULL,
              `address` text,
              `google_map_link` text,
              `profile_image` varchar(255) DEFAULT NULL,
              `templates` varchar(255) DEFAULT NULL,
              PRIMARY KEY (`id_data`),
              UNIQUE KEY `id_user` (`id_user`),
              CONSTRAINT `fk_user_data` FOREIGN KEY (`id_user`) REFERENCES `table_user` (`id_user`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE `sosial_media` (
              `id_sosial_media` int NOT NULL AUTO_INCREMENT,
              `id_data` int NOT NULL,
              `instagram` varchar(64) DEFAULT NULL,
              `facebook` varchar(64) DEFAULT NULL,
              `twitter` varchar(64) DEFAULT NULL,
              `tiktok` varchar(64) DEFAULT NULL,
              `website` varchar(64) DEFAULT NULL,
              PRIMARY KEY (`id_sosial_media`),
              KEY `fk_data_sosial_media` (`id_data`),
              CONSTRAINT `fk_data_sosial_media` FOREIGN KEY (`id_data`) REFERENCES `table_data_personal` (`id_data`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE `table_multiple_images` (
              `id_images` int NOT NULL AUTO_INCREMENT,
              `id_data` int NOT NULL,
              `images` varchar(120) NOT NULL,
              `caption` varchar(30) NOT NULL,
              PRIMARY KEY (`id_images`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE `templates` (
              `id_template` int NOT NULL AUTO_INCREMENT,
              `nama_template` varchar(32) NOT NULL,
              `nama_file` text NOT NULL,
              `images_template` text NOT NULL,
              PRIMARY KEY (`id_template`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL);
    }

    public function down()
    {
        // This migration also adopts imported legacy tables. Dropping them on
        // rollback could erase pre-existing user data, so require manual recovery.
        throw new RuntimeException('Legacy base tables may contain imported data. Restore a backup instead of rolling back this migration.');
    }
}
