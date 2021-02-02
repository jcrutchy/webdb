
/////////////////////////////////////////////////////////////////////////////////////////////////////

function basic_search()
{
  var query=encodeURIComponent(document.getElementById("basic_search_query").value);
  if (query.trim()=="")
  {
    custom_alert("Error: Empty query.");
    return;
  }
  var url=document.getElementById("basic_search_target").value+query;
  window.location.href=url;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function basic_search_keypress(event)
{
  if (event.keyCode==13)
  {
    var login_submit=document.getElementById("basic_search_button");
    login_submit.click();
    login_submit.disabled=true;
    return false;
  }
  return true;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
