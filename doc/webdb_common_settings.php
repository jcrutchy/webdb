<?php

$settings["apps_list"]=array("my_app1","my_app2");

$settings["app_web_root"]="/".$settings["app_directory_name"]."/";
$settings["app_web_resources"]=$settings["app_web_root"]."resources/";
$settings["app_web_index"]=$settings["app_web_root"]."index.php";
$settings["app_root_namespace"]="\\".$settings["app_directory_name"]."\\";
$settings["app_templates_path"]=$settings["app_root_path"]."templates".DIRECTORY_SEPARATOR;
$settings["app_sql_path"]=$settings["app_root_path"]."sql".DIRECTORY_SEPARATOR;
$settings["app_resources_path"]=$settings["app_root_path"]."resources".DIRECTORY_SEPARATOR;
$settings["app_forms_path"]=$settings["app_root_path"]."forms".DIRECTORY_SEPARATOR;
$settings["app_home_template"]="home";

$settings["webdb_templates_path"]=$settings["webdb_root_path"]."templates".DIRECTORY_SEPARATOR;
$settings["webdb_sql_path"]=$settings["webdb_root_path"]."sql".DIRECTORY_SEPARATOR;
$settings["webdb_resources_path"]=$settings["webdb_root_path"]."resources".DIRECTORY_SEPARATOR;
$settings["webdb_forms_path"]=$settings["webdb_root_path"]."forms".DIRECTORY_SEPARATOR;

$settings["webdb_default_form"]="default";

$settings["login_cookie"]="webdb_login";
$settings["email_cookie"]="webdb_email";
$settings["max_cookie_age"]=24*60*60*365;
$settings["password_reset_timeout"]=24*60*60;
$settings["password_bcrypt_cost"]=13;
$settings["row_lock_expiration"]=5*60;

$settings["db_host"]="localhost";
$settings["db_pwd_path"]="/home/jared/dev/pwd/";
$settings["db_admin_file"]=$settings["db_pwd_path"]."sql_admin";
$settings["db_user_file"]=$settings["db_pwd_path"]."sql_user";
$settings["gd_ttf"]="/usr/share/fonts/truetype/freefont/FreeSans.ttf";

$settings["webdb_web_root"]="/".$settings["webdb_directory_name"]."/";
$settings["webdb_web_resources"]=$settings["webdb_web_root"]."resources/";
$settings["webdb_web_index"]=$settings["webdb_web_root"]."index.php";
