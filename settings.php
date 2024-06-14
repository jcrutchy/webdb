<?php

$settings["app_name"]="WebDB";
$settings["app_title"]=$settings["app_name"];

$settings["dev_env"]=false;
$settings["dev_env_email"]="";

$settings["database_enable"]=true;
$settings["auth_enable"]=true;

if (isset($settings["app_root_path"])==false)
{
  $settings["app_root_path"]=$settings["env_root_path"];
}
$settings["app_directory_name"]=basename($settings["app_root_path"]);

$settings["ua_error"]="This application requires a recent version of Google Chrome or Mozilla Firefox on a desktop operating system.";

$settings["app_web_root"]="/".$settings["app_directory_name"]."/";
$settings["app_web_resources"]=$settings["app_web_root"]."resources/";
$settings["app_web_index"]=$settings["app_web_root"]."index.php";
$settings["app_root_namespace"]="\\".$settings["app_directory_name"]."\\";
$settings["app_templates_path"]=$settings["app_root_path"]."templates".DIRECTORY_SEPARATOR;
$settings["app_resources_path"]=$settings["app_root_path"]."resources".DIRECTORY_SEPARATOR;
$settings["app_forms_path"]=$settings["app_root_path"]."forms".DIRECTORY_SEPARATOR;

$settings["global_temp_path"]="";

$settings["application_default_page_id"]="";

$settings["file_field_image_preview_max_pix"]=100;

$settings["app_home_template"]="home";
$settings["app_date_format"]="Y-m-d";
$settings["default_timezone"]="UTC";
$settings["app_logo_filename"]="logo.png";

$settings["cli_dispatch"]=array();

$settings["sql_change_exclude_tables"]=array();
#$settings["sql_change_exclude_tables"]=true;
#$settings["sql_change_include_tables"]=array();
$settings["sql_change_include_tables"]=true;

$settings["sql_change_table"]=""; # sql_changes
$settings["sql_change_log_path"]="";
$settings["sql_change_log_enabled"]=true;

$settings["basic_search_forms"]=array();

$settings["fpdf_path"]=$settings["webdb_parent_path"]."fpdf".DIRECTORY_SEPARATOR."fpdf182".DIRECTORY_SEPARATOR;

$settings["sql_change_event_handler"]="";
$settings["error_event_handler"]="";

$settings["online_user_list_update_interval_sec"]=60;

$settings["webdb_apps_path"]=$settings["webdb_root_path"]."apps".DIRECTORY_SEPARATOR;

$settings["webdb_templates_path"]=$settings["webdb_root_path"]."templates".DIRECTORY_SEPARATOR;
$settings["webdb_resources_path"]=$settings["webdb_root_path"]."resources".DIRECTORY_SEPARATOR;
$settings["webdb_forms_path"]=$settings["webdb_root_path"]."forms".DIRECTORY_SEPARATOR;

$settings["env_templates_path"]="";

$settings["webdb_default_form"]="default";

$settings["csrf_hash_prefix"]="uwkTy+ZgSP5jaowf2ghk";
$settings["csrf_cookie_unauth"]="webdb_csrf_unauth";
$settings["csrf_cookie_auth"]="webdb_csrf_auth";
$settings["max_csrf_token_age"]=60*60*24*30;

$settings["app_test_include"]=$settings["app_root_path"]."test".DIRECTORY_SEPARATOR."test.php";

$settings["header_template"]="";
$settings["links_template"]="";
$settings["footer_template"]="";
$settings["login_notice_template"]="";

$settings["enable_page_links_templates"]=true;

$settings["login_cookie"]="webdb_login";
$settings["username_cookie"]="webdb_username";
$settings["confirm_status_cookie"]="webdb_confirm_status"; # don't change - used in list.js

$settings["max_cookie_age"]=60*60*24*365;

