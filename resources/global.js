
/////////////////////////////////////////////////////////////////////////////////////////////////////

function page_load()
{
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
