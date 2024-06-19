CREATE TABLE IF NOT EXISTS `#__rsfiles_file_status`
(
    `id`                  int(11)                                                       NOT NULL AUTO_INCREMENT,
    `FileId`              int(11)                                                       NOT NULL,
    `FileStatus`          tinyint(2)                                                    NOT NULL,
    `FileRelatedToStatus` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `DateRelatedToStatus` datetime                                                      NOT NULL DEFAULT '1111-11-11 00:00:00',
    `DateAdded`           datetime                                                      NOT NULL DEFAULT '0000-00-00 00:00:00',
    PRIMARY KEY (`id`)
) ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `#__rsfiles_extra_info`
(
    `id`               int(11)    NOT NULL AUTO_INCREMENT,
    `IdFile`           int(11)    NOT NULL,
    `FileDisplayAsNew` tinyint(1) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = MyISAM
  DEFAULT CHARSET = utf8;
