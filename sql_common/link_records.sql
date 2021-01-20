SELECT * FROM %%link_database%%.%%link_table%% AS link

INNER JOIN

%%database%%.%%table%% AS parent

ON link.%%link_key%%=parent.%%link_key%%

WHERE link.%%parent_key%%=:parent_key %%selected_filter_condition%%
