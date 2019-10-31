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
  $sql_params["username"]=trim(strtolower($username));
  $records=\webdb\sql\file_fetch_prepare("user_get_by_username",$sql_params);
  if (count($records)<>1)
  {
    \webdb\users\webdb_unsetcookie("login_cookie");
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
  $value_items["login_cookie"]="*"; # disable login with cookie
  \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
  \webdb\users\webdb_unsetcookie("login_cookie");
  if ($message!==false)
  {
    \webdb\utils\show_message($message);
  }
}

#####################################################################################################

function login_lockout($user_record)
{
  global $settings;
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  $value_items=array();
  $value_items["pw_hash"]="*"; # disable login with password
  $value_items["login_cookie"]="*"; # disable login with cookie
  \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
  \webdb\users\webdb_unsetcookie("login_cookie");
  \webdb\utils\show_message(\webdb\utils\template_fill("lockout_error"));
}

#####################################################################################################

function check_admin($user_record)
{
  global $settings;
  if ($user_record["username"]=="admin")
  {
    if (in_array($_SERVER["REMOTE_ADDR"],$settings["admin_remote_address_whitelist"])==false)
    {
      \webdb\users\login_failure($user_record,"error: admin login not permitted from this address [".$_SERVER["REMOTE_ADDR"]."]");
    }
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
    $login_username=trim(strtolower($_POST["login_username"]));
    \webdb\users\webdb_setcookie("username_cookie",$login_username);
    $login_form_params["default_username"]=$login_username;
    $user_record=\webdb\users\get_user_record($login_username);
    if ($user_record["pw_reset_key"]<>"")
    {
      \webdb\users\cancel_password_reset($user_record);
    }
    if ($user_record["failed_login_count"]>$settings["max_login_attempts"])
    {
      \webdb\users\login_lockout($user_record);
    }
    if (strlen($_POST["login_password"])>$settings["max_password_length"])
    {
      \webdb\users\login_failure($user_record,"error: password is too long");
    }
    \webdb\users\check_admin($user_record);
    if ($user_record["pw_hash"]<>"*")
    {
      if (password_verify($_POST["login_password"],$user_record["pw_hash"])==false)
      {
        \webdb\users\login_failure($user_record,"error: incorrect password");
      }
      \webdb\users\initialise_login_cookie($user_record["user_id"]);
      $settings["user_record"]=$user_record;
      \webdb\utils\redirect($settings["app_web_index"]);
    }
  }
  elseif ((isset($_COOKIE[$settings["login_cookie"]])==true) and (isset($_COOKIE[$settings["username_cookie"]])==true))
  {
    $user_record=\webdb\users\get_user_record($_COOKIE[$settings["username_cookie"]]);
    if ($user_record["pw_reset_key"]<>"")
    {
      \webdb\users\cancel_password_reset($user_record);
    }
    if ((\webdb\users\remote_address_changed($user_record)==false) and ($settings["user_agent"]==$user_record["user_agent"]))
    {
      if ($user_record["failed_login_count"]>$settings["max_login_attempts"])
      {
        \webdb\users\login_lockout($user_record);
      }
      \webdb\users\check_admin($user_record);
      if ($user_record["login_cookie"]<>"*")
      {
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
          if ($user_record["pw_change"]==1)
          {
            \webdb\users\change_password();
          }
          return;
        }
        else
        {
          login_failure($user_record,"invalid login cookie");
        }
      }
    }
  }
  \webdb\users\webdb_unsetcookie("login_cookie");
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

function initialise_login_cookie($user_id)
{
  global $settings;
  $where_items=array();
  $where_items["user_id"]=$user_id;
  $value_items=array();
  $value_items["pw_login_time"]=microtime(true);
  $value_items["user_agent"]=$settings["user_agent"];
  $value_items["remote_address"]=$_SERVER["REMOTE_ADDR"];
  $value_items["failed_login_count"]=0;
  $crypto_strong=true;
  $key=base64_encode(openssl_random_pseudo_bytes(30,$crypto_strong));
  $options=array();
  $options["cost"]=$settings["password_bcrypt_cost"];
  if ($user_id==1) # admin
  {
    $options["cost"]=$settings["admin_password_bcrypt_cost"];
  }
  $cookie=password_hash($key,PASSWORD_BCRYPT,$options);
  $value_items["login_cookie"]=$cookie;
  \webdb\users\webdb_setcookie("login_cookie",$key);
  \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
}

