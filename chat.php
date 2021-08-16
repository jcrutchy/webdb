<?php

namespace webdb\chat;

#####################################################################################################

function chat_initialize()
{
  global $settings;
  if ($settings["db_engine"]=="mysql")
  {
    \webdb\sql\file_execute_prepare("chat/timezone_set");
  }
  $user_record=\webdb\chat\get_user_record_by_id($settings["user_record"]["user_id"]);
  if ($user_record===false)
  {
    $value_items=array();
    $value_items["user_id"]=$settings["user_record"]["user_id"];
    $value_items["nick"]=$settings["user_record"]["username"];
    $settings["sql_check_post_params_override"]=true;
    \webdb\sql\sql_insert($value_items,"messenger_users",$settings["database_app"]);
    $user_record=\webdb\chat\get_user_record_by_id($settings["user_record"]["user_id"]);
  }
  \webdb\chat\update_user($user_record);
  return $user_record;
}

#####################################################################################################

function update_online_user_list()
{
  global $settings;
  $user_record=\webdb\chat\chat_initialize();
  $data=array();
  if ($user_record["json_data"]<>"")
  {
    $data=json_decode($user_record["json_data"],true);
  }
  if (isset($data["pages"])==false)
  {
    $data["pages"]=array();
  }
  $now=microtime(true);
  foreach ($data["pages"] as $url => $page_data)
  {
    if (isset($page_data["page_time"])==false)
    {
      unset($data["pages"][$url]);
      continue;
    }
    $page_time=$page_data["page_time"];
    $delta=$now-$page_time;
    if ($delta>$settings["online_user_list_update_interval_sec"])
    {
      unset($data["pages"][$url]);
    }
  }
  $page_data=\webdb\chat\get_stripped_url();
  $url=$page_data["url"];
  $page_data["page_time"]=$now;
  if ($page_data["skip"]==false)
  {
    $data["pages"][$url]=$page_data;
    $user_record["json_data"]=json_encode($data);
    \webdb\chat\update_user($user_record);
  }
  $user_records=\webdb\sql\file_fetch_prepare("chat/chat_user_get_all_enabled");
  $online_users=array();
  for ($i=0;$i<count($user_records);$i++)
  {
    $user=$user_records[$i];
    $nick=$user["nick"];
    $online_users[$nick]=array();
    if ($user["json_data"]<>"")
    {
      $data=json_decode($user["json_data"],true);
      if (isset($data["pages"])==true)
      {
        foreach ($data["pages"] as $url => $page_data)
        {
          if (isset($page_data["page_time"])==false)
          {
            continue;
          }
          $page_time=$page_data["page_time"];
          $delta=$now-$page_time;
          if ($delta<=$settings["online_user_list_update_interval_sec"])
          {
            $online_users[$nick][$url]=$page_data;
          }
        }
      }
    }
  }
  $data=array();
  $data["lock"]=\webdb\utils\purge_closed_page_row_locks($online_users);
  $rows="";
  foreach ($online_users as $nick => $urls)
  {
    $blank_exists=false;
    foreach ($urls as $url => $page_data)
    {
      $params=array();
      $params["nick"]=$nick;
      $params["url"]=$url;
      $params["caption"]=\webdb\chat\get_user_list_caption($url,$page_data);
      if ($params["caption"]=="")
      {
        if ($blank_exists==true)
        {
          continue;
        }
        $blank_exists=true;
      }
      $rows.=\webdb\utils\template_fill("online_user_list_row",$params);
    }
  }
  $params=array();
  $params["rows"]=$rows;
  $data["html"]=\webdb\utils\template_fill("online_user_list_table",$params);
  $data=json_encode($data);
  die($data);
}

#####################################################################################################

function replace_favourite_url_host($url)
{
  global $settings;
  $parts=parse_url($url);
  $result=$settings["app_web_index"];
  if (isset($parts["query"])==true)
  {
    $result.="?".$parts["query"];
  }
  return $result;
}

#####################################################################################################

function output_user_favorites_list()
{
  global $settings;
  $user_record=\webdb\chat\chat_initialize();
  $data=array();
  if ($user_record["json_data"]<>"")
  {
    $data=json_decode($user_record["json_data"],true);
  }
  if (isset($data["favorites"])==false)
  {
    return "";
  }
  $rows="";
  foreach ($data["favorites"] as $url => $url_data)
  {
    $row_params=array();
    $row_params["url"]=\webdb\chat\replace_favourite_url_host($url);
    $row_params["caption"]=\webdb\chat\get_user_list_caption($url,$url_data);
    if ($row_params["caption"]=="")
    {
      continue;
    }
    $rows.=\webdb\utils\template_fill("user_favorites_list_row",$row_params);
  }
  $params=array();
  $params["rows"]=$rows;
  return \webdb\utils\template_fill("user_favorites_list_table",$params);
}

