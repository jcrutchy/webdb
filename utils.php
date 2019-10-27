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
  if (isset($_GET["ajax"])==true)
  {
    $buf=ob_get_contents();
    if (strlen($buf)<>0)
    {
      ob_end_clean();
    }
    $data=array();
    $data["error"]=$message;
    $data=json_encode($data);
    die($data);
  }
  global $settings;
  $params=array();
  $params["page_title"]=$settings["app_name"];
  $params["message"]=$message;
  \webdb\utils\system_message(\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."message",$params));
}

#####################################################################################################

function ob_postprocess($buffer)
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
  return $buffer;
}

#####################################################################################################

function get_url()
{
  $url="http://";
  if (isset($_SERVER["HTTPS"])==true)
  {
    if (($_SERVER["HTTPS"]<>"") and ($_SERVER["HTTPS"]=="on"))
    {
      $url="https://";
    }
  }
  $url.=$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
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
  $user_record=$settings["user_record"];
  $user_id=$user_record["user_id"];
  $user_groups=\webdb\users\get_user_groups($user_id);
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
  $user_record=$settings["user_record"];
  $user_id=$user_record["user_id"];
  $user_groups=\webdb\users\get_user_groups($user_id);
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
  $output=\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."page",$page_params);
  die($output);
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
