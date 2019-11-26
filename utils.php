<?php

namespace webdb\utils;

#####################################################################################################

function system_message($message)
{
  global $settings;
  if (\webdb\cli\is_cli_mode()==true)
  {
    \webdb\cli\term_echo(str_replace(PHP_EOL," ",$message),33);
    die;
  }
  $settings["unauthenticated_content"]=true;
  $buf=ob_get_contents();
  if (strlen($buf)<>0)
  {
    ob_end_clean(); # discard buffer
  }
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  # TODO: DEBUG INFO ONLY (SECURITY RISK) - COMMENT/REMOVE FOR PROD
  $message.="<pre>--DEBUG--".PHP_EOL.htmlspecialchars(json_encode(debug_backtrace(),JSON_PRETTY_PRINT))."</pre>";
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $settings["system_message"]=$message;
  die;
}

#####################################################################################################

function show_message($message)
{
  if (isset($_GET["ajax"])==true)
  {
    $buf=ob_get_contents();
    if (strlen($buf)<>0)
    {
      ob_end_clean(); # discard buffer
    }
    $data=array();
    $data["error"]=$message;
    $data=json_encode($data);
    die($data);
  }
  global $settings;
  $params=array();
  $params["global_styles_modified"]=\webdb\utils\resource_modified_timestamp("global.css");
  $params["page_title"]=$settings["app_name"];
  $params["message"]=$message;
  \webdb\utils\system_message(\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."message",$params));
}

#####################################################################################################

function debug_var_dump($data)
{
  global $settings;
  $settings["unauthenticated_content"]=true;
  var_dump($data);
  die;
}

#####################################################################################################

function output_page($content,$title)
{
  global $settings;
  \webdb\users\generate_csrf_token();
  $page_params=array();
  $page_params["page_title"]=$title;
  $page_params["global_styles_modified"]=\webdb\utils\resource_modified_timestamp("global.css");
  $page_params["global_script_modified"]=\webdb\utils\resource_modified_timestamp("global.js");
  $page_params["body_text"]=$content;
  if (isset($settings["user_record"])==true)
  {
    $user_record=$settings["user_record"];
    \webdb\users\obfuscate_hashes($user_record);
    $page_params["authenticated_status"]=\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."authenticated_status",$user_record);
  }
  else
  {
    $page_params["authenticated_status"]=\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."unauthenticated_status");
  }
  $page_params["calendar"]=\webdb\forms\get_calendar();
  $output=\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."page",$page_params);
  die($output);
}

#####################################################################################################

function load_test_settings()
{
  global $settings;
  if (\webdb\cli\is_cli_mode()==true)
  {
    return;
  }
  if (file_exists($settings["test_settings_file"])==true)
  {
    $data=trim(file_get_contents($settings["test_settings_file"]));
    $lines=explode(PHP_EOL,$data);
    for ($i=0;$i<count($lines);$i++)
    {
      $parts=explode("=",trim($lines[$i]));
      $key=array_shift($parts);
      $value=implode("=",$parts);
      switch ($key)
      {
        case "change_user_agent":
          $_SERVER["HTTP_USER_AGENT"]=$value;
          break;
        case "change_remote_addr":
          $_SERVER["REMOTE_ADDR"]=$value;
          break;
        case "add_admin_whitelist_addr":
          $settings["admin_remote_address_whitelist"][]=$value;
          break;
        case "custom_ip_whitelist":
          $settings["ip_whitelist_file"]=$value;
          break;
        case "custom_ip_blacklist":
          $settings["ip_blacklist_file"]=$value;
          break;
      }
    }
  }
}

#####################################################################################################

