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
require_once("sql.php");

set_error_handler('\webdb\utils\error_handler',E_ALL);
set_exception_handler('\webdb\utils\exception_handler');

$settings=array();
$settings["webdb_root_path"]=__DIR__.DIRECTORY_SEPARATOR;
$settings["webdb_templates_path"]=$settings["webdb_root_path"]."templates".DIRECTORY_SEPARATOR;
$settings["webdb_sql_path"]=$settings["webdb_root_path"]."sql".DIRECTORY_SEPARATOR;
$settings["webdb_resources_path"]=$settings["webdb_root_path"]."resources".DIRECTORY_SEPARATOR;

\webdb\utils\check_required_file_exists($settings["webdb_templates_path"],true);
\webdb\utils\check_required_file_exists($settings["webdb_sql_path"],true);
\webdb\utils\check_required_file_exists($settings["webdb_resources_path"],true);

$settings["templates"]=\webdb\utils\load_files($settings["webdb_templates_path"],"","htm",true);
$settings["webdb_templates"]=$settings["templates"];

$settings["sql"]=\webdb\utils\load_files($settings["webdb_sql_path"],"","sql",true);
$settings["webdb_sql"]=$settings["sql"];

$includes=get_included_files();
$settings["app_root_path"]=dirname($includes[0]).DIRECTORY_SEPARATOR;

if (\webdb\utils\is_cli_mode()==false)
{
  header("Cache-Control: no-cache");
  header("Expires: -1");
  header("Pragma: no-cache");
  if (\webdb\utils\is_app_mode()==false)
  {
    die(\webdb\utils\template_fill("home"));
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
  "app_dispatch_function",
  "db_admin_file",
  "db_user_file",
  "db_users_schema",
  "db_users_table",
  "app_dispatch_include",
  "app_templates_path",
  "app_sql_path",
  "app_resources_path",
  "app_forms_path");
for ($i=0;$i<count($required_settings);$i++)
{
  \webdb\utils\check_required_setting_exists($required_settings[$i]);
}
$required_files=array(
  "db_admin_file",
  "db_user_file",
  "app_dispatch_include");
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
  $settings["sql"][$name]=\webdb\utils\template_fill($name,false,array(),$settings["sql"]);
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
    case "init_users":
      \webdb\sql\file_execute_prepare("initialize_users",array(),true);
      \webdb\utils\system_message("users table initialized");
  }
}

$records=\webdb\sql\file_fetch_query("describe_users",true);
$field_names=array(
  "user_id",
  "login_cookie",
  "enabled",
  "email",
  "pw_hash",
  "pw_reset",
  "pw_reset_time",
  "privs");
for ($i=0;$i<count($field_names);$i++)
{
  $found=false;
  for ($j=0;$j<count($records);$j++)
  {
    if ($field_names[$i]==$records[$j]["Field"])
    {
      $found=true;
      break;
    }
  }
  if ($found==false)
  {
    \webdb\utils\system_message("error: missing required users table field: ".$field_names[$i]);
  }
}

$settings["forms"]=array();
\webdb\utils\load_form_defs();

#var_dump($settings["forms"]);
#die;

require_once($settings["app_dispatch_include"]);
if (function_exists($settings["app_dispatch_function"])==false)
{
  \webdb\utils\system_message("error: application dispatch function not found: ".$settings["app_dispatch_function"]);
}

\webdb\users\authenticate();

call_user_func($settings["app_dispatch_function"]);

#####################################################################################################
