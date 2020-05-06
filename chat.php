<?php

namespace webdb\chat;

#####################################################################################################

function get_chat($form_params)
{
/*
$settings["chat_update_interval_sec"]=10;
$settings["chat_ding_file"]=$settings["app_web_resources"]."glass.mp3";
$settings["chat_timestamp_format"]="j-M-y H:i:s";
*/
  return \webdb\utils\template_fill("chat/chat",$form_params);
}

#####################################################################################################

function dispatch()
{
  global $settings;
  if ($settings["db_engine"]=="mysql")
  {
    \webdb\sql\file_execute_prepare("timezone_set");
  }
  $user_record=\messenger\utils\get_logged_in_user_record();
  $channel_record=\messenger\utils\get_channel_record_by_id($user_record["selected_channel_id"]);
  if (isset($_GET["channel"])==true)
  {
    $channel_record=\messenger\utils\get_channel_by_name($_GET["channel"]);
    $user_record["selected_channel_id"]=$channel_record["channel_id"];
    \messenger\utils\join_channel($channel_record["channel_id"],$user_record["user_id"]);
  }
  \messenger\utils\update_user($user_record);
  if (isset($_GET["cmd"])==true)
  {
    $cmd=$_GET["cmd"];
    switch ($cmd)
    {
      case "register_channel":
        $data=array();
        \messenger\utils\register_channel($_POST["channel_name"],$_POST["channel_topic"]);
        $data["redirect_url"]=\webdb\utils\template_fill("channel_url").urlencode($_POST["channel_name"]);
        $data=json_encode($data);
        die($data);
      case "update":
        $data=array();
        if (isset($_POST["message"])==true)
        {
          $message=trim($_POST["message"]);
          if (strlen($message)>0)
          {
            if (substr($message,0,1)=="/")
            {
              $parts=explode(" ",$message);
              $cmd_part=array_shift($parts);
              switch ($cmd_part)
              {
                case "/topic":
                  $channel_record["topic"]=implode(" ",$parts);
                  \messenger\utils\update_channel($channel_record);
                  break;
                case "/rename":
                  $channel_record["channel_name"]=\messenger\utils\strip_text(array_shift($parts));
                  \messenger\utils\update_channel($channel_record);
                  break;
                case "/join":
                  $channel_name=array_shift($parts);
                  $channel_topic=implode(" ",$parts);
                  \messenger\utils\register_channel($channel_name,$channel_topic);
                  $data["redirect_url"]=\webdb\utils\template_fill("channel_url").urlencode($channel_name);
                  $data=json_encode($data);
                  die($data);
              }
            }
            else
            {
              \messenger\utils\save_message($user_record,$channel_record,$message);
            }
            $data["clear_input"]=1;
          }
        }
        $records=\messenger\utils\get_new_message_records($user_record,$channel_record);
        if (count($records)>0)
        {
          $max_id=$records[0]["max_id"];
          \messenger\utils\update_last_read_message($user_record,$channel_record,$max_id);
        }
        $ding=false;
        $delta="";
        $last_message=end($records);
        for ($i=0;$i<count($records);$i++)
        {
          $record=$records[$i];
          if (isset($_GET["break"])==false)
          {
            if (strpos($record["message"],$user_record["nick"])!==false)
            {
              $ding=true;
            }
          }
          $row_params=array();
          $row_params["time"]=\messenger\utils\sql_to_iso_timestamp($record["message_time"]);
          $row_params["time"]=\webdb\utils\template_fill("server_timestamp",$row_params);
          $row_params["nick"]=htmlspecialchars($record["nick"]);
          $row_params["message"]=htmlspecialchars($record["message"]);
          if ((isset($_GET["break"])==true) and ($record==$last_message))
          {
            $delta.=\webdb\utils\template_fill("message_row_break",$row_params);
          }
          else
          {
            $delta.=\webdb\utils\template_fill("message_row",$row_params);
          }
        }
        $data["message_delta"]=$delta;
        if ($ding==true)
        {
          $data["ding_file"]=$settings["ding_file"];
        }
        $records=\messenger\utils\get_channels();
        $rows="";
        for ($i=0;$i<count($records);$i++)
        {
          $record=$records[$i];
          $row_params=array();
          $row_params["channel_name"]=htmlspecialchars($record["channel_name"]);
          if ($record["channel_id"]==$channel_record["channel_id"])
          {
            $rows.=\webdb\utils\template_fill("active_channel_row",$row_params);
          }
          else
          {
            $rows.=\webdb\utils\template_fill("channel_row",$row_params);
          }
        }
        $channels_params=array();
        $channels_params["channels_rows"]=$rows;
        $data["channels"]=\webdb\utils\template_fill("channels",$channels_params);
        $records=\messenger\utils\get_users();
        $data["nicks"]=array();
        $rows="";
        for ($i=0;$i<count($records);$i++)
        {
          $record=$records[$i];
          $data["nicks"][]=$record["nick"];
          $row_params=array();
          $row_params["nick"]=htmlspecialchars($record["nick"]);
          if ($record["user_id"]==$user_record["user_id"])
          {
            $rows.=\webdb\utils\template_fill("active_user_row",$row_params);
          }
          else
          {
            $rows.=\webdb\utils\template_fill("user_row",$row_params);
         }
        }
        $users_params=array();
        $users_params["users_rows"]=$rows;
        $data["users"]=\webdb\utils\template_fill("users",$users_params);
        $data["channel_name"]=htmlspecialchars($channel_record["channel_name"]);
        $data["channel_topic"]=htmlspecialchars($channel_record["topic"]);
        $data=json_encode($data);
        die($data);
    }
    $data=array();
    $data["error"]="unhandled_cmd";
    $data=json_encode($data);
    die($data);
  }
  \messenger\utils\update_last_read_message($user_record,$channel_record);
  \messenger\utils\purge_unused_channels();
  $records=\messenger\utils\get_new_message_records($user_record,$channel_record);
  $page_params=array();
  $channels_params=array();
  $channels_params["channels_rows"]="";
  $page_params["channels"]=\webdb\utils\template_fill("channels",$channels_params);
  $users_params=array();
  $users_params["users_rows"]="";
  $page_params["users"]=\webdb\utils\template_fill("users",$users_params);
  $content=\webdb\utils\template_fill($settings["app_home_template"],$page_params);
  \webdb\utils\output_page($content,$settings["app_name"]);
}

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
  global $settings;
  $value_items=array();
  $value_items["user_id"]=$user_record["user_id"];
  $value_items["channel_id"]=$channel_record["channel_id"];
  $value_items["message"]=$message;
  \webdb\sql\sql_insert($value_items,"messenger_messages",$settings["database_app"]);
}

#####################################################################################################
