CREATE TABLE IF NOT EXISTS `#__lanl_rsfiles_menuhits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) NOT NULL,
  `menu_title` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `country` varchar(255) DEFAULT NULL,
  `viewer_ip_address`    varchar(255) DEFAULT NULL,
  `date_viewed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
