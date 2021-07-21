SELECT * FROM $$database_webdb$$.users AS users

INNER JOIN
$$database_webdb$$.user_group_links AS links
ON links.user_id=users.user_id

WHERE (users.enabled=1) AND (links.group_id=:group_id)
