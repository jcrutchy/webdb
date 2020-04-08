SELECT

messages.*,
users.*,
channel_users.*,
messages.created_timestamp AS message_time,
(SELECT MAX(message_id) FROM messenger.messages) AS max_id

FROM $$database_app$$.messages AS messages

INNER JOIN $$database_app$$.users AS users
ON messages.user_id=users.user_id

INNER JOIN $$database_app$$.channel_users AS channel_users
ON
channel_users.channel_id=messages.channel_id

WHERE

channel_users.user_id=:user_id
AND
messages.channel_id=:channel_id
AND
messages.message_id>channel_users.last_read_message_id

ORDER BY messages.message_id ASC
