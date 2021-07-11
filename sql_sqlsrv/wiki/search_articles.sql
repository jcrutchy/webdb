DECLARE @query VARCHAR(255)
SET @query = :query

SELECT

wiki_articles.*,
users.user_id,
users.fullname

FROM $$database_webdb$$.wiki_articles AS wiki_articles

LEFT OUTER JOIN $$database_webdb$$.users AS users
ON users.user_id=wiki_articles.user_id

WHERE

(CHARINDEX(@query,LOWER(wiki_articles.title))>0)

OR

(CHARINDEX(@query,LOWER(wiki_articles.content))>0)

ORDER BY

wiki_articles.title ASC
