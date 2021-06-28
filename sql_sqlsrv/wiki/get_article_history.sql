SELECT

*

FROM $$database_webdb$$.wiki_article_oldversions AS wiki_article_oldversions

LEFT OUTER JOIN $$database_webdb$$.users AS users
ON users.user_id=wiki_article_oldversions.user_id

WHERE article_id=:article_id

ORDER BY

wiki_article_oldversions.created_timestamp DESC
