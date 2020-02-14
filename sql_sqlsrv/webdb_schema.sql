
USE [$$database_webdb$$];

DROP TABLE IF EXISTS [user_group_links] ;
DROP TABLE IF EXISTS [groups];
DROP TABLE IF EXISTS [sql_changes] ;
DROP TABLE IF EXISTS [row_locks] ;
DROP TABLE IF EXISTS [users];

CREATE TABLE users (
  [user_id] INT CHECK ([user_id] > 0) NOT NULL IDENTITY,
  [created_timestamp] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  [enabled] SMALLINT NOT NULL DEFAULT 0,
  [username] VARCHAR(255) NOT NULL,
  [fullname] VARCHAR(255) NOT NULL,
  [email] VARCHAR(255) DEFAULT NULL,
  [csrf_token] VARCHAR(255) DEFAULT '',
  [csrf_token_time] BIGINT DEFAULT 0,
  [login_cookie] VARCHAR(255) DEFAULT '*',
  [login_setcookie_time] BIGINT DEFAULT 0,
  [pw_hash] VARCHAR(255) DEFAULT '*',
  [pw_change] SMALLINT NOT NULL DEFAULT 1,
  [pw_reset_key] VARCHAR(255) DEFAULT '*',
  [pw_reset_time] BIGINT DEFAULT 0,
  [pw_login_time] BIGINT DEFAULT 0,
  [cookie_login_time] BIGINT DEFAULT 0,
  [user_agent] VARCHAR(255) DEFAULT NULL,
  [remote_address] VARCHAR(255) DEFAULT NULL,
  [failed_login_count] INT CHECK ([failed_login_count] >= 0) DEFAULT NULL,
  [failed_login_time] BIGINT DEFAULT NULL,
  PRIMARY KEY ([user_id]),
  CONSTRAINT [username] UNIQUE  ([username] ASC))
;

/*
username: admin
password: password
*/
INSERT INTO users ([username],[fullname],[pw_hash],[enabled]) VALUES ('admin','admin','$2y$13$Vn8rJB73AHq56cAqbBwkEuKrQt3lSdoA3sDmKULZEgQLE4.nmsKzW',1);

/* TODO: THE FOLLOWING UPDATE STATEMENT IS FOR DEVELOPMENT/TESTING ONLY - REMOVE FOR PRODUCTION */
UPDATE users SET [pw_change]=0 WHERE ([username]='admin');

CREATE TABLE row_locks (
  [lock_id] INT CHECK ([lock_id] > 0) NOT NULL IDENTITY,
  [created_timestamp] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  [user_id] INT CHECK ([user_id] > 0) NOT NULL,
  [lock_schema] VARCHAR(255) NOT NULL,
  [lock_table] VARCHAR(255) NOT NULL,
  [lock_key_field] VARCHAR(255) NOT NULL,
  [lock_key_value] INT CHECK ([lock_key_value] > 0) NOT NULL,
  PRIMARY KEY ([lock_id])
 ,
  CONSTRAINT [fk_row_locks_users1]
    FOREIGN KEY ([user_id])
    REFERENCES users ([user_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
;

CREATE INDEX [created_timestamp] ON row_locks ([created_timestamp] ASC);
CREATE INDEX [lock_schema] ON row_locks ([lock_schema] ASC);
CREATE INDEX [lock_table] ON row_locks ([lock_table] ASC);
CREATE INDEX [lock_key_field] ON row_locks ([lock_key_field] ASC);
CREATE INDEX [lock_key_value] ON row_locks ([lock_key_value] ASC);

CREATE TABLE sql_changes (
  [sql_change_id] INT CHECK ([sql_change_id] > 0) NOT NULL IDENTITY,
  [created_timestamp] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  [user_id] INT CHECK ([user_id] > 0) NOT NULL,
  [sql_statement] VARCHAR(max) NOT NULL,
  [change_database] VARCHAR(255) NOT NULL,
  [change_table] VARCHAR(255) NOT NULL,
  [change_type] VARCHAR(255) NOT NULL,
  [where_items] VARCHAR(max) NOT NULL,
  [value_items] VARCHAR(max) NOT NULL,
  [old_records] VARCHAR(max) NOT NULL,
  PRIMARY KEY ([sql_change_id])
 ,
  CONSTRAINT [fk_sql_changes_users1]
    FOREIGN KEY ([user_id])
    REFERENCES users ([user_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
;

CREATE INDEX [created_timestamp] ON sql_changes ([created_timestamp] ASC);

CREATE TABLE groups (
  [group_id] INT CHECK ([group_id] > 0) NOT NULL IDENTITY,
  [created_timestamp] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  [enabled] SMALLINT NOT NULL DEFAULT 0,
  [group_name] VARCHAR(255) NOT NULL,
  PRIMARY KEY ([group_id]),
  CONSTRAINT [group_name] UNIQUE  ([group_name] ASC))
;

INSERT INTO groups ([group_name],[enabled]) VALUES ('admin',1);

CREATE TABLE user_group_links (
  [user_id] INT CHECK ([user_id] > 0) NOT NULL,
  [group_id] INT CHECK ([group_id] > 0) NOT NULL,
  PRIMARY KEY ([user_id], [group_id]),
  CONSTRAINT [fk_user_group_links_users1]
    FOREIGN KEY ([user_id])
    REFERENCES users ([user_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT [fk_user_group_links_groups1]
    FOREIGN KEY ([group_id])
    REFERENCES groups ([group_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
;

GO

INSERT INTO user_group_links ([user_id],[group_id]) VALUES (1,1);