function ob_postprocess($buffer)
{
  global $settings;
  if (isset($settings["csrf_token"])==false)
  {
    $settings["csrf_token"]="";
    if (isset($_POST["csrf_token"])==true)
    {
      $settings["csrf_token"]=$_POST["csrf_token"];
    }
  }
  $buffer=str_replace("%%csrf_token%%",$settings["csrf_token"],$buffer);
  if (isset($settings["system_message"])==false)
  {
    if (strpos($buffer,"%%")!==false)
    {
      $buffer="error: unassigned % template found: ".htmlspecialchars($buffer);
    }
    if (strpos($buffer,"$$")!==false)
    {
      $buffer="error: unassigned $ template found: ".htmlspecialchars($buffer);
    }
    if (strpos($buffer,"@@")!==false)
    {
      $buffer="error: unassigned @ template found: ".htmlspecialchars($buffer);
    }
    if ((isset($settings["unauthenticated_content"])==false) and (isset($settings["user_record"])==false))
    {
      $buffer="error: authentication failure";
    }
  }
  else
  {
    $buffer=$settings["system_message"];
  }
  # TODO: CALL AUTOMATED W3C VALIDATION HERE
  #global $t;
  #return (microtime(true)-$t);
  if (isset($_SERVER["HTTP_ACCEPT_ENCODING"])==true)
  {
    if (strpos($_SERVER["HTTP_ACCEPT_ENCODING"],"gzip")!==false)
    {
      $buffer=gzencode($buffer);
      header("Content-Encoding: gzip");
    }
  }
  \webdb\utils\save_logs();
  return $buffer;
}

#####################################################################################################

function is_testing_mode()
{
  if (function_exists("\\webdb\\test\\run_tests")==true)
  {
    return true;
  }
  return false;
}

#####################################################################################################

function computed_field_iso_datetime_format($field_name,$field_data)
{
  $value=$field_data[$field_name];
  if (empty($value)==true)
  {
    return "0";
  }
  return date("c",$value);
}

#####################################################################################################

function get_url($request_uri=false)
{
  $url="http://";
  if (isset($_SERVER["HTTPS"])==true)
  {
    if (($_SERVER["HTTPS"]<>"") and ($_SERVER["HTTPS"]=="on"))
    {
      $url="https://";
    }
  }
  $url.=$_SERVER["HTTP_HOST"];
  if ($request_uri===false)
  {
    $url.=$_SERVER["REQUEST_URI"];
  }
  else
  {
    $url.=$request_uri;
  }
  return $url;
}

#####################################################################################################

function load_files($path,$root="",$ext="",$trim_ext=true) # path (and root) must have trailing delimiter, ext excludes dot, empty ext means all
{
  if ($root=="")
  {
    $root=$path;
  }
  $result=array();
  $file_list=scandir($path);
  for ($i=0;$i<count($file_list);$i++)
  {
    $fn=$file_list[$i];
    if (($fn==".") or ($fn=="..") or ($fn==".git"))
    {
      continue;
    }
    $full=$path.$fn;
    if ($path<>$root)
    {
      $fn=substr($full,strlen($root));
    }
    if (is_dir($full)==false)
    {
      $fext=pathinfo($fn,PATHINFO_EXTENSION);
      if ($ext<>"")
      {
        if ($fext<>$ext)
        {
          continue;
        }
      }
      if ($trim_ext==true)
      {
        $fn=substr($fn,0,strlen($fn)-strlen($fext)-1);
      }
      $result[$fn]=trim(file_get_contents($full));
    }
    else
    {
      $result=$result+\webdb\utils\load_files($full.DIRECTORY_SEPARATOR,$root,$ext,$trim_ext);
    }
  }
  return $result;
}

#####################################################################################################

function sql_fill($sql_key,$params=false)
{
  global $settings;
  return \webdb\utils\template_fill($sql_key,$params,array(),$settings["sql"]);
}

#####################################################################################################

function search_sql_records(&$records,$find_column,$find_value)
{
  $results=array();
  for ($i=0;$i<count($records);$i++)
  {
    if ($records[$i][$find_column]==$find_value)
    {
      $results[]=$records[$i];
    }
  }
  return $results;
}

#####################################################################################################

function group_by_fields($form_config,$record)
{
  $result=array();
  for ($i=0;$i<count($form_config["group_by"]);$i++)
  {
    $field_name=$form_config["group_by"][$i];
    $result[$field_name]=$record[$field_name];
  }
  return $result;
}

#####################################################################################################

function link_app_js_resource($name)
{
  global $settings;
  $filename=$settings["app_resources_path"].$name.".js";
  if (file_exists($filename)==true)
  {
    $params=array();
    $params["name"]=$name;
    $params["modified"]=filemtime($filename);
    return \webdb\utils\template_fill("app_resource_script",$params);
  }
  return "";
}

#####################################################################################################

