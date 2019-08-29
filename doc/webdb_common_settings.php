<?php

$settings["apps_list"]=array("mdo_risks","messenger");

$settings["app_web_root"]="/".$settings["app_directory_name"]."/";
$settings["app_web_resources"]=$settings["app_web_root"]."resources/";
$settings["app_web_index"]=$settings["app_web_root"]."index.php";
$settings["app_root_namespace"]="\\".$settings["app_directory_name"]."\\";
$settings["app_templates_path"]=$settings["app_root_path"]."templates".DIRECTORY_SEPARATOR;
$settings["app_sql_path"]=$settings["app_root_path"]."sql".DIRECTORY_SEPARATOR;
$settings["app_resources_path"]=$settings["app_root_path"]."resources".DIRECTORY_SEPARATOR;
$settings["app_forms_path"]=$settings["app_root_path"]."forms".DIRECTORY_SEPARATOR;
$settings["app_home_template"]="home";
$settings["app_date_format"]="Y-m-d";
$settings["app_logo_filename"]="logo.png";

$settings["webdb_templates_path"]=$settings["webdb_root_path"]."templates".DIRECTORY_SEPARATOR;
$settings["webdb_sql_path"]=$settings["webdb_root_path"]."sql".DIRECTORY_SEPARATOR;
$settings["webdb_resources_path"]=$settings["webdb_root_path"]."resources".DIRECTORY_SEPARATOR;
$settings["webdb_forms_path"]=$settings["webdb_root_path"]."forms".DIRECTORY_SEPARATOR;

$settings["webdb_default_form"]="default";

$settings["login_cookie"]="webdb_login";
$settings["email_cookie"]="webdb_email";
$settings["max_cookie_age"]=60*60*24*365;
$settings["password_reset_timeout"]=60*60*24;
$settings["password_bcrypt_cost"]=13;
$settings["row_lock_expiration"]=60*5;

$settings["db_host"]="localhost";
$settings["db_pwd_path"]="/home/jared/dev/pwd/";
$settings["db_admin_file"]=$settings["db_pwd_path"]."sql_admin";
$settings["db_user_file"]=$settings["db_pwd_path"]."sql_user";
$settings["gd_ttf"]="/usr/share/fonts/truetype/msttcorefonts/arial.ttf";

$settings["webdb_web_root"]="/".$settings["webdb_directory_name"]."/";
$settings["webdb_web_resources"]=$settings["webdb_web_root"]."resources/";
$settings["webdb_web_index"]=$settings["webdb_web_root"]."index.php";

$settings["pdf_temp_path"]="/home/jared/dev/temp/";
