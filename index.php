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
require_once("forms.php");
require_once("sql.php");
require_once("stubs.php");
require_once("test.php");
require_once("cli.php");

set_error_handler('\webdb\utils\error_handler',E_ALL);
set_exception_handler('\webdb\utils\exception_handler');

ob_start("\webdb\utils\ob_postprocess");

define("webdb\index\CONFIG_ID_DELIMITER",",");
define("webdb\index\LINEBREAK_PLACEHOLDER","@@@@");
define("webdb\index\LINEBREAK_DB_DELIM","\\n");

$settings=array();
$settings_ref=&$settings;

$settings["user_agent"]="";
if (isset($_SERVER["HTTP_USER_AGENT"])==true)
{
  $settings["user_agent"]=$_SERVER["HTTP_USER_AGENT"];
}

$incompatible_agents=array("trident","msie");
for ($i=0;$i<count($incompatible_agents);$i++)
{
  if (strpos(strtolower($settings["user_agent"]),$incompatible_agents[$i])!==false)
  {
    \webdb\utils\system_message("Internet Explorer is not supported. Please try a recent version of Google Chrome or Mozilla Firefox.");
  }
}

$settings["permissions"]=array();

$settings["parent_path"]=dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR;
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

\webdb\test\check_webdb_settings();

$settings["templates"]=\webdb\utils\load_files($settings["webdb_templates_path"],"","htm",true);
$settings["webdb_templates"]=$settings["templates"];

$settings["sql"]=\webdb\utils\load_files($settings["webdb_sql_path"],"","sql",true);
$settings["webdb_sql"]=$settings["sql"];

if (\webdb\cli\is_cli_mode()==false)
{
  header("Cache-Control: no-cache");
  header("Expires: -1");
  header("Pragma: no-cache");
  if (\webdb\utils\is_app_mode()==false)
  {
    $settings["unauthenticated_content"]=true;
    \webdb\utils\static_page("home","WebDB");
  }
}

# load application settings
$settings_filename=$settings["app_root_path"]."settings.php";
if (file_exists($settings_filename)==false)
{
  \webdb\utils\system_message("error: settings file not found: ".$settings_filename);
}
require_once($settings_filename);

\webdb\test\check_app_settings();

if (in_array($settings["app_directory_name"],$settings["apps_list"])==false)
{
  \webdb\utils\system_message("error: app not registered");
}

\webdb\utils\load_db_credentials("admin");
\webdb\utils\load_db_credentials("user");

\webdb\test\check_sql_settings();

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

\webdb\users\auth_dispatch();

if (isset($_GET["page"])==true)
{
  \webdb\forms\form_dispatch($_GET["page"]);
}

\webdb\utils\static_page($settings["app_home_template"],$settings["app_name"]);

#####################################################################################################
