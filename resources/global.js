/////////////////////////////////////////////////////////////////////////////////////////////////////

function page_load()
{
  update_online_user_list();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function update_online_user_list()
{
  var status=document.getElementById("status_message");
  if (status)
  {
    status.innerHTML="updating user list...";
  }
  var auth_username=document.getElementById("auth_user_name");
  if (auth_username===null)
  {
    return;
  }
  var url=window.location.href;
  if (url.includes("?")==true)
  {
    url+="&update_oul";
  }
  else
  {
    url+="?update_oul";
  }
  ajax(url,"get",update_online_user_list_load,update_online_user_list_error,update_online_user_list_timeout);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function update_online_user_list_load()
{
  var data=get_ajax_load_data(this);
  if (data===false)
  {
    return;
  }
  var status=document.getElementById("status_message");
  if (status)
  {
    status.innerHTML="";
  }
  if (data.hasOwnProperty("html")==true)
  {
    var oul=document.getElementById("online_user_list_content");
    if (oul)
    {
      oul.innerHTML=data.html;
    }
    setTimeout(update_online_user_list,oul_update_interval*1000);
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function update_online_user_list_error()
{
  var status=document.getElementById("status_message");
  if (status)
  {
    status.innerHTML="";
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function update_online_user_list_timeout()
{
  var status=document.getElementById("status_message");
  if (status)
  {
    status.innerHTML="";
  }
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

function cancel_event(event)
{
  if (event.stopPropagation)
  {
    event.stopPropagation();
  }
  if (event.preventDefault)
  {
    event.preventDefault();
  }
  event.cancelBubble=true;
  event.returnValue=false;
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

function get_base_url()
{
  var url=window.location;
  return url.protocol+"//"+url.host+"/"+url.pathname.split("/")[1];
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function get_ajax_load_data(response)
{
  var response_text=response.responseText;
  if (response_text==="cancel_ajax_load")
  {
    return false;
  }
  if (response_text==="redirect_to_login_form")
  {
    window.location.href=get_base_url();
    return false;
  }
  try
  {
    var data=JSON.parse(response_text);
  }
  catch (e)
  {
    custom_alert(response_text);
    return false;
  }
  if (data.hasOwnProperty("error")==true)
  {
    custom_alert(data.error);
    return false;
  }
  return data;
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

function darken_background(element,percent)
{
  var color=element.style.backgroundColor;
  color=color.replace(" ","");
  color=color.replace("rgb(","");
  color=color.replace(")","");
  color=color.split(",");
  var R=Number(color[0]);
  var G=Number(color[1]);
  var B=Number(color[2]);
  var d=Math.round(2.55*percent);
  R=Math.max(0,R-d);
  G=Math.max(0,G-d);
  B=Math.max(0,B-d);
  element.style.backgroundColor="rgb("+R+","+G+","+B+")";
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function lighten_background(element,percent)
{
  var color=element.style.backgroundColor;
  color=color.replace(" ","");
  color=color.replace("rgb(","");
  color=color.replace(")","");
  color=color.split(",");
  var R=Number(color[0]);
  var G=Number(color[1]);
  var B=Number(color[2]);
  var d=Math.round(2.55*percent);
  R=Math.min(255,R+d);
  G=Math.min(255,G+d);
  B=Math.min(255,B+d);
  element.style.backgroundColor="rgb("+R+","+G+","+B+")";
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function deg2rad(deg)
{
  return deg*Math.PI/180;
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
