
/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_page_load()
{
  var url=window.location.href;
  var url_obj=new URL(url);
  var update_page=url_obj.searchParams.get("update");
  if (update_page!=null)
  {
    url=remove_url_param("update",url);
    var current_state=history.state;
    history.replaceState(current_state,document.title,url);
    update_status_fade_id="update_status:"+update_page;
    update_status_fade_alpha=1.0;
    var update_status=document.getElementById(update_status_fade_id);
    update_status.style.color="rgba(153,51,0,"+update_status_fade_alpha+")";
    update_status.style.display="inline";
    setTimeout(update_status_fadeout,2000); // 2 sec
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_body_click(event)
{
  var calendar=document.getElementById("calendar_div");
  if ((event.target==calendar) || (calendar.contains(event.target)==true))
  {
    return;
  }
  var edit_row=document.getElementById("data_row_tr:"+edit_row_url_page+":"+edit_row_id);
  if (!edit_row)
  {
    return;
  }
  if ((event.target==edit_row) || (edit_row.contains(event.target)==true))
  {
    return;
  }
  var row_edit_mode=document.getElementById("row_edit_mode:"+edit_row_url_page).innerHTML;
  if (row_edit_mode=="inline")
  {
    var parent_form=document.getElementById("parent_form:"+edit_row_url_page).value;
    var parent_id=document.getElementById("parent_id:"+edit_row_url_page).value;
    var url_prefix=document.getElementById("inline_edit_page:"+edit_row_url_page).value;
    var url=url_prefix+edit_row_id+"&ajax&reset&parent_form="+parent_form+"&parent_id="+parent_id;
    ajax(url,"get",list_edit_row_reset_load,list_edit_row_reset_error,list_edit_row_reset_timeout);
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

var update_status_fade_alpha=1.0;
var update_status_fade_id="";

function update_status_fadeout()
{
  var update_status=document.getElementById(update_status_fade_id);
  update_status_fade_alpha=update_status_fade_alpha-0.05;
  if (update_status)
  {
    update_status.style.color="rgba(153,51,0,"+update_status_fade_alpha+")";
    if (update_status_fade_alpha>0.0)
    {
      setTimeout(update_status_fadeout,200); // 0.2 sec
    }
    else
    {
      update_status_fade_alpha=1.0;
      update_status.style.display="none";
    }
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_button_click(url_page,id)
{
  window.location=document.getElementById("edit_page:"+url_page).value+id;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_reset_load()
{
  var calendar=document.getElementById("calendar_div");
  if (calendar!==null)
  {
    document.body.appendChild(calendar);
  }
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
  if ((data.hasOwnProperty("url_page")==true) && (data.hasOwnProperty("primary_key")==true) && (data.hasOwnProperty("html")==true))
  {
    var data_row=document.getElementById("data_row_tr:"+data.url_page+":"+data.primary_key);
    data_row.innerHTML=data.html;
    if ((typeof calendar_inputs)!==(typeof undefined))
    {
      calendar_inputs=initial_calendar_inputs;
    }
    edit_row_controls=new Array();
    edit_row_url_page=null;
    edit_row_id=null;
    if (waiting_edit_row_element!=null)
    {
      waiting_edit_row_element.onclick();
    }
    return;
  }
  list_edit_row_reset_error();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_reset_error()
{
  custom_alert("error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_reset_timeout()
{
  list_edit_row_reset_error();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function encoded_form_field(form,field_name)
{
  if (field_name.startsWith("date_field__")==true)
  {
    field_name="iso_"+field_name;
  }
  var field_value=form.elements.namedItem(field_name).value;
  if (form.elements.namedItem(field_name).type=="checkbox")
  {
    field_value=form.elements.namedItem(field_name).checked;
  }
  return field_name+"="+encodeURIComponent(field_value);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_insert_row_click(form,url_page)
{
  var data=new Array();
  var field_names=insert_row_controls[url_page];
  for (var i=0;i<field_names.length;i++)
  {
    data.push(encoded_form_field(form,field_names[i]));
  }
  var body=data.join("&");
  var url_prefix=form.elements.namedItem("insert_page:"+url_page).value;
  var parent=document.getElementById("top_level_url_page");
  var url=url_prefix+"&ajax";
  if (parent!==null)
  {
    var parent_form=form.elements.namedItem("parent_form:"+url_page).value;
    var parent_id=form.elements.namedItem("parent_id:"+url_page).value;
    var url=url+"&subform="+url_page+"&parent_form="+parent_form+"&parent_id="+parent_id;
  }
  ajax(url,"post",list_insert_row_load,list_insert_row_error,list_insert_row_timeout,body);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_insert_row_load()
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
  if ((data.hasOwnProperty("url_page")==true) && (data.hasOwnProperty("html")==true))
  {
    insert_row_parents[data.url_page].innerHTML=data.html;
    return;
  }
  list_insert_row_error();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_insert_row_error()
{
  custom_alert("error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_insert_row_timeout()
{
  list_insert_row_error();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_insert_click(url_page)
{
  window.location=document.getElementById("insert_page:"+url_page).value;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_advanced_search_click(url_page)
{
  window.location=document.getElementById("advanced_search_page:"+url_page).value;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_record_cell_click(element,url_page,id,field_name)
{
  if (edit_row_id!=null)
  {
    waiting_edit_row_element=element;
    return;
  }
  var row_edit_mode=document.getElementById("row_edit_mode:"+url_page).innerHTML;
  if (row_edit_mode=="row")
  {
    window.location=document.getElementById("edit_page:"+url_page).value+id;
    return;
  }
  if (row_edit_mode=="inline")
  {
    var url=document.getElementById("inline_edit_page:"+url_page).value+id+"&ajax";
    ajax(url,"get",list_edit_row_load,list_edit_row_error,list_edit_row_timeout);
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_load()
{
  if (edit_row_id!=null)
  {
    return;
  }
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
  if ((data.hasOwnProperty("url_page")==true) && (data.hasOwnProperty("primary_key")==true) && (data.hasOwnProperty("calendar_fields")==true) && (data.hasOwnProperty("edit_fields")==true) && (data.hasOwnProperty("html")==true))
  {
    var data_row=document.getElementById("data_row_tr:"+data.url_page+":"+data.primary_key);
    data_row.innerHTML=data.html;
    if ((typeof calendar_inputs)!==(typeof undefined))
    {
      calendar_inputs=calendar_inputs.concat(JSON.parse(data.calendar_fields));
    }
    edit_row_controls=JSON.parse(data.edit_fields);
    edit_row_url_page=data.url_page;
    edit_row_id=data.primary_key;
    waiting_edit_row_element=null;
    return;
  }
  list_edit_row_error();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_error()
{
  custom_alert("error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_timeout()
{
  list_edit_row_error();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_update(form,url_page)
{
  var data=new Array();
  for (var i=0;i<edit_row_controls.length;i++)
  {
    data.push(encoded_form_field(form,edit_row_controls[i]));
  }
  var body=data.join("&");
  var url_prefix=form.elements.namedItem("ajax_edit_page:"+url_page).value;
  var parent=document.getElementById("top_level_url_page");
  var url=url_prefix+edit_row_id+"&ajax";
  if (parent!==null)
  {
    var parent_form=form.elements.namedItem("parent_form:"+edit_row_url_page).value;
    var parent_id=form.elements.namedItem("parent_id:"+edit_row_url_page).value;
    var url=url+"&subform="+url_page+"&parent_form="+parent_form+"&parent_id="+parent_id;
  }
  ajax(url,"post",list_edit_row_update_load,list_edit_row_update_error,list_edit_row_update_timeout,body);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_update_load()
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
  location.reload();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_update_error()
{
  custom_alert("error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_update_timeout()
{
  list_edit_row_update_error();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_record_cell_mouseover(url_page,id,field_name)
{
  var row_edit_mode=document.getElementById("row_edit_mode:"+url_page).innerHTML;
  if (row_edit_mode=="inline")
  {
    return;
  }
  var cells=document.getElementsByClassName("list_record:"+url_page+":"+id);
  for (var i=0;i<cells.length;i++)
  {
    var idstr=cells[i].id.substr(9); // trim 'data_cell' from beginning
    var cell_style=document.getElementById("cell_style"+idstr);
    cell_style.innerHTML=cells[i].style.cssText;
    cells[i].style.backgroundColor="#a9d2ef";
    cells[i].style.cursor="pointer";
  }
  var footer=document.getElementById("global_page_footer_content");
  if (footer)
  {
    footer.innerHTML="Navigate to: "+document.getElementById("edit_page:"+url_page).value+id;
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_record_cell_mouseout(url_page,id,field_name)
{
  var row_edit_mode=document.getElementById("row_edit_mode:"+url_page).innerHTML;
  if (row_edit_mode=="inline")
  {
    return;
  }
  var cells=document.getElementsByClassName("list_record:"+url_page+":"+id);
  for (var i=0;i<cells.length;i++)
  {
    var idstr=cells[i].id.substr(9); // trim 'data_cell' from beginning
    var cell_style=document.getElementById("cell_style"+idstr).innerHTML;
    cells[i].style.cssText=cell_style;
  }
  var footer=document.getElementById("global_page_footer_content");
  if (footer)
  {
    footer.innerHTML="";
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

if (window.addEventListener)
{
  window.addEventListener("load",list_page_load);
  window.addEventListener("click",list_body_click);
}
else
{
  window.attachEvent("onload",list_page_load);
  window.attachEvent("onclick",list_body_click);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
