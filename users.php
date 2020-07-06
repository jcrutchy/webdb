<?php

namespace webdb\users;

# TODO:
# $hash=sodium_crypto_pwhash_str($password,SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
# sodium_crypto_pwhash_str_verify($hash,$password)

#####################################################################################################

function auth_dispatch()
{
  global $settings;
  if (isset($_GET["logout"])==true)
  {
    \webdb\users\logout();
  }
  \webdb\csrf\check_unauthenticated_csrf_token();
  if (isset($_POST["send_reset_password"])==true) # user clicked reset password button on login form (unauthenticated)
  {
    \webdb\users\send_reset_password_message();
  }
  if (isset($_GET["reset_password"])==true) # user clicked emailed password reset link or change password button on reset password change form (unauthenticated)
  {
    \webdb\users\reset_password();
  }
  \webdb\users\login();
  \webdb\users\user_login_settings_set($settings["user_record"]);
  \webdb\csrf\check_authenticated_csrf_token();
  \webdb\utils\check_user_app_permission();
  if (isset($_GET["change_password"])==true)
  {
    \webdb\users\change_password();
  }
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
    \webdb\users\unset_login_cookie();
    \webdb\utils\error_message("error: username not found: ".htmlspecialchars($username));
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
  global $settings;
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  $value_items=array();
  $value_items["pw_reset_key"]="*";
  $value_items["pw_reset_time"]=0;
  $settings["sql_check_post_params_override"]=true;
  $settings["user_record"]=$user_record; # allow unauthenticated change to database
  \webdb\sql\sql_update($value_items,$where_items,"users",$settings["database_webdb"],true);
  unset($settings["user_record"]);
}

#####################################################################################################

function login_failure($user_record,$message)
{
  global $settings;
  if ($user_record["pw_reset_key"]<>"*")
  {
    \webdb\users\cancel_password_reset($user_record);
  }
  if (empty($user_record["failed_login_count"])==true)
  {
    $user_record["failed_login_count"]=0;
  }
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  $value_items=array();
  $value_items["failed_login_count"]=$user_record["failed_login_count"]+1;
  $value_items["failed_login_time"]=time();
  $value_items["csrf_token"]="";
  $value_items["csrf_token_time"]=null;
  $value_items["login_cookie"]="*"; # disable login with cookie
  $settings["sql_check_post_params_override"]=true;
  $settings["user_record"]=$user_record; # allow unauthenticated change to database
  \webdb\sql\sql_update($value_items,$where_items,"users",$settings["database_webdb"],true);
  unset($settings["user_record"]);
  \webdb\csrf\unset_authenticated_csrf(false);
  \webdb\users\unset_login_cookie();
  \webdb\users\auth_log($user_record,"LOGIN_FAILURE",$message);
  if ($value_items["failed_login_count"]>$settings["max_login_attempts"])
  {
    \webdb\users\login_lockout($user_record);
  }
  \webdb\utils\error_message($message);
}

#####################################################################################################

function auth_log($user_record,$status,$message="")
{
  global $settings;
  $username="<NOUSER>";
  if ($user_record===false)
  {
    $user_record=array();
    if (isset($settings["user_record"])==true)
    {
      $username=$settings["user_record"]["username"];
      $user_record=$settings["user_record"];
    }
  }
  else
  {
    $username=$user_record["username"];
  }
  $referer="";
  if (isset($_SERVER["HTTP_REFERER"])==true)
  {
    $referer=$_SERVER["HTTP_REFERER"];
  }
  $remote_address="";
  if (isset($_SERVER["REMOTE_ADDR"])==true)
  {
    $remote_address=$_SERVER["REMOTE_ADDR"];
  }
  \webdb\users\obfuscate_hashes($user_record);
  $content=date("Y-m-d H:i:s")."\tusername=".$username."\tstatus=".$status."\tremote_address=".$remote_address."\treferer=".$referer."\tuser_record=".json_encode($user_record)."\tmessage=".$message;
  $settings["logs"]["auth"][]=$content;
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
  $settings["sql_check_post_params_override"]=true;
  $settings["user_record"]=$user_record; # allow unauthenticated change to database
  \webdb\sql\sql_update($value_items,$where_items,"users",$settings["database_webdb"],true);
  unset($settings["user_record"]);
  \webdb\users\unset_login_cookie();
  \webdb\users\auth_log($user_record,"LOCKOUT","");
  \webdb\utils\error_message(\webdb\utils\template_fill("lockout_error"));
}

#####################################################################################################

