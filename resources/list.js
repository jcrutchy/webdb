/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_insert_row_click(form,url_page)
{
  var data=new Array();
  var field_names=insert_row_controls[url_page];
  for (var i=0;i<field_names.length;i++)
  {
    var field_name=field_names[i];
    if (form.elements[field_name].type=="checkbox")
    {
      var field_value=form.elements[field_name].checked;
    }
    else
    {
      var field_value=form.elements[field_name].value;
    }
    data.push(field_name+"="+field_value);
  }
  var body=data.join("&");
  var url=form.elements["insert_page:"+url_page].value+"&ajax";
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
    alert(this.responseText);
    return;
  }
  if (data.hasOwnProperty("error")==true)
  {
    alert(data.error);
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
  alert("error");
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

function list_record_cell_click(url_page,id)
{
  window.location=document.getElementById("edit_page:"+url_page).value+id;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_record_cell_mouseover(url_page,id)
{
  var cells=document.getElementsByClassName("list_record:"+url_page+":"+id);
  var footer=document.getElementById("global_page_footer_content");
  for (var i=0;i<cells.length;i++)
  {
    cells[i].style.backgroundColor="#a9d2ef";
    cells[i].style.cursor="pointer";
  }
  if (footer)
  {
    footer.innerHTML="Navigate to: "+document.getElementById("edit_page:"+url_page).value+id;
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_record_cell_mouseout(url_page,id)
{
  var cells=document.getElementsByClassName("list_record:"+url_page+":"+id);
  for (var i=0;i<cells.length;i++)
  {
    cells[i].style.backgroundColor="transparent";
    cells[i].style.cursor="default";
    document.getElementById("global_page_footer_content").innerHTML="";
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
