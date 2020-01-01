/////////////////////////////////////////////////////////////////////////////////////////////////////

function control_filter_select_click(value,subform)
{
  var url=window.location.href;
  var url_obj=new URL(url);
  var filters=url_obj.searchParams.get("filters");
  if (filters==null)
  {
    filters={};
  }
  else
  {
    filters=JSON.parse(filters);
  }
  filters[subform]=value;
  var url_append="&filters="+JSON.stringify(filters);
  url=remove_url_param("filters",url);
  var current_state=history.state;
  history.replaceState(current_state,document.title,url+url_append);
  var url=url+"&ajax=control_filter_select&field_name=risk_control_type&new_filter="+value+"&subform="+subform+"&redirect="+encodeURIComponent(url+url_append);
  ajax(url,"get",control_filter_select_load,control_filter_select_error,control_filter_select_timeout);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function control_filter_select_load()
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
  control_filter_select_error();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function control_filter_select_error()
{
  custom_alert("error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function control_filter_select_timeout()
{
  control_filter_select_error();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
