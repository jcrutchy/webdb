<?php

namespace webdb\index;

#####################################################################################################

ini_set("display_errors","on");
ini_set("error_reporting",E_ALL);
ini_set("max_execution_time",120);
ini_set("memory_limit","512M");
date_default_timezone_set("UTC");

require_once("utils.php");
require_once("users.php");

set_error_handler('\webdb\utils\error_handler',E_ALL);
set_exception_handler('\webdb\utils\exception_handler');

$settings=array();
$settings["webdb_root_path"]=__DIR__.DIRECTORY_SEPARATOR;
$settings["webdb_templates_path"]=$settings["webdb_root_path"]."templates".DIRECTORY_SEPARATOR;
$settings["webdb_sql_path"]=$settings["webdb_root_path"]."sql".DIRECTORY_SEPARATOR;

\webdb\utils\check_required_file_exists($settings["webdb_templates_path"],true);
\webdb\utils\check_required_file_exists($settings["webdb_sql_path"],true);

$settings["templates"]=\webdb\utils\load_files($settings["webdb_templates_path"],"","htm",true);
$settings["webdb_templates"]=$settings["templates"];

$includes=get_included_files();
$settings["app_root_path"]=dirname($includes[0]).DIRECTORY_SEPARATOR;

if (\webdb\utils\is_app_mode()==true)
{
  require_once($settings["app_root_path"]."settings.php");

  \webdb\utils\check_required_setting_exists("db_schema");
  \webdb\utils\check_required_setting_exists("db_host");
  \webdb\utils\check_required_setting_exists("app_name");
  \webdb\utils\check_required_setting_exists("app_dispatch_function");

  \webdb\utils\check_required_setting_exists("db_admin_file");
  \webdb\utils\check_required_setting_exists("db_user_file");
  \webdb\utils\check_required_setting_exists("app_dispatch_include");
  \webdb\utils\check_required_setting_exists("app_templates_path");
  \webdb\utils\check_required_setting_exists("app_sql_path");
  \webdb\utils\check_required_setting_exists("app_testing_path");

  \webdb\utils\check_required_file_exists($settings["db_admin_file"]);
  \webdb\utils\check_required_file_exists($settings["db_user_file"]);
  \webdb\utils\check_required_file_exists($settings["app_dispatch_include"]);
  \webdb\utils\check_required_file_exists($settings["app_templates_path"],true);
  \webdb\utils\check_required_file_exists($settings["app_sql_path"],true);
  \webdb\utils\check_required_file_exists($settings["app_testing_path"],true);

  \webdb\utils\load_db_credentials("admin");
  \webdb\utils\load_db_credentials("user");

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

  $settings["app_templates"]=\webdb\utils\load_files($settings["app_templates_path"],"","htm",true);
  $settings["templates"]=array_merge($settings["webdb_templates"],$settings["app_templates"]);
}

if (isset($_GET["testing"])==true)
{
  include_once("testing.php");
  \webdb\testing\output_all_tests();
}

header("Cache-Control: no-cache");
header("Expires: -1");
header("Pragma: no-cache");

if (\webdb\utils\is_app_mode()==false)
{
  die(\webdb\utils\template_fill("home"));
}

require_once($settings["app_dispatch_include"]);
if (function_exists($settings["app_dispatch_function"])==false)
{
  \webdb\utils\system_message("error: application dispatch function not found: ".$settings["app_dispatch_function"]);
}

\webdb\users\authenticate();

call_user_func($settings["app_dispatch_function"]);

#####################################################################################################
