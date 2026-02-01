-- Table structure for table `event_registration_event`
--

CREATE TABLE `event_registration_event` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `registration_start_date` int NOT NULL,
  `registration_end_date` int NOT NULL,
  `event_date` int NOT NULL,
  `status` int NOT NULL DEFAULT '1',
  `created` int NOT NULL DEFAULT '0',
  `changed` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `event_registration_entry`
--

CREATE TABLE `event_registration_entry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `college` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `created` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `event_registration_entry_event` (`event_id`),
  CONSTRAINT `event_registration_entry_event` FOREIGN KEY (`event_id`) REFERENCES `event_registration_event` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;