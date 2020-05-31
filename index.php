<?php

namespace webdb\index;

$start_time=microtime(true); # debug
$stop_time=microtime(true); # debug

#####################################################################################################

ini_set("display_errors","on");
ini_set("error_reporting",E_ALL);
ini_set("max_execution_time",120);
ini_set("memory_limit","512M");
date_default_timezone_set("UTC");

chdir(__DIR__);

require_once("utils.php");
require_once("encrypt.php");
require_once("users.php");
require_once("csrf.php");
require_once("forms.php");
require_once("sql.php");
require_once("stubs.php");
require_once("chat.php");
require_once("manage.php");
require_once("cli.php");
require_once("tools".DIRECTORY_SEPARATOR."dxf.php");
require_once("tools".DIRECTORY_SEPARATOR."graphics.php");

set_error_handler("\\webdb\\utils\\error_handler",E_ALL);
set_exception_handler("\\webdb\\utils\\exception_handler");

define("webdb\\index\\CONFIG_ID_DELIMITER",",");
define("webdb\\index\\LINEBREAK_PLACEHOLDER","@@@@");
define("webdb\\index\\LINEBREAK_DB_DELIM","\\n");
define("webdb\\index\\LOOKUP_DISPLAY_FIELD_DELIM"," - ");
define("webdb\\index\\TEMPLATE_PLACEHOLDER_1","!~template_placeholder_1~!");
define("webdb\\index\\TEMPLATE_PLACEHOLDER_2","!~template_placeholder_2~!");
define("webdb\\index\\TEMPLATE_PLACEHOLDER_3","!~template_placeholder_3~!");

if (\webdb\cli\is_cli_mode()==false)
{
  ob_start("\\webdb\\utils\\ob_postprocess");
}

if (isset($settings)==false)
{
  $settings=array();
}

if (\webdb\cli\is_cli_mode()==true)
{
  \webdb\cli\cli_dispatch();
}

\webdb\utils\load_settings();

$settings["request_url"]=\webdb\utils\get_url();
$settings["request_base_url"]=\webdb\utils\get_base_url();

$msg="REQUEST_RECEIVED: ".\webdb\utils\get_url();
$settings["logs"]["auth"][]=$msg;
$settings["logs"]["sql"][]=$msg;
if ($settings["ip_blacklist_enabled"]==true)
{
  if (\webdb\users\remote_address_listed($_SERVER["REMOTE_ADDR"],"black")==true)
  {
    \webdb\utils\system_message("ip blacklisted: ".htmlspecialchars($_SERVER["REMOTE_ADDR"]));
  }
}
if ($settings["ip_whitelist_enabled"]==true)
{
  if (\webdb\users\remote_address_listed($_SERVER["REMOTE_ADDR"],"white")==false)
  {
    \webdb\utils\system_message("ip not whitelisted: ".htmlspecialchars($_SERVER["REMOTE_ADDR"]));
  }
}
if (\webdb\utils\is_app_mode()==false)
{
  $settings["unauthenticated_content"]=true;
  \webdb\utils\static_page("home","WebDB");
}

\webdb\utils\database_connect();

$settings["user_agent"]="";
if (isset($_SERVER["HTTP_USER_AGENT"])==true)
{
  $settings["user_agent"]=$_SERVER["HTTP_USER_AGENT"];
}
$settings["browser_info"]=array();
$settings["browser_info"]["browser"]="chrome"; # default to chrome settings if user agent check not enabled

if ($settings["check_ua"]==true)
{
  $ua_error=\webdb\utils\template_fill("user_agent_error");
  if ($settings["user_agent"]<>"")
  {
    $settings["browser_info"]=get_browser($_SERVER["HTTP_USER_AGENT"],true);
    switch (strtolower($settings["browser_info"]["browser"]))
    {
      case "chrome":
      case "firefox":
        break;
      default:
        \webdb\utils\system_message($ua_error." [neither chrome nor firefox]");
    }
    if (strtolower($settings["browser_info"]["device_type"])<>"desktop")
    {
      \webdb\utils\system_message($ua_error." [not desktop]");
    }
    if (($settings["browser_info"]["ismobiledevice"]<>"") or ($settings["browser_info"]["istablet"]<>""))
    {
      \webdb\utils\system_message($ua_error." [is mobile or tablet]");
    }
  }
  else
  {
    \webdb\utils\system_message($ua_error." [no user agent]");
  }
}

\webdb\users\auth_dispatch();

if (isset($_GET["update_oul"])==true)
{
  \webdb\chat\update_online_user_list();
}

if (isset($settings["controller_dispatch"])==true)
{
  if (function_exists($settings["controller_dispatch"])==true)
  {
    call_user_func($settings["controller_dispatch"]);
    die;
  }
}

# 11.36 sec to load page
# 19953 calls to template_fill
/*$field_params=array();
$field_params["primary_key"]="104";
$field_params["page_id"]="test_page";
$field_params["border_color"]="888";
$field_params["border_width"]=1;
$field_params["value"]="1";
$field_params["field_name"]="test_field";
$field_params["group_span"]="";
$field_params["handlers"]="";
$field_params["table_cell_style"]="";
$field_params["edit_cmd_id"]="104";
$start_time=microtime(true); # debug
for ($i=1;$i<=19953;$i++)
{
  $test=\webdb\forms\form_template_fill("list_field_handlers",$field_params);
}
$stop_time=microtime(true); # debug
# 11.13 sec to run test
die;*/

if (isset($_GET["page"])==true)
{
  \webdb\forms\form_dispatch($_GET["page"]);
}

\webdb\utils\static_page($settings["app_home_template"],$settings["app_title"]);

#####################################################################################################
