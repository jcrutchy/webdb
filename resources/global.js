/////////////////////////////////////////////////////////////////////////////////////////////////////

function page_load()
{
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function get_cookie(cookie_name)
{
  var cookies=decodeURIComponent(document.cookie);
  var cookies=cookies.split(";");
  for (var i=0;i<cookies.length;i++)
  {
    var cookie=cookies[i].trim();
    var j=cookie.indexOf("=");
    if (j>0)
    {
      var test_name=cookie.substring(0,j).trim();
      if (test_name==cookie_name)
      {
        var cookie_value=cookie.substring(j+1).trim();
        cookie_value=cookie_value.replace(/\+/g," ");
        return cookie_value;
      }
    }
  }
  return "";
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function set_session_cookie(cookie_name,cookie_value)
{
  document.cookie=cookie_name+"="+cookie_value+"; Expires=0; Domain="+window.location.hostname+"; Path=/;";
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

function ajax(url,method,load,error,timeout,params={},timeout_sec=false)
{
  if (timeout_sec===false)
  {
    timeout_sec=default_ajax_timeout_sec;
  }
  var xhttp=new XMLHttpRequest();
  xhttp.onerror=error;
  xhttp.onload=load;
  xhttp.ontimeout=timeout;
  xhttp.timeout=timeout_sec*1000; // ms
  xhttp.open(method,url,true);
  var body="";
  if (method=="post") // method is "get" or "post"
  {
    params["csrf_token"]=document.getElementById("csrf_token").innerText;
    var form_data=new FormData();
    for (var key in params)
    {
      form_data.append(key,params[key]);
    }
    var inputs=document.querySelectorAll("[type=file]");
    for (var i=0;i<inputs.length;i++)
    {
      var input=inputs[i];
      var file=input.files[0];
      if (file)
      {
        form_data.append(input.name,file);
      }
    }
    body=form_data;
  }
  xhttp.send(body);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function remove_url_param(key,url)
{
  var url_parts=url.split("?");
  var result=url_parts[0];
  if (url_parts.length>1)
  {
    var query_string=url_parts[1];
    var params=query_string.split("&");
    for (var i=params.length-1;i>=0;i--)
    {
      var param_parts=params[i].split("=");
      if (param_parts[0]===key)
      {
        params.splice(i,1);
      }
    }
    var params_str=params.join("&");
    if (params_str!="")
    {
      params_str="?"+params_str;
    }
    result=result+params_str;
  }
  return result;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function webdb_get_position(e)
{
  var r=e.getBoundingClientRect();
  x_pos=r.left+window.scrollX;
  y_pos=r.top+window.scrollY;
  return {x: x_pos,y: y_pos};
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

var default_ajax_timeout_sec=30;

if (window.addEventListener)
{
  window.addEventListener("load",page_load);
}
else
{
  window.attachEvent("onload",page_load);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
