
/////////////////////////////////////////////////////////////////////////////////////////////////////

function basic_search()
{
  var query=encodeURIComponent(document.getElementById("basic_search_query").value);
  if (query.trim()=="")
  {
    custom_alert("Error: Empty query.");
    return;
  }
  document.getElementById("basic_search_button").disabled=true;
  document.getElementById("basic_search_query").disabled=true;
  var url=document.getElementById("basic_search_target").value+query;
  window.location.href=url;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function basic_search_keypress(event)
{
  if (event.keyCode==13)
  {
    var submit_button=document.getElementById("basic_search_button");
    submit_button.click();
    return false;
  }
  return true;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
