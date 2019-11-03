<?php

namespace webdb\test\security;

#####################################################################################################

function start()
{
  global $settings;
  \webdb\test\utils\test_info_message("STARTING WEBDB SECURITY TESTS...");
  $settings["test_error_handler"]="\\webdb\\test\\security\\security_test_error_callback";

  \webdb\test\security\remote_address_change();

  \webdb\test\utils\test_info_message("FINISHED SECURITY TESTS");
}

#####################################################################################################

function security_test_error_callback()
{
  \webdb\test\security\finish_test_user();
}

#####################################################################################################

function start_test_user()
{
  if (\webdb\test\security\get_test_user()===false)
  {
    \webdb\test\security\create_test_user();
    if (\webdb\test\security\get_test_user()===false)
    {
      \webdb\test\utils\test_error_message("ERROR STARTING TEST USER: USER NOT FOUND AFTER INSERT");
    }
  }
  \webdb\test\utils\test_info_message("TEST USER STARTED");
}

#####################################################################################################

function finish_test_user()
{
  if (\webdb\test\security\get_test_user()===false)
  {
    \webdb\test\utils\test_error_message("ERROR FINISHING TEST USER: USER NOT FOUND");
  }
  \webdb\test\security\delete_test_user();
  if (\webdb\test\security\get_test_user()!==false)
  {
    \webdb\test\utils\test_error_message("ERROR FINISHING TEST USER: ERROR DELETING");
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

function create_test_user()
{
  $items=array();
  $items["username"]="test_user";
  $items["enabled"]=1;
  $items["email"]="";
  $items["pw_hash"]="\$2y\$13\$Vn8rJB73AHq56cAqbBwkEuKrQt3lSdoA3sDmKULZEgQLE4.nmsKzW"; # 'password'
  $items["pw_change"]=0;
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
  $response=\webdb\test\utils\wget($redirect,$settings["test_login_cookie_header"]);
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
  $response=\webdb\test\utils\wget($uri,$settings["test_login_cookie_header"]);
  if (\webdb\test\security\check_authentication_status($response)==true)
  {
    \webdb\test\utils\test_success_message("COOKIE AUTHENTICATION SUCCESS FOR URI: ".$uri);
  }
  else
  {
    \webdb\test\utils\test_error_message("COOKIE AUTHENTICATION FAILED FOR URI: ".$uri);
  }
}

#####################################################################################################

function remote_address_change()
{
  global $settings;
  \webdb\test\utils\test_case_message("TEST CASE: if the user's remote address changes, invalidate cookie login (require password)");
  \webdb\test\security\test_user_login();
  \webdb\test\utils\test_server_setting("change_remote_addr","::2","changing remote address from ::1 to ::2");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  \webdb\test\utils\delete_test_config();
  if (\webdb\test\security\check_authentication_status($response)==false)
  {
    \webdb\test\utils\test_success_message("REMOTE ADDRESS CHANGE TEST SUCCESS");
  }
  else
  {
    \webdb\test\utils\test_error_message("REMOTE ADDRESS CHANGE TEST FAILED");
  }
  \webdb\test\utils\test_case_message("TEST CASE: if the last octet of the user's IPv4 remote address changes, don't invalidate cookie login");
  \webdb\test\utils\test_server_setting("change_remote_addr","192.168.0.21","changing request remote address to 192.168.0.21");
  \webdb\test\security\test_user_login();
  \webdb\test\utils\test_server_setting("change_remote_addr","192.168.0.22","changing request remote address to 192.168.0.22");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  if (\webdb\test\security\check_authentication_status($response)==true)
  {
    \webdb\test\utils\test_success_message("REMOTE ADDRESS SUBNET OCTET CHANGE TEST SUCCESS");
  }
  else
  {
    \webdb\test\utils\test_error_message("REMOTE ADDRESS SUBNET OCTET CHANGE TEST FAILED");
  }
  \webdb\test\utils\test_server_setting("change_remote_addr","192.168.1.22","changing request remote address to 192.168.1.22");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  if (\webdb\test\security\check_authentication_status($response)==false)
  {
    \webdb\test\utils\test_success_message("REMOTE ADDRESS SUBNET OCTET CHANGE TEST SUCCESS");
  }
  else
  {
    \webdb\test\utils\test_error_message("REMOTE ADDRESS SUBNET OCTET CHANGE TEST FAILED");
  }
  \webdb\test\utils\delete_test_config();
  \webdb\test\security\finish_test_user();
}

#####################################################################################################