function check_admin($user_record)
{
  global $settings;
  if ($user_record["username"]=="admin")
  {
    if (in_array($_SERVER["REMOTE_ADDR"],$settings["admin_remote_address_whitelist"])==false)
    {
      $params=array();
      $params["remote_address"]=$_SERVER["REMOTE_ADDR"];
      $msg=\webdb\utils\template_fill("admin_address_whitelist_error",$params);
      \webdb\users\login_failure($user_record,$msg);
    }
  }
}

#####################################################################################################

function unset_login_cookie()
{
  global $settings;
  if ($settings["login_cookie_unset"]==false)
  {
    \webdb\utils\webdb_unsetcookie("login_cookie");
    $settings["login_cookie_unset"]=true;
  }
}

#####################################################################################################

function login()
{
  global $settings;
  $login_form_params=array();
  $login_form_params["login_notice"]="";
  if ($settings["login_notice_template"]<>"")
  {
    $login_form_params["login_notice"]=\webdb\utils\template_fill($settings["login_notice_template"]);
  }
  $login_form_params["welcome_tip"]="";
  $login_form_params["default_username"]="";
  if (isset($_COOKIE[$settings["username_cookie"]])==true)
  {
    $login_form_params["default_username"]=$_COOKIE[$settings["username_cookie"]];
  }
  else
  {
    $login_form_params["welcome_tip"]=\webdb\utils\template_fill("welcome_tip");
  }
  $login_form_params["default_remember_me"]=\webdb\utils\template_fill("checkbox_checked");
  $login_form_params["login_script_modified"]=\webdb\utils\resource_modified_timestamp("login.js");
  $login_form_params["login_styles_modified"]=\webdb\utils\resource_modified_timestamp("login.css");
  if ((isset($_POST["login_username"])==true) and (isset($_POST["login_password"])==true))
  {
    $login_username=trim(strtolower($_POST["login_username"]));
    \webdb\utils\webdb_setcookie("username_cookie",$login_username);
    $login_form_params["default_username"]=$login_username;
    $user_record=\webdb\users\get_user_record($login_username);
    if ($user_record["pw_reset_key"]<>"*")
    {
      \webdb\users\cancel_password_reset($user_record);
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
      unset($_POST["login_password"]);
      $settings["user_record"]=$user_record;
      \webdb\users\auth_log($user_record,"PASSWORD_LOGIN","");
      \webdb\users\initialise_login_cookie($user_record);
      $target_url=$_POST["target_url"];
      if (($target_url<>"") and ($target_url<>$settings["app_web_root"]) and ($target_url<>$settings["app_web_index"]))
      {
        \webdb\utils\redirect($target_url);
      }
      \webdb\utils\redirect($settings["app_web_index"]);
    }
    else
    {
      \webdb\utils\error_message(\webdb\utils\template_fill("lockout_first_time_message"));
    }
  }
  elseif ((isset($_COOKIE[$settings["login_cookie"]])==true) and (isset($_COOKIE[$settings["username_cookie"]])==true))
  {
    $user_record=\webdb\users\get_user_record($_COOKIE[$settings["username_cookie"]]);
    if ($user_record["pw_reset_key"]<>"*")
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
      if (($user_record["login_cookie"]<>"*") and ($user_record["login_setcookie_time"]>0))
      {
        $delta=time()-$user_record["login_setcookie_time"];
        if ($delta>$settings["max_cookie_age"])
        {
          \webdb\users\login_failure($user_record,"login cookie exceeds max age");
        }
        if (password_verify($_COOKIE[$settings["login_cookie"]],$user_record["login_cookie"])==true)
        {
          $where_items=array();
          $where_items["user_id"]=$user_record["user_id"];
          $value_items=array();
          $value_items["cookie_login_time"]=time();
          $value_items["user_agent"]=$settings["user_agent"];
          $value_items["remote_address"]=$_SERVER["REMOTE_ADDR"];
          $value_items["failed_login_count"]=0;
          $settings["user_record"]=$user_record;
          $settings["sql_check_post_params_override"]=true;
          \webdb\sql\sql_update($value_items,$where_items,"users",$settings["database_webdb"],true);
          $settings["user_record"]=\webdb\users\get_user_record($user_record["username"]);
          \webdb\users\auth_log($user_record,"COOKIE_LOGIN","");
          if ($user_record["pw_change"]==1)
          {
            \webdb\users\change_password();
          }
          return;
        }
        else
        {
          \webdb\users\login_failure($user_record,"invalid login cookie");
        }
      }
    }
  }
  \webdb\csrf\unset_authenticated_csrf(true);
  \webdb\users\unset_login_cookie();
  $target_url=\webdb\utils\get_url();
  $login_form_params["target_url"]="";
  if (($target_url<>$settings["app_web_root"]) and ($target_url<>$settings["app_web_index"]))
  {
    $login_form_params["target_url"]=$target_url;
  }
  $content=\webdb\utils\template_fill("login_form",$login_form_params);
  $buffer=ob_get_contents();
  if (strlen($buffer)<>0)
  {
    ob_end_clean(); # discard buffer
  }
  $settings["unauthenticated_content"]=true;
  \webdb\users\auth_log(false,"LOGIN_FORM","");
  \webdb\utils\output_page($content,"Login");
}

