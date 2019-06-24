<?php

namespace webdb\users;

#####################################################################################################

function authenticate()
{
  global $settings;
  $login_form_params=array();
  $login_form_params["default_email"]="";
  $login_form_params["auth_error"]="";
  $login_form_params["default_remember_me"]=\webdb\utils\template_fill("checkbox_checked");
  $login_form_params["login_script_modified"]=\webdb\utils\script_modified_timestamp("login");
  if (isset($_POST["reset_password"])==true)
  {
    if (isset($_POST["login_email"])==false)
    {
      \webdb\utils\show_message("error: missing email address");
    }
  }
  if ((isset($_POST["login_email"])==true) and (isset($_POST["login_password"])==true))
  {
    if (isset($_POST["remember_me"])==true)
    {
      setcookie($settings["email_cookie"],$_POST["login_email"],time()+$settings["max_cookie_age"],"/");
      $login_form_params["default_email"]=$_POST["login_email"];
    }
    else
    {
      $login_form_params["default_remember_me"]="";
    }
    $sql_params=array();
    $sql_params["email"]=$_POST["login_email"];
    $records=\webdb\sql\file_fetch_prepare("user_get_by_login",$sql_params);
    if (count($records)<>1)
    {
      setcookie($settings["login_cookie"],null,-1,"/");
      \webdb\utils\show_message("error: email address not found");
    }
    $user_record=$records[0];
    if (password_verify($_POST["login_password"],$user_record["pw_hash"])==false)
    {
      setcookie($settings["login_cookie"],null,-1,"/");
      \webdb\utils\show_message("error: incorrect password");
    }
    $settings["auth"]=$user_record;
    $where_items=array();
    $where_items["user_id"]=$user_record["user_id"];
    if ($user_record["pw_reset"]==="1")
    {
      $value_items=array();
      $value_items["pw_reset"]=0;
      $value_items["pw_reset_time"]=zero_sql_timestamp();
      \webdb\sql\sql_update($value_items,$where_items,$settings["db_users_table"],$settings["db_users_schema"],true);
    }
    $value_items=array();
    $value_items["last_login_microtime"]=round(microtime(true));
    if (isset($_POST["remember_me"])==true)
    {
      $crypto_strong=true;
      $key=base64_encode(openssl_random_pseudo_bytes(30,$crypto_strong));
      $options=array();
      $options["cost"]=13;
      $cookie=password_hash($_POST["login_email"].$key,PASSWORD_BCRYPT,$options);
      $value_items["login_cookie"]=$cookie;
      $value_items["login_cookie_microtime"]=$value_items["last_login_microtime"];
      $expiry=$value_items["login_cookie_microtime"]+$settings["max_cookie_age"];
      setcookie($settings["login_cookie"],$key,$expiry,"/");
    }
    else
    {
      setcookie($settings["login_cookie"],null,-1,"/");
      $value_items["login_cookie"]="";
      $value_items["login_cookie_microtime"]=0;
    }
    \webdb\sql\sql_update($value_items,$where_items,$settings["db_users_table"],$settings["db_users_schema"],true);
    var_dump($user_record);
    die;
  }
  if (isset($_COOKIE[$settings["login_cookie"]])==true)
  {

  }
  setcookie($settings["login_cookie"],null,-1,"/");
  $page_params=array();
  $page_params["page_title"]="Login";
  $page_params["page_head"]="";
  $page_params["body_text"]=\webdb\utils\template_fill("login_form",$login_form_params);
  die(\webdb\utils\template_fill("global/page",$page_params));
}

#####################################################################################################
