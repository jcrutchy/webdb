<?php

namespace webdb\test;

#####################################################################################################

function run_tests()
{
  global $settings;
  require_once("test".DIRECTORY_SEPARATOR."test_utils.php");
  #require_once("test".DIRECTORY_SEPARATOR."w3c.php");
  system("clear");
  #$input=readline("Running tests will reinitialize the webdb and test_app databases. Are you sure you want to continue? (type 'yes' to continue, press Enter or type anything else to cancel): ");
  $input="yes"; # TODO / DEBUG
  if ($input<>"yes")
  {
    \webdb\test\utils\test_info_message("testing terminated without changes to databases");
    die;
  }
  \webdb\test\test_utils();
  \webdb\test\utils\test_cleanup();
  $settings["test_user_agent"]="webdb testing framework";
  \webdb\test\utils\test_info_message("CHECKING SETTINGS...");
  \webdb\test\check_settings();
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

function check_settings()
{
  global $settings;
  $required_settings=array(
    "cli_dispatch",
    "group_admin_user_id",
    "ua_error",
    "sql_change_table",
    "sql_change_log_path",
    "sql_change_log_enabled",
    "wiki_home_article",
    "wiki_file_subdirectory",
    "basic_search_forms",
    "sql_change_exclude_tables",
    "sql_change_include_tables",
    "fpdf_path",
    "sql_change_event_handler",
    "error_event_handler",
    "env_root_path",
    "app_root_path",
    "app_directory_name",
    "webdb_root_path",
    "webdb_parent_path",
    "file_upload_mode",
    "app_file_uploads_path",
    "ftp_app_target_path",
    "enable_pwd_file_encrypt",
    "encrypt_key_file",
    "ftp_credentials_file",
    "ftp_address",
    "ftp_port",
    "ftp_timeout",
    "ftp_credentials_username",
    "ftp_credentials_password",
    "app_root_path",
    "db_host",
    "db_engine",
    "db_database",
    "db_admin_username",
    "db_admin_password",
    "db_user_username",
    "db_user_password",
    "app_name",
    "app_title",
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
    "webdb_templates_path",
    "app_templates_path",
    "webdb_resources_path",
    "app_resources_path",
    "webdb_forms_path",
    "app_forms_path",
    "gd_ttf",
    "app_test_include",
    "webdb_default_form",
    "list_diagonal_border_color",
    "list_border_color",
    "list_border_width",
    "list_group_border_color",
    "list_group_border_width",
    "header_template",
    "links_template",
    "footer_template",
    "login_notice_template",
    "server_email_from",
    "server_email_reply_to",
    "server_email_bounce_to",
    "prohibited_passwords",
    "min_password_length",
    "max_login_attempts",
    "admin_remote_address_whitelist",
    "admin_email",
    "test_settings_file",
    "ip_blacklist_file",
    "ip_whitelist_file",
    "ip_whitelist_enabled",
    "ip_blacklist_enabled",
    "sql_log_path",
    "auth_log_path",
    "sql_log_enabled",
    "auth_log_enabled",
    "irregular_plurals",
    "csrf_hash_prefix",
    "format_tag_templates_subdirectory",
    "check_ua",
    "check_templates",
    "database_webdb",
    "database_app",
    "sqlsrv_catalog",
    "sqlsrv_schema",
    "app_group_access",
    "webdb_sql_common_path",
    "webdb_sql_engine_path",
    "app_sql_common_path",
    "app_sql_engine_path",
    "permitted_upload_types",
    "chat_update_interval_sec",
    "chat_ding_file",
    "chat_timestamp_format",
    "chat_global_enable",
    "chat_channel_prefix",
    "online_user_list_update_interval_sec");
  for ($i=0;$i<count($required_settings);$i++)
  {
    \webdb\test\utils\check_required_setting_exists($required_settings[$i]);
  }
  $required_files=array(
    "db_admin_file",
    "db_user_file",
    "encrypt_key_file",
    "gd_ttf",
    "app_test_include");
  for ($i=0;$i<count($required_files);$i++)
  {
    $file=$required_files[$i];
    \webdb\test\utils\check_required_file_exists($settings[$file]);
  }
  $required_paths=array(
    "fpdf_path",
    "app_root_path",
    "webdb_root_path",
    "webdb_templates_path",
    "app_templates_path",
    "webdb_sql_common_path",
    "webdb_sql_engine_path",
    "app_sql_common_path",
    "app_sql_engine_path",
    "webdb_resources_path",
    "app_resources_path",
    "webdb_forms_path",
    "app_forms_path",
    "sql_log_path",
    "auth_log_path",
    "app_file_uploads_path");
  for ($i=0;$i<count($required_paths);$i++)
  {
    $path=$required_paths[$i];
    \webdb\test\utils\check_required_file_exists($settings[$path],true);
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
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $response=file_get_contents(__DIR__.DIRECTORY_SEPARATOR."template_compare_test_input");
  $template="login_form";
  $test_success=\webdb\utils\compare_template($template,$response);
  $test_case_msg="\\webdb\\test\\utils\\compare_form_template";
  \webdb\test\utils\test_result_message($test_case_msg,$test_success);
}

#####################################################################################################
