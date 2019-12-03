SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ALLOW_INVALID_DATES';

DROP SCHEMA IF EXISTS `webdb` ;
CREATE SCHEMA IF NOT EXISTS `webdb` DEFAULT CHARACTER SET utf8 ;

DROP TABLE IF EXISTS `webdb`.`users`;
CREATE TABLE IF NOT EXISTS `webdb`.`users` (
  `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `enabled` TINYINT NOT NULL DEFAULT 0,
  `username` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `csrf_token` VARCHAR(255) DEFAULT "*",
  `csrf_token_time` BIGINT DEFAULT 0,
  `login_cookie` VARCHAR(255) DEFAULT "*",
  `login_setcookie_time` BIGINT DEFAULT 0,
  `pw_hash` VARCHAR(255) DEFAULT "*",
  `pw_change` TINYINT NOT NULL DEFAULT 1,
  `pw_reset_key` VARCHAR(255) DEFAULT "*",
  `pw_reset_time` BIGINT DEFAULT 0,
  `pw_login_time` BIGINT DEFAULT 0,
  `cookie_login_time` BIGINT DEFAULT 0,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `remote_address` VARCHAR(255) DEFAULT NULL,
  `failed_login_count` INT UNSIGNED DEFAULT NULL,
  `failed_login_time` BIGINT DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE INDEX `username` (`username` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 1;

/*
username: admin
password: password
*/
INSERT INTO `webdb`.`users` (`username`,`pw_hash`,`enabled`) VALUES ("admin","$2y$13$Vn8rJB73AHq56cAqbBwkEuKrQt3lSdoA3sDmKULZEgQLE4.nmsKzW",1);

DROP TABLE IF EXISTS `webdb`.`row_locks` ;
CREATE TABLE IF NOT EXISTS `webdb`.`row_locks` (
  `lock_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` INT UNSIGNED NOT NULL,
  `lock_schema` VARCHAR(255) NOT NULL,
  `lock_table` VARCHAR(255) NOT NULL,
  `lock_key_field` VARCHAR(255) NOT NULL,
  `lock_key_value` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`lock_id`),
  INDEX `created_timestamp` (`created_timestamp` ASC),
  INDEX `lock_schema` (`lock_schema` ASC),
  INDEX `lock_table` (`lock_table` ASC),
  INDEX `lock_key_field` (`lock_key_field` ASC),
  INDEX `lock_key_value` (`lock_key_value` ASC),
  CONSTRAINT `fk_row_locks_users1`
    FOREIGN KEY (`user_id`)
    REFERENCES `webdb`.`users` (`user_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS `webdb`.`sql_changes` ;
CREATE TABLE IF NOT EXISTS `webdb`.`sql_changes` (
  `sql_change_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` INT UNSIGNED NOT NULL,
  `sql_statement` LONGTEXT NOT NULL,
  `change_database` VARCHAR(255) NOT NULL,
  `change_table` VARCHAR(255) NOT NULL,
  `change_type` VARCHAR(255) NOT NULL,
  `where_items` LONGTEXT NOT NULL,
  `value_items` LONGTEXT NOT NULL,
  `old_records` LONGTEXT NOT NULL,
  PRIMARY KEY (`sql_change_id`),
  INDEX `created_timestamp` (`created_timestamp` ASC),
  CONSTRAINT `fk_sql_changes_users1`
    FOREIGN KEY (`user_id`)
    REFERENCES `webdb`.`users` (`user_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS `webdb`.`groups`;
CREATE TABLE IF NOT EXISTS `webdb`.`groups` (
  `group_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `enabled` TINYINT NOT NULL DEFAULT 0,
  `group_name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`group_id`),
  UNIQUE INDEX `group_name` (`group_name` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 1;

INSERT INTO `webdb`.`groups` (`group_name`,`enabled`) VALUES ("admin",1);

DROP TABLE IF EXISTS `webdb`.`user_group_links` ;
CREATE TABLE IF NOT EXISTS `webdb`.`user_group_links` (
  `user_id` INT UNSIGNED NOT NULL,
  `group_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `group_id`),
  CONSTRAINT `fk_user_group_links_users1`
    FOREIGN KEY (`user_id`)
    REFERENCES `webdb`.`users` (`user_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_user_group_links_groups1`
    FOREIGN KEY (`group_id`)
    REFERENCES `webdb`.`groups` (`group_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

INSERT INTO `webdb`.`user_group_links` (`user_id`,`group_id`) VALUES (1,1);

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
