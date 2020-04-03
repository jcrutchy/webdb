select cast(f.name as varchar(255)) as CONSTRAINT_NAME
, cast(c.name as varchar(255)) as TABLE_NAME
, cast(fc.name as varchar(255)) as COLUMN_NAME
, cast(p.name as varchar(255)) as REFERENCED_TABLE_NAME
, cast(rc.name as varchar(255)) as REFERENCED_COLUMN_NAME
from  sysobjects f
inner join sysobjects c on f.parent_obj = c.id
inner join sysreferences r on f.id = r.constid
inner join sysobjects p on r.rkeyid = p.id
inner join syscolumns rc on r.rkeyid = rc.id and r.rkey1 = rc.colid
inner join syscolumns fc on r.fkeyid = fc.id and r.fkey1 = fc.colid
where ((f.type = 'F')
and
(p.name = :table))

/* https://stackoverflow.com/questions/11866081/mssql-how-to-i-find-all-tables-that-have-foreign-keys-that-reference-particular */
