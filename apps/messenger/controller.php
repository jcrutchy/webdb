<?php

namespace messenger\controller;

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
