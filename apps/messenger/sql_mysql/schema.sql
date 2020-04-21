DROP SCHEMA IF EXISTS $$database_app$$;
CREATE SCHEMA IF NOT EXISTS $$database_app$$ DEFAULT CHARACTER SET utf8;

DROP TABLE IF EXISTS $$database_app$$.`messenger_channels`;
CREATE TABLE IF NOT EXISTS $$database_app$$.`messenger_channels` (
  `channel_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `channel_name` varchar(255) NOT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `enabled` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`channel_id`),
  UNIQUE INDEX `channel_name` (`channel_name` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS $$database_app$$.`messenger_users`;
CREATE TABLE IF NOT EXISTS $$database_app$$.`messenger_users` (
  `user_id` INT UNSIGNED NOT NULL,
  `created_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `enabled` TINYINT NOT NULL DEFAULT 1,
  `last_online` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `nick` VARCHAR(20) NOT NULL,
  `selected_channel_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE INDEX `nick` (`nick` ASC),
  CONSTRAINT `fk_users_users1`
    FOREIGN KEY (`user_id`)
    REFERENCES $$database_webdb$$.`users` (`user_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_users_channels1`
    FOREIGN KEY (`selected_channel_id`)
    REFERENCES $$database_app$$.`messenger_channels` (`channel_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

DROP TABLE IF EXISTS $$database_app$$.`messenger_messages`;
CREATE TABLE IF NOT EXISTS $$database_app$$.`messenger_messages` (
  `message_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` integer unsigned NOT NULL,
  `channel_id` integer unsigned NOT NULL,
  `message` longtext NOT NULL,
  PRIMARY KEY (`message_id`),
  INDEX `user_id` (`user_id` ASC),
  INDEX `channel_id` (`channel_id` ASC),
  CONSTRAINT `fk_messages_users1`
    FOREIGN KEY (`user_id`)
    REFERENCES $$database_app$$.`messenger_users` (`user_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_messages_channels1`
    FOREIGN KEY (`channel_id`)
    REFERENCES $$database_app$$.`messenger_channels` (`channel_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS $$database_app$$.`messenger_channel_users` ;
CREATE TABLE IF NOT EXISTS `messenger`.`messenger_channel_users` (
  `channel_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `last_read_message_id` INT DEFAULT 0,
  PRIMARY KEY (`channel_id`, `user_id`),
  CONSTRAINT `fk_channel_users_channels1`
    FOREIGN KEY (`channel_id`)
    REFERENCES $$database_app$$.`messenger_channels` (`channel_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_channel_users_users1`
    FOREIGN KEY (`user_id`)
    REFERENCES $$database_app$$.`messenger_users` (`user_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
