SELECT * FROM $$database_webdb$$.messenger_users

WHERE
enabled=1
AND
last_online >= DATEADD(minute,-5,GETUTCDATE())

ORDER BY nick ASC
