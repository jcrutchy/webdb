
/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_page_load()
{
  confirm_status_id="";
  var page_id=document.getElementById("parent_form");
  if (page_id)
  {
    if (page_id.innerHTML!="")
    {
      confirm_status_id="confirm_status:"+page_id.innerHTML;
      var confirm_status=document.getElementById(confirm_status_id);
      var app_name=document.getElementById("app_name").innerHTML;
      var cookie_name=app_name+":confirm_status:"+page_id.innerHTML;
      cookie_name=cookie_name.replace(/ /g,"_");
      var confirm_status_message=get_cookie(cookie_name);
      if ((confirm_status) && (confirm_status_message!=""))
      {
        confirm_status.innerHTML=confirm_status_message;
        document.cookie=cookie_name+"=; Domain="+window.location.hostname+"; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;";
        confirm_status_fade_alpha=1.0;
        confirm_status.style.color="rgba(153,51,0,"+confirm_status_fade_alpha+")";
        confirm_status.style.visibility="visible";
        setTimeout(confirm_status_fadeout,2000); // 2 sec
      }
    }
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function set_filter_cookie(page_id,value)
{
  var cookie_enabled=document.getElementById("filter_cookie:"+page_id);
  if (cookie_enabled)
  {
    if (cookie_enabled.innerHTML==1)
    {
      var app_name=document.getElementById("app_name").innerHTML;
      var cookie_name=app_name+":filters:"+page_id;
      cookie_name=cookie_name.replace(/ /g,"_");
      set_session_cookie(cookie_name,value);
    }
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function parent_url_params(page_id)
{
  var url_append="";
  var parent=document.getElementById("parent_form");
  if (parent!==null)
  {
    var parent_form=parent.innerHTML;
    var parent_id=document.getElementById("parent_id").innerHTML;
    var url_append=url_append+"&subform="+page_id+"&parent_form="+parent_form+"&parent_id="+parent_id;
  }
  return url_append;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_body_click(event)
{
  var calendar=document.getElementById("calendar_div");
  if ((event.target==calendar) || (calendar.contains(event.target)==true))
  {
    return;
  }
  var edit_row=document.getElementById("data_row_tr:"+edit_row_page_id+":"+edit_row_id);
  if (!edit_row)
  {
    return;
  }
  if ((event.target==edit_row) || (edit_row.contains(event.target)==true))
  {
    return;
  }
  var row_edit_mode=document.getElementById("row_edit_mode:"+edit_row_page_id).innerHTML;
  if (row_edit_mode=="inline")
  {
    var url_prefix=document.getElementById("inline_edit_page:"+edit_row_page_id).value;
    var url=url_prefix+edit_row_id+"&ajax&reset";
    url=url+parent_url_params(edit_row_page_id);
    ajax(url,"get",list_edit_row_reset_load,list_edit_row_reset_error,list_edit_row_reset_timeout);
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

var confirm_status_fade_alpha=1.0;
var confirm_status_id="";

function confirm_status_fadeout()
{
  confirm_status_fade_alpha=confirm_status_fade_alpha-0.05;
  var confirm_status=document.getElementById(confirm_status_id);
  confirm_status.style.color="rgba(153,51,0,"+confirm_status_fade_alpha+")";
  if (confirm_status_fade_alpha>0.0)
  {
    setTimeout(confirm_status_fadeout,200); // 0.2 sec
  }
  else
  {
    confirm_status_fade_alpha=1.0;
    confirm_status.style.visibility="hidden";
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_button_click(page_id,id)
{
  window.location=document.getElementById("edit_page:"+page_id).value+id;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_reset_load()
{
  var data=get_ajax_load_data(this);
  var calendar=document.getElementById("calendar_div");
  if (calendar!==null)
  {
    document.body.appendChild(calendar);
  }
  if ((data.hasOwnProperty("page_id")==true) && (data.hasOwnProperty("primary_key")==true) && (data.hasOwnProperty("html")==true))
  {
    var data_row=document.getElementById("data_row_tr:"+data.page_id+":"+data.primary_key);
    data_row.innerHTML=data.html;
    if ((typeof calendar_inputs)!==(typeof undefined))
    {
      calendar_inputs=initial_calendar_inputs;
    }
    edit_row_controls=new Array();
    edit_row_page_id=null;
    edit_row_id=null;
    if (waiting_edit_row_element!=null)
    {
      waiting_edit_row_element.onclick();
    }
    return;
  }
  custom_alert("list_edit_row_reset_load");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_reset_error()
{
  custom_alert("list_edit_row_reset_error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_reset_timeout()
{
  custom_alert("list_edit_row_reset_timeout");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function append_form_field_param(params,page_id,form,field_name,record_id)
{
  if (field_name.startsWith("date_field__")==true)
  {
    field_name="iso_"+field_name;
  }
  field_name=page_id+":edit_control:"+record_id+":"+field_name;
  var field_input=form.elements.namedItem(field_name);
  if (!field_input)
  {
    custom_alert(field_name);
  }
  var field_value=field_input.value;
  if (form.elements.namedItem(field_name).type=="checkbox")
  {
    field_value=form.elements.namedItem(field_name).checked;
  }
  params[field_name]=field_value;
  return params;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_insert_row_click(form,page_id)
{
  var params={};
  var field_names=insert_row_controls[page_id];
  for (var i=0;i<field_names.length;i++)
  {
    params=append_form_field_param(params,page_id,form,field_names[i],"");
  }
  var url_prefix=form.elements.namedItem("row_insert_page:"+page_id).value;
  var url=url_prefix+"&ajax"+parent_url_params(page_id);
  ajax(url,"post",list_insert_row_load,list_insert_row_error,list_insert_row_timeout,params);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_insert_row_load()
{
  var data=get_ajax_load_data(this);
  if ((data.hasOwnProperty("page_id")==true) && (data.hasOwnProperty("html")==true))
  {
    document.getElementById("subform_table_"+data.page_id).innerHTML=data.html;
    return;
  }
  custom_alert("list_insert_row_load");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_insert_row_error()
{
  custom_alert("list_insert_row_error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_insert_row_timeout()
{
  custom_alert("list_insert_row_timeout");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_insert_click(page_id)
{
  window.location=document.getElementById("insert_page:"+page_id).value;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_advanced_search_click(page_id)
{
  window.location=document.getElementById("advanced_search_page:"+page_id).value;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_record_cell_click(element,page_id,id,field_name,edit_cmd_id)
{
  if (edit_row_id!=null)
  {
    waiting_edit_row_element=element;
    return;
  }
  var row_edit_mode=document.getElementById("row_edit_mode:"+page_id).innerHTML;
  if (row_edit_mode=="row")
  {
    window.location=document.getElementById("edit_page:"+page_id).value+edit_cmd_id;
    return;
  }
  if (row_edit_mode=="inline")
  {
    var url=document.getElementById("inline_edit_page:"+page_id).value+id+"&ajax"+parent_url_params(page_id);
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
  var data=get_ajax_load_data(this);
  if ((data.hasOwnProperty("page_id")==true) && (data.hasOwnProperty("primary_key")==true) && (data.hasOwnProperty("calendar_fields")==true) && (data.hasOwnProperty("edit_fields")==true) && (data.hasOwnProperty("html")==true))
  {
    var data_row=document.getElementById("data_row_tr:"+data.page_id+":"+data.primary_key);
    data_row.innerHTML=data.html;
    if ((typeof calendar_inputs)!==(typeof undefined))
    {
      calendar_inputs=calendar_inputs.concat(JSON.parse(data.calendar_fields));
    }
    edit_row_controls=JSON.parse(data.edit_fields);
    edit_row_page_id=data.page_id;
    edit_row_id=data.primary_key;
    waiting_edit_row_element=null;
    return;
  }
  custom_alert("list_edit_row_load");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_error()
{
  custom_alert("list_edit_row_error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_timeout()
{
  custom_alert("list_edit_row_timeout");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_update(form,page_id)
{
  var params={};
  for (var i=0;i<edit_row_controls.length;i++)
  {
    params=append_form_field_param(params,page_id,form,edit_row_controls[i],edit_row_id);
  }
  var url_prefix=form.elements.namedItem("ajax_edit_page:"+page_id).value;
  var url=url_prefix+edit_row_id+parent_url_params(page_id);
  ajax(url,"post",list_edit_row_update_load,list_edit_row_update_error,list_edit_row_update_timeout,params);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_update_load()
{
  var data=get_ajax_load_data(this);
  if (data.hasOwnProperty("html")==true)
  {
    // TODO: data.html
    return;
  }
  location.reload();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_update_error()
{
  custom_alert("list_edit_row_update_error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_edit_row_update_timeout()
{
  custom_alert("list_edit_row_update_timeout");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_record_cell_mouseover(page_id,id,field_name,edit_cmd_id)
{
  var row_edit_mode=document.getElementById("row_edit_mode:"+page_id).innerHTML;
  if (row_edit_mode=="inline")
  {
    return;
  }
  var cells=document.getElementsByClassName("list_record:"+page_id+":"+id);
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
    footer.innerHTML="Navigate to: "+document.getElementById("edit_page:"+page_id).value+edit_cmd_id;
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_record_cell_mouseout(page_id,id,field_name,edit_cmd_id)
{
  var row_edit_mode=document.getElementById("row_edit_mode:"+page_id).innerHTML;
  if (row_edit_mode=="inline")
  {
    return;
  }
  var cells=document.getElementsByClassName("list_record:"+page_id+":"+id);
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
/////////////////////////////////////////////////////////////////////////////////////////////////////

function file_field_view(page_id,field_name,filename)
{
  var parts=field_name.split(":");
  field_name=parts[0]+":"+parts[2]+":"+parts[3];
  var url=window.location.href;
  url=remove_url_param("cmd",url);
  url=remove_url_param("id",url);
  url+="&file_view="+encodeURIComponent(field_name);
  window.open(url,"_blank");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////

function file_delete_confirm()
{
  document.getElementById("file_delete_confirm_background").style.display="none";
  var page_id=document.getElementById("file_delete_confirm_page_id").innerHTML;
  var field_name=encodeURIComponent(document.getElementById("file_delete_confirm_field_name").innerHTML);
  var form=document.getElementById(page_id);
  var url=form.action+"&ajax&file_delete="+field_name;
  ajax(url,"get",file_field_delete_load,file_field_delete_error,file_field_delete_timeout);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function file_delete_cancel()
{
  document.getElementById("file_delete_confirm_background").style.display="none";
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function file_field_delete(page_id,field_name,filename)
{
  document.getElementById("file_delete_confirm_page_id").innerHTML=page_id;
  document.getElementById("file_delete_confirm_field_name").innerHTML=field_name;
  document.getElementById("file_delete_confirm_filename").innerHTML=filename;
  document.getElementById("file_delete_confirm_background").style.display="block";
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function file_field_delete_load()
{
  var data=get_ajax_load_data(this);
  if ((data.hasOwnProperty("page_id")==true) && (data.hasOwnProperty("primary_key")==true) && (data.hasOwnProperty("html")==true))
  {
    var data_row=document.getElementById("data_row_tr:"+data.page_id+":"+data.primary_key);
    data_row.innerHTML=data.html;
    return;
  }
  window.location.reload(true);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function file_field_delete_error()
{
  custom_alert("file_field_delete_error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function file_field_delete_timeout()
{
  custom_alert("file_field_delete_timeout");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
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
