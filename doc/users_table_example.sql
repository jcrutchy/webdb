CREATE TABLE IF NOT EXISTS `my_app`.`users` (
  `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `last_login_microtime` BIGINT NOT NULL,
  `login_cookie` VARCHAR(255) NOT NULL,
  `login_cookie_microtime` BIGINT NOT NULL,
  `enabled` TINYINT NOT NULL DEFAULT 1,
  `email` VARCHAR(255) NOT NULL,
  `pw_hash` VARCHAR(255) NOT NULL,
  `pw_reset` TINYINT NOT NULL DEFAULT 0,
  `privs` LONGTEXT NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE INDEX `email` (`email` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 1;
