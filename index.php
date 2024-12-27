<?php

namespace webdb\index;

$start_time=microtime(true); # debug
$stop_time=microtime(true); # debug

#####################################################################################################

ini_set("display_errors","on");
ini_set("error_reporting",E_ALL);
ini_set("max_execution_time","360");
ini_set("memory_limit","512M");

ini_set("xdebug.var_display_max_children",-1);
ini_set("xdebug.var_display_max_data",-1);
ini_set("xdebug.var_display_max_depth",-1);

date_default_timezone_set("UTC");

chdir(__DIR__);

$dir=__DIR__.DIRECTORY_SEPARATOR;

require_once($dir."utils.php");
require_once($dir."encrypt.php");
require_once($dir."users.php");
require_once($dir."csrf.php");
require_once($dir."forms.php");
require_once($dir."sql.php");
require_once($dir."stubs.php");
require_once($dir."chat.php");
require_once($dir."manage.php");
require_once($dir."cli.php");
require_once($dir."http.php");
require_once($dir."websocket.php");
require_once($dir."dbquery.php");
require_once($dir."wiki.php");
require_once($dir."wiki_utils.php");
require_once($dir."teams.php");
require_once($dir."tools".DIRECTORY_SEPARATOR."nn.php");
require_once($dir."tools".DIRECTORY_SEPARATOR."dxf.php");
require_once($dir."tools".DIRECTORY_SEPARATOR."graphics.php");
require_once($dir."tools".DIRECTORY_SEPARATOR."tree.php");
require_once($dir."tools".DIRECTORY_SEPARATOR."chart.php");
require_once($dir."tools".DIRECTORY_SEPARATOR."excel.php");
require_once($dir."tools".DIRECTORY_SEPARATOR."word.php");

require_once($dir."apps".DIRECTORY_SEPARATOR."rps".DIRECTORY_SEPARATOR."rps.php");

define("webdb\\index\\CONFIG_ID_DELIMITER",",");
define("webdb\\index\\LINEBREAK_PLACEHOLDER","@@@@");
define("webdb\\index\\LINEBREAK_DB_DELIM","\\n");
define("webdb\\index\\LOOKUP_DISPLAY_FIELD_DELIM"," - ");
define("webdb\\index\\TEMPLATE_PLACEHOLDER_1","!~template_placeholder_1~!");
define("webdb\\index\\TEMPLATE_PLACEHOLDER_2","!~template_placeholder_2~!");
define("webdb\\index\\TEMPLATE_PLACEHOLDER_3","!~template_placeholder_3~!");

if (isset($settings)==false)
{
  $settings=array();
}

if (\webdb\cli\is_cli_mode()==true)
{
  \webdb\cli\cli_dispatch(); # cli mode doesn't have normal error/exception handlers assigned by default
  die;
}

\webdb\utils\load_settings();

if (\webdb\cli\is_cli_mode()==false)
{
  ob_start("\\webdb\\utils\\ob_postprocess");
}

set_error_handler("\\webdb\\utils\\error_handler",E_ALL);
set_exception_handler("\\webdb\\utils\\exception_handler");

$settings["request_url"]=\webdb\utils\get_url();
$settings["request_base_url"]=\webdb\utils\get_base_url();

$msg="REQUEST_RECEIVED: ".\webdb\utils\get_url();
$settings["logs"]["auth"][]=$msg;
$settings["logs"]["sql"][]=$msg;
#$settings["logs"]["sql_change"][]=$msg;

if ($settings["ip_blacklist_enabled"]==true)
{
  if (\webdb\users\remote_address_listed($_SERVER["REMOTE_ADDR"],"black")==true)
  {
    \webdb\utils\system_message("ip blacklisted: ".\webdb\utils\webdb_htmlspecialchars($_SERVER["REMOTE_ADDR"]));
  }
}
if ($settings["ip_whitelist_enabled"]==true)
{
  if (\webdb\users\remote_address_listed($_SERVER["REMOTE_ADDR"],"white")==false)
  {
    \webdb\utils\system_message("ip not whitelisted: ".\webdb\utils\webdb_htmlspecialchars($_SERVER["REMOTE_ADDR"]));
  }
}
if (\webdb\utils\is_app_mode()==false)
{
  $settings["unauthenticated_content"]=true;
  \webdb\utils\static_page("home","WebDB");
}

\webdb\utils\database_connect();

date_default_timezone_set($settings["default_timezone"]);

$settings["user_agent"]="";
if (isset($_SERVER["HTTP_USER_AGENT"])==true)
{
  $settings["user_agent"]=$_SERVER["HTTP_USER_AGENT"];
}

# poor man's browser detection
if (strpos($settings["user_agent"],"Chrome")===false)
{
  \webdb\utils\system_message("Please use the Google Chrome web browser.");
}

$settings["browser_info"]=array();
$settings["browser_info"]["browser"]="chrome"; # default to chrome settings if user agent check not enabled

if ($settings["check_ua"]==true)
{
  $ua_error=$settings["ua_error"];
  if ($settings["user_agent"]<>"")
  {
    $settings["browser_info"]=get_browser($_SERVER["HTTP_USER_AGENT"],true);
    switch (\webdb\utils\webdb_strtolower($settings["browser_info"]["browser"]))
    {
      case "chrome":
      case "firefox":
        break;
      default:
        \webdb\utils\system_message($ua_error." [neither chrome nor firefox]");
    }
    if (\webdb\utils\webdb_strtolower($settings["browser_info"]["device_type"])<>"desktop")
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

if (($settings["auth_enable"]==true) and ($settings["database_enable"]==true))
{
  \webdb\users\auth_dispatch();
}

if ((isset($_GET["update_oul"])==true) and ($settings["database_enable"]==true))
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
$field_params["group_span_style"]="";
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

if (isset($_GET["basic_search"])==true)
{
  \webdb\forms\basic_search();
}

if (isset($_GET["page"])==true)
{
  \webdb\forms\form_dispatch($_GET["page"]);
}

if ($settings["application_default_page_id"]<>"")
{
  $url=\webdb\utils\get_url();
  $url.="?page=".$settings["application_default_page_id"];
  \webdb\utils\redirect($url);
}

$home_params=array();
$home_params["user_favorites_list"]=\webdb\chat\output_user_favorites_list();
$content=\webdb\utils\template_fill($settings["app_home_template"],$home_params);
\webdb\utils\output_page($content,$settings["app_title"]);

#####################################################################################################
