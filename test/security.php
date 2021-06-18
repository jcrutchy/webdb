<?php

namespace webdb\test\security;

#####################################################################################################

function start()
{
  global $settings;
  require_once("test".DIRECTORY_SEPARATOR."security_utils.php");
  \webdb\test\utils\test_info_message("STARTING WEBDB SECURITY TESTS...");
  $settings["test_error_handler"]="\\webdb\\test\\security\\utils\\security_test_error_callback";
  \webdb\test\utils\test_cleanup();

  #$settings["test_include_backtrace"]=true; # use for debugging problem test (prevents trimming of backtrace in responses)

  #\webdb\test\security\utils\test_user_login();
  #$routes=array();
  #$routes=\webdb\test\security\utils\parse_routes("",$routes);
  #var_dump($routes);
  #var_dump(\webdb\test\security\utils\parse_get_params());
  #\webdb\test\utils\test_cleanup();
  #die;

  # check to make sure only \webdb\forms\get_form_config is loading form configs (checking for permission)

  \webdb\test\security\test_login_redirect();
  \webdb\test\security\test_user_agent();
  $settings["test_user_agent"]=\webdb\test\security\utils\TEST_USER_AGENT;
  \webdb\test\security\test_login_csrf_token();
  \webdb\test\security\test_remote_address();
  \webdb\test\security\test_admin_login();
  \webdb\test\security\test_case_insensitive_login();
  \webdb\test\security\test_login_cookie_max_age();
  \webdb\test\security\test_login_attempt_lockout();

  # use /webdb/doc/test_app/index.php as a testing platform (start by doing index.php init_app_schema)

  \webdb\test\utils\test_cleanup();
  \webdb\test\utils\test_info_message("FINISHED WEBDB SECURITY TESTS");
}

#####################################################################################################

function test_login_redirect()
{
  global $settings;
  \webdb\test\utils\apply_test_app_settings();
  $settings["test_user_agent"]=\webdb\test\security\utils\TEST_USER_AGENT;
  $test_case_msg="when navigating to a data page, if not authenticated show login form and on login redirect to original data page";
  $test_success=true;
  $test_url=$settings["app_web_index"]."?page=locations&cmd=edit&id=2";
  $response=\webdb\test\utils\wget($test_url);
  $delim1="<input type=\"hidden\" name=\"target_url\" value=\"";
  $delim2="\">";
  $target_url=\webdb\test\utils\extract_text($response,$delim1,$delim2);
  $parts=parse_url($target_url);
  if ((isset($parts["path"])==true) and (isset($parts["query"])==true))
  {
    $target_url=$parts["path"]."?".$parts["query"];
  }
  if ($target_url<>$test_url)
  {
    $test_success=false;
  }
  $field_values=\webdb\test\security\utils\output_user_field_values();
  \webdb\test\security\utils\start_test_user($field_values);
  $params=array();
  $params["login_username"]=$field_values["username"];
  $params["login_password"]=$field_values["password"];
  $params["csrf_token"]=\webdb\test\security\utils\extract_csrf_token($response);
  $params["target_url"]=$test_url;
  $response=\webdb\test\utils\wpost($settings["app_web_root"],$params);
  if (\webdb\utils\check_csrf_error($response)==true)
  {
    $test_success=false;
  }
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  $delim1="<form id=\"locations\" class=\"locations\" action=\"";
  $delim2="\" method=\"post\" enctype=\"multipart/form-data\">";
  $action=\webdb\test\utils\extract_text($response,$delim1,$delim2);
  if ($action<>$test_url)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  \webdb\test\utils\restore_app_settings();
  \webdb\test\utils\test_cleanup();
}

#####################################################################################################

