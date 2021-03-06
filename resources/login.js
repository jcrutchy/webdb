
/////////////////////////////////////////////////////////////////////////////////////////////////////

function login_password_keypress(event)
{
  if (event.keyCode==13)
  {
    var login_submit=document.getElementById("login_submit");
    login_submit.click();
    login_submit.disabled=true;
    return false;
  }
  login_check_caps(event);
  return true;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function login_check_caps(event)
{
  if (event.keyCode)
  {
    key_code=event.keyCode;
  }
  else
  {
    key_code=event.which;
  }
  shift_key=false;
  if (event.shiftKey)
  {
    shift_key=event.shiftKey;
  }
  if ((key_code>=65 && key_code<=90) && !shift_key)
  {
    document.getElementById("caps_lock_warning").style.visibility="visible";
  }
  else
  {
    document.getElementById("caps_lock_warning").style.visibility="hidden";
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function login_show_password(id="login_password")
{
  if (document.getElementById("show_password_check").checked==true)
  {
    document.getElementById(id).type="text";
  }
  else
  {
    document.getElementById(id).type="password";
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
