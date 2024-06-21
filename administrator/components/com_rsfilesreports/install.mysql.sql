CREATE TABLE IF NOT EXISTS `#__lanl_rsfiles_downloaded`
(
    `id`                    int(11) NOT NULL AUTO_INCREMENT,
    `file_id`               int(11) NOT NULL,
    `downloader_ip_address` varchar(255) DEFAULT NULL,
    `downloader_country`    varchar(255) DEFAULT NULL,
    `date_downloaded`       datetime     DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__lanl_rsfiles_viewed`
(
    `id`                int(11) NOT NULL AUTO_INCREMENT,
    `file_id`           int(11)      DEFAULT NULL,
    `viewer_ip_address` varchar(255) DEFAULT NULL,
    `viewer_country`    varchar(255) DEFAULT NULL,
    `date_viewed`       datetime     DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__rsfilesreports_ip_to_country`
(
    `id`           int(11)     NOT NULL AUTO_INCREMENT,
    `ip_start`     varchar(15) NOT NULL,
    `ip_end`       varchar(15) NOT NULL,
    `country_code` char(2)     NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;
