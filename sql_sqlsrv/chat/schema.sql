
USE $$sqlsrv_catalog$$;

DROP TABLE IF EXISTS [messenger_channel_users];
DROP TABLE IF EXISTS [messenger_messages];
DROP TABLE IF EXISTS [messenger_users];
DROP TABLE IF EXISTS [messenger_channels];

CREATE TABLE messenger_channels (
  [channel_id] INT CHECK ([channel_id] > 0) NOT NULL IDENTITY,
  [created_timestamp] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  [channel_name] varchar(255) NOT NULL,
  [topic] varchar(255) DEFAULT NULL,
  [enabled] SMALLINT NOT NULL DEFAULT 1,
  PRIMARY KEY ([channel_id]),
  CONSTRAINT [channel_name] UNIQUE  ([channel_name] ASC))
;

CREATE TABLE messenger_users (
  [user_id] INT CHECK ([user_id] > 0) NOT NULL,
  [created_timestamp] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  [enabled] SMALLINT NOT NULL DEFAULT 1,
  [last_online] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  [nick] VARCHAR(20) NOT NULL,
  [selected_channel_id] INT CHECK ([selected_channel_id] > 0) DEFAULT NULL,
  [json_data] varchar(max) DEFAULT NULL,
  PRIMARY KEY ([user_id]),
  CONSTRAINT [nick] UNIQUE  ([nick] ASC),
  CONSTRAINT [fk_users_users1]
    FOREIGN KEY ([user_id])
    REFERENCES users ([user_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT [fk_users_channels1]
    FOREIGN KEY ([selected_channel_id])
    REFERENCES messenger_channels ([channel_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
;

CREATE TABLE messenger_messages (
  [message_id] INT CHECK ([message_id] > 0) NOT NULL IDENTITY,
  [created_timestamp] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  [user_id] integer check ([user_id] > 0) NOT NULL,
  [channel_id] integer check ([channel_id] > 0) NOT NULL,
  [message] varchar(max) NOT NULL,
  PRIMARY KEY ([message_id])
 ,
  CONSTRAINT [fk_messages_users1]
    FOREIGN KEY ([user_id])
    REFERENCES messenger_users ([user_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT [fk_messages_channels1]
    FOREIGN KEY ([channel_id])
    REFERENCES messenger_channels ([channel_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
;

CREATE INDEX [user_id] ON messenger_messages ([user_id] ASC);
CREATE INDEX [channel_id] ON messenger_messages ([channel_id] ASC);

CREATE TABLE messenger_channel_users (
  [channel_id] INT CHECK ([channel_id] > 0) NOT NULL,
  [user_id] INT CHECK ([user_id] > 0) NOT NULL,
  [last_read_message_id] INT DEFAULT 0,
  PRIMARY KEY ([channel_id], [user_id]),
  CONSTRAINT [fk_channel_users_channels1]
    FOREIGN KEY ([channel_id])
    REFERENCES messenger_channels ([channel_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT [fk_channel_users_users1]
    FOREIGN KEY ([user_id])
    REFERENCES messenger_users ([user_id])
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
;