#####################################################################################################

function page_favorite_ajax_stub()
{
  global $settings;
  $result_data=array();
  $user_record=\webdb\chat\chat_initialize();
  $data=array();
  if ($user_record["json_data"]<>"")
  {
    $data=json_decode($user_record["json_data"],true);
  }
  if (isset($data["favorites"])==false)
  {
    $data["favorites"]=array();
  }
  $url_data=\webdb\chat\get_stripped_url(true);
  $url=$url_data["url"];
  switch ($_GET["ajax"])
  {
    case "favourite":
      if ($url_data["skip"]==false)
      {
        $data["favorites"][$url]=$url_data;
        $result_data["success_msg"]="Added to favourites.";
      }
      $result_data["button_caption"]="unfavourite";
      break;
    case "unfavourite":
      if (isset($data["favorites"][$url])==true)
      {
        unset($data["favorites"][$url]);
        $result_data["success_msg"]="Removed from favourites.";
      }
      $result_data["button_caption"]="favourite";
      break;
  }
  $user_record["json_data"]=json_encode($data);
  \webdb\chat\update_user($user_record);
  $result_data=json_encode($result_data);
  die($result_data);
}

#####################################################################################################

function get_stripped_url($ignore_ajax=false)
{
  $result=array();
  $result["url"]=\webdb\utils\get_url();
  $result["page_id"]="";
  $result["record_id"]="";
  $result["skip"]=false;
  # keep params: page,cmd,id,article,file
  $strip_params=array("update_oul","chat_break","redirect","filters","sort","dir","home");
  $skip_params=array("subform","parent_form","parent_id","format");
  if ($ignore_ajax==true)
  {
    $strip_params[]="ajax";
  }
  else
  {
    $skip_params[]="ajax";
  }
  $params=explode("?",$result["url"]);
  if (count($params)<2)
  {
    return $result;
  }
  $result["url"]=$params[0];
  $params=$params[1];
  $params=explode("&",$params);
  $output=array();
  for ($i=0;$i<count($params);$i++)
  {
    $parts=explode("=",$params[$i]);
    $test_key=array_shift($parts);
    $test_val=implode("=",$parts);
    if (($test_key=="cmd") and ($test_val=="insert"))
    {
      $result["skip"]=true;
      break;
    }
    if (in_array($test_key,$skip_params)==true)
    {
      $result["skip"]=true;
      break;
    }
    if ($test_key=="page")
    {
      $result["page_id"]=$test_val;
    }
    if ($test_key=="id")
    {
      $result["record_id"]=$test_val;
    }
    if (in_array($test_key,$strip_params)==false)
    {
      $output[]=$params[$i];
    }
  }
  if (count($output)>0)
  {
    $result["url"].="?".implode("&",$output);
  }
  return $result;
}

#####################################################################################################

function get_user_list_caption($url,$url_data)
{
  $result="";
  $page_id=$url_data["page_id"];
  $record_id=$url_data["record_id"];
  $form_config=\webdb\forms\get_form_config($page_id,true);
  if ($form_config!==false)
  {
    $caption=$form_config["title"];
    if (($record_id!=="") and ($form_config["user_list_fields"]<>"") and ($form_config["user_list_format"]<>""))
    {
      $record=\webdb\forms\get_record_by_id($form_config,$record_id,"primary_key",false);
      if ($record===false)
      {
        return $result;
      }
      $result=\webdb\chat\get_topic($form_config,$record,"user_list");
    }
  }
  if ($result=="")
  {
    if (strpos($url,"?")!==false)
    {
      $caption=explode("?",$url);
      $result=array_pop($caption);
    }
  }
  return $result;
}

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

function page_chat_stub($form_config)
{
  $record=array();
  $record["topic"]=$form_config["topic"];
  $content=\webdb\chat\chat_dispatch("",$form_config,$record,"chat/page_chat");
  \webdb\utils\output_page($content,$form_config["title"]);
}

#####################################################################################################

