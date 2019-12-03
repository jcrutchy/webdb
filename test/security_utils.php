<?php

namespace webdb\test\security\utils;

#####################################################################################################

function security_test_error_callback()
{
  \webdb\test\utils\test_cleanup();
}

#####################################################################################################

function start_test_user($test_overrides=true)
{
  if (\webdb\test\security\utils\get_test_user()!==false)
  {
    \webdb\test\utils\initialize_webdb_schema();
  }
  \webdb\test\security\utils\create_test_user($test_overrides);
  if (\webdb\test\security\utils\get_test_user()===false)
  {
    \webdb\test\utils\test_error_message("ERROR STARTING TEST USER: USER NOT FOUND AFTER INSERT");
  }
}

#####################################################################################################

function get_test_user()
{
  $sql_params=array();
  $sql_params["username"]="test_user";
  $sql="SELECT * FROM webdb.users WHERE username=:username";
  $records=\webdb\sql\fetch_prepare($sql,$sql_params);
  if (count($records)==1)
  {
    return $records[0];
  }
  return false;
}

#####################################################################################################

function create_test_user($test_overrides)
{
  $items=array();
  $items["username"]="test_user";
  $items["fullname"]="test_user";
  $items["enabled"]=1;
  $items["email"]="";
  if ($test_overrides==true)
  {
    $items["pw_hash"]="\$2y\$13\$Vn8rJB73AHq56cAqbBwkEuKrQt3lSdoA3sDmKULZEgQLE4.nmsKzW"; # 'password'
    $items["pw_change"]=0;
  }
  \webdb\sql\sql_insert($items,"users","webdb");
}

#####################################################################################################

function check_authentication_status($response,$username="test_user")
{
  global $settings;
  if (isset($settings["test_cookie_jar"])==false)
  {
    return false;
  }
  if (isset($settings["test_cookie_jar"]["webdb_login"])==false)
  {
    return false;
  }
  $value=\webdb\test\utils\extract_cookie_value($settings["test_cookie_jar"]["webdb_login"]);
  if ($value=="deleted")
  {
    return false;
  }
  if (isset($settings["test_cookie_jar"]["csrf_token_hash"])==false)
  {
    return false;
  }
  $value=\webdb\test\utils\extract_cookie_value($settings["test_cookie_jar"]["csrf_token_hash"]);
  if ($value=="deleted")
  {
    return false;
  }
  $params=array();
  $params["username"]=$username;
  $authenticated_status=\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."authenticated_status",$params);
  $unauthenticated_status=\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."unauthenticated_status");
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

function test_user_login($uri=false)
{
  global $settings;
  $settings["test_user_agent"]="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36";
  \webdb\test\security\utils\start_test_user();
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  $csrf_token=\webdb\test\security\utils\extract_csrf_token($response);
  $params=array();
  $params["login_username"]="test_user";
  $params["login_password"]="password";
  $params["csrf_token"]=$csrf_token;
  return \webdb\test\utils\wpost($settings["app_web_root"],$params);
}

#####################################################################################################

function admin_login($uri=false)
{
  global $settings;
  $settings["test_user_agent"]="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36";
  \webdb\test\utils\clear_cookie_jar();
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  $csrf_token=\webdb\test\security\utils\extract_csrf_token($response);
  $params=array();
  $params["login_username"]="admin";
  $params["login_password"]="password";
  $params["csrf_token"]=$csrf_token;
  return \webdb\test\utils\wpost($settings["app_web_root"],$params);
}

#####################################################################################################
