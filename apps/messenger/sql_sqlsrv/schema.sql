
USE $$sqlsrv_catalog$$;

DROP TABLE IF EXISTS [messenger`.`channels];
CREATE TABLE messenger.channels (
  [channel_id] INT CHECK ([channel_id] > 0) NOT NULL IDENTITY,
  [created_timestamp] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  [channel_name] varchar(255) NOT NULL,
  [topic] varchar(255) DEFAULT NULL,
  [enabled] SMALLINT NOT NULL DEFAULT 1,
  PRIMARY KEY ([channel_id]),
  CONSTRAINT [channel_name] UNIQUE  ([channel_name] ASC))
;

DROP TABLE IF EXISTS [messenger`.`users];
CREATE TABLE messenger.users (
  [user_id] INT CHECK ([user_id] > 0) NOT NULL,
  [created_timestamp] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  [enabled] SMALLINT NOT NULL DEFAULT 1,
  [last_online] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  [nick] VARCHAR(20) NOT NULL,
  [selected_channel_id] INT CHECK ([selected_channel_id] > 0) NOT NULL,
  PRIMARY KEY ([user_id]),
  CONSTRAINT [nick] UNIQUE  ([nick] ASC),
  CONSTRAINT [fk_users_users1]
    FOREIGN KEY ([user_id])
    REFERENCES webdb.users ([user_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT [fk_users_channels1]
    FOREIGN KEY ([selected_channel_id])
    REFERENCES messenger.channels ([channel_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
;

DROP TABLE IF EXISTS [messenger`.`messages];
CREATE TABLE messenger.messages (
  [message_id] INT CHECK ([message_id] > 0) NOT NULL IDENTITY,
  [created_timestamp] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  [user_id] integer check ([user_id] > 0) NOT NULL,
  [channel_id] integer check ([channel_id] > 0) NOT NULL,
  [message] varchar(max) NOT NULL,
  PRIMARY KEY ([message_id])
 ,
  CONSTRAINT [fk_messages_users1]
    FOREIGN KEY ([user_id])
    REFERENCES messenger.users ([user_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT [fk_messages_channels1]
    FOREIGN KEY ([channel_id])
    REFERENCES messenger.channels ([channel_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
;

CREATE INDEX [user_id] ON messenger.messages ([user_id] ASC);
CREATE INDEX [channel_id] ON messenger.messages ([channel_id] ASC);

DROP TABLE IF EXISTS [messenger`.`channel_users] ;
CREATE TABLE messenger.channel_users (
  [channel_id] INT CHECK ([channel_id] > 0) NOT NULL,
  [user_id] INT CHECK ([user_id] > 0) NOT NULL,
  [last_read_message_id] INT CHECK ([last_read_message_id] > 0) DEFAULT 0,
  PRIMARY KEY ([channel_id], [user_id]),
  CONSTRAINT [fk_channel_users_channels1]
    FOREIGN KEY ([channel_id])
    REFERENCES messenger.channels ([channel_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT [fk_channel_users_users1]
    FOREIGN KEY ([user_id])
    REFERENCES messenger.users ([user_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
;