function get_topic($form_config,$record,$config_prefix="chat_topic")
{
  $topic="";
  if (($record!==false) and ($form_config[$config_prefix."_fields"]<>"") and ($form_config[$config_prefix."_format"]<>""))
  {
    $field_names=explode(",",$form_config[$config_prefix."_fields"]);
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
    $format=trim($form_config[$config_prefix."_format"]);
    $topic=vsprintf($format,$field_values);
  }
  return $topic;
}

#####################################################################################################

function chat_dispatch($record_id,$form_config,$record=false,$template="chat/popup_chat")
{
  global $settings;
  $user_record=\webdb\chat\chat_initialize();
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
  $value_items=array();
  $value_items["channel_id"]=$channel_record["channel_id"];
  $value_items["user_id"]=$user_record["user_id"];
  $records=\webdb\sql\file_fetch_prepare("chat/get_joined_channel",$value_items);
  if (count($records)==0)
  {
    $settings["sql_check_post_params_override"]=true;
    \webdb\sql\sql_insert($value_items,"messenger_channel_users",$settings["database_app"]);
  }
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
              $trailing=implode(" ",$parts);
              switch ($cmd_part)
              {
                case "/topic":
                  $channel_record["topic"]=$trailing;
                  \webdb\chat\update_channel($channel_record);
                  $response=array();
                  $response[]="topic changed";
                  \webdb\chat\private_notice($response);
                  break;
                case "/rps":
                  require_once($settings["webdb_apps_path"]."rps".DIRECTORY_SEPARATOR."rps.php");
                  $response=\webdb\chat\rps\play_rps($user_record,$trailing);
                  if (count($response)>0)
                  {
                    \webdb\chat\private_notice($response);
                  }
                  break;
                case "/delete":
                  \webdb\chat\delete_chat_message($parts,$trailing);
                  break;
                case "/shell":
                  $response=array();
                  $admin_channel_name=array();
                  $admin_channel_name["chat_channel_prefix"]=$settings["chat_channel_prefix"];
                  $admin_channel_name["page_id"]="admin_chat";
                  $admin_channel_name["record_id"]="";
                  $admin_channel_name=json_encode($admin_channel_name);
                  if ((\webdb\users\logged_in_user_in_group("shell_admin")==true) and ($channel_name==$admin_channel_name))
                  {
                    if (empty($trailing)==false)
                    {
                      $response[]="running shell command: ".htmlspecialchars($trailing);
                      $result=shell_exec($trailing);
                      if (empty($result)==false)
                      {
                        $result=explode("\n",$result);
                        for ($i=0;$i<count($result);$i++)
                        {
                          $result[$i]=htmlspecialchars($result[$i]);
                          $result[$i]=str_replace(" ","&nbsp;",$result[$i]);
                        }
                        $response=array_merge($response,$result);
                      }
                      \webdb\chat\private_notice($response);
                    }
                    else
                    {
                      $response[]=htmlspecialchars("syntax: /shell <windows shell command>");
                      \webdb\chat\private_notice($response);
                    }
                  }
                  $response[]="error: command not permitted";
                  \webdb\chat\private_notice($response);
                  break;
                case "/sql":
                  $response=array();
                  if (\webdb\users\logged_in_user_in_group("admin")==true)
                  {
                    # todo: less important because can use sql studio
                  }
                  $response[]="error: command not permitted";
                  \webdb\chat\private_notice($response);
                  break;
              }
              $response=array();
              $response[]="command not recognised";
              \webdb\chat\private_notice($response);
            }
            \webdb\chat\save_message($user_record,$channel_record,$message);
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
          $nick=$record["nick"];
          $message=$record["message"];
          if (isset($_GET["chat_break"])==false)
          {
            if (strpos($message,$user_record["nick"])!==false)
            {
              $ding=true;
            }
          }
          if (substr($message,0,1)=="/")
          {
            $parts=explode(" ",$message);
            $cmd_part=array_shift($parts);
            $trailing=implode(" ",$parts);
            switch ($cmd_part)
            {
              case "/topic":
                $message=$nick." changed topic to '".$trailing."'";
                $nick="*";
                break;
            }
          }
          $row_params=array();
          $row_params["time"]=\webdb\chat\sql_to_iso_timestamp($record["message_time"]);
          $row_params["time"]=\webdb\utils\template_fill("chat/server_timestamp",$row_params);
          $row_params["nick"]=htmlspecialchars($nick);
          $row_params["message"]=htmlspecialchars($message);
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
          $data["ding_file"]=$settings["chat_ding_file"];
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
  $params=array();
  $params["id"]=$record_id;
  $params["favourite_caption"]=\webdb\chat\get_favorite_button_caption();
  $params["page_id"]=$form_config["page_id"];
  return \webdb\utils\template_fill($template,$params);
}

