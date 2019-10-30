<?php

namespace webdb\users;

#####################################################################################################

function auth_dispatch()
{
  global $settings;
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
  \webdb\users\login();
  $settings["logged_in_username"]=$settings["user_record"]["username"];
}

#####################################################################################################

function obfuscate_hashes(&$data)
{
  $obfuscation_value="(obfuscated)";
  if (isset($data["login_cookie"])==true)
  {
    $data["login_cookie"]=$obfuscation_value;
  }
  if (isset($data["pw_hash"])==true)
  {
    $data["pw_hash"]=$obfuscation_value;
  }
  if (isset($data["pw_reset_key"])==true)
  {
    $data["pw_reset_key"]=$obfuscation_value;
  }
}

#####################################################################################################

function get_user_record($username)
{
  global $settings;
  $sql_params=array();
  $sql_params["username"]=$username;
  $records=\webdb\sql\file_fetch_prepare("user_get_by_username",$sql_params);
  if (count($records)<>1)
  {
    setcookie($settings["login_cookie"],null,-1,"/");
    \webdb\utils\show_message("error: username not found");
  }
  return $records[0];
}

#####################################################################################################

function get_user_groups($user_id)
{
  $sql_params=array();
  $sql_params["user_id"]=$user_id;
  $records=\webdb\sql\file_fetch_prepare("user_groups",$sql_params);
  return $records;
}

#####################################################################################################

function cancel_password_reset($user_record)
{
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  $value_items=array();
  $value_items["pw_reset_key"]="*";
  $value_items["pw_reset_time"]=0;
  \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
}

#####################################################################################################

function login_failure($user_record,$message)
{
  global $settings;
  if (empty($user_record["failed_login_count"])==true)
  {
    $user_record["failed_login_count"]=0;
  }
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  $value_items=array();
  $value_items["failed_login_count"]=$user_record["failed_login_count"]+1;
  $value_items["failed_login_time"]=microtime(true);
  \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
  setcookie($settings["login_cookie"],null,-1,"/");
  if ($message!==false)
  {
    \webdb\utils\show_message($message);
  }
}

#####################################################################################################

