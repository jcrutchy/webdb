
/////////////////////////////////////////////////////////////////////////////////////////////////////

function register_turns()
{
  document.getElementById("turns_submit").disabled=true;
  var url=window.location+"&ajax=rps&field_name=turns&id="+document.getElementById("turns_text").value;
  document.getElementById("turns_text").value="";
  ajax(url,"get",register_turns_load,register_turns_error,register_turns_timeout);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function register_turns_load()
{
  var data=get_ajax_load_data(this);
  if (data===false)
  {
    return;
  }
  if (data.hasOwnProperty("results")==true)
  {
    document.getElementById("results").innerHTML=data.results;
  }
  document.getElementById("turns_submit").disabled=false;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function register_turns_error()
{
  custom_alert("register_turns_error");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function register_turns_timeout()
{
  custom_alert("register_turns_timeout");
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function turns_text_keydown(event)
{
  if (event.keyCode==13)
  {
    register_turns();
    return false;
  }
  return true;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
