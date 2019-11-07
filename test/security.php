<?php

namespace webdb\test\security;

#####################################################################################################

function start()
{
  global $settings;
  require_once("test".DIRECTORY_SEPARATOR."security_utils.php");
  \webdb\test\utils\test_info_message("STARTING WEBDB SECURITY TESTS...");
  $settings["test_error_handler"]="\\webdb\\test\\security\\utils\\security_test_error_callback";

  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  \webdb\test\utils\test_dump_message($response);

  \webdb\test\security\test_user_agent();

  #\webdb\test\security\test_login_csrf_token();

  /*\webdb\test\security\remote_address_change();
  \webdb\test\security\user_agent_change();
  \webdb\test\security\test_first_time_user();*/

  \webdb\test\utils\test_info_message("FINISHED SECURITY TESTS");
}

#####################################################################################################

function test_user_agent()
{

}

#####################################################################################################

function remote_address_change()
{
  global $settings;
  \webdb\test\utils\test_case_message("TEST CASE: if the user's remote address changes, invalidate cookie login (require password)");
  \webdb\test\security\test_user_login();
  \webdb\test\utils\test_server_setting("change_remote_addr","::2","changing request remote address to ::2");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
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
  \webdb\test\utils\test_case_message("TEST CASE: if any of the higher octets of the user's IPv4 remote address changes, invalidate cookie login");
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
  \webdb\test\security\test_user_login();
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  if (\webdb\test\security\check_authentication_status($response)==true)
  {
    \webdb\test\utils\test_success_message("COOKIE LOGIN TEST SUCCESS (AFTER NO CHANGE TO OVERRIDEN REMOTE ADDRESS)");
  }
  else
  {
    \webdb\test\utils\test_error_message("COOKIE LOGIN TEST FAILED (AFTER NO CHANGE TO OVERRIDEN REMOTE ADDRESS)");
  }
  \webdb\test\utils\test_server_setting("change_remote_addr","192.169.1.22","changing request remote address to 192.169.1.22");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  if (\webdb\test\security\check_authentication_status($response)==false)
  {
    \webdb\test\utils\test_success_message("REMOTE ADDRESS SUBNET OCTET CHANGE TEST SUCCESS");
  }
  else
  {
    \webdb\test\utils\test_error_message("REMOTE ADDRESS SUBNET OCTET CHANGE TEST FAILED");
  }
  \webdb\test\security\test_user_login();
  \webdb\test\utils\test_server_setting("change_remote_addr","193.169.1.22","changing request remote address to 193.169.1.22");
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

function user_agent_change()
{
  global $settings;
  \webdb\test\utils\test_case_message("TEST CASE: if the user's user agent changes, invalidate cookie login (require password)");
  \webdb\test\security\test_user_login();
  \webdb\test\utils\test_server_setting("change_user_agent","test_user_agent","changing request user agent");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  if (\webdb\test\security\check_authentication_status($response)==false)
  {
    \webdb\test\utils\test_success_message("USER AGENT CHANGE TEST SUCCESS");
  }
  else
  {
    \webdb\test\utils\test_error_message("USER AGENT CHANGE TEST FAILED");
  }
  \webdb\test\utils\delete_test_config();
  \webdb\test\security\finish_test_user();
}

#####################################################################################################

function test_login_csrf_token()
{
  global $settings;
  \webdb\test\utils\test_case_message("TEST CASE: all post requests are required to contain a valid csrf token field, which must be verified against a valid csrf token hash cookie (including logins)");
  \webdb\test\security\start_test_user(false);
  $params=array();
  $params["login_username"]="test_user";
  $params["login_password"]="password";
  $response=\webdb\test\utils\wpost($settings["app_web_root"],$params);
  $content=\webdb\test\utils\strip_http_headers($response);
  if ($content=="csrf error")
  {
    \webdb\test\utils\test_success_message("LOGIN CSRF TOKEN ERROR: TEST SUCCESS");
  }
  else
  {
    \webdb\test\utils\test_error_message("LOGIN CSRF TOKEN ERROR: TEST FAILED");
  }

  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  $headers=\webdb\test\utils\extract_http_headers($response);
  $cookie_jar=\webdb\test\utils\search_http_headers($headers,"set-cookie");
  $settings["test_login_cookie_header"]=\webdb\test\utils\construct_cookie_header($cookie_jar);
  if (count($cookie_jar)<1)
  {
    \webdb\test\utils\test_error_message("SERVER RETURNED NO COOKIES");
  }
  $delim1="<input type=\"hidden\" name=\"csrf_token\" value=\"";
  $delim2="\">";
  $token=\webdb\test\utils\extract_text($response,$delim1,$delim2);
  \webdb\test\utils\test_dump_message($token);
  $params=array();
  $params["login_username"]="test_user";
  $params["login_password"]="password";
  $params["csrf_token"]=$token;
  $response=\webdb\test\utils\wpost($settings["app_web_root"],$params);
  $content=\webdb\test\utils\strip_http_headers($response);
  if ($content=="csrf error")
  {
    \webdb\test\utils\test_success_message("PASSWORD LOGIN WITH CSRF TOKEN: TEST SUCCESS");
  }
  else
  {
    \webdb\test\utils\test_error_message("PASSWORD LOGIN WITH CSRF TOKEN: TEST FAILED");
  }

  \webdb\test\security\finish_test_user();
}

#####################################################################################################

function test_first_time_user()
{
  global $settings;
  \webdb\test\utils\test_case_message("TEST CASE: test first time user process");
  \webdb\test\security\create_test_user(false);

  # login prompt
  # reset password (requires apache side test setting)
  # read password reset link from test settings
  # navigate to link and ensure new password prompt appears
  # post new password
  # ensure redirect and login cookie work

  $test_user=get_test_user();
  var_dump($test_user);

  $response=\webdb\test\security\test_user_login();
  if ((\webdb\test\security\check_authentication_status($response)==true) and (\webdb\test\utils\compare_template_exluding_percents("change_password",$response)==true))
  {
    \webdb\test\utils\test_success_message("FIRST TIME USER PASSWORD PROMPT TEST SUCCESS");
  }
  else
  {
    \webdb\test\utils\test_error_message("FIRST TIME USER PASSWORD PROMPT TEST FAILED");
  }
  \webdb\test\utils\test_case_message("TEST CASE: when a new user is inserted, the password field contains an invalid hash (*)");
  $test_user=get_test_user();
  if ($test_user["pw_hash"]=="*")
  {
    \webdb\test\utils\test_success_message("FIRST TIME USER PASSWORD HASH TEST SUCCESS");
  }
  else
  {
    \webdb\test\utils\test_error_message("FIRST TIME USER PASSWORD HASH TEST FAILED");
  }
  \webdb\test\security\finish_test_user();
}

#####################################################################################################
