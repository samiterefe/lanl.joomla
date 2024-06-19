CREATE TABLE IF NOT EXISTS `#__rsfiles_extra_info` (
    `id`               int(11)    NOT NULL AUTO_INCREMENT,
    `IdFile`           int(11)    NOT NULL,
    `FileDisplayAsNew` tinyint(1) NOT NULL,
    PRIMARY KEY (`id`) )
    ENGINE = MyISAM DEFAULT CHARSET = utf8;
