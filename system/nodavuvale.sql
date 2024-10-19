SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE `comment_reactions` (
  `id` int NOT NULL,
  `comment_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `reaction_type` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `reacted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `discussions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_sticky` tinyint(1) DEFAULT '0',
  `is_news` tinyint(1) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `discussion_comments` (
  `id` int NOT NULL,
  `discussion_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `discussion_reactions` (
  `id` int NOT NULL,
  `discussion_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `reaction_type` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `reacted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `files` (
  `id` int NOT NULL,
  `file_type` enum('image','document') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `file_format` varchar(10) COLLATE utf8mb3_unicode_ci NOT NULL,
  `file_description` text COLLATE utf8mb3_unicode_ci,
  `user_id` int DEFAULT NULL,
  `upload_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `file_links` (
  `id` int NOT NULL,
  `file_id` int NOT NULL,
  `individual_id` int DEFAULT NULL,
  `item_id` int DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `individuals` (
  `id` int NOT NULL,
  `nodavuvale_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_names` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aka_names` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `birth_prefix` enum('about','after','before') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_year` int DEFAULT NULL,
  `birth_month` int DEFAULT NULL,
  `birth_date` int DEFAULT NULL,
  `death_prefix` enum('exactly','about','after','before') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `death_year` int DEFAULT NULL,
  `death_month` int DEFAULT NULL,
  `death_date` int DEFAULT NULL,
  `gender` enum('male','female','other') COLLATE utf8mb4_unicode_ci DEFAULT 'other',
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DELIMITER $$
CREATE TRIGGER `before_insert_individuals` BEFORE INSERT ON `individuals` FOR EACH ROW BEGIN
    IF NEW.nodavuvale_id IS NULL THEN
        SET NEW.nodavuvale_id = UUID();
    END IF;
END
$$
DELIMITER ;

CREATE TABLE `items` (
  `item_id` int NOT NULL,
  `detail_type` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `detail_value` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `item_identifier` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `item_groups` (
  `id` int NOT NULL,
  `item_identifier` int NOT NULL,
  `item_group_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `item_links` (
  `id` int NOT NULL,
  `individual_id` int NOT NULL,
  `item_id` int NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `relationships` (
  `id` int NOT NULL,
  `individual_id_1` int NOT NULL,
  `individual_id_2` int NOT NULL,
  `relationship_type` enum('child','spouse') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `site_settings` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb3_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `users` (
  `id` int NOT NULL,
  `individuals_id` int DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `relative_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `relationship` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `approved` tinyint(1) DEFAULT '0',
  `role` enum('unconfirmed','member','admin') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'unconfirmed',
  `registration_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


ALTER TABLE `comment_reactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comment_id` (`comment_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `discussions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `discussion_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `discussion_id` (`discussion_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `discussion_reactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `discussion_id` (`discussion_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `files`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `file_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `individual_id` (`individual_id`),
  ADD KEY `item_id` (`item_id`);

ALTER TABLE `individuals`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_item_identifier` (`item_identifier`);

ALTER TABLE `item_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_identifier` (`item_identifier`);

ALTER TABLE `item_links`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `relationships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `individual_id_1` (`individual_id_1`),
  ADD KEY `individual_id_2` (`individual_id_2`);

ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);


ALTER TABLE `comment_reactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `discussions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `discussion_comments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `discussion_reactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `files`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `file_links`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `individuals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `items`
  MODIFY `item_id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `item_groups`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `item_links`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `relationships`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `site_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;


ALTER TABLE `relationships`
  ADD CONSTRAINT `relationships_ibfk_1` FOREIGN KEY (`individual_id_1`) REFERENCES `individuals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `relationships_ibfk_2` FOREIGN KEY (`individual_id_2`) REFERENCES `individuals` (`id`) ON DELETE CASCADE;