$settings["password_reset_timeout"]=60*60*24;
$settings["password_bcrypt_cost"]=11; # 10 is a good baseline, 13 is very difficult to crack (but slower to hash) - eventually replace with Argon2id (requires PHP 7.3)
$settings["admin_password_bcrypt_cost"]=13;
$settings["row_lock_expiration"]=60*15; # seconds

$settings["prohibited_passwords"]=array("password");
$settings["min_password_length"]=8;
$settings["max_password_length"]=400;
$settings["max_login_attempts"]=7;

$settings["admin_remote_address_whitelist"]=array("127.0.0.1","::1");

$settings["admin_email"]="";
$settings["admin_group_id"]=1;
$settings["group_admin_user_id"]=array(1);

$settings["db_host"]="host=localhost";
$settings["db_engine"]="mysql";
$settings["db_database"]="";

$settings["webdb_sql_common_path"]=$settings["webdb_root_path"]."sql_common".DIRECTORY_SEPARATOR;
$settings["webdb_sql_engine_path"]=$settings["webdb_root_path"]."sql_".$settings["db_engine"].DIRECTORY_SEPARATOR;
$settings["app_sql_common_path"]=$settings["app_root_path"]."sql_common".DIRECTORY_SEPARATOR;
$settings["app_sql_engine_path"]=$settings["app_root_path"]."sql_".$settings["db_engine"].DIRECTORY_SEPARATOR;

$settings["database_webdb"]="webdb";
$settings["database_app"]="";

$settings["sqlsrv_catalog"]=""; # setting specific to MS SQL Server (used to query INFORMATION_SCHEMA)
$settings["sqlsrv_schema"]=""; # setting specific to MS SQL Server (used to query INFORMATION_SCHEMA)

$settings["check_ua"]=true;
$settings["check_templates"]=true;

$settings["gd_ttf"]="/usr/share/fonts/truetype/msttcorefonts/arial.ttf";

$settings["webdb_web_root"]="/".$settings["webdb_directory_name"]."/";
$settings["webdb_web_resources"]=$settings["webdb_web_root"]."resources/";
$settings["webdb_web_index"]=$settings["webdb_web_root"]."index.php";

$settings["favicon_source"]=$settings["webdb_web_resources"]."favicon.png";

$settings["format_tag_templates_subdirectory"]="format_tags";

$settings["app_group_access"]="*"; # * => all groups

$settings["permitted_upload_types"]=array();
$settings["permitted_upload_types"]["pdf"]="application/pdf";
$settings["permitted_upload_types"]["txt"]="text/plain";
$settings["permitted_upload_types"]["htm"]="text/html";
$settings["permitted_upload_types"]["html"]="text/html";
$settings["permitted_upload_types"]["doc"]="application/msword";
$settings["permitted_upload_types"]["docx"]="application/vnd.openxmlformats-officedocument.wordprocessingml.document";
$settings["permitted_upload_types"]["pptx"]="application/vnd.openxmlformats-officedocument.presentationml.presentation";
$settings["permitted_upload_types"]["xls"]="application/vnd.ms-excel";
$settings["permitted_upload_types"]["xlsx"]="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
$settings["permitted_upload_types"]["jpg"]="image/jpeg";
$settings["permitted_upload_types"]["jpeg"]="image/jpeg";
$settings["permitted_upload_types"]["gif"]="image/gif";
$settings["permitted_upload_types"]["bmp"]="image/bmp";
$settings["permitted_upload_types"]["png"]="image/png";
$settings["permitted_upload_types"]["wav"]="audio/wav";
$settings["permitted_upload_types"]["mp3"]="audio/mpeg";
$settings["permitted_upload_types"]["mpeg"]="video/mpeg";
$settings["permitted_upload_types"]["msg"]="application/vnd.ms-outlook";
$settings["permitted_upload_types"]["csv"]="text/csv";