function login()
{
  global $settings;
  $login_form_params=array();
  $login_form_params["default_username"]="";
  if (isset($_COOKIE[$settings["username_cookie"]])==true)
  {
    $login_form_params["default_username"]=$_COOKIE[$settings["username_cookie"]];
  }
  $login_form_params["default_remember_me"]=\webdb\utils\template_fill("checkbox_checked");
  $login_form_params["login_script_modified"]=\webdb\utils\resource_modified_timestamp("login.js");
  $login_form_params["login_styles_modified"]=\webdb\utils\resource_modified_timestamp("login.css");
  if ((isset($_POST["login_username"])==true) and (isset($_POST["login_password"])==true))
  {
    $expiry=time()+$settings["max_cookie_age"];
    setcookie($settings["username_cookie"],$_POST["login_username"],$expiry,"/");
    $login_form_params["default_username"]=$_POST["login_username"];
    $user_record=\webdb\users\get_user_record($_POST["login_username"]);
    if ($user_record["failed_login_count"]>\webdb\index\MAX_LOGIN_ATTEMPTS)
    {
      $where_items=array();
      $where_items["user_id"]=$user_record["user_id"];
      $value_items=array();
      $value_items["pw_hash"]="*"; # disable login with password
      $value_items["login_cookie"]="*"; # disable login with cookie
      \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
      setcookie($settings["login_cookie"],null,-1,"/");
      \webdb\utils\show_message(\webdb\utils\template_fill("lockout_error"));
    }
    if (password_verify($_POST["login_password"],$user_record["pw_hash"])==false)
    {
      \webdb\users\login_failure($user_record,"error: incorrect password");
    }
    $where_items=array();
    $where_items["user_id"]=$user_record["user_id"];
    if ($user_record["pw_reset_key"]<>"")
    {
      \webdb\users\cancel_password_reset($user_record);
    }
    $value_items=array();
    $value_items["pw_login_time"]=microtime(true);
    $value_items["user_agent"]=$settings["user_agent"];
    $value_items["remote_address"]=$_SERVER["REMOTE_ADDR"];
    $value_items["failed_login_count"]=0;
    $crypto_strong=true;
    $key=base64_encode(openssl_random_pseudo_bytes(30,$crypto_strong));
    $options=array();
    $options["cost"]=$settings["password_bcrypt_cost"];
    $cookie=password_hash($key,PASSWORD_BCRYPT,$options);
    $value_items["login_cookie"]=$cookie;
    setcookie($settings["login_cookie"],$key,$expiry,"/");
    \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
    $settings["user_record"]=$user_record;
    \webdb\utils\redirect($settings["app_web_index"]);
  }
  elseif ((isset($_COOKIE[$settings["login_cookie"]])==true) and (isset($_COOKIE[$settings["username_cookie"]])==true))
  {
    $user_record=\webdb\users\get_user_record($_COOKIE[$settings["username_cookie"]]);
    if ($user_record["pw_reset_key"]<>"")
    {
      \webdb\users\cancel_password_reset($user_record);
    }
    if (password_verify($_COOKIE[$settings["login_cookie"]],$user_record["login_cookie"])==true)
    {
      $where_items=array();
      $where_items["user_id"]=$user_record["user_id"];
      $value_items=array();
      $value_items["cookie_login_time"]=microtime(true);
      $value_items["user_agent"]=$settings["user_agent"];
      $value_items["remote_address"]=$_SERVER["REMOTE_ADDR"];
      $value_items["failed_login_count"]=0;
      \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
      $settings["user_record"]=$user_record;
      return;
    }
    login_failure($user_record,false);
  }
  setcookie($settings["login_cookie"],null,-1,"/");
  $content=\webdb\utils\template_fill("login_form",$login_form_params);
  $buf=ob_get_contents();
  if (strlen($buf)<>0)
  {
    ob_end_clean(); # discard buffer
  }
  $settings["unauthenticated_content"]=true;
  \webdb\utils\output_page($content,"Login");
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
  $records=\webdb\sql\file_fetch_prepare("user_get_all_enabled");
  $validated=false;
  for ($i=0;$i<count($records);$i++)
  {
    $record=$records[$i];
    if ($record["pw_reset_key"]=="")
    {
      continue;
    }
    $time_delta=microtime(true)-$record["pw_reset_time"];
    if ($time_delta>$settings["password_reset_timeout"])
    {
      continue;
    }
    if (password_verify($_GET["reset_password"],$record["pw_reset_key"])==true)
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
  \webdb\users\cancel_password_reset($validated);
  \webdb\users\change_password($validated);
}

#####################################################################################################

function send_reset_password_message()
{
  global $settings;
  if (isset($_POST["login_username"])==false)
  {
    \webdb\utils\show_message("error: missing username");
  }
  $user_record=\webdb\users\get_user_record($_POST["login_username"]);
  $value_items=array();
  $crypto_strong=true;
  $key=base64_encode(openssl_random_pseudo_bytes(30,$crypto_strong));
  $options=array();
  $options["cost"]=$settings["password_bcrypt_cost"];
  $value_items["pw_reset_key"]=password_hash($key,PASSWORD_BCRYPT,$options);
  $value_items["pw_reset_time"]=microtime(true);
  $value_items["pw_hash"]="*"; # disable login with password
  $value_items["login_cookie"]="*"; # disable login with cookie
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
  $t=$value_items["pw_reset_time"]+$settings["password_reset_timeout"];
  $msg_params=array();
  $msg_params["key"]=urlencode($key);
  $msg_params["valid_to_time"]=date("g:i a",$t);
  $msg_params["valid_to_date"]=date("l, j F Y (T)",$t);
  $message=\webdb\utils\template_fill("password_reset_message",$msg_params);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  \webdb\utils\show_message($message); # TESTING (REMOVE/COMMENT OUT FOR PROD)
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  \webdb\utils\send_email($user_record["email"],$settings["app_name"]." password reset",$message);
  setcookie($settings["login_cookie"],null,-1,"/");
  $message=\webdb\utils\template_fill("password_reset_valid_to_message",$msg_params);
  \webdb\utils\show_message($message);
}

#####################################################################################################

function change_password($password_reset_user=false)
{
  global $settings;
  if (isset($_POST["change_password"])==true)
  {
    $pw_old=$_POST["change_password_old"];
    $pw_new=trim($_POST["change_password_new"]);
    $pw_new_conf=$_POST["change_password_new_confirm"];
    if ($pw_new<>$pw_new_conf)
    {
      setcookie($settings["login_cookie"],null,-1,"/");
      \webdb\utils\show_message("error: new passwords do not match");
    }
    if ($pw_new==\webdb\index\DEFAULT_PASSWORD)
    {
      setcookie($settings["login_cookie"],null,-1,"/");
      \webdb\utils\show_message("error: cannot use default password of 'password' for your new password");
    }
    if (strlen($pw_new)<\webdb\index\MIN_PASSWORD_LENGTH)
    {
      setcookie($settings["login_cookie"],null,-1,"/");
      \webdb\utils\show_message("error: new password must be at least ".\webdb\index\MIN_PASSWORD_LENGTH." characters");
    }
    if ($pw_new==$pw_old)
    {
      setcookie($settings["login_cookie"],null,-1,"/");
      \webdb\utils\show_message("error: new password cannot be the same as your old password");
    }
    $user_record=\webdb\users\get_user_record($_POST["login_username"]);
    if (password_verify($pw_old,$user_record["pw_hash"])==false)
    {
      setcookie($settings["login_cookie"],null,-1,"/");
      \webdb\utils\show_message("error: old password is incorrect");
    }
    $options=array();
    $options["cost"]=$settings["password_bcrypt_cost"];
    $value_items=array();
    $value_items["pw_hash"]=password_hash($pw_new,PASSWORD_BCRYPT,$options);
    $where_items=array();
    $where_items["user_id"]=$user_record["user_id"];
    \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
    \webdb\users\cancel_password_reset($user_record);
    \webdb\utils\redirect($settings["app_web_index"]);
  }
  $change_password_params=array();
  $change_password_params["login_script_modified"]=\webdb\utils\resource_modified_timestamp("login.js");
  $change_password_params["login_styles_modified"]=\webdb\utils\resource_modified_timestamp("login.css");
  if ($password_reset_user===false)
  {
    $change_password_params["old_password_default"]="";
    $change_password_params["old_password_display"]="table-row";
    $user_record=\webdb\users\login();
    $change_password_params["login_username"]=$user_record["username"];
  }
  else
  {
    $value_items=array();
    $crypto_strong=true;
    $temp_password=base64_encode(openssl_random_pseudo_bytes(30,$crypto_strong));
    $options=array();
    $options["cost"]=$settings["password_bcrypt_cost"];
    $value_items["pw_hash"]=password_hash($temp_password,PASSWORD_BCRYPT,$options);
    $where_items=array();
    $where_items["user_id"]=$password_reset_user["user_id"];
    \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
    $change_password_params["old_password_default"]=$temp_password;
    $change_password_params["old_password_display"]="none";
    $change_password_params["login_username"]=$password_reset_user["username"];
  }
  $content=\webdb\utils\template_fill("change_password",$change_password_params);
  \webdb\utils\output_page($content,"Change Password");
}

#####################################################################################################