function link_app_css_resource($name)
{
  global $settings;
  $filename=$settings["app_resources_path"].$name.".css";
  if (file_exists($filename)==true)
  {
    $params=array();
    $params["name"]=$name;
    $params["modified"]=filemtime($filename);
    return \webdb\utils\template_fill("app_resource_styles",$params);
  }
  return "";
}

#####################################################################################################

function append_resource_links($template_key,$template_content)
{
  global $settings;
  $styles=\webdb\utils\link_app_css_resource($template_key);
  $script=\webdb\utils\link_app_js_resource($template_key);
  return $styles.PHP_EOL.$template_content.PHP_EOL.$script;
}

#####################################################################################################

function check_user_form_permission($form_name,$permission)
{
  global $settings;
  $whitelisted=false;
  foreach ($settings["permissions"] as $group_name_iterator => $group_permissions)
  {
    if (isset($group_permissions["forms"][$form_name])==true)
    {
      $whitelisted=true;
      break;
    }
  }
  if ($whitelisted==false)
  {
    return true;
  }
  if (isset($settings["logged_in_user_groups"])==false)
  {
    return false;
  }
  $user_groups=$settings["logged_in_user_groups"];
  for ($i=0;$i<count($user_groups);$i++)
  {
    $user_group=$user_groups[$i]["group_name"];
    if (isset($settings["permissions"][$user_group]["forms"][$form_name])==true)
    {
      $permissions=$settings["permissions"][$user_group]["forms"][$form_name];
      if (strpos($permissions,$permission)!==false)
      {
        return true;
      }
      else
      {
        return false;
      }
    }
  }
  return false;
}

#####################################################################################################

function check_user_template_permission($template_name)
{
  global $settings;
  $whitelisted=false;
  foreach ($settings["permissions"] as $group_name_iterator => $group_permissions)
  {
    if (isset($group_permissions["templates"][$template_name])==true)
    {
      $whitelisted=true;
      break;
    }
  }
  if ($whitelisted==false)
  {
    return $template_name;
  }
  if (isset($settings["logged_in_user_groups"])==false)
  {
    return false;
  }
  $user_groups=$settings["logged_in_user_groups"];
  for ($i=0;$i<count($user_groups);$i++)
  {
    $user_group=$user_groups[$i]["group_name"];
    if (isset($settings["permissions"][$user_group]["templates"][$template_name])==true)
    {
      $substitute_template=$settings["permissions"][$user_group]["templates"][$template_name];
      if ($substitute_template=="")
      {
        return $template_name;
      }
      return $substitute_template;
    }
  }
  return false;
}

#####################################################################################################

function template_fill($template_key,$params=false,$tracking=array(),$custom_templates=false) # tracking array is used internally to limit recursion and should not be manually passed
{
  global $settings;
  if ($template_key=="")
  {
    return "";
  }
  $template_array=$settings["templates"];
  if ($custom_templates!==false)
  {
    $template_array=$custom_templates;
  }
  if (isset($template_array[$template_key])==false)
  {
    \webdb\utils\system_message("error: template '".$template_key."' not found");
  }
  if (in_array($template_key,$tracking)==true)
  {
    \webdb\utils\system_message("error: circular reference to template '".$template_key."'");
  }
  $substitute_template=\webdb\utils\check_user_template_permission($template_key);
  if ($substitute_template===false)
  {
    return "";
  }
  if ($substitute_template<>$template_key)
  {
    return template_fill($substitute_template,$params,$tracking,$custom_templates);
  }
  $tracking[]=$template_key;
  $result=$template_array[$template_key];
  $constants=get_defined_constants(true);
  if (isset($constants["user"])==true)
  {
    $constants=$constants["user"];
    foreach ($constants as $name => $value)
    {
      if (strpos($result,"??".$name."??")===false)
      {
        continue;
      }
      $result=str_replace("??".$name."??",$value,$result);
    }
  }
  foreach ($settings as $key => $value)
  {
    if (strpos($result,'$$'.$key.'$$')===false)
    {
      continue;
    }
    $result=str_replace('$$'.$key.'$$',$value,$result);
  }
  if ($params!==false)
  {
    foreach ($params as $key => $value)
    {
      if (is_array($value)==false)
      {
        $result=str_replace("%%".$key."%%",$value,$result);
      }
    }
  }
  if (($custom_templates===false) and ($template_key<>"app_resource_styles") and ($template_key<>"app_resource_script"))
  {
    $result=\webdb\utils\append_resource_links($template_key,$result);
  }
  foreach ($template_array as $key => $value)
  {
    if (strpos($result,"@@".$key."@@")===false)
    {
      continue;
    }
    $value=\webdb\utils\template_fill($key,$params,$tracking,$custom_templates);
    $result=str_replace("@@".$key."@@",$value,$result);
  }
  return $result;
}

