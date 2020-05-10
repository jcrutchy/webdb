<?php

namespace webdb\chat;

#####################################################################################################

function chat_messages_list($event_params)
{
  global $settings;
  $form_config=$event_params["form_config"];
  $sql_params=array();
  $sql_params["channel_name_prefix"]=$settings["chat_channel_prefix"]."_";
  $sql=\webdb\utils\sql_fill("chat/recent_messages",$sql_params);
  $records=\webdb\sql\fetch_prepare($sql,array(),"chat/recent_messages",false,"","",$form_config);
  $list_records=array();
  for ($i=0;$i<count($records);$i++)
  {
    $channel_name=json_decode($records[$i]["channel_name"],true);
    if ($channel_name===null)
    {
      continue;
    }
    if ($channel_name["chat_channel_prefix"]<>$settings["chat_channel_prefix"])
    {
      continue;
    }
    $records[$i]["page_id"]=$channel_name["page_id"];
    $message_form_config=\webdb\forms\get_form_config($records[$i]["page_id"],true);
    if ($message_form_config===false)
    {
      continue;
    }
    $topic=\webdb\chat\get_topic($form_config,$records[$i]);
    $records[$i]["record_id"]=$channel_name["record_id"];
    $records[$i]["timestamp"]=$records[$i]["message_timestamp"];
    $records[$i]["user"]=$records[$i]["message_user"];
    $records[$i]["id_field"]=$message_form_config["primary_key"];
    $list_records[]=$records[$i];
    if (count($list_records)>=100)
    {
      break;
    }
  }
  # TODO: maybe add from/to $_GET params (make sure to add them to stubs.php list)
  $event_params["records"]=$list_records;
  return $event_params;
}

#####################################################################################################

function get_topic($form_config,$record)
{
  $topic="";
  if (($record!==false) and ($form_config["chat_topic_fields"]<>"") and ($form_config["chat_topic_format"]<>""))
  {
    $field_names=explode(",",$form_config["chat_topic_fields"]);
    $field_values=array();
    for ($i=0;$i<count($field_names);$i++)
    {
      $field_name=$field_names[$i];
      if (array_key_exists($field_name,$record)==false)
      {
        \webdb\stubs\stub_error("error: field not found in record: ".$field_name);
      }
      $value=$record[$field_name];
      if (strlen($value)>50)
      {
        $value=trim(substr($value,0,50))."...";
      }
      $field_values[]=$value;
    }
    $format=trim($form_config["chat_topic_format"]);
    $topic=vsprintf($format,$field_values);
  }
  return $topic;
}

#####################################################################################################

