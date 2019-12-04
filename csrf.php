<?php

namespace webdb\csrf;

#####################################################################################################

function invalid_csrf_token()
{
  return "*";
}

#####################################################################################################

function generate_csrf_token()
{
  global $settings;
  if (\webdb\cli\is_cli_mode()==true)
  {
    return;
  }
  $token=false;
  if (isset($_COOKIE[$settings["csrf_cookie"]])==true)
  {
    $cookie=$_COOKIE[$settings["csrf_cookie"]];
    if (($cookie<>"") and (strtolower($cookie)<>"deleted"))
    {
      if (isset($settings["user_record"])==true)
      {
        $delta=microtime(true)-$settings["user_record"]["csrf_token_time"];
        if ($delta<(24*60*60))
        {
          $token=$settings["user_record"]["csrf_token"];
        }
        else
        {
          \webdb\csrf\update_csrf_token($settings["user_record"],\webdb\csrf\invalid_csrf_token());
        }
      }
    }
  }
  if ($token===false)
  {
    $token=\webdb\users\crypto_random_key();
    $hash=\webdb\users\webdb_password_hash($token);
    \webdb\utils\webdb_setcookie("csrf_cookie",$hash,0);
    if (isset($settings["user_record"])==true)
    {
      \webdb\csrf\update_csrf_token($settings["user_record"],$token);
    }
    \webdb\users\auth_log(false,"GENERATE_CSRF_TOKEN","generated csrf token for ".$_SERVER["REMOTE_ADDR"]);
  }
  $settings["csrf_token"]=$token;
}

#####################################################################################################

function update_csrf_token($user_record,$new_token)
{
  global $settings;
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  $value_items=array();
  $value_items["csrf_token"]=$new_token;
  if ($new_token<>"")
  {
    $value_items["csrf_token_time"]=microtime(true);
  }
  else
  {
    $value_items["csrf_token_time"]=0;
  }
  $settings["sql_check_post_params_override"]=true;
  \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
}

#####################################################################################################

function check_csrf_token()
{
  global $settings;
  $csrf_ok=false;
  if (count($_POST)==0)
  {
    $csrf_ok=true;
  }
  if (\webdb\cli\is_cli_mode()==true)
  {
    $csrf_ok=true;
  }
  $referer="";
  if (isset($_SERVER["HTTP_REFERER"])==true)
  {
    $referer=$_SERVER["HTTP_REFERER"];
  }
  if ((isset($_POST["csrf_token"])==true) and (isset($_COOKIE[$settings["csrf_cookie"]])==true))
  {
    if ($_POST["csrf_token"]<>"*")
    {
      if (password_verify($_POST["csrf_token"],$_COOKIE[$settings["csrf_cookie"]])==true)
      {
        \webdb\users\auth_log(false,"VALID_CSRF_TOKEN","valid csrf token from ".$_SERVER["REMOTE_ADDR"]);
        $csrf_ok=true;
      }
      else
      {
        \webdb\users\auth_log(false,"INVALID_CSRF_TOKEN_1","invalid csrf token from ".$_SERVER["REMOTE_ADDR"]." [referer=".$referer."]");
      }
    }
    else
    {
      \webdb\users\auth_log(false,"INVALID_CSRF_TOKEN_2","invalid csrf token from ".$_SERVER["REMOTE_ADDR"]." [referer=".$referer."]");
    }
  }
  if ($csrf_ok==false)
  {
    \webdb\users\auth_log(false,"INVALID_CSRF_TOKEN_3","invalid csrf token from ".$_SERVER["REMOTE_ADDR"]." [referer=".$referer."]");
    \webdb\utils\system_message("csrf error");
  }
}

#####################################################################################################

function fill_csrf_token($buffer)
{
  global $settings;
  if (isset($settings["csrf_token"])==false)
  {
    $settings["csrf_token"]=false;
    if (isset($_POST["csrf_token"])==true)
    {
      $settings["csrf_token"]=$_POST["csrf_token"];
    }
  }
  if ($settings["csrf_token"]===false)
  {
    return $buffer;
    return "error: no csrf token found";
  }
  else
  {
    return str_replace("%%csrf_token%%",$settings["csrf_token"],$buffer);
  }
}

#####################################################################################################
