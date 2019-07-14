
/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_record_cell_click(url_page,id)
{
  window.location=document.getElementById("detail_page:"+url_page).value+id;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function list_record_cell_mouseover(url_page,id)
{
  var cells=document.getElementsByClassName("list_record:"+url_page+":"+id);
  for (var i=0;i<cells.length;i++)
  {
    cells[i].style.backgroundColor="#d4def7";
    cells[i].style.cursor="pointer";
    document.getElementById("global_page_footer_content").innerHTML="Navigate to: "+document.getElementById("detail_page:"+url_page).value+id;
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
