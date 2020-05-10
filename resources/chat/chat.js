
/////////////////////////////////////////////////////////////////////////////////////////////////////

function open_chat(page_id,record_id)
{
  var chat_button=document.getElementById("chat_button");
  chat_button.style.color="#555";
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
  document.getElementById("update_status_1").style.visibility="visible";
  document.getElementById("update_status_2").style.visibility="visible";
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function localize_server_timestamps()
{
  var items=document.querySelectorAll("span.server_timestamp");
  for (var i=0;i<items.length;i++)
  {
    var item=items[i];
    item.outerHTML=iso_to_chat_timestamp(item.innerHTML);
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function iso_to_chat_timestamp(iso_date)
{
  var date=new Date(iso_date);
  return format_date(date,document.getElementById("chat_timestamp_format").innerHTML);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function message_update(init=false)
{
  show_update_status();
  var url=ajax_url_update;
  if (init==true)
  {
    url+="&chat_break";
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
  if (data.hasOwnProperty("channel_topic")==true)
  {
    document.getElementById("channel_topic").innerHTML=data.channel_topic;
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
      if (document.getElementById("chat_background").style.display!="block")
      {
        var chat_button=document.getElementById("chat_button");
        if (data.chat_break==false)
        {
          chat_button.style.color="red";
        }
        else
        {
          chat_button.style.color="green";
        }
      }
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
  document.getElementById("update_status_1").style.visibility="hidden";
  document.getElementById("update_status_2").style.visibility="hidden";
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
var scroll_anchored=false;
var last_key_code=false;
var user_nicks=[];

if (window.addEventListener)
{
  window.addEventListener("load",page_load);
}
else
{
  window.attachEvent("onload",page_load);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
