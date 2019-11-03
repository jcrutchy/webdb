<?php

$settings["apps_list"]=array("my_app");

$settings["db_pwd_path"]="/home/user/dev/pwd/";
$settings["db_admin_file"]=$settings["db_pwd_path"]."sql_admin";
$settings["db_user_file"]=$settings["db_pwd_path"]."sql_user";

$settings["sql_log_path"]="/home/user/dev/log/";
$settings["auth_log_path"]="/home/user/dev/log/";

$settings["server_email_from"]="User <user@example.com>";
$settings["server_email_reply_to"]="User <user@example.com>";
$settings["server_email_bounce_to"]="user@example.com";

$settings["admin_remote_address_whitelist"][]="192.168.0.50"; # add as required

$settings["test_settings_file"]="/home/user/".$settings["test_settings_file"];
