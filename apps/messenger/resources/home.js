
/////////////////////////////////////////////////////////////////////////////////////////////////////

function page_load()
{
  ajax_url_update=document.getElementById("ajax_url_update").innerHTML;
  set_update_timeout();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function set_update_timeout()
{
  update_timeout=setTimeout(message_update,5000); // 5 sec
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function message_send()
{
  var message_input=document.getElementById("message_input");
  var message=message_input.value;
  if (message=="")
  {
    return;
  }
  message_input.value="";
  var body="message="+encodeURIComponent(message);
  ajax(ajax_url_update,"post",message_update_load,message_update_error,message_update_timeout,body);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function message_update()
{
  var url=document.getElementById("ajax_url_update").innerHTML;
  ajax(ajax_url_update,"get",message_update_load,message_update_error,message_update_timeout);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function message_update_load()
{
  try
  {
    data=JSON.parse(this.responseText);
  }
  catch (e)
  {
    custom_alert(this.responseText);
    return;
  }
  if (data.hasOwnProperty("error")==true)
  {
    custom_alert(data.error);
    return;
  }
  if (data.hasOwnProperty("message_delta")==true)
  {
    if (data.message_delta.length>0)
    {
      var messages_table=document.getElementById("messages_table");
      messages_table.insertAdjacentHTML("beforeend",data.message_delta);
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
    return;
  }
  message_update_error();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function message_update_error()
{
  custom_alert("message_update_error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function message_update_timeout()
{
  message_update_error();
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

function splitter_left_mousedown(event)
{
  if (event.button==0)
  {
    splitter_element=document.getElementById("splitter_left");
    splitter_element.style.backgroundColor="#CCCCCC";
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function splitter_right_mousedown(event)
{
  if (event.button==0)
  {
    splitter_element=document.getElementById("splitter_right");
    splitter_element.style.backgroundColor="#CCCCCC";
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function splitter_left_mouseup(event)
{
  if (event.button==0)
  {
    splitter_element.style.backgroundColor="#fdede8";
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function splitter_right_mouseup(event)
{
  if (event.button==0)
  {
    splitter_element.style.backgroundColor="#fdede8";
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function splitter_left_mousemove(event)
{
  var left_panel=document.getElementById("left_panel");
  var center_panel=document.getElementById("center_panel");
  var right_panel=document.getElementById("right_panel");
  var splitter_left=document.getElementById("splitter_left");
  var splitter_right=document.getElementById("splitter_right");
  if ((event.clientX>=150) && ((window.innerWidth-event.clientX)>=(right_panel.offsetWidth+300)))
  {
    splitter_left.style.left=(event.clientX-1)+"px";
    left_panel.style.width=(event.clientX-left_panel.offsetLeft-2)+"px";
    center_panel.style.left=(event.clientX+splitter_left.offsetWidth)+"px";
    center_panel.style.width=(splitter_right.offsetLeft-center_panel.offsetLeft-1)+"px";
  }
  if (window.innerWidth<640)
  {
    splitter_left.style.visibility="hidden";
  }
  else
  {
    splitter_left.style.visibility="visible";
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function splitter_right_mousemove(event)
{
  var left_panel=document.getElementById("left_panel");
  var center_panel=document.getElementById("center_panel");
  var right_panel=document.getElementById("right_panel");
  var splitter_right=document.getElementById("splitter_right");
  if (((window.innerWidth-event.clientX)>=150) && (event.clientX>=(left_panel.offsetWidth+300)))
  {
    splitter_right.style.left=event.clientX+"px";
    right_panel.style.width=(right_panel.offsetLeft+right_panel.offsetWidth-event.clientX-splitter_right.offsetWidth-1)+"px";
    center_panel.style.width=(event.clientX-center_panel.offsetLeft-1)+"px";
  }
  if (window.innerWidth<640)
  {
    splitter_right.style.visibility="hidden";
  }
  else
  {
    splitter_right.style.visibility="visible";
  }
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

var update_timeout=false;
var scroll_anchored=false;
var ajax_url_update=false;
var splitter_element=false;
var mousedown_left=false;

if (window.addEventListener)
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
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
