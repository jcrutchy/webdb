SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ALLOW_INVALID_DATES';

DROP SCHEMA IF EXISTS `test_app` ;
CREATE SCHEMA IF NOT EXISTS `test_app` DEFAULT CHARACTER SET utf8 ;

USE `test_app` ;

DROP TABLE IF EXISTS `test_app`.`items` ;
CREATE TABLE IF NOT EXISTS `test_app`.`items` (
  `item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `item_name` VARCHAR(255) NOT NULL,
  `item_type` VARCHAR(255) NOT NULL,
  `location_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`item_id`),
  UNIQUE INDEX `item_name` (`item_name` ASC),
  CONSTRAINT `fk_items_locations1`
    FOREIGN KEY (`location_id`)
    REFERENCES `test_app`.`locations` (`location_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS `test_app`.`locations` ;
CREATE TABLE IF NOT EXISTS `test_app`.`locations` (
  `location_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `location_name` VARCHAR(255) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `parent_location_id` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`location_id`),
  UNIQUE INDEX `location_name` (`location_name` ASC),
  CONSTRAINT `fk_locations_locations1`
    FOREIGN KEY (`parent_location_id`)
    REFERENCES `test_app`.`locations` (`location_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 1;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
