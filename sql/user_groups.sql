SELECT group_name FROM webdb.groups AS grp
INNER JOIN
webdb.user_group_links AS lnk
ON grp.group_id = lnk.group_id
WHERE (enabled = 1) AND (user_id = :user_id)
