<?php

namespace webdb\utils;

#####################################################################################################

function system_message($message)
{
  if (isset($_GET["testing"])==true)
  {
    global $settings;
    $settings["system_message_flag"]=true;
    $settings["system_message_output"]=$message;
    return;
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
  \webdb\utils\system_message(\webdb\utils\template_fill("global/message",$params));
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
