CREATE TABLE IF NOT EXISTS `reconciliations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `statement_date` date NOT NULL,
  `statement_ending_balance` decimal(15,2) NOT NULL,
  `is_finalized` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `journal_entry_lines` ADD COLUMN `cleared_at` DATE DEFAULT NULL;
ALTER TABLE `journal_entry_lines` ADD COLUMN `reconciliation_id` INT(11) DEFAULT NULL;
ALTER TABLE `journal_entry_lines` ADD CONSTRAINT `fk_jel_recon` FOREIGN KEY (`reconciliation_id`) REFERENCES `reconciliations`(`id`);
