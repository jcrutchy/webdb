<?php

namespace webdb\test\security;

#####################################################################################################

function start()
{
  global $settings;
  require_once("test".DIRECTORY_SEPARATOR."security_utils.php");
  \webdb\test\utils\test_info_message("STARTING WEBDB SECURITY TESTS...");
  $settings["test_error_handler"]="\\webdb\\test\\security\\utils\\security_test_error_callback";
  \webdb\test\security\test_user_agent();
  \webdb\test\security\test_login_csrf_token();
  \webdb\test\security\test_remote_address();
  \webdb\test\security\test_admin_login();
  \webdb\test\utils\test_info_message("FINISHED SECURITY TESTS");
  # use /webdb/doc/test_app/index.php as a testing platform (start by doing index.php init_app_schema)
}

#####################################################################################################

function test_user_agent()
{
  global $settings;
  $user_agent_error=trim(\webdb\utils\template_fill("user_agent_error"));
  $test_agents=array(); # agent => error_expected
  # unacceptable agents
  $test_agents[""]=true;
  $test_agents["dsfsdgdsfgdsg"]=true;
  $test_agents["chrome"]=true;
  $test_agents["firefox"]=true;
  $test_agents["Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)"]=true;
  $test_agents["Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)"]=true;
  $test_agents["Mozilla/5.0 (compatible; Yahoo! Slurp; http://help.yahoo.com/help/us/ysearch/slurp)"]=true;
  $test_agents["Mozilla/5.0 (X11; U; Linux armv7l like Android; en-us) AppleWebKit/531.2+ (KHTML, like Gecko) Version/5.0 Safari/533.2+ Kindle/3.0+"]=true;
  $test_agents["Mozilla/5.0 (Linux; Android 8.0.0; SM-G960F Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.84 Mobile Safari/537.36"]=true;
  $test_agents["Mozilla/5.0 (Linux; Android 6.0.1; Nexus 6P Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.83 Mobile Safari/537.36"]=true;
  $test_agents["Mozilla/5.0 (iPhone; CPU iPhone OS 12_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/69.0.3497.105 Mobile/15E148 Safari/605.1"]=true;
  $test_agents["Mozilla/5.0 (Windows Phone 10.0; Android 4.2.1; Microsoft; RM-1127_16056) AppleWebKit/537.36(KHTML, like Gecko) Chrome/42.0.2311.135 Mobile Safari/537.36 Edge/12.10536"]=true;
  $test_agents["Mozilla/5.0 (Linux; Android 7.0; SM-T827R4 Build/NRD90M) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.116 Safari/537.36"]=true;
  $test_agents["Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246"]=true;
  $test_agents["Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/601.3.9 (KHTML, like Gecko) Version/9.0.2 Safari/601.3.9"]=true;
  $test_agents["Mozilla/5.0 (CrKey armv7l 1.5.16041) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.0 Safari/537.36"]=true;
  $test_agents["AppleTV5,3/9.1.1"]=true;
  $test_agents["Mozilla/5.0 (PlayStation 4 3.11) AppleWebKit/537.73 (KHTML, like Gecko)"]=true;
  $test_agents["Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322)"]=true;
  $test_agents["Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko"]=true;
  $test_agents["Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; KTXN)"]=true;
  $test_agents["Mozilla/4.0 (compatible; MSIE 6.0; Windows 98)"]=true;
  $test_agents["Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; rv:11.0) like Gecko"]=true;
  $test_agents["Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0; SLCC1; .NET CLR 2.0.50727; Media Center PC 5.0; .NET CLR 3.0.04506)"]=true;
  $test_agents["Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.2; WOW64; Trident/6.0)"]=true;
  $test_agents["Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; .NET4.0C; .NET4.0E; .NET CLR 2.0.50727; .NET CLR 3.0.30729; .NET CLR 3.5.30729; rv:11.0) like Gecko"]=true;
  # acceptable agents
  $test_agents["Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36"]=false;
  $test_agents["Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:15.0) Gecko/20100101 Firefox/15.0.1"]=false;
  $test_agents["Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36"]=false;
  foreach ($test_agents as $agent => $error_expected)
  {
    $condition="no error";
    if ($error_expected==true)
    {
      $condition="error";
    }
    $test_case_msg="user agent '".$agent."' is expected to return ".$condition;
    $settings["test_user_agent"]=$agent;
    $response=\webdb\test\utils\wget($settings["app_web_root"]);
    $content=\webdb\test\utils\strip_http_headers($response);
    $returned_error=false;
    $error_suffix="";
    if (strpos($content,$user_agent_error)===0)
    {
      $returned_error=true;
      $error_suffix=" ".trim(substr($content,strlen($user_agent_error)));
      $parts=explode("<pre>",$error_suffix);
      $error_suffix=array_shift($parts);
    }
    $test_success=false;
    if ($error_expected==$returned_error)
    {
      $test_success=true;
    }
    else
    {
      var_dump($response);
    }
    \webdb\test\utils\test_result_message($test_case_msg.$error_suffix,$test_success);
  }
  # test user agent change
  $test_case_msg="if user agent changes, invalidate cookie login (require password)";
  $response=\webdb\test\security\utils\test_user_login();
  $test_success=true;
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  $new_user_agent="Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36";
  \webdb\test\utils\test_server_setting("change_user_agent",$new_user_agent,"changing user agent on request back end");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  if (\webdb\test\utils\compare_template_exluding_percents("login_form",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  \webdb\test\utils\test_cleanup();
}

#####################################################################################################

function test_login_csrf_token()
{
  global $settings;
  $test_case_msg="unable to login with username and password via post request without csrf token";
  \webdb\test\security\utils\start_test_user(false);
  $params=array();
  $params["login_username"]="test_user";
  $params["login_password"]="password";
  $response=\webdb\test\utils\wpost($settings["app_web_root"],$params);
  $content=\webdb\test\utils\strip_http_headers($response);
  $test_success=true;
  if ($content<>"csrf error")
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  $test_case_msg="able to successfully login with username and password via post request with valid csrf token and hash";
  $response=\webdb\test\security\utils\test_user_login();
  $test_success=true;
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  $test_case_msg="after successful cookie login, post request without a csrf token to insert a new user fails with csrf error";
  $params=array();
  $params["form_cmd[insert_confirm]"]="Insert User";
  $params["enabled"]="checked";
  $params["username"]="test_user2";
  $params["email"]="test_user2@localhost.local";
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  $test_success=true;
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  $response=\webdb\test\utils\wpost($settings["app_web_root"]."?page=users&cmd=edit",$params);
  $content=\webdb\test\utils\strip_http_headers($response);
  if ($content<>"csrf error")
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  $test_case_msg="after successful cookie login, ajax post request without a csrf token to edit an existing user fails with csrf error";
  $params=array();
  $params["edit_control:user_groups_subform:1,1:group_id"]=1;
  $response=\webdb\test\utils\wpost($settings["app_web_root"]."?page=user_groups_subform&cmd=edit&id=1&ajax",$params);
  $content=\webdb\test\utils\strip_http_headers($response);
  if ($content<>"csrf error")
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  \webdb\test\utils\test_cleanup();
}

#####################################################################################################

function test_remote_address() # assumes ::1 and 192.168.0.0/16 are in system ip whitelist
{
  global $settings;
  $test_case_msg="if the user's remote address changes, invalidate cookie login (require password login)";
  $response=\webdb\test\security\utils\test_user_login();
  $test_settings=array();
  $test_settings["change_remote_addr"]="::2";
  $test_settings["custom_ip_whitelist"]=__DIR__.DIRECTORY_SEPARATOR."test_ip_whitelist_2";
  \webdb\test\utils\test_server_settings($test_settings,"initialising different ipv6 remote address with test ip whitelist on request back end");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  $test_success=true;
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  if (\webdb\test\utils\compare_template_exluding_percents("login_form",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  \webdb\test\utils\delete_test_config();
  $test_case_msg="if the last octet of the user's IPv4 remote address changes, don't invalidate cookie login";
  \webdb\test\utils\test_server_setting("change_remote_addr","192.168.0.21","initialising ipv4 remote address on request back end");
  $response=\webdb\test\security\utils\test_user_login();
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  $test_success=true;
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_server_setting("change_remote_addr","192.168.0.22","changing low octet of ipv4 remote address on request back end");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  $test_case_msg="if the third octet of the user's IPv4 remote address changes, force password login";
  \webdb\test\utils\test_server_setting("change_remote_addr","192.168.1.22","changing third octet of ipv4 remote address on request back end");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  $test_success=true;
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  if (\webdb\test\utils\compare_template_exluding_percents("login_form",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  $test_case_msg="if the second octet of the user's IPv4 remote address changes, force password login";
  $response=\webdb\test\security\utils\test_user_login();
  $test_success=true;
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  $test_settings=array();
  $test_settings["change_remote_addr"]="192.169.1.22";
  $test_settings["custom_ip_whitelist"]=__DIR__.DIRECTORY_SEPARATOR."test_ip_whitelist_2";
  \webdb\test\utils\test_server_settings($test_settings,"changing second octet of ipv4 remote address with test ip whitelist on request back end");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  if (\webdb\test\utils\compare_template_exluding_percents("login_form",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  $test_case_msg="if the first octet of the user's IPv4 remote address changes, force password login";
  $response=\webdb\test\security\utils\test_user_login();
  $test_success=true;
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  $test_settings=array();
  $test_settings["change_remote_addr"]="193.169.1.22";
  $test_settings["custom_ip_whitelist"]=__DIR__.DIRECTORY_SEPARATOR."test_ip_whitelist_2";
  \webdb\test\utils\test_server_settings($test_settings,"changing first octet of ipv4 remote address with test ip whitelist on request back end");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  if (\webdb\test\utils\compare_template_exluding_percents("login_form",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  $test_case_msg="fail password and cookie login if remote address isn't whitelisted";
  $test_settings=array();
  $test_settings["change_remote_addr"]="193.169.1.22";
  $test_settings["custom_ip_whitelist"]=__DIR__.DIRECTORY_SEPARATOR."test_ip_whitelist_1";
  \webdb\test\utils\test_server_settings($test_settings,"initialising non-whitelisted ipv4 remote address and test ip whitelist on request back end");
  $response=\webdb\test\security\utils\test_user_login();
  $test_success=true;
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  $test_case_msg="login successfully if remote address is added to whitelist";
  $test_settings=array();
  $test_settings["change_remote_addr"]="193.169.1.22";
  $test_settings["custom_ip_whitelist"]=__DIR__.DIRECTORY_SEPARATOR."test_ip_whitelist_2";
  \webdb\test\utils\test_server_settings($test_settings,"initialising whitelisted ipv4 remote address and test ip whitelist on request back end");
  $response=\webdb\test\security\utils\test_user_login();
  $test_success=true;
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  $test_case_msg="fail password and cookie login if remote address is blacklisted";
  $test_settings=array();
  $test_settings["change_remote_addr"]="193.169.1.22";
  $test_settings["custom_ip_blacklist"]=__DIR__.DIRECTORY_SEPARATOR."test_ip_blacklist_1";
  \webdb\test\utils\test_server_settings($test_settings,"initialising blacklisted ipv4 remote address and test ip blacklist on request back end");
  $response=\webdb\test\security\utils\test_user_login();
  $test_success=true;
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  \webdb\test\utils\test_cleanup();
}

#####################################################################################################

function test_admin_login()
{
  global $settings;
  $test_address="192.168.0.21";
  $test_settings=array();
  $test_settings["change_remote_addr"]=$test_address;
  $test_settings["add_admin_whitelist_addr"]=$test_address;
  \webdb\test\utils\test_server_settings($test_settings,"initialising remote address and admin whitelist on request back end");
  $test_case_msg="test that admin login is successful from whitelisted remote address";
  $response=\webdb\test\security\utils\admin_login();
  $test_success=true;
  if (\webdb\test\security\utils\check_authentication_status($response,"admin")==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  $test_case_msg="test that admin login fails when remote address changes to non-whitelisted address (and password login required)";
  \webdb\test\utils\test_server_setting("change_remote_addr",$test_address,"reinitialising remote address without adding to admin whitelist on request back end");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  $test_success=true;
  if (\webdb\test\utils\compare_template_exluding_percents("admin_address_whitelist_error",$response)==false)
  {
    $test_success=false;
  }
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  if (\webdb\test\utils\compare_template_exluding_percents("login_form",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  \webdb\test\utils\delete_test_config();
}

#####################################################################################################
