DECLARE @query VARCHAR(255)
SET @query = :query

SELECT

wiki_files.*,
users.user_id,
users.fullname

FROM $$database_webdb$$.wiki_files AS wiki_files

LEFT OUTER JOIN $$database_webdb$$.users AS users
ON users.user_id=wiki_files.user_id

WHERE

(CHARINDEX(@query,LOWER(wiki_files.title))>0)

OR

(CHARINDEX(@query,LOWER(wiki_files.notes))>0)

ORDER BY

wiki_files.title ASC