#####################################################################################################

function error_handler($errno,$errstr,$errfile,$errline)
{
  \webdb\utils\system_message("[".date("Y-m-d, H:i:s T",time())."] ".$errstr." in \"".$errfile."\" on line ".$errline);
  return false;
}

#####################################################################################################

function exception_handler($exception)
{
  \webdb\utils\system_message("[".date("Y-m-d, H:i:s T",time())."] ".$exception->getMessage()." in \"".$exception->getFile()."\" on line ".$exception->getLine());
}

#####################################################################################################

function redirect($url,$clean_buffer=true)
{
  if ($clean_buffer==true)
  {
    ob_end_clean(); # discard buffer
  }
  header("Location: ".$url);
  die;
}

#####################################################################################################

function load_db_credentials($type)
{
  global $settings;
  $filename=$settings["db_".$type."_file"];
  $data=file_get_contents($filename);
  if ($data===false)
  {
    \webdb\utils\system_message("error: unable to read database credentials file: ".$filename);
  }
  $data=trim($data);
  $data=explode(PHP_EOL,$data);
  if (count($data)<>2)
  {
    \webdb\utils\system_message("error: invalid database credentials file: ".$filename);
  }
  $settings["db_".$type."_username"]=$data[0];
  $settings["db_".$type."_password"]=$data[1];
}

#####################################################################################################

function send_email($recipient,$cc,$subject,$message,$from,$reply_to,$bounce_to)
{
  $headers=array();
  $headers[]="From: ".$from;
  $headers[]="Cc: ".$cc;
  $headers[]="Reply-To: ".$reply_to;
  $headers[]="X-Sender: ".$from;
  $headers[]="X-Mailer: PHP/".phpversion();
  $headers[]="MIME-Version: 1.0";
  $headers[]="Content-Type: text/html; charset=iso-8859-1";
  mail($recipient,$subject,$message,implode(PHP_EOL,$headers),"-f".$bounce_to);
}

#####################################################################################################

function is_app_mode()
{
  global $settings;
  if ($settings["app_root_path"]==$settings["webdb_root_path"])
  {
    return false;
  }
  else
  {
    return true;
  }
}

#####################################################################################################

function resource_modified_timestamp($resource_file,$source="webdb")
{
  global $settings;
  $filename=$settings[$source."_resources_path"].$resource_file;
  if (file_exists($filename)==true)
  {
    return filemtime($filename);
  }
  return "error";
}

#####################################################################################################

function get_child_array_key(&$array,$parent_key)
{
  if (is_array($array[$parent_key])==false)
  {
    \webdb\utils\show_message("error: array expected with parent key: ".$parent_key);
  }
  $child_keys=array_keys($array[$parent_key]);
  if (count($child_keys)<>1)
  {
    \webdb\utils\show_message("error: invalid child array key count: ".$parent_key);
  }
  return $child_keys[0];
}

#####################################################################################################

function static_page($template,$title)
{
  $content=\webdb\utils\template_fill($template);
  \webdb\utils\output_page($content,$title);
}

#####################################################################################################

function save_logs()
{
  global $settings;
  foreach ($settings["logs"] as $key => $lines)
  {
    $fn=$settings[$key."_log_path"].$key."_".date("Ymd").".log";
    #file_put_contents($log_filename,$content,FILE_APPEND);
    $fp=fopen($fn,"a");
    stream_set_blocking($fp,false);
    if (flock($fp,LOCK_EX)==true)
    {
      $data=PHP_EOL.PHP_EOL.trim(implode(PHP_EOL,$lines));
      fwrite($fp,$data);
    }
    flock($fp,LOCK_UN);
    fclose($fp);
  }
}

#####################################################################################################

function is_row_locked($schema,$table,$key_field,$key_value)
{
  global $settings;
  # $settings["user_record"]
  # delete expired locks
}

#####################################################################################################
