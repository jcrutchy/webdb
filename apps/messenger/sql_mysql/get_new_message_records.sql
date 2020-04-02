SELECT messages.* FROM messenger.messages AS messages

INNER JOIN messenger.channel_users AS channel_users
ON channel_users.channel_id=messages.channel_id
WHERE channel_users.user_id=:user_id AND messages.message_id>channel_users.last_read_message_id

ORDER BY messages.message_id
