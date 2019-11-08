<?php

namespace webdb\test;

#####################################################################################################

function run_tests()
{
  global $settings;
  require_once("test".DIRECTORY_SEPARATOR."test_utils.php");
  system("clear");
  $settings["test_user_agent"]="webdb testing framework";
  /*\webdb\test\utils\test_info_message("CHECKING SETTINGS");
  \webdb\test\check_webdb_settings();
  \webdb\test\check_app_settings();
  \webdb\test\check_sql_settings();
  \webdb\test\utils\test_success_message("SETTINGS CHECK OK");*/
  require_once("test".DIRECTORY_SEPARATOR."security.php");
  \webdb\test\security\start();
}

#####################################################################################################

function check_webdb_settings()
{
  global $settings;
  $required_settings=array(
    "webdb_templates_path",
    "webdb_sql_path",
    "webdb_resources_path",
    "webdb_forms_path");
  for ($i=0;$i<count($required_settings);$i++)
  {
    \webdb\test\utils\check_required_setting_exists($required_settings[$i]);
  }
  \webdb\test\utils\check_required_file_exists($settings["webdb_templates_path"],true);
  \webdb\test\utils\check_required_file_exists($settings["webdb_sql_path"],true);
  \webdb\test\utils\check_required_file_exists($settings["webdb_resources_path"],true);
  \webdb\test\utils\check_required_file_exists($settings["webdb_forms_path"],true);
}

#####################################################################################################

function check_app_settings()
{
  global $settings;
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
    "app_date_format",
    "login_cookie",
    "username_cookie",
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
    "sql_log_path",
    "auth_log_path",
    "gd_ttf",
    "webdb_default_form",
    "list_border_color",
    "list_border_width",
    "list_group_border_color",
    "list_group_border_width",
    "links_template",
    "footer_template",
    "server_email_from",
    "server_email_reply_to",
    "server_email_bounce_to",
    "prohibited_passwords",
    "min_password_length",
    "max_login_attempts",
    "admin_remote_address_whitelist",
    "test_settings_file",
    "ip_blacklist_file",
    "ip_whitelist_file");
  for ($i=0;$i<count($required_settings);$i++)
  {
    \webdb\test\utils\check_required_setting_exists($required_settings[$i]);
  }
  $required_files=array(
    "db_admin_file",
    "db_user_file",
    "gd_ttf");
  for ($i=0;$i<count($required_files);$i++)
  {
    $file=$required_files[$i];
    \webdb\test\utils\check_required_file_exists($settings[$file]);
  }
  $required_paths=array(
    "app_templates_path",
    "app_sql_path",
    "app_resources_path",
    "app_forms_path",
    "sql_log_path",
    "auth_log_path");
  for ($i=0;$i<count($required_paths);$i++)
  {
    $path=$required_paths[$i];
    \webdb\test\utils\check_required_file_exists($settings[$path],true);
  }
}

#####################################################################################################

function check_sql_settings()
{
  $required_settings=array(
    "db_admin_username",
    "db_admin_password",
    "db_user_username",
    "db_user_password");
  for ($i=0;$i<count($required_settings);$i++)
  {
    \webdb\test\utils\check_required_setting_exists($required_settings[$i]);
  }
}

#####################################################################################################
