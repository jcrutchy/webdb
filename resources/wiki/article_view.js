/////////////////////////////////////////////////////////////////////////////////////////////////////

function wiki_search()
{
  var query=encodeURIComponent(document.getElementById("wiki_search_query").value);
  if (query.trim()=="")
  {
    custom_alert("Error: Empty query.");
    return;
  }
  document.getElementById("wiki_search_button").disabled=true;
  document.getElementById("wiki_search_query").disabled=true;
  var url=document.getElementById("wiki_search_target").value+query;
  window.location.href=url;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function wiki_search_keypress(event)
{
  if (event.keyCode==13)
  {
    var submit_button=document.getElementById("wiki_search_button");
    submit_button.click();
    return false;
  }
  return true;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
