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
  var data=get_ajax_load_data(this);
  if (data===false)
  {
    return;
  }
  if ((data.hasOwnProperty("html")==true) && (data.hasOwnProperty("subform")==true))
  {
    document.getElementById("subform_table_"+data.subform).innerHTML=data.html;
    return;
  }
  custom_alert("item_filter_select_load");
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
