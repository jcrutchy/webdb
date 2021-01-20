SELECT

messages.*,
users.*,
channels.*,
messages.created_timestamp AS message_timestamp,
users.nick AS message_user

FROM $$database_app$$.messenger_messages AS messages

INNER JOIN $$database_app$$.messenger_users AS users
ON messages.user_id=users.user_id

INNER JOIN $$database_app$$.messenger_channels AS channels
ON messages.channel_id=channels.channel_id

ORDER BY

messages.created_timestamp DESC
