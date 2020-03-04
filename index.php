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
require_once("users.php");
require_once("csrf.php");
require_once("forms.php");
require_once("sql.php");
require_once("stubs.php");
require_once("cli.php");

set_error_handler("\\webdb\\utils\\error_handler",E_ALL);
set_exception_handler("\\webdb\\utils\\exception_handler");

define("webdb\\index\\CONFIG_ID_DELIMITER",",");
define("webdb\\index\\LINEBREAK_PLACEHOLDER","@@@@");
define("webdb\\index\\LINEBREAK_DB_DELIM","\\n");
define("webdb\\index\\LOOKUP_DISPLAY_FIELD_DELIM"," - ");

if (\webdb\cli\is_cli_mode()==false)
{
  ob_start("\\webdb\\utils\\ob_postprocess");
}

$settings=array();

$includes=get_included_files();
$settings["app_root_path"]=dirname($includes[0]).DIRECTORY_SEPARATOR;

\webdb\utils\build_settings();

/*$settings_cache_filename=$settings["app_root_path"]."settings.cache";

if (file_exists($settings_cache_filename)==false)
{
  \webdb\utils\build_settings();
  $settings_cache_data=json_encode($settings,JSON_PRETTY_PRINT);
  file_put_contents($settings_cache_filename,$settings_cache_data);
}
else
{
  $settings_cache_data=file_get_contents($settings_cache_filename);
  $settings=json_decode($settings_cache_data,true);
}*/

#$settings["constants"]=get_defined_constants(false); # BAD FOR PERFORMANCE OF TEMPLATE_FILL FUNCTION
$settings["constants"]=array();
$settings["constants"]["DIRECTORY_SEPARATOR"]=DIRECTORY_SEPARATOR;

\webdb\utils\load_test_settings();

if (\webdb\cli\is_cli_mode()==false)
{
  $msg="REQUEST_RECEIVED: ".\webdb\utils\get_url();
  $settings["logs"]["auth"][]=$msg;
  $settings["logs"]["sql"][]=$msg;
  header("Cache-Control: no-cache");
  header("Expires: -1");
  header("Pragma: no-cache");
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
}

$db_database="";
if ($settings["db_database"]<>"")
{
  $db_database=";".$settings["db_database"];
}

$settings["pdo_admin"]=new \PDO($settings["db_engine"].":".$settings["db_host"].$db_database,$settings["db_admin_username"],$settings["db_admin_password"]);
if ($settings["pdo_admin"]===false)
{
  \webdb\utils\system_message("error: unable to connect to sql server as admin");
}
$settings["pdo_user"]=new \PDO($settings["db_engine"].":".$settings["db_host"].$db_database,$settings["db_user_username"],$settings["db_user_password"]);
if ($settings["pdo_user"]===false)
{
  \webdb\utils\system_message("error: unable to connect to sql server as user");
}

if (\webdb\cli\is_cli_mode()==true)
{
  \webdb\cli\cli_dispatch();
}

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

\webdb\csrf\check_unauthenticated_csrf_token();
\webdb\users\auth_dispatch();
\webdb\csrf\check_authenticated_csrf_token();

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
