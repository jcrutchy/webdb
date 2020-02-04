<?php

namespace webdb\csrf;

#####################################################################################################

function check_unauthenticated_csrf_token()
{
  global $settings;
  $settings["csrf_token"]="";
  if (\webdb\cli\is_cli_mode()==true)
  {
    return;
  }
  if ((isset($_COOKIE[$settings["csrf_cookie_auth"]])==true) and (isset($_POST["login_username"])==false))
  {
    return;
  }
  $csrf_ok=false;
  if (count($_POST)==0)
  {
    $csrf_ok=true;
  }
  elseif ((isset($_POST["csrf_token"])==true) and (isset($_COOKIE[$settings["csrf_cookie_unauth"]])==true))
  {
    $hash=$_POST["csrf_token"];
    $cookie=$_COOKIE[$settings["csrf_cookie_unauth"]];
    $token=$settings["csrf_hash_prefix"].$cookie;
    if (password_verify($token,$hash)==true)
    {
      \webdb\users\auth_log(false,"VALID_UNAUTHENTICATED_CSRF_TOKEN");
      $csrf_ok=true;
    }
    else
     {
      \webdb\users\auth_log(false,"INVALID_UNAUTHENTICATED_CSRF_TOKEN");
    }
  }
  if ($csrf_ok==false)
  {
    \webdb\utils\error_message(\webdb\utils\template_fill("csrf_error_unauth"));
  }
  $token=\webdb\users\crypto_random_key();
  $settings["csrf_header_unauth"]=$token;
  $token=$settings["csrf_hash_prefix"].$token;
  $settings["csrf_token"]=\webdb\users\webdb_password_hash($token);
}

#####################################################################################################

function check_authenticated_csrf_token()
{
  global $settings;
  $settings["csrf_token"]="";
  if (\webdb\cli\is_cli_mode()==true)
  {
    return;
  }
  unset($settings["csrf_header_unauth"]);
  $user_record=$settings["user_record"];
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  $csrf_ok=false;
  if (count($_POST)==0)
  {
    $csrf_ok=true;
  }
  $user_token_regen=false;
  $delta=0;
  if ($user_record["csrf_token_time"]>0)
  {
    $delta=time()-$user_record["csrf_token_time"];
  }
  if ($delta>$settings["max_csrf_token_age"])
  {
    $user_token_regen=true;
    \webdb\users\auth_log($user_record,"AUTHENTICATED_CSRF_TOKEN_EXCEEDS_MAX_AGE");
  }
  $token=$user_record["csrf_token"];
  if ($token=="")
  {
    $user_token_regen=true;
    \webdb\users\auth_log($user_record,"AUTHENTICATED_CSRF_TOKEN_EMPTY");
  }
  if ($user_token_regen==true)
  {
    $token=\webdb\users\crypto_random_key();
    $value_items=array();
    $value_items["csrf_token"]=$token;
    $value_items["csrf_token_time"]=time();
    $settings["sql_check_post_params_override"]=true;
    \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
    \webdb\users\auth_log($user_record,"GENERATE_AUTHENTICATED_CSRF_TOKEN");
    $settings["csrf_token"]=\webdb\users\webdb_password_hash($token);
  }
  if ((isset($_POST["csrf_token"])==true) and (isset($_COOKIE[$settings["csrf_cookie_auth"]])==true))
  {
    $hash=$_POST["csrf_token"];
    $cookie=$_COOKIE[$settings["csrf_cookie_auth"]];
    if ($hash<>$cookie)
    {
      \webdb\users\auth_log($user_record,"AUTHENTICATED_CSRF_HASH_MISMATCH");
    }
    if (password_verify($token,$hash)==true)
    {
      \webdb\users\auth_log($user_record,"VALID_AUTHENTICATED_CSRF_TOKEN");
      $settings["csrf_token"]=$hash;
      $csrf_ok=true;
    }
    else
    {
      \webdb\users\auth_log($user_record,"INVALID_AUTHENTICATED_CSRF_TOKEN");
    }
  }
  if ($csrf_ok==false)
  {
    \webdb\users\login_failure($user_record,\webdb\utils\template_fill("csrf_error_auth"));
  }
  else
  {
    if ($settings["csrf_token"]=="")
    {
      $settings["csrf_token"]=\webdb\users\webdb_password_hash($token);
    }
    $settings["csrf_header_auth"]=$settings["csrf_token"];
  }
}

#####################################################################################################

function fill_csrf_token($buffer)
{
  global $settings;
  if (isset($settings["csrf_header_unauth"])==true)
  {
    \webdb\utils\webdb_setcookie("csrf_cookie_unauth",$settings["csrf_header_unauth"],0);
    \webdb\utils\webdb_unsetcookie("csrf_cookie_auth");
  }
  elseif (isset($settings["csrf_header_auth"])==true)
  {
    \webdb\utils\webdb_setcookie("csrf_cookie_auth",$settings["csrf_header_auth"],0);
    \webdb\utils\webdb_unsetcookie("csrf_cookie_unauth");
  }
  else
  {
    \webdb\utils\webdb_unsetcookie("csrf_cookie_auth");
    \webdb\utils\webdb_unsetcookie("csrf_cookie_unauth");
    \webdb\users\unset_login_cookie();
  }
  if (isset($settings["csrf_token"])==false)
  {
    $settings["csrf_token"]="";
  }
  return str_replace("%%csrf_token%%",$settings["csrf_token"],$buffer);
}

#####################################################################################################

function unset_authenticated_csrf($redirect_on_cookie)
{
  global $settings;
  \webdb\utils\webdb_unsetcookie("csrf_cookie_auth");
  if ($redirect_on_cookie==true)
  {
    if (isset($_COOKIE[$settings["csrf_cookie_auth"]])==true)
    {
      \webdb\utils\redirect($settings["app_web_index"]);
    }
  }
  unset($settings["csrf_header_auth"]);
  unset($_COOKIE[$settings["csrf_cookie_auth"]]);
}

#####################################################################################################
