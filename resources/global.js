/////////////////////////////////////////////////////////////////////////////////////////////////////

function page_load()
{
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function custom_alert(message)
{
  document.getElementById("custom_alert_message").innerHTML=message;
  document.getElementById("custom_alert_background").style.display="block";
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function custom_alert_close()
{
  document.getElementById("custom_alert_message").innerHTML="";
  document.getElementById("custom_alert_background").style.display="none";
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
    var csrf_token=document.getElementById("csrf_token").innerText;
    body=body+"&csrf_token="+encodeURIComponent(csrf_token);
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

function remove_url_param(key,url)
{
  var result=url.split("?")[0];
  var param;
  var params_arr=[];
  var query_string;
  if (url.indexOf("?")!==-1)
  {
    query_string=url.split("?")[1];
    params_arr=query_string.split("&");
    for (var i=params_arr.length-1;i>=0;i-=1)
    {
      param=params_arr[i].split("=")[0];
      if (param===key)
      {
        params_arr.splice(i,1);
      }
    }
    var params_str=params_arr.join("&");
    if (params_str!="")
    {
      params_str="?"+params_str;
    }
    result=result+params_str;
  }
  return result;
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
