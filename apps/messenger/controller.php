<?php

namespace messenger\controller;

#####################################################################################################

function dispatch()
{
  global $settings;
  $messenger_user_record=\messenger\utils\get_user_record();
  $selected_channel_record=\messenger\utils\get_channel_record_by_id($messenger_user_record["selected_channel_id"]);
  # register user's last_online field to current timestamp for all requests (default for new user)
  # get list of active channels with last message no greater than a week old (based on setting)
  # get list of channels that the user has joined, or if none join the default channel (based on setting)
  # get list of users that have joined the active channel, including online status (last_online less than 15 mins ago is considered online, based on setting)
  # get list of all users registered in the messenger database, including online status
  # get all messages for active channel
  if (isset($_GET["cmd"])==true)
  {
    $cmd=$_GET["cmd"];
    switch ($cmd)
    {
      case "update":
        # check for messages active channel since last_read_message_id, and update last_read_message_id
        $data=array();
        $new_messages=\messenger\utils\get_new_message_records();
        $delta="";
        for ($i=0;$i<count($new_messages);$i++)
        {
          $record=$new_messages[$i];
          if ($record["channel_id"]<>$selected_channel_record["channel_id"])
          {
            continue;
          }
          $row_params=array();
          $row_params["time"]=$record["created_timestamp"];
          $message_user_record=\messenger\utils\get_user_record_by_id($record["user_id"]);
          $row_params["nick"]=$message_user_record["nick"];
          $row_params["message"]=$record["message"];
          $delta.=\webdb\utils\template_fill("message_row",$row_params);
        }
        # check for number of new messages in other joined channels and show counts in other tables
        $data["message_delta"]=$delta;
        $data=json_encode($data);
        die($data);
      case "message":
        $data=array();
        if (isset($_POST["message"])==true)
        {
          $message=trim($_POST["message"]);
          if (strlen($message)>0)
          {
            # check for command (line beginning with / based on setting) and if command then parse and process
            # if not command, save and output message
            $row_params=array();
            $row_params["time"]=date("c");
            $row_params["nick"]="test";
            $row_params["message"]=$message;
            $delta=\webdb\utils\template_fill("message_row",$row_params);
            $data["message_delta"]=$delta;
          }
          else
          {
            $data["error"]="empty message";
          }
        }
        else
        {
          $data["error"]="no message post field";
        }
        $data=json_encode($data);
        die($data);
    }
    $data=array();
    $data["error"]="unhandled_cmd";
    $data=json_encode($data);
    die($data);
  }
  $page_params=array();
  $page_params["messages_rows"]="";
  $page_params["joined_channels_rows"]="";
  $page_params["active_channels_rows"]="";
  $page_params["channel_users_rows"]="";
  $page_params["active_users_rows"]="";
  $content=\webdb\utils\template_fill($settings["app_home_template"],$page_params);
  \webdb\utils\output_page($content,$settings["app_name"]);
}

#####################################################################################################
