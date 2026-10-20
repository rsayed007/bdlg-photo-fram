-- Separate database for the Pest suite so `make test` never touches dev data.
CREATE DATABASE IF NOT EXISTS `bdlg_testing` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `bdlg_testing`.* TO 'bdlg'@'%';
FLUSH PRIVILEGES;
