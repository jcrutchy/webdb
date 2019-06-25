SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ALLOW_INVALID_DATES';

DROP TABLE IF EXISTS `$$db_users_schema$$`.`$$db_users_table$$`;

CREATE TABLE IF NOT EXISTS `$$db_users_schema$$`.`$$db_users_table$$` (
  `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login_cookie` VARCHAR(255) NOT NULL,
  `enabled` TINYINT NOT NULL DEFAULT 1,
  `email` VARCHAR(255) NOT NULL,
  `pw_hash` VARCHAR(255) NOT NULL,
  `pw_reset` TINYINT NOT NULL DEFAULT 0,
  `pw_reset_time` TIMESTAMP NOT NULL,
  `privs` LONGTEXT NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE INDEX `email` (`email` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 1;

/*
username: admin
password: password
*/
INSERT INTO `$$db_users_schema$$`.`$$db_users_table$$` (`email`,`pw_hash`,`privs`) VALUES ("admin","$2y$13$Vn8rJB73AHq56cAqbBwkEuKrQt3lSdoA3sDmKULZEgQLE4.nmsKzW","admin");

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