$settings["chat_global_enable"]=true;
$settings["chat_update_interval_sec"]=10;
$settings["chat_ding_file"]=$settings["webdb_web_resources"]."chat/glass.mp3";
$settings["chat_timestamp_format"]="j-M-y H:i:s";
$settings["chat_channel_prefix"]="webdb";

$settings["enable_pwd_file_encrypt"]=false;
$settings["encrypt_key_file"]=dirname($settings["webdb_parent_path"]).DIRECTORY_SEPARATOR."webdb_pwd_key.txt";

$settings["file_upload_mode"]="rename"; # rename | ftp
$settings["app_file_uploads_path"]=$settings["webdb_parent_path"]."file_uploads".DIRECTORY_SEPARATOR;
$settings["ftp_app_target_path"]="";
$settings["ftp_credentials_file"]="";
$settings["ftp_address"]="127.0.0.1";
$settings["ftp_port"]=21;
$settings["ftp_timeout"]=90;

$settings["email_enabled"]=false;
$settings["server_email_from"]="";
$settings["server_email_reply_to"]="";
$settings["server_email_bounce_to"]="";

$settings["db_admin_file"]="";
$settings["db_user_file"]="";

$settings["ip_blacklist_file"]="";
$settings["ip_whitelist_file"]="";

$settings["ip_whitelist_enabled"]=true;
$settings["ip_blacklist_enabled"]=true;

$settings["sql_log_path"]="";
$settings["auth_log_path"]="";

$settings["sql_log_enabled"]=true;
$settings["auth_log_enabled"]=true;

$settings["email_file_log_enabled"]=false;
$settings["email_file_log_path"]="";

$settings["report_proprietary_notice"]="";

$settings["ssl_cafile"]=$settings["env_root_path"]."cacert.pem";
$settings["http_user_agent"]="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36";

$settings["test_settings_file"]="webdb_test.conf";

$settings["admin_remote_address_whitelist"][]="192.168.0.50"; # add as required

$settings["login_check_address"]=true;
$settings["login_check_agent"]=true;

$settings["wiki_home_article"]="Home";
$settings["wiki_file_subdirectory"]="wiki";

$settings["irregular_plurals"]=array(); # singular => plural

# the following settings are also in list.css
$settings["list_diagonal_border_color"]="888";
$settings["list_border_color"]="888";
$settings["list_border_width"]=1;
$settings["list_group_border_color"]="000";
$settings["list_group_border_width"]=2;

\webdb\websocket\ws_default_settings();

#########################################################

$settings["dbquery_database"]="";

#########################################################
################### WEBDB PERMISSIONS ###################
#########################################################

# webdb template permissions
# $settings["permissions"]["group_name"]["templates"]["template_name"]="template_name_on_success (or empty for no substitution)";

$settings["permissions"]["admin"]["templates"]["admin_links"]="";
$settings["permissions"]["admin"]["templates"]["groups_page_link"]="";
$settings["permissions"]["admin"]["templates"]["user_groups_page_link"]="";
$settings["permissions"]["admin"]["templates"]["users_page_link"]="";
$settings["permissions"]["admin"]["templates"]["chat_users_page_link"]="";
$settings["permissions"]["admin"]["templates"]["admin_chat_page_link"]="";

# webdb form permissions
# $settings["permissions"]["group_name"]["forms"]["page_id"]="riud";
# r=read, i=insert, u=update, d=delete

$settings["permissions"]["admin"]["forms"]["groups"]="riud";
$settings["permissions"]["admin"]["forms"]["users"]="riud";
$settings["permissions"]["admin"]["forms"]["subform_group_users"]="riud";
$settings["permissions"]["admin"]["forms"]["subform_user_groups"]="riud";

$settings["permissions"]["admin"]["forms"]["admin_chat"]="riud";

$settings["permissions"]["admin"]["forms"]["webdb_forms"]="riud";

$settings["permissions"]["admin"]["forms"]["dbquery"]="ruid";

$settings["permissions"]["*"]["forms"]["rps"]="r";

#########################################################
#########################################################