#####################################################################################################

function logout()
{
  global $settings;
  \webdb\users\webdb_unsetcookie("login_cookie");
  $url=$settings["app_web_index"];
  \webdb\utils\redirect($url,false);
}

#####################################################################################################

function update_user_stub($form_name,$value_items,$form_config)
{
  $value_items["username"]=trim(strtolower($value_items["username"]));
  return false;
}

#####################################################################################################

function insert_user_stub($form_name,$id,$where_items,$value_items,$form_config)
{
  $value_items["username"]=trim(strtolower($value_items["username"]));
  return false;
}

#####################################################################################################

function remote_address_changed($user_record) # allow right/lowest octet to change (ipv4 dhcp subnet); no ipv6 subnet changes allowed for
{
  if ($user_record["remote_address"]=="")
  {
    return true;
  }
  if ($_SERVER["REMOTE_ADDR"]=="")
  {
    return true;
  }
  if ($_SERVER["REMOTE_ADDR"]==$user_record["remote_address"])
  {
    return false;
  }
  $octets_d=explode(".",$user_record["remote_address"]);
  $octets_r=explode(".",$_SERVER["REMOTE_ADDR"]);
  if (count($octets_d)<>4)
  {
    return true;
  }
  if (count($octets_r)<>4)
  {
    return true;
  }
  if (($octets_d[0]==$octets_r[0]) and ($octets_d[1]==$octets_r[1]) and ($octets_d[2]==$octets_r[2]))
  {
    return false;
  }
  return true;
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
    \webdb\users\webdb_unsetcookie("login_cookie");
    \webdb\utils\show_message("error: invalid password reset key");
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
  if ($user_record["username"]=="admin")
  {
    $options["cost"]=$settings["admin_password_bcrypt_cost"];
  }
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
  \webdb\utils\send_email($user_record["email"],"",$settings["app_name"]." password reset",$message,$settings["server_email_from"],$settings["server_email_reply_to"],$settings["server_email_bounce_to"]);
  \webdb\users\webdb_unsetcookie("login_cookie");
  $message=\webdb\utils\template_fill("password_reset_valid_to_message",$msg_params);
  \webdb\utils\show_message($message);
}

#####################################################################################################

function webdb_setcookie($setting_key,$value,$max_age=false)
{
  # Set-Cookie: webdb_username=admin; expires=Fri, 30-Oct-2020 04:19:38 GMT; Max-Age=31536000; path=/; domain=192.168.43.50; HttpOnly
  # Set-Cookie: webdb_login=B8sfv0erO5v%2F3uVjzU4IgIdlVams8X1UcxyCoXd0; expires=Fri, 30-Oct-2020 04:19:39 GMT; Max-Age=31536000; path=/; domain=192.168.43.50; HttpOnly
  global $settings;
  if ($max_age===false)
  {
    $max_age=$settings["max_cookie_age"];
  }
  $expiry=time()+$max_age;
  $params=array();
  $params["cookie_name"]=$settings[$setting_key];
  $params["value"]=$value;
  $params["expires"]=date("D, d M Y H:i:s \G\M\T",$expiry);
  $params["max_age"]=$max_age;
  $params["domain"]=$_SERVER["HTTP_HOST"];
  header(trim(\webdb\utils\template_fill("cookie_header",$params)));
  #setcookie($settings[$setting_key],$value,$expiry,"/",$_SERVER["HTTP_HOST"],false,true);
}

#####################################################################################################

