<?php

namespace webdb\users;

#####################################################################################################

function auth_dispatch()
{
  if (isset($_GET["logout"])==true)
  {
    \webdb\users\logout();
  }
  if (isset($_POST["send_reset_password"])==true)
  {
    \webdb\users\send_reset_password_message();
  }
  if (isset($_GET["reset_password"])==true)
  {
    \webdb\users\reset_password();
  }
  if (isset($_GET["change_password"])==true)
  {
    \webdb\users\change_password();
  }
  if (isset($_GET["user_admin"])==true)
  {
    \webdb\users\user_admin_page();
  }
  \webdb\users\login();
}

#####################################################################################################

function get_user_record($email)
{
  global $settings;
  $sql_params=array();
  $sql_params["email"]=$email;
  $records=\webdb\sql\file_fetch_prepare("user_get_by_email",$sql_params);
  if (count($records)<>1)
  {
    setcookie($settings["login_cookie"],null,-1,"/");
    \webdb\utils\show_message("error: email address not found");
  }
  return $records[0];
}

#####################################################################################################

function cancel_password_reset($user_record)
{
  global $settings;
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  $value_items=array();
  $value_items["pw_reset_key"]="";
  $value_items["pw_reset_time"]=\webdb\sql\zero_sql_timestamp();
  \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
}

#####################################################################################################

function login()
{
  global $settings;
  $login_form_params=array();
  $login_form_params["default_email"]="";
  if (isset($_COOKIE[$settings["email_cookie"]])==true)
  {
    $login_form_params["default_email"]=$_COOKIE[$settings["email_cookie"]];
  }
  $login_form_params["auth_error"]="";
  $login_form_params["default_remember_me"]=\webdb\utils\template_fill("checkbox_checked");
  $login_form_params["login_script_modified"]=\webdb\utils\webdb_resource_modified_timestamp("login.js");
  $login_form_params["login_styles_modified"]=\webdb\utils\webdb_resource_modified_timestamp("login.css");
  if ((isset($_POST["login_email"])==true) and (isset($_POST["login_password"])==true))
  {
    if (isset($_POST["remember_me"])==true)
    {
      setcookie($settings["email_cookie"],$_POST["login_email"],time()+$settings["max_cookie_age"],"/");
      $login_form_params["default_email"]=$_POST["login_email"];
    }
    else
    {
      setcookie($settings["email_cookie"],null,-1,"/");
      $login_form_params["default_remember_me"]="";
    }
    $user_record=\webdb\users\get_user_record($_POST["login_email"]);
    if (password_verify($_POST["login_password"],$user_record["pw_hash"])==false)
    {
      setcookie($settings["login_cookie"],null,-1,"/");
      \webdb\utils\show_message("error: incorrect password");
    }
    $settings["auth"]=$user_record;
    $where_items=array();
    $where_items["user_id"]=$user_record["user_id"];
    if ($user_record["pw_reset_key"]<>"")
    {
      \webdb\users\cancel_password_reset($user_record);
    }
    $value_items=array();
    if (isset($_POST["remember_me"])==true)
    {
      $crypto_strong=true;
      $key=base64_encode(openssl_random_pseudo_bytes(30,$crypto_strong));
      $options=array();
      $options["cost"]=13;
      $cookie=password_hash($_POST["login_email"].$key,PASSWORD_BCRYPT,$options);
      $value_items["login_cookie"]=$cookie;
      $expiry=microtime(true)+$settings["max_cookie_age"];
      setcookie($settings["login_cookie"],$key,$expiry,"/");
    }
    else
    {
      setcookie($settings["login_cookie"],null,-1,"/");
      $value_items["login_cookie"]="";
    }
    \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
    return;
  }
  elseif ((isset($_COOKIE[$settings["login_cookie"]])==true) and (isset($_COOKIE[$settings["email_cookie"]])==true))
  {
    $user_record=\webdb\users\get_user_record($_COOKIE[$settings["email_cookie"]]);
    if ($user_record["pw_reset_key"]<>"")
    {
      \webdb\users\cancel_password_reset($user_record);
    }
    if (password_verify($user_record["email"].$_COOKIE[$settings["login_cookie"]],$user_record["login_cookie"])==true)
    {
      return;
    }
  }
  setcookie($settings["login_cookie"],null,-1,"/");
  $page_params=array();
  $page_params["page_title"]="Login";
  $page_params["page_head"]="";
  $page_params["body_text"]=\webdb\utils\template_fill("login_form",$login_form_params);
  die(\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."page",$page_params));
}

#####################################################################################################

function logout()
{
  global $settings;
  setcookie($settings["login_cookie"],null,-1,"/");
  $url=$settings["app_web_index"];
  \webdb\utils\redirect($url,false);
}

#####################################################################################################

function reset_password()
{
  global $settings;
  $records=\webdb\sql\fetch_all_records("users","","","webdb");
  $validated=false;
  for ($i=0;$i<count($records);$i++)
  {
    $record=$records[$i];
    if ($record["enabled"]<>1)
    {
      continue;
    }
    if ($record["pw_reset_key"]=="")
    {
      continue;
    }
    $time_delta=microtime(true)-$record["pw_reset_time"];
    if ($time_delta>(24*60*60))
    {
      continue;
    }
    if (password_verify($record["email"].$_GET["reset_password"],$record["pw_reset_key"])==true)
    {
      $validated=$record;
      break;
    }
  }
  if ($validated===false)
  {
    setcookie($settings["login_cookie"],null,-1,"/");
    \webdb\utils\show_message("Invalid password reset key.");
  }
  cancel_password_reset($validated);
  change_password($validated);
}

#####################################################################################################

function send_reset_password_message()
{
  global $settings;
  if (isset($_POST["login_email"])==false)
  {
    \webdb\utils\show_message("error: missing email address");
  }
  $sql_params=array();
  $sql_params["email"]=$_POST["login_email"];
  $records=\webdb\sql\file_fetch_prepare("user_get_by_email",$sql_params);
  if (count($records)<>1)
  {
    setcookie($settings["login_cookie"],null,-1,"/");
    \webdb\utils\show_message("error: email address not found");
  }
  $user_record=$records[0];
  $value_items=array();
  $crypto_strong=true;
  $key=base64_encode(openssl_random_pseudo_bytes(30,$crypto_strong));
  $options=array();
  $options["cost"]=13;
  $value_items["pw_reset_key"]=password_hash($_POST["login_email"].$key,PASSWORD_BCRYPT,$options);
  $value_items["pw_reset_time"]=microtime(true);
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
  $msg_params=array();
  $msg_params["key"]=urlencode($key);
  $message=\webdb\utils\template_fill("password_reset_message",$msg_params);
  # DEBUG
  \webdb\utils\show_message($message);
  \webdb\utils\send_email($_POST["login_email"],$settings["app_name"]." password reset",$message);
  setcookie($settings["login_cookie"],null,-1,"/");
  \webdb\utils\show_message("Password reset link sent to registered email address. It will be valid for 24 hours.");
}

#####################################################################################################

function change_password($password_reset_user=false)
{
  die("change_password");
}

#####################################################################################################

function user_admin_page()
{
  die("user_admin_page");
}

#####################################################################################################
