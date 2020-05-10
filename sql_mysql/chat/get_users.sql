SELECT * FROM $$database_app$$.messenger_users

WHERE
enabled=1
AND
last_online >= DATE_SUB(UTC_TIMESTAMP(),INTERVAL $$user_list_max_age_minutes$$ MINUTE)

ORDER BY nick ASC