function test_user_agent()
{
  global $settings;
  $user_agent_error=$settings["ua_error"];
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
  $test_agents[\webdb\test\security\utils\TEST_USER_AGENT]=false;
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
    $content=\webdb\utils\strip_http_headers($response);
    $returned_error=false;
    $error_suffix="";
    if (strpos($content,$user_agent_error)===0)
    {
      $returned_error=true;
      $error_suffix=" ".trim(substr($content,strlen($user_agent_error)));
    }
    $test_success=false;
    if ($error_expected==$returned_error)
    {
      $test_success=true;
    }
    \webdb\test\utils\test_result_message($test_case_msg.$error_suffix,$test_success);
  }
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
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
  if (\webdb\utils\compare_template("login_form",$response)==false)
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
  \webdb\test\utils\test_cleanup();
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="unable to login with username and password via post request without csrf token";
  $test_success=true;
  $field_values=\webdb\test\security\utils\output_user_field_values();
  \webdb\test\security\utils\start_test_user($field_values);
  $params=array();
  $params["login_username"]=$field_values["username"];
  $params["login_password"]=$field_values["password"];
  $params["target_url"]="";
  $response=\webdb\test\utils\wpost($settings["app_web_root"],$params);
  # [cookies] webdb_login=deleted, webdb_csrf_token=(new value with no corresponding outputted token so unable to be used)
  if (\webdb\utils\check_csrf_error($response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="able to successfully login with username and password via post request with valid csrf token and hash cookie";
  $test_success=true;
  $response=\webdb\test\security\utils\test_user_login();
  # get request for csrf token
  # post request for test_user login
  # on successful login post request, the server will redirect to eliminate re-posting on page refresh (get request)
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="after successful cookie login as test_user, post login as a different user (admin) fails with csrf error and previous login invalidated";
  $test_success=true;
  $response=\webdb\test\security\utils\admin_login();
  if (\webdb\utils\check_csrf_error($response)==false)
  {
    $test_success=false;
  }
  if (isset($settings["test_cookie_jar"][$settings["login_cookie"]])==true)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="after csrf login attempt by different user, subsequent request reverts to login form";
  $test_success=true;
  $response=\webdb\test\utils\wget($settings["app_web_root"],false);
  if (\webdb\utils\compare_template("login_form",$response)==false)
  {
    $test_success=false;
  }
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="after successful cookie login, post request without a csrf token to insert a new user fails with csrf error";
  $test_success=true;
  $response=\webdb\test\utils\wget($settings["app_web_root"]."?logout");
  $response=\webdb\test\security\utils\admin_login();
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg." [stage 1]",$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_success=true;
  $params=array();
  $params["form_cmd[insert_confirm]"]="Insert User";
  $params["enabled"]="checked";
  $params["username"]="test_user2";
  $params["fullname"]="test_user2";
  $params["email"]="test_user2@localhost.local";
  $response=\webdb\test\utils\wpost($settings["app_web_root"]."?page=users&cmd=edit",$params);
  if (\webdb\utils\check_csrf_error($response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg." [stage 2]",$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="after successful cookie login, post request with valid csrf token to insert a new user succeeds without csrf error";
  $test_success=true;
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  $response=\webdb\test\security\utils\admin_login();
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  $params=array();
  $params["form_cmd[insert_confirm]"]="Insert User";
  $params["users:enabled"]="checked";
  $params["users:username"]="test_user2";
  $params["users:fullname"]="test_user2";
  $params["users:email"]="test_user2@localhost.local";
  $params["csrf_token"]=\webdb\test\security\utils\get_csrf_token();
  $response=\webdb\test\utils\wpost($settings["app_web_root"]."?page=users&cmd=insert",$params);
  $field_values=\webdb\test\security\utils\output_user_field_values("admin",1,"",0,false);
  $user_record=\webdb\test\security\utils\get_test_user($field_values);
  \webdb\users\user_login_settings_set($user_record);
  if (\webdb\test\utils\compare_form_template("editor_page",$response)==false)
  {
    $test_success=false;
  }
  \webdb\users\user_login_settings_unset();
  if (\webdb\utils\check_csrf_error($response)==true)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="after successful cookie login, ajax post request without a csrf token to edit an existing user fails with csrf error";
  $test_success=true;
  $params=array();
  $params["edit_control:user_groups_subform:1,1:group_id"]=1;
  $test_url=$settings["app_web_root"]."?page=user_groups_subform&cmd=edit&id=1,1&ajax&subform=user_groups_subform&parent=users";
  $response=\webdb\test\utils\wpost($test_url,$params);
  if (\webdb\utils\check_csrf_error($response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="after successful cookie login, ajax post request with valid csrf token to edit an existing user succeeds without csrf error";
  $test_success=true;
  $params["csrf_token"]=\webdb\test\security\utils\get_csrf_token();
  $response=\webdb\test\utils\wpost($test_url,$params);
  if (\webdb\utils\check_csrf_error($response)==true)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="on get request renew csrf token if exceeds max age";
  $test_success=true;
  $response=\webdb\test\security\utils\test_user_login();
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  $field_values=\webdb\test\security\utils\output_user_field_values();
  $field_values["csrf_token_time"]=time()-$settings["max_csrf_token_age"]-1;
  \webdb\test\security\utils\update_test_user($field_values);
  $user_record=\webdb\test\security\utils\get_test_user();
  $old_csrf_token=$user_record["csrf_token"];
  $old_csrf_token_time=$user_record["csrf_token_time"];
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  $user_record=\webdb\test\security\utils\get_test_user();
  $new_csrf_token=$user_record["csrf_token"];
  $new_csrf_token_time=$user_record["csrf_token_time"];
  if ($old_csrf_token==$new_csrf_token)
  {
    $test_success=false;
  }
  if ($old_csrf_token_time>=$new_csrf_token_time)
  {
    $test_success=false;
  }
  if (\webdb\utils\check_csrf_error($response)==true)
  {
    $test_success=false;
  }
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="on get request renew csrf token if exceeds max age";
  $test_success=true;
  $response=\webdb\test\security\utils\test_user_login();
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }

  die;

  /*$params=array();
  $params["edit_control:user_groups_subform:1,1:group_id"]=1;
  $params["csrf_token"]=\webdb\test\security\utils\get_csrf_token();
  $field_values=\webdb\test\security\utils\output_user_field_values();
  $field_values["csrf_token_time"]=time()-$settings["max_csrf_token_age"]-1;
  \webdb\test\security\utils\update_test_user($field_values);
  $user_record=\webdb\test\security\utils\get_test_user();
  $old_csrf_token=$user_record["csrf_token"];
  $old_csrf_token_time=$user_record["csrf_token_time"];
  $response=\webdb\test\utils\wpost($test_url,$params);
  var_dump($response);
  die;
  $user_record=\webdb\test\security\utils\get_test_user();
  $new_csrf_token=$user_record["csrf_token"];
  $new_csrf_token_time=$user_record["csrf_token_time"];
  if ($old_csrf_token==$new_csrf_token)
  {
    $test_success=false;
  }
  if ($old_csrf_token_time>=$new_csrf_token_time)
  {
    $test_success=false;
  }
  $response=\webdb\test\utils\wget($test_url);
  var_dump($user_record);
  var_dump($response);
  die;
  if (\webdb\utils\check_csrf_error($response)==true)
  {
    $test_success=false;
  }
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }*/




  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  \webdb\test\utils\test_cleanup();
}

#####################################################################################################

function test_remote_address() # assumes ::1 and 192.168.0.0/16 are in system ip whitelist (should be)
{
  global $settings;
  $test_case_msg="if the user's remote address changes, invalidate cookie login (require password login)";
  $test_success=true;
  $response=\webdb\test\security\utils\test_user_login();
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  $test_settings=array();
  $test_settings["change_remote_addr"]="::2";
  $test_settings["custom_ip_whitelist"]=__DIR__.DIRECTORY_SEPARATOR."test_ip_whitelist_2";
  \webdb\test\utils\test_server_settings($test_settings,"initialising different ipv6 remote address with test ip whitelist on request back end");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  if (\webdb\utils\compare_template("login_form",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  \webdb\test\utils\delete_test_config();
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
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
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="if the third octet of the user's IPv4 remote address changes, force password login";
  \webdb\test\utils\test_server_setting("change_remote_addr","192.168.1.22","changing third octet of ipv4 remote address on request back end");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  $test_success=true;
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  if (\webdb\utils\compare_template("login_form",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
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
  if (\webdb\utils\compare_template("login_form",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
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
  if (\webdb\utils\compare_template("login_form",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
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
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
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
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
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
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="test that admin login fails when remote address changes to non-whitelisted address (and password login required)";
  \webdb\test\utils\test_server_setting("change_remote_addr",$test_address,"reinitialising remote address without adding to admin whitelist on request back end");
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  $test_success=true;
  if (\webdb\utils\compare_template("admin_address_whitelist_error",$response)==false)
  {
    $test_success=false;
  }
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  if (\webdb\utils\compare_template("login_form",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  \webdb\test\utils\test_cleanup();
}

#####################################################################################################

function test_case_insensitive_login()
{
  global $settings;
  $test_case_msg="test that login is successful with case insensitive username";
  $test_success=true;
  $response=\webdb\test\security\utils\test_user_login();
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  $field_values=\webdb\test\security\utils\output_user_field_values("Test_User",1,"",0,false);
  $response=\webdb\test\security\utils\test_user_login($field_values,false);
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  \webdb\test\utils\test_cleanup();
}

#####################################################################################################

function test_login_cookie_max_age()
{
  global $settings;
  $test_case_msg="throw error if user login cookie exceeds max age";
  $test_success=true;
  $response=\webdb\test\security\utils\test_user_login();
  if (\webdb\test\security\utils\check_authentication_status($response)==false)
  {
    $test_success=false;
  }
  $field_values=\webdb\test\security\utils\output_user_field_values();
  $field_values["login_setcookie_time"]=time()-$settings["max_cookie_age"]-1;
  \webdb\test\security\utils\update_test_user($field_values);
  $response=\webdb\test\utils\wget($settings["app_web_root"]);
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  \webdb\test\utils\test_cleanup();
}

#####################################################################################################

function test_login_attempt_lockout()
{
  global $settings;
  $test_case_msg="lockout user if too many wrong passwords tried";
  $test_success=true;
  $field_values=\webdb\test\security\utils\output_user_field_values("admin");
  $params=array();
  $params["login_username"]="admin";
  $params["login_password"]="bad_password";
  for ($i=1;$i<=$settings["max_login_attempts"];$i++)
  {
    $params["csrf_token"]=\webdb\test\security\utils\get_csrf_token();
    $response=\webdb\test\utils\wpost($settings["app_web_root"],$params);
    if (\webdb\test\security\utils\check_authentication_status($response)==true)
    {
      $test_success=false;
    }
  }
  $params["csrf_token"]=\webdb\test\security\utils\get_csrf_token();
  $response=\webdb\test\utils\wpost($settings["app_web_root"],$params);
  if (\webdb\utils\compare_template("lockout_error",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="after lockout, check that password and cookie logins are invalidated";
  $test_success=true;
  $user_record=\webdb\test\security\utils\get_test_user($field_values);
  if (($user_record["pw_hash"]<>"*") or ($user_record["login_cookie"]<>"*"))
  {
    $test_success=false;
  }
  $response=\webdb\test\security\utils\test_user_login(false,false);
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="after lockout, ensure page dispatch fails";
  $test_success=true;
  $response=\webdb\test\security\utils\admin_login();
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  $test_url=$settings["app_web_root"]."?page=users";
  $response=\webdb\test\utils\wget($test_url);
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="after lockout, ensure ajax dispatch fails";
  $test_success=true;
  $response=\webdb\test\security\utils\admin_login();
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  $test_url=$settings["app_web_root"]."?page=user_groups_subform&cmd=edit&id=1,1&ajax&subform=user_groups_subform&parent=users";
  $params=array();
  $params["login_username"]="admin";
  $params["login_password"]=$field_values["password"];
  $params["edit_control:user_groups_subform:1,1:group_id"]=1;
  $params["csrf_token"]=\webdb\test\security\utils\get_csrf_token();
  $response=\webdb\test\utils\wpost($test_url,$params);
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  if (\webdb\utils\compare_template("lockout_first_time_message",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $test_case_msg="after lockout, ensure * password fails";
  $test_success=true;
  $response=\webdb\test\security\utils\admin_login();
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  $params=array();
  $params["login_username"]="admin";
  $params["login_password"]="*";
  $params["csrf_token"]=\webdb\test\security\utils\get_csrf_token();
  $response=\webdb\test\utils\wpost($settings["app_web_root"],$params);
  if (\webdb\test\security\utils\check_authentication_status($response)==true)
  {
    $test_success=false;
  }
  if (\webdb\utils\compare_template("lockout_first_time_message",$response)==false)
  {
    $test_success=false;
  }
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  \webdb\test\utils\test_cleanup();
}

#####################################################################################################