#####################################################################################################

function user_login_settings_set($user_record)
{
  global $settings;
  $settings["user_record"]=$user_record;
  $settings["logged_in_username"]=$settings["user_record"]["username"];
  $settings["logged_in_user_id"]=$settings["user_record"]["user_id"];
  $user_id=$settings["user_record"]["user_id"];
  $settings["logged_in_user_groups"]=\webdb\users\get_user_groups($user_id);
}

#####################################################################################################

function user_login_settings_unset() # used for testing
{
  global $settings;
  unset($settings["user_record"]);
  unset($settings["logged_in_username"]);
  unset($settings["logged_in_user_id"]);
  unset($settings["logged_in_user_groups"]);
}

#####################################################################################################

function logged_in_user_in_group($group_name)
{
  global $settings;
  if (isset($settings["logged_in_user_groups"])==false)
  {
    return false;
  }
  for ($i=0;$i<count($settings["logged_in_user_groups"]);$i++)
  {
    if ($settings["logged_in_user_groups"][$i]["group_name"]==$group_name)
    {
      return true;
    }
  }
  return false;
}

#####################################################################################################

function crypto_random_key()
{
  $crypto_strong=true;
  return base64_encode(openssl_random_pseudo_bytes(30,$crypto_strong));
}

#####################################################################################################

function webdb_password_hash($password=false,$username=false)
{
  global $settings;
  if ($password===false)
  {
    $password=\webdb\users\crypto_random_key();
  }
  if ($username===false)
  {
    if (isset($settings["user_record"])==true)
    {
      $username=$settings["user_record"]["username"];
    }
  }
  $options=array();
  $options["cost"]=$settings["password_bcrypt_cost"];
  if ($username=="admin")
  {
    $options["cost"]=$settings["admin_password_bcrypt_cost"];
  }
  return password_hash($password,PASSWORD_BCRYPT,$options);
}

#####################################################################################################

function initialise_login_cookie(&$user_record)
{
  global $settings;
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  $value_items=array();
  $value_items["pw_login_time"]=time();
  $value_items["user_agent"]=$settings["user_agent"];
  $value_items["remote_address"]=$_SERVER["REMOTE_ADDR"];
  $value_items["failed_login_count"]=0;
  $key=\webdb\users\crypto_random_key();
  $value_items["login_cookie"]=\webdb\users\webdb_password_hash($key,$user_record["username"]);
  $value_items["login_setcookie_time"]=time();
  \webdb\utils\webdb_setcookie("login_cookie",$key);
  \webdb\users\auth_log($user_record,"LOGIN_COOKIE_INIT","");
  \webdb\sql\sql_update($value_items,$where_items,"users",$settings["database_webdb"],true);
}

#####################################################################################################

function logout()
{
  global $settings;
  \webdb\users\auth_log(false,"LOGOUT","");
  \webdb\users\unset_login_cookie();
  $url=$settings["app_web_index"];
  \webdb\utils\redirect($url,false);
}

#####################################################################################################

function update_user_stub($form_config,$event_params,$event_name)
{
  $event_params["value_items"]["username"]=trim(strtolower($event_params["value_items"]["username"]));
  return $event_params;
}

#####################################################################################################

