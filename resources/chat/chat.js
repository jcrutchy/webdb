
/////////////////////////////////////////////////////////////////////////////////////////////////////

function open_chat(page_id,record_id)
{
  document.getElementById("chat_background").style.display="block";
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function close_chat()
{
  document.getElementById("chat_background").style.display="none";
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function page_load()
{
  ajax_url_update=document.getElementById("ajax_url_update").innerHTML;
  ajax_url_register_channel=document.getElementById("ajax_url_register_channel").innerHTML;
  update_interval_seconds=document.getElementById("update_interval_seconds").innerHTML;
  message_update(true);
  document.getElementById("message_input").disabled=false;
  document.getElementById("message_input_button").disabled=false;
  document.getElementById("message_input").focus();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function set_update_timeout()
{
  update_timeout=setTimeout(message_update,update_interval_seconds*1000);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function message_send()
{
  var message_input=document.getElementById("message_input");
  var message_value=message_input.value;
  if (message_value=="")
  {
    return;
  }
  clearTimeout(update_message_scroll);
  clearTimeout(update_timeout);
  var params={"message":message_value};
  show_update_status();
  ajax(ajax_url_update,"post",message_update_load,message_update_error,message_update_timeout,params);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function show_update_status()
{
  document.getElementById("update_status").style.visibility="visible";
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function register_channel(channel_name,channel_topic)
{
  clearTimeout(update_message_scroll);
  clearTimeout(update_timeout);
  var params={"channel_name":channel_name,"channel_topic":channel_topic};
  show_update_status();
  ajax(ajax_url_register_channel,"post",register_channel_load,register_channel_error,register_channel_timeout,params);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function register_channel_load()
{
  var data=get_ajax_load_data(this);
  if (data===false)
  {
    return;
  }
  if (data.hasOwnProperty("redirect_url")==true)
  {
    window.location=data.redirect_url;
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function register_channel_error()
{
  custom_alert("register_channel_error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function register_channel_timeout()
{
  custom_alert("register_channel_timeout");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function join_channel(channel_name)
{
  var url=document.getElementById("url_channel").innerHTML;
  url+=encodeURIComponent(channel_name);
  window.location=url;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function localize_server_timestamps()
{
  var items=document.querySelectorAll("span.server_timestamp");
  for (var i=0;i<items.length;i++)
  {
    var item=items[i];
    item.outerHTML=iso_to_formatted_date(item.innerHTML);
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function message_update(init=false)
{
  show_update_status();
  var url=ajax_url_update;
  if (init==true)
  {
    url+="&break";
  }
  ajax(url,"get",message_update_load,message_update_error,message_update_timeout);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function message_update_load()
{
  var data=get_ajax_load_data(this);
  if (data===false)
  {
    return;
  }
  if (data.hasOwnProperty("redirect_url")==true)
  {
    window.location=data.redirect_url;
  }
  if (data.hasOwnProperty("clear_input")==true)
  {
    var message_input=document.getElementById("message_input");
    if (message_input.value!="")
    {
      message_input.value="";
      message_input.focus();
    }
  }
  if (data.hasOwnProperty("channel_name")==true)
  {
    document.getElementById("channel_name").innerHTML=data.channel_name;
  }
  if (data.hasOwnProperty("channel_topic")==true)
  {
    document.getElementById("channel_topic").innerHTML=data.channel_topic;
  }
  if (data.hasOwnProperty("channels")==true)
  {
    document.getElementById("channels_div").innerHTML=data.channels;
  }
  if (data.hasOwnProperty("users")==true)
  {
    document.getElementById("users_div").innerHTML=data.users;
  }
  if (data.hasOwnProperty("nicks")==true)
  {
    user_nicks=data.nicks;
  }
  if (data.hasOwnProperty("message_delta")==true)
  {
    if (data.message_delta.length>0)
    {
      document.getElementById("messages_table").insertAdjacentHTML("beforeend",data.message_delta);
      localize_server_timestamps();
    }
  }
  var messages_scroll=document.getElementById("messages_scroll");
  if ((messages_scroll.scrollHeight>messages_scroll.clientHeight) && (scroll_anchored==false))
  {
    setTimeout(update_message_scroll,50);
  }
  else
  {
    set_update_timeout();
  }
  document.getElementById("update_status").style.visibility="hidden";
  if (data.hasOwnProperty("ding_file")==true)
  {
    var audio=new Audio(data.ding_file);
    audio.play();
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function message_update_error()
{
  custom_alert("message_update_error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function message_update_timeout()
{
  custom_alert("message_update_timeout");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function update_message_scroll()
{
  var messages_scroll=document.getElementById("messages_scroll");
  messages_scroll.scrollTop=messages_scroll.scrollHeight;
  scroll_anchored=true;
  set_update_timeout();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function body_mousedown(event)
{
  if (event.button===0)
  {
    mousedown_left=true;
  }
  if (splitter_element!==false)
  {
    cancel_event(event);
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function body_mouseup(event)
{
  if (event.button===0)
  {
    mousedown_left=false;
    if (splitter_element!==false)
    {
      var func_name=splitter_element.id+"_mouseup";
      if (window[func_name])
      {
        window[func_name](event);
      }
      splitter_element=false;
    }
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function body_mousemove(event)
{
  if ((event.button==0) && (splitter_element!==false))
  {
    var func_name=splitter_element.id+"_mousemove";
    if (window[func_name])
    {
      window[func_name](event);
    }
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function window_resize(event)
{
  if (typeof splitter_left_mousemove==="function")
  {
    var fake_event={clientX:Math.round(0.25*window.innerWidth)};
    splitter_left_mousemove(fake_event);
  }
  if (typeof splitter_right_mousemove==="function")
  {
    var fake_event={clientX:Math.round(0.75*window.innerWidth)};
    splitter_right_mousemove(fake_event);
  }
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

function message_input_keydown(event)
{
  last_key_code=event.keyCode;
  if (event.keyCode==9)
  {
    event.preventDefault();
    var message_input=document.getElementById("message_input");
    var message=message_input.value;
    if (message=="")
    {
      return false;
    }
    var parts=message.split(" ");
    var part=parts.pop();
    for (var i=0;i<user_nicks.length;i++)
    {
      var user_nick=user_nicks[i];
      if (user_nick.startsWith(part)==true)
      {
        message_input.value=parts.join(" ");
        if (message_input.value.length>0)
        {
          message_input.value+=" ";
        }
        message_input.value+=user_nick;
        message_input.selectionStart=message_input.value.length;
        message_input.selectionEnd=message_input.value.length;
      }
    }
    return false;
  }
  if (event.keyCode==13)
  {
    message_send();
    return false;
  }
  return true;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

var update_timeout=false;
var update_interval_seconds=20;
var scroll_anchored=false;
var ajax_url_update=false;
var mousedown_left=false;
var last_key_code=false;
var user_nicks=[];

/*if (window.addEventListener)
{
  window.addEventListener("load",page_load);
  window.addEventListener("resize",window_resize);
  window.addEventListener("mousedown",body_mousedown);
  window.addEventListener("mouseup",body_mouseup);
  window.addEventListener("mousemove",body_mousemove);
}
else
{
  window.attachEvent("onload",page_load);
  window.attachEvent("onresize",window_resize);
  window.attachEvent("mousedown",body_mousedown);
  window.attachEvent("mouseup",body_mouseup);
  window.attachEvent("mousemove",body_mousemove);
}*/

/////////////////////////////////////////////////////////////////////////////////////////////////////
