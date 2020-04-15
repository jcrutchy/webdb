<?php

$settings["app_name"]="Messenger";
$settings["app_title"]=$settings["app_name"];

$settings["app_date_format"]="j-M-y H:i:s";

$settings["app_web_root"]="/webdb/apps/".$settings["app_directory_name"]."/";
$settings["app_web_resources"]=$settings["app_web_root"]."resources/";
$settings["app_web_index"]=$settings["app_web_root"]."index.php";

$settings["header_template"]="header";
$settings["links_template"]="links";
$settings["footer_template"]="footer";
$settings["login_notice_template"]="login_notice";

$settings["favicon_source"]="/favicon.png";

$settings["controller_dispatch"]="\\messenger\\controller\\dispatch";

$settings["csrf_hash_prefix"]="eVPwCOEwD4dkkDwjv20J";

$settings["admin_password_bcrypt_cost"]=10; # TODO: use 12 or 13 for prod

$settings["database_app"]="messenger";

$settings["app_group_access"]="*";

$settings["logo_resource_file"]="/logo.png";

$fn=dirname(dirname(dirname(__DIR__))).DIRECTORY_SEPARATOR."environment_specific_settings.php";
require_once($fn);

# application-specific settings

$settings["ding_file"]=$settings["app_web_resources"]."glass.mp3";

$settings["user_list_max_age_minutes"]=5;

$settings["update_interval_seconds"]=10;

$settings["initial_channel_name"]="(lobby)";
$settings["initial_channel_topic"]="Welcome to the lobby.";