function remote_address_listed($remote_address,$type) # $type = black|white
{
  global $settings;
  $fn=$settings["ip_".$type."list_file"];
  if (file_exists($fn)==false)
  {
    return false;
  }
  $data=trim(file_get_contents($fn));
  $lines=explode(PHP_EOL,$data);
  for ($i=0;$i<count($lines);$i++)
  {
    $line=trim($lines[$i]);
    if ($line=="")
    {
      continue;
    }
    if ($remote_address==$line)
    {
      return true;
    }
    $line=escapeshellarg($line);
    $address=escapeshellarg($remote_address);
    $cmd=$settings["webdb_root_path"]."sh".DIRECTORY_SEPARATOR."test_ip.sh ".$line." ".$address;
    $result=trim(shell_exec($cmd));
    if ($result==$remote_address)
    {
      return true;
    }
  }
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
  # warning: this function is run without authentication
  if (isset($_POST["reset_change_password"])==true)
  {
    if (isset($_COOKIE[$settings["username_cookie"]])==false)
    {
      \webdb\utils\error_message("error: username cookie not set");
    }
    $pw_old=$_POST["change_password_old"];
    $pw_new=trim($_POST["change_password_new"]);
    $pw_new_conf=$_POST["change_password_new_confirm"];
    \webdb\users\validate_new_password($pw_old,$pw_new,$pw_new_conf);
    $user_record=\webdb\users\get_user_record($_COOKIE[$settings["username_cookie"]]);
    if (password_verify($pw_old,$user_record["pw_hash"])==false)
    {
      \webdb\utils\error_message("error: old password is incorrect");
    }
    $value_items=array();
    $value_items["pw_hash"]=\webdb\users\webdb_password_hash($pw_new,$user_record["username"]);
    $value_items["pw_change"]=0;
    $where_items=array();
    $where_items["user_id"]=$user_record["user_id"];
    $settings["user_record"]=$user_record; # allow unauthenticated change to database
    \webdb\sql\sql_update($value_items,$where_items,"users",$settings["database_webdb"],true);
    unset($settings["user_record"]);
    \webdb\users\auth_log($user_record,"RESET_PASSWORD_CHANGED","");
    \webdb\utils\info_message(\webdb\utils\template_fill("reset_password_changed_message"));
  }
  $records=\webdb\sql\file_fetch_prepare("user_get_all_enabled");
  $user_record=false;
  for ($i=0;$i<count($records);$i++)
  {
    $record=$records[$i];
    if ($record["pw_reset_key"]=="")
    {
      continue;
    }
    $time_delta=time()-$record["pw_reset_time"];
    if ($time_delta>$settings["password_reset_timeout"])
    {
      continue;
    }
    if (password_verify($_GET["reset_password"],$record["pw_reset_key"])==true)
    {
      $user_record=$record;
      break;
    }
  }
  if ($user_record===false)
  {
    \webdb\users\unset_login_cookie();
    \webdb\users\auth_log(false,"INVALID__PASSWORD_RESET_KEY","");
    \webdb\utils\error_message("error: invalid password reset key");
  }
  \webdb\users\cancel_password_reset($user_record);
  $value_items=array();
  $temp_password=\webdb\users\crypto_random_key();
  $value_items["pw_hash"]=\webdb\users\webdb_password_hash($temp_password,$user_record["username"]);
  $value_items["pw_login_time"]=time();
  $value_items["user_agent"]=$settings["user_agent"];
  $value_items["remote_address"]=$_SERVER["REMOTE_ADDR"];
  $value_items["failed_login_count"]=0;
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  $settings["sql_check_post_params_override"]=true;
  $settings["user_record"]=$user_record; # allow unauthenticated change to database
  \webdb\sql\sql_update($value_items,$where_items,"users",$settings["database_webdb"],true);
  unset($settings["user_record"]);
  $change_password_params=array();
  $change_password_params["login_script_modified"]=\webdb\utils\resource_modified_timestamp("login.js");
  $change_password_params["login_styles_modified"]=\webdb\utils\resource_modified_timestamp("login.css");
  $change_password_params["old_password_default"]=$temp_password;
  \webdb\users\auth_log($user_record,"RESET_PASSWORD_PROMPT","");
  $content=\webdb\utils\template_fill("change_password_reset",$change_password_params);
  $settings["unauthenticated_content"]=true;
  \webdb\utils\output_page($content,"Reset Password Change");
}

#####################################################################################################

