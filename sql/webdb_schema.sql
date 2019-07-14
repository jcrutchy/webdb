SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ALLOW_INVALID_DATES';

DROP SCHEMA IF EXISTS `webdb` ;
CREATE SCHEMA IF NOT EXISTS `webdb` DEFAULT CHARACTER SET utf8 ;

DROP TABLE IF EXISTS `webdb`.`users`;
CREATE TABLE IF NOT EXISTS `webdb`.`users` (
  `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login_cookie` VARCHAR(255) NOT NULL,
  `enabled` TINYINT NOT NULL DEFAULT 1,
  `email` VARCHAR(255) NOT NULL,
  `pw_hash` VARCHAR(255) NOT NULL,
  `pw_reset_key` VARCHAR(255) NOT NULL,
  `pw_reset_time` BIGINT NOT NULL,
  `privs` LONGTEXT NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE INDEX `email` (`email` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 1;

/*
username: admin
password: password
*/
INSERT INTO `webdb`.`users` (`email`,`pw_hash`,`privs`) VALUES ("admin","$2y$13$Vn8rJB73AHq56cAqbBwkEuKrQt3lSdoA3sDmKULZEgQLE4.nmsKzW","admin");

DROP TABLE IF EXISTS `webdb`.`row_locks` ;
CREATE TABLE IF NOT EXISTS `webdb`.`row_locks` (
  `lock_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `lock_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lock_schema` VARCHAR(255) NOT NULL,
  `lock_table` VARCHAR(255) NOT NULL,
  `lock_key_field` VARCHAR(255) NOT NULL,
  `lock_key_value` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`lock_id`),
  INDEX `lock_timestamp` (`lock_timestamp` ASC),
  INDEX `lock_schema` (`lock_schema` ASC),
  INDEX `lock_table` (`lock_table` ASC),
  INDEX `lock_key_field` (`lock_key_field` ASC),
  INDEX `lock_key_value` (`lock_key_value` ASC),
  INDEX `fk_row_locks_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_row_locks_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `webdb`.`users` (`user_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 1;


DROP TABLE IF EXISTS `webdb`.`sql_log` ;
CREATE TABLE IF NOT EXISTS `webdb`.`sql_log` (
  `log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `change_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sql_statement` LONGTEXT NOT NULL,
  PRIMARY KEY (`log_id`),
  INDEX `change_timestamp` (`change_timestamp` ASC),
  INDEX `fk_sql_log_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_sql_log_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `webdb`.`users` (`user_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 1;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
