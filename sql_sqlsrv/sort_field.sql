CASE
WHEN ISNUMERIC(%%field_name%%) = 1 THEN
RIGHT('00000000000000'+%%field_name%%,10)
ELSE %%field_name%%
END
%%direction%%
