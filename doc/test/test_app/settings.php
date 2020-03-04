<?php

$settings["db_pwd_path"]="/home/jared/dev/pwd/";
$settings["db_admin_file"]=$settings["db_pwd_path"]."sql_admin";
$settings["db_user_file"]=$settings["db_pwd_path"]."sql_user";

$settings["ip_blacklist_file"]="/home/jared/dev/public/ip_blacklist.txt";
$settings["ip_whitelist_file"]="/home/jared/dev/public/ip_whitelist.txt";

$settings["sql_log_path"]="/home/jared/dev/log/";
$settings["auth_log_path"]="/home/jared/dev/log/";

$settings["server_email_from"]="User <user@example.com>";
$settings["server_email_reply_to"]="User <user@example.com>";
$settings["server_email_bounce_to"]="user@example.com";

$settings["admin_remote_address_whitelist"][]="192.168.43.210"; # add as required

$settings["test_settings_file"]="/home/jared/".$settings["test_settings_file"];

#################################################################################

$settings["app_name"]="webdb test app";
$settings["app_date_format"]="j-M-y";

$settings["links_template"]="links";
$settings["footer_template"]="footer";

$settings["permissions"]["admin"]["forms"]["locations"]="riud";
$settings["permissions"]["test_group"]["forms"]["locations"]="riud";

$settings["app_web_root"]="/webdb/doc/test/".$settings["app_directory_name"]."/";
$settings["app_web_resources"]=$settings["app_web_root"]."resources/";
$settings["app_web_index"]=$settings["app_web_root"]."index.php";

$settings["favicon_source"]=$settings["app_web_resources"]."favicon.png";

$settings["app_logo_filename"]="favicon.png";

$settings["csrf_hash_prefix"]="g7qbz62.Og";
