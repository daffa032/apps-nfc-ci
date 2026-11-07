-- Legacy base schema (structure only, no data) as it existed before the app's migrations.
-- Test fixture for tests/database: the production tables were created outside migrations.
-- Source: structure of the local schema on 2026-10-11; AUTO_INCREMENT values removed.

CREATE TABLE `table_user` (
  `id_user` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `registration_date` date NOT NULL,
  `expired_date` date NOT NULL,
  `access_level` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `reset_token` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reset_expired_at` date DEFAULT NULL,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `table_data_personal` (
  `id_data` int NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `company_name` text COLLATE utf8mb4_general_ci,
  `no_hp` text COLLATE utf8mb4_general_ci,
  `email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `no_whatsapp` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `google_map_link` text COLLATE utf8mb4_general_ci,
  `profile_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `templates` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id_data`),
  UNIQUE KEY `id_user` (`id_user`),
  CONSTRAINT `fk_user_data` FOREIGN KEY (`id_user`) REFERENCES `table_user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sosial_media` (
  `id_sosial_media` int NOT NULL AUTO_INCREMENT,
  `id_data` int NOT NULL,
  `instagram` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `facebook` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `twitter` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tiktok` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `website` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id_sosial_media`),
  KEY `fk_data_sosial_media` (`id_data`),
  CONSTRAINT `fk_data_sosial_media` FOREIGN KEY (`id_data`) REFERENCES `table_data_personal` (`id_data`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `table_multiple_images` (
  `id_images` int NOT NULL AUTO_INCREMENT,
  `id_data` int NOT NULL,
  `images` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `caption` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_images`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `templates` (
  `id_template` int NOT NULL AUTO_INCREMENT,
  `nama_template` varchar(32) COLLATE utf8mb4_general_ci NOT NULL,
  `nama_file` text COLLATE utf8mb4_general_ci NOT NULL,
  `images_template` text COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_template`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
