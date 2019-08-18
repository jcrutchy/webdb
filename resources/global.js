
/////////////////////////////////////////////////////////////////////////////////////////////////////

function page_load()
{
  document.onclick=body_click;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function body_click(event)
{
  if (typeof calendar_body_click==="function")
  {
    calendar_body_click(event);
    return;
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function ajax(url,method,load,error,timeout,body="",timeout_sec=30)
{
  var xhttp=new XMLHttpRequest();
  xhttp.onerror=error;
  xhttp.onload=load;
  xhttp.ontimeout=timeout;
  xhttp.timeout=timeout_sec*1000; // ms
  xhttp.open(method,url,true);
  if (method=="post") // method is "get" or "post"
  {
    xhttp.setRequestHeader("Content-type","application/x-www-form-urlencoded");
  }
  xhttp.send(body);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function get_element_position(element)
{
  var rect=element.getBoundingClientRect();
  var xy=new Array(rect.left+window.scrollX,rect.top+window.scrollY);
  return xy;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function is_descendant(parent,child)
{
  var node=child.parentNode;
  while (node!=null)
  {
    if (node==parent)
    {
      return true;
    }
    node=node.parentNode;
  }
  return false;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

if (window.addEventListener)
{
  window.addEventListener("load",page_load);
}
else
{
  window.attachEvent("onload",page_load);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
