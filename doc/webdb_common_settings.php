<?php

$settings["db_pwd_path"]="/home/user/dev/pwd/";
$settings["db_admin_file"]=$settings["db_pwd_path"]."sql_admin";
$settings["db_user_file"]=$settings["db_pwd_path"]."sql_user";

$settings["ip_blacklist_file"]="/home/user/dev/ip_blacklist.txt";
$settings["ip_whitelist_file"]="/home/user/dev/ip_whitelist.txt";

$settings["server_email_from"]="User <user@example.com>";
$settings["server_email_reply_to"]="User <user@example.com>";
$settings["server_email_bounce_to"]="user@example.com";

$settings["admin_remote_address_whitelist"][]="192.168.0.50"; # add as required

$settings["test_settings_file"]="/home/user/".$settings["test_settings_file"];
