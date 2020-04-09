SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ALLOW_INVALID_DATES';

DROP SCHEMA IF EXISTS $$database_app$$;
CREATE SCHEMA IF NOT EXISTS $$database_app$$ DEFAULT CHARACTER SET utf8;

USE $$database_app$$;

DROP TABLE IF EXISTS item_location_links;
DROP TABLE IF EXISTS item_types;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS locations;

CREATE TABLE IF NOT EXISTS item_types (
  item_type_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  description VARCHAR(255) NOT NULL,
  PRIMARY KEY (item_type_id),
  UNIQUE INDEX description (description ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 1;

CREATE TABLE IF NOT EXISTS items (
  item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  item_name VARCHAR(255) NOT NULL,
  item_type_id INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (item_id),
  UNIQUE INDEX item_name (item_name ASC),
  CONSTRAINT fk_items_item_types1
    FOREIGN KEY (item_type_id)
    REFERENCES item_types (item_type_id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 1;

CREATE TABLE IF NOT EXISTS locations (
  location_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  location_name VARCHAR(255) NOT NULL,
  description VARCHAR(255) NOT NULL,
  parent_location_id INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (location_id),
  UNIQUE INDEX location_name (location_name ASC),
  CONSTRAINT fk_locations_locations1
    FOREIGN KEY (parent_location_id)
    REFERENCES locations (location_id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 1;

CREATE TABLE IF NOT EXISTS item_location_links (
  item_id INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED DEFAULT 0,
  notes LONGTEXT DEFAULT NULL,
  PRIMARY KEY (item_id, location_id),
  CONSTRAINT fk_item_location_links_items1
    FOREIGN KEY (item_id)
    REFERENCES items (item_id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT fk_item_location_links_locations1
    FOREIGN KEY (location_id)
    REFERENCES locations (location_id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

INSERT INTO item_types (description) VALUES ("hardware");
INSERT INTO item_types (description) VALUES ("software");

INSERT INTO items (item_name,item_type_id) VALUES ('Microsoft Windows XP',2);
INSERT INTO items (item_name,item_type_id) VALUES ('Adobe Acrobat Reader',2);
INSERT INTO items (item_name,item_type_id) VALUES ('Debian GNU/Linux',2);
INSERT INTO items (item_name,item_type_id) VALUES ('Intel Processor',1);
INSERT INTO items (item_name,item_type_id) VALUES ('Asus Laptop',1);
INSERT INTO items (item_name,item_type_id) VALUES ('Dell Monitor',1);
INSERT INTO items (item_name,item_type_id) VALUES ('Dell Docking Station',1);

INSERT INTO locations (location_name,description) VALUES ('Melbourne City Store','Store located in Melbourne city square.');
INSERT INTO locations (location_name,description,parent_location_id) VALUES ('Front Window Cabinet','Locked cabinet inside front window of shop.',1);
INSERT INTO locations (location_name,description,parent_location_id) VALUES ('Checkout Cabinet','Locked cabinet at checkout near rear of shop.',1);

INSERT INTO item_location_links (item_id,location_id,quantity) VALUES (1,2,10);
INSERT INTO item_location_links (item_id,location_id,quantity) VALUES (2,2,7);
INSERT INTO item_location_links (item_id,location_id,quantity) VALUES (5,3,2);

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
