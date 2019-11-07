<?php

namespace webdb\test\security\utils;

#####################################################################################################

function security_test_error_callback()
{
  \webdb\test\security\finish_test_user(true);
}

#####################################################################################################

function start_test_user($test_overrides=true)
{
  if (\webdb\test\security\get_test_user()!==false)
  {
    \webdb\test\security\finish_test_user();
  }
  \webdb\test\security\create_test_user($test_overrides);
  if (\webdb\test\security\get_test_user()===false)
  {
    \webdb\test\utils\test_error_message("ERROR STARTING TEST USER: USER NOT FOUND AFTER INSERT");
  }
  \webdb\test\utils\test_info_message("TEST USER STARTED");
}

#####################################################################################################

function finish_test_user($is_error=false)
{
  if (\webdb\test\security\get_test_user()===false)
  {
    $msg="ERROR FINISHING TEST USER: USER NOT FOUND";
    if ($is_error==true)
    {
      \webdb\test\utils\test_info_message($msg);
    }
    else
    {
      \webdb\test\utils\test_error_message($msg);
    }
  }
  \webdb\test\security\delete_test_user();
  if (\webdb\test\security\get_test_user()!==false)
  {
    $msg="ERROR FINISHING TEST USER: ERROR DELETING";
    if ($is_error==true)
    {
      \webdb\test\utils\test_info_message($msg);
    }
    else
    {
      \webdb\test\utils\test_error_message($msg);
    }
  }
  \webdb\test\utils\test_info_message("TEST USER FINISHED");
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

function delete_test_user()
{
  $sql_params=array();
  $sql_params["username"]="test_user";
  \webdb\sql\sql_delete($sql_params,"users","webdb");
}

#####################################################################################################

function check_authentication_status($response)
{
  $params=array();
  $params["username"]="test_user";
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
  \webdb\test\utils\test_error_message("AUTHENTICATION STATUS NOT FOUND IN PAGE CONTENT");
}

#####################################################################################################

function test_user_login($uri=false)
{
  global $settings;
  \webdb\test\security\start_test_user();
  $params=array();
  $params["login_username"]="test_user";
  $params["login_password"]="password";
  $response=\webdb\test\utils\wpost($settings["app_web_root"],$params);
  $headers=\webdb\test\utils\extract_http_headers($response);
  $cookie_jar=\webdb\test\utils\search_http_headers($headers,"set-cookie");
  if (count($cookie_jar)<1)
  {
    \webdb\test\utils\test_error_message("SERVER RETURNED NO COOKIES");
  }
  $result=\webdb\test\utils\search_http_headers($headers,"location");
  if (count($result)<>1)
  {
    \webdb\test\utils\test_error_message("ERROR: NO REDIRECT HEADER FOUND ON PASSWORD LOGIN");
  }
  $redirect=$result[0];
  $cookie_jar[]="webdb_username=test_user";
  $settings["test_login_cookie_header"]=\webdb\test\utils\construct_cookie_header($cookie_jar);
  $response=\webdb\test\utils\wget($redirect); # TODO
  if (\webdb\test\security\check_authentication_status($response)==true)
  {
    \webdb\test\utils\test_success_message("PASSWORD LOGIN REDIRECT COOKIE AUTHENTICATION SUCCESS");
  }
  else
  {
    \webdb\test\utils\test_error_message("PASSWORD LOGIN REDIRECT COOKIE AUTHENTICATION FAILED");
  }
  if ($uri===false)
  {
    $uri=$settings["app_web_root"];
  }
  $response=\webdb\test\utils\wget($uri);
  if (\webdb\test\security\check_authentication_status($response)==true)
  {
    \webdb\test\utils\test_success_message("COOKIE AUTHENTICATION SUCCESS FOR URI: ".$uri);
  }
  else
  {
    \webdb\test\utils\test_error_message("COOKIE AUTHENTICATION FAILED FOR URI: ".$uri);
  }
  return $response;
}

#####################################################################################################
