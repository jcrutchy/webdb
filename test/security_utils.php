<?php

namespace webdb\test\security\utils;

#####################################################################################################

define("webdb\\test\\security\\utils\\TEST_USERNAME","test_user");
define("webdb\\test\\security\\utils\\TEST_PASSWORD","password");
define("webdb\\test\\security\\utils\\TEST_USER_AGENT","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36");

#####################################################################################################

function security_test_error_callback()
{
  \webdb\test\utils\test_cleanup();
}

#####################################################################################################

function output_user_field_values($username=false,$enabled=1,$email="",$pw_change=0,$password=false)
{
  $field_values=array();
  if ($username===false)
  {
    $username=\webdb\test\security\utils\TEST_USERNAME;
  }
  $field_values["username"]=$username;
  $field_values["fullname"]=$username;
  $field_values["enabled"]=$enabled;
  $field_values["email"]=$email;
  if ($password===false)
  {
    $password=\webdb\test\security\utils\TEST_PASSWORD;
  }
  $field_values["password"]=$password;
  $field_values["pw_hash"]=\webdb\users\webdb_password_hash($password,$username);
  $field_values["pw_change"]=$pw_change;
  return $field_values;
}

#####################################################################################################

function start_test_user($field_values=false)
{
  if ($field_values===false)
  {
    $field_values=\webdb\test\security\utils\output_user_field_values(false,1,"",1,"*");
  }
  if (\webdb\test\security\utils\get_test_user($field_values)!==false)
  {
    \webdb\test\utils\initialize_webdb_schema();
  }
  if (\webdb\test\security\utils\get_test_user($field_values)===false)
  {
    \webdb\test\security\utils\insert_test_user($field_values);
  }
  else
  {
    \webdb\test\security\utils\update_test_user($field_values);
  }
  if (\webdb\test\security\utils\get_test_user($field_values)===false)
  {
    \webdb\test\utils\test_error_message("ERROR STARTING TEST USER: USER NOT FOUND AFTER INSERT");
  }
}

#####################################################################################################

function insert_test_user($field_values)
{
  unset($field_values["password"]);
  \webdb\sql\sql_insert($field_values,"users","webdb",true);
}

#####################################################################################################

function update_test_user($field_values)
{
  $where_items=array();
  $where_items["username"]=$field_values["username"];
  unset($field_values["username"]);
  unset($field_values["password"]);
  \webdb\sql\sql_update($field_values,$where_items,"users","webdb",true);
}

#####################################################################################################

function get_test_user($field_values=false)
{
  $username=\webdb\test\security\utils\TEST_USERNAME;
  if ($field_values!==false)
  {
    $username=$field_values["username"];
  }
  $sql_params=array();
  $sql_params["username"]=$username;
  $sql="SELECT * FROM webdb.users WHERE username=:username";
  $records=\webdb\sql\fetch_prepare($sql,$sql_params);
  if (count($records)==1)
  {
    return $records[0];
  }
  return false;
}

#####################################################################################################

function check_authentication_status($response)
{
  global $settings;
  $value=\webdb\test\utils\extract_cookie_value("login_cookie");
  if ($value===false)
  {
    return false;
  }
  $username=\webdb\test\utils\extract_cookie_value("username_cookie");
  if ($username===false)
  {
    return false;
  }
  $params=array();
  $params["username"]=$username;
  $authenticated_status=\webdb\utils\template_fill("authenticated_status",$params);
  $unauthenticated_status=\webdb\utils\template_fill("unauthenticated_status");
  if (strpos($response,$authenticated_status)!==false)
  {
    return true;
  }
  if (strpos($response,$unauthenticated_status)!==false)
  {
    return false;
  }
  \webdb\test\utils\test_info_message("AUTHENTICATION STATUS NOT FOUND IN PAGE CONTENT");
}

#####################################################################################################

function extract_csrf_token($response)
{
  $delim1="<div id=\"csrf_token\" style=\"display: none;\">";
  $delim2="</div>";
  return \webdb\test\utils\extract_text($response,$delim1,$delim2);
}

#####################################################################################################

function parse_routes($uri,$routes)
{
  global $settings;
  $uri=$settings["app_web_index"].$uri;
  \webdb\test\utils\test_info_message("parsing routes: ".$uri);
  $response=\webdb\test\utils\wget($uri);
  $parts=explode($settings["app_web_index"],$response);
  array_shift($parts);
  $subroutes=array();
  for ($i=0;$i<count($parts);$i++)
  {
    $subparts=explode("\"",$parts[$i]);
    $subroutes[]=array_shift($subparts);
  }
  for ($i=0;$i<count($subroutes);$i++)
  {
    $sub_uri=trim($subroutes[$i]);
    if ($sub_uri=="")
    {
      continue;
    }
    if (in_array($sub_uri,$routes)==true)
    {
      continue;
    }
    if ($sub_uri=="?logout") # TEST AFTER TO AVOID HAVING TO LOGIN AGAIN
    {
      continue;
    }
    $routes[]=$sub_uri;
    $routes=\webdb\test\security\utils\parse_routes($sub_uri,$routes);
  }
  return $routes;
}

#####################################################################################################

function parse_get_params()
{
  global $settings;
  $cmd="grep --include=\*.php -whr '".$settings["webdb_root_path"]."' -e '_GET'";
  $output=shell_exec($cmd);
  $parts=explode("\$_GET[\"",$output);
  array_shift($parts);
  $params=array();
  for ($i=0;$i<count($parts);$i++)
  {
    $subparts=explode("\"",$parts[$i]);
    $param=array_shift($subparts);
    if (in_array($param,$params)==false)
    {
      $params[]=$param;
    }
  }
  return $params;
}

#####################################################################################################

function get_csrf_token()
{
  global $settings;
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  return \webdb\test\security\utils\extract_csrf_token($response);
}

#####################################################################################################

function test_user_login($field_values=false,$reset_user=true)
{
  global $settings;
  $settings["test_user_agent"]=\webdb\test\security\utils\TEST_USER_AGENT;
  if ($field_values===false)
  {
    $field_values=\webdb\test\security\utils\output_user_field_values();
  }
  if (($field_values["username"]<>"admin") and ($reset_user==true))
  {
    \webdb\test\security\utils\start_test_user($field_values);
  }
  $params=array();
  $params["login_username"]=$field_values["username"];
  $params["login_password"]=$field_values["password"];
  $params["csrf_token"]=\webdb\test\security\utils\get_csrf_token();
  return \webdb\test\utils\wpost($settings["app_web_root"],$params);
}

#####################################################################################################

function admin_login()
{
  $field_values=\webdb\test\security\utils\output_user_field_values("admin",1,"",0,false);
  return \webdb\test\security\utils\test_user_login($field_values);
}

#####################################################################################################
