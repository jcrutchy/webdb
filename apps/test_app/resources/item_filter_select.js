/////////////////////////////////////////////////////////////////////////////////////////////////////

function item_filter_select_click(value,subform)
{
  var filters=get_cookie("filters");
  if (!filters)
  {
    filters={};
  }
  else
  {
    filters=JSON.parse(filters);
  }
  filters[subform]=value;
  set_session_cookie("filters",JSON.stringify(filters));
  var url=window.location.href+"&ajax=item_filter_select&field_name=item_link&new_filter="+value+"&subform="+subform;
  ajax(url,"get",item_filter_select_load,item_filter_select_error,item_filter_select_timeout);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function item_filter_select_load()
{
  try
  {
    data=JSON.parse(this.responseText);
  }
  catch (e)
  {
    custom_alert(this.responseText);
    return;
  }
  if (data.hasOwnProperty("error")==true)
  {
    custom_alert(data.error);
    return;
  }
  if ((data.hasOwnProperty("html")==true) && (data.hasOwnProperty("subform")==true))
  {
    insert_row_parents[data.subform].innerHTML=data.html;
    return;
  }
  item_filter_select_error();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function item_filter_select_error()
{
  custom_alert("item_filter_select_error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function item_filter_select_timeout()
{
  custom_alert("item_filter_select_timeout");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
