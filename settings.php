<?php

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

$settings["links_template"]="";
$settings["footer_template"]="";

$settings["login_cookie"]="webdb_login";
$settings["email_cookie"]="webdb_email";
$settings["max_cookie_age"]=60*60*24*365;
$settings["password_reset_timeout"]=60*60*24;
$settings["password_bcrypt_cost"]=10; # 10 is a good baseline, 13 is very difficult to crack (but slower to hash)
$settings["row_lock_expiration"]=60*5;

$settings["db_host"]="localhost";

$settings["gd_ttf"]="/usr/share/fonts/truetype/msttcorefonts/arial.ttf";

$settings["webdb_web_root"]="/".$settings["webdb_directory_name"]."/";
$settings["webdb_web_resources"]=$settings["webdb_web_root"]."resources/";
$settings["webdb_web_index"]=$settings["webdb_web_root"]."index.php";

# the following settings are also in list.css
$settings["list_border_color"]="888";
$settings["list_border_width"]=1;
$settings["list_group_border_color"]="000";
$settings["list_group_border_width"]=2;

#########################################################
################### WEBDB PERMISSIONS ###################
#########################################################

# webdb template permissions
# $settings["permissions"]["group_name"]["templates"]["template_name"]="template_name_on_success (or empty for no substitution)";

$settings["permissions"]["admin"]["templates"]["groups_page_link"]="";
$settings["permissions"]["admin"]["templates"]["user_groups_page_link"]="";
$settings["permissions"]["admin"]["templates"]["users_page_link"]="";

# webdb form permissions
# $settings["permissions"]["group_name"]["forms"]["form_name"]="riud";
# r=read, i=insert, u=update, d=delete

$settings["permissions"]["admin"]["forms"]["groups"]="riud";
$settings["permissions"]["admin"]["forms"]["users"]="riud";
$settings["permissions"]["admin"]["forms"]["user_group_links"]="riud";
