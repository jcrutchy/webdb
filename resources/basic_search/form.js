
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
