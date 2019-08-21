<?php

namespace webdb\index;

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
require_once("graphics.php");
require_once("sql.php");

set_error_handler('\webdb\utils\error_handler',E_ALL);
set_exception_handler('\webdb\utils\exception_handler');

define("webdb\index\PRIMARY_KEY_DELIMITER",",");
define("webdb\index\LINEBREAK_PLACEHOLDER","@@@@");

$settings=array();

$settings["parent_path"]=dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR;
$settings["webdb_root_path"]=__DIR__.DIRECTORY_SEPARATOR;

$includes=get_included_files();
$settings["app_root_path"]=dirname($includes[0]).DIRECTORY_SEPARATOR;

$settings["webdb_directory_name"]=basename($settings["webdb_root_path"]);
$settings["app_directory_name"]=basename($settings["app_root_path"]);

$common_settings_filename=$settings["parent_path"]."webdb_common_settings.php";
if (file_exists($common_settings_filename)==true)
{
  require_once($common_settings_filename);
}
else
{
  \webdb\utils\system_message("error: webdb common settings file not found");
}

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

\webdb\utils\check_required_file_exists($settings["webdb_templates_path"],true);
\webdb\utils\check_required_file_exists($settings["webdb_sql_path"],true);
\webdb\utils\check_required_file_exists($settings["webdb_resources_path"],true);
\webdb\utils\check_required_file_exists($settings["webdb_forms_path"],true);

$settings["templates"]=\webdb\utils\load_files($settings["webdb_templates_path"],"","htm",true);
$settings["webdb_templates"]=$settings["templates"];

$settings["sql"]=\webdb\utils\load_files($settings["webdb_sql_path"],"","sql",true);
$settings["webdb_sql"]=$settings["sql"];

if (\webdb\utils\is_cli_mode()==false)
{
  header("Cache-Control: no-cache");
  header("Expires: -1");
  header("Pragma: no-cache");
  if (\webdb\utils\is_app_mode()==false)
  {
    \webdb\utils\static_page("home","WebDB");
  }
}

# TODO: MOVE SETTINGS VALIDATIONS INTO A TESTING ROUTINE (DON'T RUN EVERY REQUEST)

$settings_filename=$settings["app_root_path"]."settings.php";
if (file_exists($settings_filename)==false)
{
  \webdb\utils\system_message("error: settings file not found: ".$settings_filename);
}
require_once($settings_filename);
$required_settings=array(
  "db_host",
  "app_name",
  "webdb_web_root",
  "webdb_web_index",
  "webdb_web_resources",
  "app_web_root",
  "app_web_index",
  "app_web_resources",
  "app_root_namespace",
  "login_cookie",
  "email_cookie",
  "max_cookie_age",
  "password_reset_timeout",
  "row_lock_expiration",
  "app_home_template",
  "db_admin_file",
  "db_user_file",
  "app_templates_path",
  "app_sql_path",
  "app_resources_path",
  "app_forms_path",
  "gd_ttf",
  "apps_list",
  "webdb_default_form");
for ($i=0;$i<count($required_settings);$i++)
{
  \webdb\utils\check_required_setting_exists($required_settings[$i]);
}
if (in_array($settings["app_directory_name"],$settings["apps_list"])==false)
{
  \webdb\utils\system_message("error: app not registered in common settings apps list");
}
$required_files=array(
  "db_admin_file",
  "db_user_file",
  "gd_ttf");
for ($i=0;$i<count($required_files);$i++)
{
  $file=$required_files[$i];
  \webdb\utils\check_required_file_exists($settings[$file]);
}
$required_paths=array(
  "app_templates_path",
  "app_sql_path",
  "app_resources_path",
  "app_forms_path");
for ($i=0;$i<count($required_paths);$i++)
{
  $path=$required_paths[$i];
  \webdb\utils\check_required_file_exists($settings[$path],true);
}
\webdb\utils\load_db_credentials("admin");
\webdb\utils\load_db_credentials("user");
$required_settings=array(
  "db_admin_username",
  "db_admin_password",
  "db_user_username",
  "db_user_password");
for ($i=0;$i<count($required_settings);$i++)
{
  \webdb\utils\check_required_setting_exists($required_settings[$i]);
}

$settings["app_templates"]=\webdb\utils\load_files($settings["app_templates_path"],"","htm",true);
$settings["templates"]=array_merge($settings["webdb_templates"],$settings["app_templates"]);

$settings["app_sql"]=\webdb\utils\load_files($settings["app_sql_path"],"","sql",true);
$settings["sql"]=array_merge($settings["webdb_sql"],$settings["app_sql"]);

# process setting templates in sql
foreach ($settings["sql"] as $name => $sql)
{
  $settings["sql"][$name]=\webdb\utils\sql_fill($name);
}

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

if (isset($argv[1])==true)
{
  switch ($argv[1])
  {
    case "load_webdb_schema":
      \webdb\sql\file_execute_prepare("webdb_schema",array(),true);
      \webdb\utils\system_message("webdb schema created");
    case "load_app_schema":
      $filename=$settings["app_sql_path"]."schema.sql";
      if (file_exists($filename)==true)
      {
        $sql=trim(file_get_contents($filename));
        \webdb\sql\execute_prepare($sql,array(),"",true);
        \webdb\utils\system_message("app schema created");
      }
      else
      {
        \webdb\utils\system_message("error: schema file not found: ".$filename);
      }
  }
}

$settings["forms"]=array();
\webdb\forms\load_form_defs();

\webdb\users\auth_dispatch();

if (isset($_GET["page"])==true)
{
  \webdb\forms\form_dispatch($_GET["page"]);
}

\webdb\utils\app_static_page($settings["app_home_template"],$settings["app_name"]);

#####################################################################################################
