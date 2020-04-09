USE $$database_app$$;

DROP TABLE IF EXISTS item_location_links;
DROP TABLE IF EXISTS item_types;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS locations;

CREATE TABLE item_types (
  item_type_id INT CHECK (item_type_id > 0) NOT NULL IDENTITY,
  created_timestamp DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  description VARCHAR(255) NOT NULL,
  PRIMARY KEY (item_type_id),
  CONSTRAINT description UNIQUE (description ASC))
;

CREATE TABLE items (
  item_id INT CHECK (item_id > 0) NOT NULL IDENTITY,
  created_timestamp DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  item_name VARCHAR(255) NOT NULL,
  item_type_id INT CHECK (item_type_id > 0) DEFAULT NULL,
  PRIMARY KEY (item_id),
  CONSTRAINT item_name UNIQUE (item_name ASC),
  CONSTRAINT fk_items_item_types1
    FOREIGN KEY (item_type_id)
    REFERENCES item_types (item_type_id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
;

CREATE TABLE locations (
  location_id INT CHECK (location_id > 0) NOT NULL IDENTITY,
  created_timestamp DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  location_name VARCHAR(255) NOT NULL,
  description VARCHAR(255) NOT NULL,
  parent_location_id INT CHECK (parent_location_id > 0) DEFAULT NULL,
  PRIMARY KEY (location_id),
  CONSTRAINT location_name UNIQUE (location_name ASC),
  CONSTRAINT fk_locations_locations1
    FOREIGN KEY (parent_location_id)
    REFERENCES locations (location_id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
;

CREATE TABLE item_location_links (
  item_id INT CHECK (item_id > 0) NOT NULL,
  location_id INT CHECK (location_id > 0) NOT NULL,
  quantity INT CHECK (quantity > 0) DEFAULT 0,
  notes VARCHAR(max) DEFAULT NULL,
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
;

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
