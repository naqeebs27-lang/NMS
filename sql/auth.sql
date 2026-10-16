-- Authentication System

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('Admin', 'Accountant', 'Viewer') DEFAULT 'Accountant',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin: admin / admin123
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`) VALUES
('admin', '$2y$10$SAPC5Qrh0QXUDna6MuCece1BBdxXNAzihf6RLzLNXwek7RxCccSLW', 'System Admin', 'admin@pakacc.pk', 'Admin');
