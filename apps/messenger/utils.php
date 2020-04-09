<?php

namespace messenger\utils;

#####################################################################################################

function sql_to_iso_timestamp($timestamp)
{
  $timestamp=strtotime($timestamp);
  return date("c",$timestamp);
}

#####################################################################################################

function get_logged_in_user_record()
{
  global $settings;
  $user_record=get_user_record_by_id($settings["user_record"]["user_id"]);
  if ($user_record===false)
  {
    $channel_record=\messenger\utils\get_channel_by_name($settings["initial_channel_name"]);
    \messenger\utils\register_user($settings["user_record"]["user_id"],$settings["user_record"]["username"],$channel_record["channel_id"]);
    $user_record=get_user_record_by_id($settings["user_record"]["user_id"]);
    \messenger\utils\join_channel($channel_record["channel_id"],$user_record["user_id"]);
  }
  return $user_record;
}

#####################################################################################################

function register_user($user_id,$nick,$selected_channel_id)
{
  global $settings;
  $value_items=array();
  $value_items["user_id"]=$user_id;
  $value_items["nick"]=$nick;
  $value_items["selected_channel_id"]=$selected_channel_id;
  $settings["sql_check_post_params_override"]=true;
  \webdb\sql\sql_insert($value_items,"messenger_users",$settings["database_app"]);
}

#####################################################################################################

function update_user($user_record)
{
  global $settings;
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  $value_items=array();
  $value_items["enabled"]=$user_record["enabled"];
  $value_items["nick"]=$user_record["nick"];
  $value_items["selected_channel_id"]=$user_record["selected_channel_id"];
  $value_items["last_online"]=\webdb\sql\current_sql_timestamp();
  $settings["sql_check_post_params_override"]=true;
  \webdb\sql\sql_update($value_items,$where_items,"messenger_users",$settings["database_app"]);
}

#####################################################################################################

function get_user_record_by_id($user_id)
{
  $where_items=array();
  $where_items["user_id"]=$user_id;
  $records=\webdb\sql\file_fetch_prepare("get_user_record_by_id",$where_items);
  if (count($records)<>1)
  {
    return false;
  }
  return $records[0];
}

#####################################################################################################

function update_channel($channel_record)
{
  global $settings;
  $where_items=array();
  $where_items["channel_id"]=$channel_record["channel_id"];
  $value_items=array();
  $value_items["enabled"]=$channel_record["enabled"];
  $value_items["channel_name"]=$channel_record["channel_name"];
  $value_items["topic"]=$channel_record["topic"];
  $settings["sql_check_post_params_override"]=true;
  \webdb\sql\sql_update($value_items,$where_items,"messenger_channels",$settings["database_app"]);
}

#####################################################################################################

function get_channel_by_name($channel_name)
{
  $channel_name=\messenger\utils\strip_text($channel_name," _#.*@[](){}");
  $where_items=array();
  $where_items["channel_name"]=$channel_name;
  $records=\webdb\sql\file_fetch_prepare("get_channel_record_by_name",$where_items);
  if (count($records)==0)
  {
    \messenger\utils\register_channel($channel_name);
    $records=\webdb\sql\file_fetch_prepare("get_channel_record_by_name",$where_items);
  }
  return $records[0];
}

#####################################################################################################

function strip_text($value,$additional_valid_chars="")
{
  $valid_chars="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789".$additional_valid_chars;
  $result="";
  for ($i=0;$i<strlen($value);$i++)
  {
    if (strpos($valid_chars,$value[$i])!==false)
    {
      $result=$result.$value[$i];
    }
  }
  return $result;
}

#####################################################################################################

function register_channel($channel_name,$topic="")
{
  global $settings;
  $channel_name=\messenger\utils\strip_text($channel_name,"_#.*@[](){}");
  $value_items=array();
  $value_items["channel_name"]=$channel_name;
  $records=\webdb\sql\file_fetch_prepare("get_channel_record_by_name",$value_items);
  if (count($records)>0)
  {
    return;
  }
  $value_items["topic"]=$topic;
  if ($channel_name==$settings["initial_channel_name"])
  {
    $value_items["topic"]=$settings["initial_channel_topic"];
  }
  $settings["sql_check_post_params_override"]=true;
  \webdb\sql\sql_insert($value_items,"messenger_channels",$settings["database_app"]);
}

#####################################################################################################

function purge_unused_channels()
{

}

#####################################################################################################

function get_channel_record_by_id($channel_id)
{
  $where_items=array();
  $where_items["channel_id"]=$channel_id;
  $records=\webdb\sql\file_fetch_prepare("get_channel_record_by_id",$where_items);
  return $records[0];
}

#####################################################################################################

function join_channel($channel_id,$user_id)
{
  global $settings;
  $value_items=array();
  $value_items["channel_id"]=$channel_id;
  $value_items["user_id"]=$user_id;
  $records=\webdb\sql\file_fetch_prepare("get_joined_channel",$value_items);
  if (count($records)>0)
  {
    return;
  }
  $settings["sql_check_post_params_override"]=true;
  \webdb\sql\sql_insert($value_items,"messenger_channel_users",$settings["database_app"]);
}

#####################################################################################################

function get_new_message_records($user_record,$channel_record)
{
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  $where_items["channel_id"]=$channel_record["channel_id"];
  return \webdb\sql\file_fetch_prepare("get_new_message_records",$where_items);
}

#####################################################################################################

function get_users()
{
  return \webdb\sql\file_fetch_prepare("get_users");
}

#####################################################################################################

function get_channels()
{
  return \webdb\sql\file_fetch_prepare("get_channels");
}

#####################################################################################################

function update_last_read_message($user_record,$channel_record,$max_message_id=0)
{
  global $settings;
  $where_items=array();
  $where_items["channel_id"]=$channel_record["channel_id"];
  $where_items["user_id"]=$user_record["user_id"];
  $value_items=array();
  $value_items["last_read_message_id"]=$max_message_id;
  $settings["sql_check_post_params_override"]=true;
  \webdb\sql\sql_update($value_items,$where_items,"messenger_channel_users",$settings["database_app"]);
}

#####################################################################################################

function save_message($user_record,$channel_record,$message)
{
  $value_items=array();
  $value_items["user_id"]=$user_record["user_id"];
  $value_items["channel_id"]=$channel_record["channel_id"];
  $value_items["message"]=$message;
  \webdb\sql\sql_insert($value_items,"messenger_messages",$settings["database_app"]);
}

#####################################################################################################
