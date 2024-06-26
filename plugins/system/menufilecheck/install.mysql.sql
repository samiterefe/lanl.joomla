CREATE TABLE IF NOT EXISTS `#__rsfiles_menuhits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) NOT NULL,
  `menu_title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `hits` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