function send_reset_password_message()
{
  global $settings;
  # warning: this function is run without authentication
  if (isset($_POST["login_username"])==false)
  {
    \webdb\utils\error_message("error: missing username");
  }
  $login_username=trim(strtolower($_POST["login_username"]));
  \webdb\utils\webdb_setcookie("username_cookie",$login_username);
  $user_record=\webdb\users\get_user_record($login_username);
  $value_items=array();
  $key=\webdb\users\crypto_random_key();
  $value_items["pw_reset_key"]=\webdb\users\webdb_password_hash($key,$user_record["username"]);
  $value_items["pw_reset_time"]=time();
  $value_items["pw_hash"]="*"; # disable login with password
  $value_items["login_cookie"]="*"; # disable login with cookie
  $value_items["pw_change"]=1; # force password change on user clicking link from email
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  $settings["sql_check_post_params_override"]=true;
  $settings["user_record"]=$user_record; # allow unauthenticated change to database
  \webdb\sql\sql_update($value_items,$where_items,"users",$settings["database_webdb"],true);
  unset($settings["user_record"]);
  $t=$value_items["pw_reset_time"]+$settings["password_reset_timeout"];
  $msg_params=array();
  $msg_params["base_url"]=\webdb\utils\get_base_url();
  $msg_params["key"]=urlencode($key);
  $msg_params["valid_to_time"]=date("g:i a",$t);
  $msg_params["valid_to_date"]=date("l, j F Y (T)",$t);
  $message=\webdb\utils\template_fill("password_reset_message",$msg_params);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  #\webdb\utils\info_message($message); # TESTING (REMOVE/COMMENT OUT FOR PROD)
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $subject=$settings["app_name"]." password reset";
  \webdb\utils\send_email($user_record["email"],"",$subject,$message);
  \webdb\users\unset_login_cookie();
  $message=\webdb\utils\template_fill("password_reset_valid_to_message",$msg_params);
  \webdb\users\auth_log($user_record,"RESET_PASSWORD_EMAIL","");
  $settings["unauthenticated_content"]=true;
  \webdb\utils\info_message($message);
}

#####################################################################################################

function change_password()
{
  global $settings;
  if (isset($_POST["change_password"])==true)
  {
    if (isset($_COOKIE[$settings["username_cookie"]])==false)
    {
      \webdb\utils\error_message("error: username cookie not set");
    }
    $pw_old=$_POST["change_password_old"];
    $pw_new=trim($_POST["change_password_new"]);
    $pw_new_conf=$_POST["change_password_new_confirm"];
    \webdb\users\validate_new_password($pw_old,$pw_new,$pw_new_conf);
    $user_record=\webdb\users\get_user_record($_COOKIE[$settings["username_cookie"]]);
    if (password_verify($pw_old,$user_record["pw_hash"])==false)
    {
      \webdb\utils\error_message("error: old password is incorrect");
    }
    $value_items=array();
    $value_items["pw_hash"]=\webdb\users\webdb_password_hash($pw_new,$user_record["username"]);
    $value_items["pw_change"]=0;
    $where_items=array();
    $where_items["user_id"]=$user_record["user_id"];
    \webdb\sql\sql_update($value_items,$where_items,"users",$settings["database_webdb"],true);
    \webdb\users\initialise_login_cookie($user_record);
    $settings["user_record"]=$user_record;
    \webdb\users\auth_log($user_record,"CHANGED_PASSWORD","");
    \webdb\utils\info_message(\webdb\utils\template_fill("password_changed_message"));
  }
  $change_password_params=array();
  $change_password_params["login_script_modified"]=\webdb\utils\resource_modified_timestamp("login.js");
  $change_password_params["login_styles_modified"]=\webdb\utils\resource_modified_timestamp("login.css");
  $change_password_params["home_link_display"]="none";
  if ($settings["user_record"]["pw_change"]==0)
  {
    $change_password_params["home_link_display"]="block";
  }
  $content=\webdb\utils\template_fill("change_password",$change_password_params);
  \webdb\utils\output_page($content,"Change Password");
}

#####################################################################################################

function validate_new_password($old_password,$new_password,$new_password_confirm)
{
  global $settings;
  if ($new_password<>$new_password_confirm)
  {
    \webdb\utils\error_message("error: new passwords do not match");
  }
  if (in_array($new_password,$settings["prohibited_passwords"])==true)
  {
    \webdb\utils\error_message("error: cannot use any of the following for your new password: ".htmlspecialchars(implode(" ",$settings["prohibited_passwords"])));
  }
  if (strlen($new_password)<$settings["min_password_length"])
  {
    \webdb\utils\error_message("error: new password must be at least ".$settings["min_password_length"]." characters");
  }
  if (strlen($new_password)>$settings["max_password_length"])
  {
    \webdb\utils\error_message("error: a password of more than ".$settings["max_password_length"]." characters, while commendable, is considered a bit much. please try something shorter");
  }
  if ($new_password==$old_password)
  {
    \webdb\utils\error_message("error: new password cannot be the same as your old password");
  }
}

#####################################################################################################