function chat_dispatch($record_id,$form_config,$record=false)
{
  global $settings;
  if ($settings["db_engine"]=="mysql")
  {
    \webdb\sql\file_execute_prepare("chat/timezone_set");
  }
  $channel_name=array();
  $channel_name["chat_channel_prefix"]=$settings["chat_channel_prefix"];
  $channel_name["page_id"]=$form_config["page_id"];
  $channel_name["record_id"]=$record_id;
  if ($form_config["chat_page_id_override"]<>"")
  {
    $channel_name["page_id"]=$form_config["chat_page_id_override"];
  }
  $channel_name=json_encode($channel_name);
  $topic=\webdb\chat\get_topic($form_config,$record);
  $channel_record=\webdb\chat\get_channel_by_name($channel_name,$topic);
  $user_record=\webdb\chat\get_user_record_by_id($settings["user_record"]["user_id"]);
  if ($user_record===false)
  {
    $value_items=array();
    $value_items["user_id"]=$settings["user_record"]["user_id"];
    $value_items["nick"]=$settings["user_record"]["username"];
    $value_items["selected_channel_id"]=$channel_record["channel_id"];
    $settings["sql_check_post_params_override"]=true;
    \webdb\sql\sql_insert($value_items,"messenger_users",$settings["database_app"]);
    $user_record=\webdb\chat\get_user_record_by_id($settings["user_record"]["user_id"]);
  }
  $value_items=array();
  $value_items["channel_id"]=$channel_record["channel_id"];
  $value_items["user_id"]=$user_record["user_id"];
  $records=\webdb\sql\file_fetch_prepare("chat/get_joined_channel",$value_items);
  if (count($records)==0)
  {
    $settings["sql_check_post_params_override"]=true;
    \webdb\sql\sql_insert($value_items,"messenger_channel_users",$settings["database_app"]);
  }
  $user_record["selected_channel_id"]=$channel_record["channel_id"];
  \webdb\chat\update_user($user_record);
  if (isset($_GET["ajax"])==true)
  {
    $cmd=$_GET["ajax"];
    switch ($cmd)
    {
      case "chat_update":
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
                  \webdb\chat\update_channel($channel_record);
                  break;
              }
            }
            else
            {
              \webdb\chat\save_message($user_record,$channel_record,$message);
            }
            $data["clear_input"]=1;
          }
        }
        $records=\webdb\chat\get_new_message_records($user_record,$channel_record);
        if (count($records)>0)
        {
          $max_id=$records[0]["max_id"];
          \webdb\chat\update_last_read_message($user_record,$channel_record,$max_id);
        }
        $ding=false;
        $delta="";
        $last_message=end($records);
        for ($i=0;$i<count($records);$i++)
        {
          $record=$records[$i];
          if (isset($_GET["chat_break"])==false)
          {
            if (strpos($record["message"],$user_record["nick"])!==false)
            {
              $ding=true;
            }
          }
          $row_params=array();
          $row_params["time"]=\webdb\chat\sql_to_iso_timestamp($record["message_time"]);
          $row_params["time"]=\webdb\utils\template_fill("chat/server_timestamp",$row_params);
          $row_params["nick"]=htmlspecialchars($record["nick"]);
          $row_params["message"]=htmlspecialchars($record["message"]);
          if ((isset($_GET["chat_break"])==true) and ($record==$last_message))
          {
            $delta.=\webdb\utils\template_fill("chat/message_row_break",$row_params);
          }
          else
          {
            $delta.=\webdb\utils\template_fill("chat/message_row",$row_params);
          }
        }
        $data["message_delta"]=$delta;
        if ($ding==true)
        {
          $data["ding_file"]=$settings["ding_file"];
        }
        $records=\webdb\chat\get_users();
        $data["nicks"]=array();
        for ($i=0;$i<count($records);$i++)
        {
          $record=$records[$i];
          $data["nicks"][]=$record["nick"];
        }
        $data["channel_topic"]=htmlspecialchars($channel_record["topic"]);
        $data["chat_break"]=false;
        if (isset($_GET["chat_break"])==true)
        {
          $data["chat_break"]=true;
        }
        $data=json_encode($data);
        die($data);
    }
  }
  \webdb\chat\update_last_read_message($user_record,$channel_record);
  $records=\webdb\chat\get_new_message_records($user_record,$channel_record);
  $form_config["id"]=$record_id;
  return \webdb\utils\template_fill("chat/chat",$form_config);
}

#####################################################################################################

function sql_to_iso_timestamp($timestamp)
{
  $timestamp=strtotime($timestamp);
  return date("c",$timestamp);
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
  $records=\webdb\sql\file_fetch_prepare("chat/get_user_record_by_id",$where_items);
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

function get_channel_by_name($channel_name,$topic="")
{
  $where_items=array();
  $where_items["channel_name"]=$channel_name;
  $records=\webdb\sql\file_fetch_prepare("chat/get_channel_record_by_name",$where_items);
  if (count($records)==0)
  {
    \webdb\chat\register_channel($channel_name,$topic);
    $records=\webdb\sql\file_fetch_prepare("chat/get_channel_record_by_name",$where_items);
  }
  return $records[0];
}

#####################################################################################################

function register_channel($channel_name,$topic="")
{
  global $settings;
  $value_items=array();
  $value_items["channel_name"]=$channel_name;
  $records=\webdb\sql\file_fetch_prepare("chat/get_channel_record_by_name",$value_items);
  if (count($records)>0)
  {
    return;
  }
  $value_items["topic"]=$topic;
  $settings["sql_check_post_params_override"]=true;
  \webdb\sql\sql_insert($value_items,"messenger_channels",$settings["database_app"]);
}

#####################################################################################################

function get_channel_record_by_id($channel_id)
{
  $where_items=array();
  $where_items["channel_id"]=$channel_id;
  $records=\webdb\sql\file_fetch_prepare("chat/get_channel_record_by_id",$where_items);
  return $records[0];
}

#####################################################################################################

function get_new_message_records($user_record,$channel_record)
{
  $where_items=array();
  $where_items["user_id"]=$user_record["user_id"];
  $where_items["channel_id"]=$channel_record["channel_id"];
  return \webdb\sql\file_fetch_prepare("chat/get_new_message_records",$where_items);
}

#####################################################################################################

function get_users()
{
  return \webdb\sql\file_fetch_prepare("chat/get_users");
}

#####################################################################################################

function get_channels()
{
  return \webdb\sql\file_fetch_prepare("chat/get_channels");
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
