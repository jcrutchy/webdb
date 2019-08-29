<?php

namespace webdb\utils;

#####################################################################################################

function system_message($message)
{
  global $settings;
  if (\webdb\utils\is_cli_mode()==true)
  {
    die(str_replace(PHP_EOL,", ",$message).PHP_EOL);
  }
  $buf=ob_get_contents();
  if (strlen($buf)<>0)
  {
    ob_end_clean();
  }
  die($message);
}

#####################################################################################################

function show_message($message)
{
  global $settings;
  $params=array();
  $params["page_title"]=$settings["app_name"];
  $params["message"]=$message;
  \webdb\utils\system_message(\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."message",$params));
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

function app_template_fill($template_key,$params=false,$tracking=array(),$custom_templates=false)
{
  global $settings;
  $styles="";
  $script="";
  $filename=$settings["app_resources_path"].$template_key.".css";
  if (file_exists($filename)==true)
  {
    $params=array();
    $params["name"]=$template_key;
    $params["modified"]=filemtime($filename);
    $styles=template_fill("app_resource_styles",$params);
  }
  $filename=$settings["app_resources_path"].$template_key.".js";
  if (file_exists($filename)==true)
  {
    $params=array();
    $params["name"]=$template_key;
    $params["modified"]=filemtime($filename);
    $script=template_fill("app_resource_script",$params);
  }
  return $styles.PHP_EOL.template_fill($template_key,$params,$tracking,$custom_templates).PHP_EOL.$script;
}

#####################################################################################################

function check_template_group($content)
{
  $lines=explode(PHP_EOL,$content);
  if (count($lines)==0)
  {
    return $content;
  }
  $check_line=array_shift($lines);
  if (strpos($check_line,\webdb\index\TEMPLATE_GROUP_PERMISSION_PREFIX)!==0)
  {
    return $content;
  }
  $groups=substr($check_line,strlen(\webdb\index\TEMPLATE_GROUP_PERMISSION_PREFIX));
  $groups=explode(",",$groups);
  if (\webdb\utils\check_user_permission($groups)==true)
  {
    return implode(PHP_EOL,$lines);
  }
  else
  {
    return false;
  }
}

#####################################################################################################

function check_user_permission($allowed_groups)
{
  global $settings;
  $user_record=$settings["user_record"];
  $user_id=$user_record["user_id"];
  $user_groups=\webdb\users\get_user_groups($user_id);
  for ($i=0;$i<count($user_groups);$i++)
  {
    if (in_array($user_groups[$i]["group_name"],$allowed_groups)==true)
    {
      return true;
    }
  }
  return false;
}

#####################################################################################################

function template_fill($template_key,$params=false,$tracking=array(),$custom_templates=false) # tracking array is used internally to limit recursion and should not be manually passed
{
  global $settings;
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
  $tracking[]=$template_key;
  $result=$template_array[$template_key];
  $result=\webdb\utils\check_template_group($result);
  if ($result===false)
  {
    return "";
  }
  foreach ($template_array as $key => $value)
  {
    if (strpos($result,"@@".$key."@@")===false)
    {
      continue;
    }
    $value=\webdb\utils\template_fill($key,false,$tracking,$template_array);
    $result=str_replace("@@".$key."@@",$value,$result);
  }
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
    ob_end_clean();
  }
  header("Location: ".$url);
  die();
}

#####################################################################################################

function check_required_file_exists($filename,$is_path=false)
{
  if (file_exists($filename)==false)
  {
    if ($is_path==true)
    {
      \webdb\utils\system_message("error: required path not found: ".$filename);
    }
    else
    {
      \webdb\utils\system_message("error: required file not found: ".$filename);
    }
  }
  if ($is_path==true)
  {
    if (is_dir($filename)==false)
    {
      \webdb\utils\system_message("error: required path is not a directory: ".$filename);
    }
  }
}

#####################################################################################################

function check_required_setting_exists($key)
{
  global $settings;
  if (isset($settings[$key])==false)
  {
    \webdb\utils\system_message("error: required setting not found: ".$key);
  }
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

function send_email($recipient,$subject,$message)
{
  $headers="MIME-Version: 1.0".PHP_EOL;
  $headers=$headers."Content-type: text/html; charset=iso-8859-1".PHP_EOL;
  mail($recipient,$subject,$message,$headers);
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

function is_cli_mode()
{
  if (isset($_SERVER["argv"])==true)
  {
    return true;
  }
  else
  {
    return false;
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

function output_page($content,$title)
{
  $page_params=array();
  $page_params["page_title"]=$title;
  $page_params["global_styles_modified"]=\webdb\utils\resource_modified_timestamp("global.css");
  $page_params["global_script_modified"]=\webdb\utils\resource_modified_timestamp("global.js");
  $page_params["body_text"]=$content;
  die(\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."page",$page_params));
}

#####################################################################################################

function app_static_page($template,$title)
{
  $content=\webdb\utils\app_template_fill($template);
  \webdb\utils\output_page($content,$title);
}

#####################################################################################################

function static_page($template,$title)
{
  $content=\webdb\utils\template_fill($template);
  \webdb\utils\output_page($content,$title);
}

#####################################################################################################

function is_row_locked($schema,$table,$key_field,$key_value)
{
  global $settings;
  # $settings["user_record"]
  # delete expired locks
}

#####################################################################################################
