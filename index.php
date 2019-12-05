<?php

namespace webdb\index;

#$t=microtime(true);

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

set_error_handler('\webdb\utils\error_handler',E_ALL);
set_exception_handler('\webdb\utils\exception_handler');

define("webdb\\index\\CONFIG_ID_DELIMITER",",");
define("webdb\\index\\LINEBREAK_PLACEHOLDER","@@@@");
define("webdb\\index\\LINEBREAK_DB_DELIM","\\n");
define("webdb\\index\\LOOKUP_DISPLAY_FIELD_DELIM"," - ");

if (\webdb\cli\is_cli_mode()==false)
{
  ob_start("\webdb\utils\ob_postprocess");
}

$settings=array();

$settings["logs"]=array();
$settings["logs"]["sql"]=array();
$settings["logs"]["auth"]=array();

$settings["sql_check_post_params_override"]=false;
$settings["sql_database_change"]=false;
$settings["calendar_fields"]=array();
$settings["permissions"]=array();

$settings["parent_path"]=dirname(__DIR__).DIRECTORY_SEPARATOR;
$settings["webdb_root_path"]=__DIR__.DIRECTORY_SEPARATOR;

$includes=get_included_files();
$settings["app_root_path"]=dirname($includes[0]).DIRECTORY_SEPARATOR;

$settings["webdb_directory_name"]=basename($settings["webdb_root_path"]);
$settings["app_directory_name"]=basename($settings["app_root_path"]);

# load webdb settings
$webdb_settings_filename=$settings["webdb_root_path"]."settings.php";
if (file_exists($webdb_settings_filename)==true)
{
  require_once($webdb_settings_filename);
}
else
{
  \webdb\utils\system_message("error: webdb settings file not found");
}

# load common application settings
$common_settings_filename=$settings["parent_path"]."webdb_common_settings.php";
if (file_exists($common_settings_filename)==true)
{
  require_once($common_settings_filename);
}
else
{
  \webdb\utils\system_message("error: webdb common settings file not found");
}

\webdb\utils\load_test_settings();

$settings["templates"]=\webdb\utils\load_files($settings["webdb_templates_path"],"","htm",true);
$settings["webdb_templates"]=$settings["templates"];

$settings["sql"]=\webdb\utils\load_files($settings["webdb_sql_path"],"","sql",true);
$settings["webdb_sql"]=$settings["sql"];

# load application settings
$settings_filename=$settings["app_root_path"]."settings.php";
if (file_exists($settings_filename)==false)
{
  \webdb\utils\system_message("error: settings file not found: ".$settings_filename);
}
require_once($settings_filename);

if (\webdb\cli\is_cli_mode()==false)
{
  header("Cache-Control: no-cache");
  header("Expires: -1");
  header("Pragma: no-cache");
  if (\webdb\users\remote_address_listed($_SERVER["REMOTE_ADDR"],"black")==true)
  {
    \webdb\utils\system_message("ip blacklisted: ".htmlspecialchars($_SERVER["REMOTE_ADDR"]));
  }
  if (\webdb\users\remote_address_listed($_SERVER["REMOTE_ADDR"],"white")==false)
  {
    \webdb\utils\system_message("ip not whitelisted: ".htmlspecialchars($_SERVER["REMOTE_ADDR"]));
  }
  if (\webdb\utils\is_app_mode()==false)
  {
    \webdb\csrf\generate_csrf_token();
    $settings["unauthenticated_content"]=true;
    \webdb\utils\static_page("home","WebDB");
  }
}

\webdb\utils\load_db_credentials("admin");
\webdb\utils\load_db_credentials("user");

$settings["app_templates"]=\webdb\utils\load_files($settings["app_templates_path"],"","htm",true);
$settings["templates"]=array_merge($settings["webdb_templates"],$settings["app_templates"]);

$settings["app_sql"]=\webdb\utils\load_files($settings["app_sql_path"],"","sql",true);
$settings["sql"]=array_merge($settings["webdb_sql"],$settings["app_sql"]);

$settings["pdo_admin"]=new \PDO("mysql:host=".$settings["db_host"],$settings["db_admin_username"],$settings["db_admin_password"]);
if ($settings["pdo_admin"]===false)
{
  \webdb\utils\system_message("error: unable to connect to sql server as admin");
}
$settings["pdo_user"]=new \PDO("mysql:host=".$settings["db_host"],$settings["db_user_username"],$settings["db_user_password"]);
if ($settings["pdo_user"]===false)
{
  \webdb\utils\system_message("error: unable to connect to sql server as user");
}

$settings["forms"]=array();
\webdb\forms\load_form_defs();

if (\webdb\cli\is_cli_mode()==true)
{
  \webdb\cli\cli_dispatch();
}

$settings["user_agent"]="";
$settings["browser_info"]=array();
$settings["browser_info"]["browser"]="";
$ua_error=\webdb\utils\template_fill("user_agent_error");
if (isset($_SERVER["HTTP_USER_AGENT"])==true)
{
  $settings["user_agent"]=$_SERVER["HTTP_USER_AGENT"];
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

\webdb\csrf\check_csrf_token();
\webdb\users\auth_dispatch();
\webdb\csrf\check_csrf_token();
\webdb\csrf\generate_csrf_token();

if (isset($_GET["page"])==true)
{
  \webdb\forms\form_dispatch($_GET["page"]);
}

\webdb\utils\static_page($settings["app_home_template"],$settings["app_name"]);

#####################################################################################################