function webdb_unsetcookie($setting_key)
{
  # Set-Cookie: webdb_login=deleted; expires=Thu, 01-Jan-1970 00:00:01 GMT; Max-Age=0; path=/
  #webdb_setcookie($setting_key,"deleted",-1);
  global $settings;
  setcookie($settings[$setting_key],null,-1,"/");
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
      \webdb\users\webdb_unsetcookie("login_cookie");
      \webdb\utils\show_message("error: new passwords do not match");
    }
    if (in_array($pw_new,$settings["prohibited_passwords"])==true)
    {
      \webdb\users\webdb_unsetcookie("login_cookie");
      \webdb\utils\show_message("error: cannot use any of the following for your new password: ".implode(" ",$settings["prohibited_passwords"]));
    }
    if (strlen($pw_new)<$settings["min_password_length"])
    {
      \webdb\users\webdb_unsetcookie("login_cookie");
      \webdb\utils\show_message("error: new password must be at least ".$settings["min_password_length"]." characters");
    }
    if (strlen($pw_new)>$settings["max_password_length"])
    {
      \webdb\users\webdb_unsetcookie("login_cookie");
      \webdb\utils\show_message("error: a password of more than ".$settings["max_password_length"]." characters, while commendable, is considered a bit much. please try something shorter");
    }
    if ($pw_new==$pw_old)
    {
      \webdb\users\webdb_unsetcookie("login_cookie");
      \webdb\utils\show_message("error: new password cannot be the same as your old password");
    }
    $user_record=\webdb\users\get_user_record($_POST["login_username"]);
    if (password_verify($pw_old,$user_record["pw_hash"])==false)
    {
      \webdb\users\webdb_unsetcookie("login_cookie");
      \webdb\utils\show_message("error: old password is incorrect");
    }
    $options=array();
    $options["cost"]=$settings["password_bcrypt_cost"];
    if ($user_record["username"]=="admin")
    {
      $options["cost"]=$settings["admin_password_bcrypt_cost"];
    }
    $value_items=array();
    $value_items["pw_hash"]=password_hash($pw_new,PASSWORD_BCRYPT,$options);
    $value_items["pw_change"]=0;
    $where_items=array();
    $where_items["user_id"]=$user_record["user_id"];
    \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
    \webdb\users\cancel_password_reset($user_record);
    \webdb\users\initialise_login_cookie($user_record["user_id"]);
    $settings["user_record"]=$user_record;
    \webdb\utils\redirect($settings["app_web_index"]);
  }
  $change_password_params=array();
  $change_password_params["login_script_modified"]=\webdb\utils\resource_modified_timestamp("login.js");
  $change_password_params["login_styles_modified"]=\webdb\utils\resource_modified_timestamp("login.css");
  if ($password_reset_user===false)
  {
    $change_password_params["old_password_default"]="";
    $change_password_params["old_password_display"]="table-row";
    if (isset($settings["user_record"])==false)
    {
      \webdb\users\login();
    }
    $change_password_params["login_username"]=$settings["user_record"]["username"];
  }
  else
  {
    # from password reset
    $value_items=array();
    $crypto_strong=true;
    $temp_password=base64_encode(openssl_random_pseudo_bytes(30,$crypto_strong));
    $options=array();
    $options["cost"]=$settings["password_bcrypt_cost"];
    if ($password_reset_user["username"]=="admin")
    {
      $options["cost"]=$settings["admin_password_bcrypt_cost"];
    }
    $value_items["pw_hash"]=password_hash($temp_password,PASSWORD_BCRYPT,$options);
    $value_items["pw_login_time"]=microtime(true);
    $value_items["user_agent"]=$settings["user_agent"];
    $value_items["remote_address"]=$_SERVER["REMOTE_ADDR"];
    $value_items["failed_login_count"]=0;
    $where_items=array();
    $where_items["user_id"]=$password_reset_user["user_id"];
    \webdb\sql\sql_update($value_items,$where_items,"users","webdb",true);
    $change_password_params["old_password_default"]=$temp_password;
    $change_password_params["old_password_display"]="none";
    $change_password_params["login_username"]=$password_reset_user["username"];
    $settings["user_record"]=$password_reset_user;
  }
  $content=\webdb\utils\template_fill("change_password",$change_password_params);
  \webdb\utils\output_page($content,"Change Password");
}

#####################################################################################################