#####################################################################################################

function get_favorite_button_caption()
{
  $user_record=\webdb\chat\chat_initialize();
  $data=array();
  if ($user_record["json_data"]<>"")
  {
    $data=json_decode($user_record["json_data"],true);
  }
  if (isset($data["favorites"])==false)
  {
    return "favourite";
  }
  $url_data=\webdb\chat\get_stripped_url(true);
  $url=$url_data["url"];
  if (isset($data["favorites"][$url])==true)
  {
    return "unfavourite";
  }
  return "favourite";
}

#####################################################################################################

function delete_chat_message($parts,$trailing)
{
  $response=array();
  if (\webdb\users\logged_in_user_in_group("admin")==true)
  {
    $message_id=array_shift($parts);
    $confirm_code=implode(" ",$parts);
    $data=array();
    if ($user_record["json_data"]<>"")
    {
      $data=json_decode($user_record["json_data"],true);
    }
    if (is_numeric($message_id)==true)
    {
      if (empty($confirm_code)==true)
      {
        $message_record=\webdb\chat\get_message_record_by_id($message_id);
        if ($message_record!==false)
        {
          foreach ($message_record as $field_name => $value)
          {
            $message_record[$field_name]=htmlspecialchars($value);
          }
          $delete_data=array();
          $delete_data["message_id"]=$message_id;
          $delete_data["confirm_code"]=\webdb\users\crypto_random_key();
          $data["admin_delete"]=$delete_data;
          $user_record["json_data"]=json_encode($data);
          \webdb\chat\update_user($user_record);
          $response[]="chat record to delete: ".json_encode($message_record);
          $response[]="repeat command with confirm code: ".$delete_data["confirm_code"];
          \webdb\chat\private_notice($response);
        }
        $response[]="error: message with id not found";
        \webdb\chat\private_notice($response);
      }
      else
      {
        if (isset($data["admin_delete"])==true)
        {
          $reference_code=$data["admin_delete"]["confirm_code"];
          unset($data["admin_delete"]);
          $user_record["json_data"]=json_encode($data);
          \webdb\chat\update_user($user_record);
          if ($confirm_code===$reference_code)
          {
            $items=array();
            $items["message_id"]=$message_id;
            \webdb\sql\sql_delete($items,"messenger_messages",$settings["database_app"]);
            $response[]="chat message with id ".$message_id." deleted";
            \webdb\chat\private_notice($response);
          }
          else
          {
            $response[]="error: confirm code mismatch";
            \webdb\chat\private_notice($response);
          }
        }
        $response[]="error: no admin_delete data found";
        \webdb\chat\private_notice($response);
      }
    }
    if (empty($trailing)==true)
    {
      $response[]=htmlspecialchars("syntax: /delete <message_id> [<confirm_code>]");
    }
    else
    {
      $response[]="error: invalid message id";
    }
    \webdb\chat\private_notice($response);
  }
  $response[]="error: command not permitted";
  \webdb\chat\private_notice($response);
}

#####################################################################################################

function private_notice($lines)
{
  $data=array();
  $data["clear_input"]=1;
  $delta="";
  for ($i=0;$i<count($lines);$i++)
  {
    $row_params=array();
    $row_params["time"]=date("c");
    $row_params["time"]=\webdb\utils\template_fill("chat/server_timestamp",$row_params);
    $row_params["message"]=$lines[$i];
    $delta.=\webdb\utils\template_fill("chat/message_row_notice",$row_params);
  }
  $data["message_delta"]=$delta;
  $data=json_encode($data);
  die($data);
}

#####################################################################################################

function insert_notice_breaks(&$response)
{
  $max_len=0;
  for ($i=0;$i<count($response);$i++)
  {
    $max_len=max($max_len,strlen($response[$i]));
  }
  $break=str_repeat("*",$max_len);
  array_unshift($response,$break);
  $response[]=$break;
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
  $value_items["last_online"]=\webdb\sql\current_sql_timestamp();
  $value_items["json_data"]=$user_record["json_data"];
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

function get_message_record_by_id($message_id)
{
  $where_items=array();
  $where_items["message_id"]=$message_id;
  $records=\webdb\sql\file_fetch_prepare("chat/get_message_record_by_id",$where_items);
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
