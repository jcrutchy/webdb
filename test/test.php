<?php

namespace webdb\test;

#####################################################################################################

function run_tests()
{
  global $settings;
  require_once("test".DIRECTORY_SEPARATOR."test_utils.php");
  #require_once("test".DIRECTORY_SEPARATOR."w3c.php");
  system("clear");
  #$input=readline("Running tests will reinitialize the webdb database. Are you sure you want to continue? (type 'yes' to continue, press Enter or type anything else to cancel): ");
  $input="yes"; # TODO / DEBUG
  if ($input<>"yes")
  {
    \webdb\test\utils\test_info_message("testing terminated without changes to database");
    die;
  }
  \webdb\test\test_utils();
  \webdb\test\utils\test_cleanup();
  $settings["test_user_agent"]="webdb testing framework";
  \webdb\test\utils\test_info_message("CHECKING SETTINGS");
  \webdb\test\check_webdb_settings();
  \webdb\test\check_app_settings();
  \webdb\test\check_sql_settings();
  \webdb\test\utils\test_success_message("SETTINGS CHECK OK");
  if (\webdb\utils\is_app_mode()==true)
  {
    require_once("test".DIRECTORY_SEPARATOR."security.php");
    \webdb\test\security\start();
    \webdb\test\utils\test_cleanup();
    require_once("test".DIRECTORY_SEPARATOR."functional.php");
    \webdb\test\functional\run_functional_tests();
    \webdb\test\utils\test_cleanup();
    if (file_exists($settings["app_test_include"])==true)
    {
      require_once($settings["app_test_include"]);
    }
  }
  else
  {
    \webdb\test\utils\test_info_message("security testing not required for display of framework static home page");
  }
  \webdb\test\utils\test_cleanup();
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
    "csrf_cookie_unauth",
    "csrf_cookie_auth",
    "max_cookie_age",
    "max_csrf_token_age",
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
    "app_test_include",
    "webdb_default_form",
    "list_diagonal_border_color",
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
    "ip_whitelist_file",
    "sql_log_path",
    "auth_log_path",
    "irregular_plurals",
    "csrf_hash_prefix",
    "format_tag_templates_subdirectory");
  for ($i=0;$i<count($required_settings);$i++)
  {
    \webdb\test\utils\check_required_setting_exists($required_settings[$i]);
  }
  $required_files=array(
    "db_admin_file",
    "db_user_file",
    "gd_ttf",
    "app_test_include");
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

function test_utils()
{
  \webdb\test\utils\test_info_message("STARTING UTILITY TESTS...");
  $test_cases=array(
    "something_something_document"=>"something_something_documents",
    "something_something_aircraft"=>"something_something_aircraft");
  foreach ($test_cases as $correct_singular => $correct_plural)
  {
    $test_result=\webdb\utils\make_plural($correct_singular);
    $test_success=true;
    if ($test_result<>$correct_plural)
    {
      $test_success=false;
    }
    $test_case_msg="\\webdb\\utils\\make_plural(\"".$correct_singular."\")";
    \webdb\test\utils\test_result_message($test_case_msg,$test_success);
    $test_result=\webdb\utils\make_singular($correct_plural);
    $test_success=true;
    if ($test_result<>$correct_singular)
    {
      $test_success=false;
    }
    $test_case_msg="\\webdb\\utils\\make_singular(\"".$correct_plural."\")";
    \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  }
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $correct_result="correct";
  $test_input="test<test>".$correct_result."</test>test";
  $test_result=\webdb\test\utils\extract_text($test_input,"<test>","</test>");
  $test_success=true;
  if ($test_result<>$correct_result)
  {
    $test_success=false;
  }
  $test_case_msg="\\webdb\\test\\utils\\extract_text";
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $wildcard_value="1*3*5";
  $compare_value="122234445";
  $test_success=\webdb\utils\wildcard_compare($compare_value,$wildcard_value);
  $test_case_msg="\\webdb\\utils\\wildcard_compare";
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
}

#####################################################################################################
