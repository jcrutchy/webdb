<?php

namespace messenger\utils;

#####################################################################################################

function get_user_record()
{
  global $settings;
  $user_record=get_user_record_by_id($settings["user_record"]["user_id"]);
  if ($user_record===false)
  {
    $value_items=array();
    $value_items["user_id"]=$settings["user_record"]["user_id"];
    $value_items["nick"]=$settings["user_record"]["username"];
    $channel_record=\messenger\utils\get_channel_record_by_name($settings["initial_channel_name"],$settings["initial_channel_topic"]);
    $value_items["selected_channel_id"]=$channel_record["channel_id"];
    $settings["sql_check_post_params_override"]=true;
    \webdb\sql\sql_insert($value_items,"users","messenger");
    \webdb\utils\redirect($settings["app_web_index"]."?channel=".$settings["initial_channel_name"]);
  }
  return $user_record;
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

function get_channel_record_by_name($channel_name,$topic)
{
  global $settings;
  $where_items=array();
  $where_items["channel_name"]=$channel_name;
  $records=\webdb\sql\file_fetch_prepare("get_channel_record_by_name",$where_items);
  if (count($records)<>1)
  {
    $value_items=$where_items;
    $value_items["topic"]=$topic;
    $settings["sql_check_post_params_override"]=true;
    \webdb\sql\sql_insert($value_items,"channels","messenger");
    $records=\webdb\sql\file_fetch_prepare("get_channel_record_by_name",$where_items);
  }
  return $records[0];
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

function get_new_message_records()
{
  global $settings;
  $where_items=array();
  $where_items["user_id"]=$settings["user_record"]["user_id"];
  return \webdb\sql\file_fetch_prepare("get_new_message_records",$where_items);
}

#####################################################################################################
