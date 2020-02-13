SELECT [%%database%%].[%%table%%].* FROM [%%database%%].[%%table%%] %%inner_joins%% %%prepared_where%% ORDER BY %%sort_sql%%;
