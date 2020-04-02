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
  $buffer=ob_get_contents();
  if (strlen($buffer)<>0)
  {
    ob_end_clean(); # discard buffer
  }
  $settings["system_message"]=$message;
  die;
}

#####################################################################################################

function webdb_debug_backtrace()
{
  global $settings;
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  # TODO: DEBUG INFO ONLY (SECURITY RISK) - UNCOMMENT FOLLOWING LINE FOR PRODUCTION
  #return "";
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $backtrace=json_encode(debug_backtrace()); # can't have pretty print indents for replacing settings
  $settings_json=json_encode($settings);
  $backtrace=str_replace($settings_json,"{\"settings\":\"...\"}",$backtrace);
  $backtrace=json_decode($backtrace);
  $backtrace=json_encode($backtrace,JSON_PRETTY_PRINT);
  if (\webdb\cli\is_cli_mode()==true)
  {
    return $backtrace;
  }
  $params=array();
  $params["backtrace"]=htmlspecialchars($backtrace);
  return \webdb\utils\template_fill("debug_backtrace",$params);
}

#####################################################################################################

function info_message($message,$show_backtrace=false)
{
  global $settings;
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
    $settings["system_message"]=$message;
    die;
  }
  if (\webdb\cli\is_cli_mode()==true)
  {
    \webdb\utils\system_message($message);
  }
  $params=array();
  $params["global_styles_modified"]=\webdb\utils\resource_modified_timestamp("global.css");
  $params["page_title"]=$settings["app_name"];
  $params["message"]=$message;
  $content=\webdb\utils\template_fill("error_message",$params);
  if ($show_backtrace==true)
  {
    $content.=\webdb\utils\webdb_debug_backtrace();
  }
  \webdb\utils\system_message($content);
}

#####################################################################################################

function error_message($message)
{
  if (isset($_GET["ajax"])==true)
  {
    $message.=\webdb\utils\webdb_debug_backtrace();
  }
  \webdb\utils\info_message($message,true);
}

#####################################################################################################

function debug_var_dump($data,$backtrace=false)
{
  global $settings;
  $settings["unauthenticated_content"]=true;
  var_dump($data);
  if ($backtrace==true)
  {
    echo \webdb\utils\webdb_debug_backtrace();
  }
  die;
}

#####################################################################################################

function load_settings()
{
  global $settings;
  \webdb\utils\build_settings();
  /*$settings_cache_filename=$settings["app_root_path"]."settings.cache";
  if (file_exists($settings_cache_filename)==false)
  {
    \webdb\utils\build_settings();
    $settings_cache_data=json_encode($settings,JSON_PRETTY_PRINT);
    file_put_contents($settings_cache_filename,$settings_cache_data);
  }
  else
  {
    $settings_cache_data=file_get_contents($settings_cache_filename);
    $settings=json_decode($settings_cache_data,true);
  }*/
  \webdb\utils\load_test_settings();
}

#####################################################################################################

function build_settings()
{
  global $settings;
  \webdb\utils\initialize_settings();
  \webdb\utils\load_webdb_settings();
  \webdb\utils\load_application_settings();
  $settings["templates"]=\webdb\utils\load_files($settings["webdb_templates_path"],"","htm",true);
  $settings["webdb_templates"]=$settings["templates"];
  $settings["sql"]=\webdb\utils\load_files($settings["webdb_sql_path"],"","sql",true);
  $settings["webdb_sql"]=$settings["sql"];
  \webdb\utils\load_db_credentials("admin");
  \webdb\utils\load_db_credentials("user");
  $settings["app_templates"]=\webdb\utils\load_files($settings["app_templates_path"],"","htm",true);
  $settings["templates"]=array_merge($settings["webdb_templates"],$settings["app_templates"]);
  $settings["app_sql"]=\webdb\utils\load_files($settings["app_sql_path"],"","sql",true);
  $settings["sql"]=array_merge($settings["webdb_sql"],$settings["app_sql"]);
  $settings["forms"]=array();
  \webdb\forms\load_form_defs();
  $settings["app_group_access"]=explode(",",$settings["app_group_access"]);
}

#####################################################################################################

function initialize_settings()
{
  global $settings;
  $includes=get_included_files();
  $settings["app_root_path"]=dirname($includes[0]).DIRECTORY_SEPARATOR;
  $settings["links_css"]=array();
  $settings["links_js"]=array();
  $settings["logs"]=array();
  $settings["logs"]["sql"]=array();
  $settings["logs"]["auth"]=array();
  $settings["login_cookie_unset"]=false;
  $settings["sql_check_post_params_override"]=false;
  $settings["sql_database_change"]=false;
  $settings["calendar_fields"]=array();
  $settings["permissions"]=array();
  $settings["parent_path"]=dirname(__DIR__).DIRECTORY_SEPARATOR;
  $settings["webdb_root_path"]=__DIR__.DIRECTORY_SEPARATOR;
  $settings["webdb_directory_name"]=basename($settings["webdb_root_path"]);
  $settings["app_directory_name"]=basename($settings["app_root_path"]);
  #$settings["constants"]=get_defined_constants(false); # BAD FOR PERFORMANCE OF TEMPLATE_FILL FUNCTION
  $settings["constants"]=array();
  $settings["constants"]["DIRECTORY_SEPARATOR"]=DIRECTORY_SEPARATOR;
}

#####################################################################################################

function load_webdb_settings()
{
  global $settings;
  $webdb_settings_filename=$settings["webdb_root_path"]."settings.php";
  if (file_exists($webdb_settings_filename)==true)
  {
    require_once($webdb_settings_filename);
  }
  else
  {
    \webdb\utils\system_message("error: webdb settings file not found");
  }
}

#####################################################################################################

function load_application_settings()
{
  global $settings;
  $settings_filename=$settings["app_root_path"]."settings.php";
  if (file_exists($settings_filename)==false)
  {
    \webdb\utils\system_message("error: settings file not found: ".$settings_filename);
  }
  require_once($settings_filename);
}

#####################################################################################################

function database_connect()
{
  global $settings;
  $db_database="";
  if ($settings["db_database"]<>"")
  {
    $db_database=";".$settings["db_database"];
  }
  $settings["pdo_admin"]=new \PDO($settings["db_engine"].":".$settings["db_host"].$db_database,$settings["db_admin_username"],$settings["db_admin_password"]);
  if ($settings["pdo_admin"]===false)
  {
    \webdb\utils\system_message("error: unable to connect to sql server as admin");
  }
  $settings["pdo_user"]=new \PDO($settings["db_engine"].":".$settings["db_host"].$db_database,$settings["db_user_username"],$settings["db_user_password"]);
  if ($settings["pdo_user"]===false)
  {
    \webdb\utils\system_message("error: unable to connect to sql server as user");
  }
}

#####################################################################################################

function webdb_setcookie($setting_key,$value,$max_age=false)
{
  global $settings;
  $settings["cookie_headers_set"][$setting_key]=$value;
  if ($max_age===false)
  {
    $max_age=$settings["max_cookie_age"];
  }
  \webdb\utils\webdb_setcookie_raw($settings[$setting_key],$value,$max_age);
}

#####################################################################################################

function webdb_setcookie_raw($name,$value,$max_age=0,$http_only=true)
{
  if ($max_age>0)
  {
    $expiry=time()+$max_age;
  }
  else
  {
    $expiry=0;
  }
  setcookie($name,$value,$expiry,"/",$_SERVER["HTTP_HOST"],false,$http_only);
}

#####################################################################################################

function webdb_unsetcookie($setting_key,$http_only=true)
{
  global $settings;
  setcookie($settings[$setting_key],"",1,"/",$_SERVER["HTTP_HOST"],false,$http_only);
}

#####################################################################################################

function output_page($content,$title)
{
  global $settings;
  $page_params=array();
  $page_params["page_title"]=$title;
  $page_params["global_styles_modified"]=\webdb\utils\resource_modified_timestamp("global.css");
  $page_params["global_script_modified"]=\webdb\utils\resource_modified_timestamp("global.js");
  $page_params["header"]="";
  if ($settings["header_template"]<>"")
  {
    $page_params["header"]=\webdb\utils\template_fill($settings["header_template"]);
  }
  $page_params["body_text"]=$content;
  if (isset($settings["user_record"])==true)
  {
    $user_record=$settings["user_record"];
    \webdb\users\obfuscate_hashes($user_record);
    $page_params["authenticated_status"]=\webdb\utils\template_fill("authenticated_status",$user_record);
  }
  else
  {
    $page_params["authenticated_status"]=\webdb\utils\template_fill("unauthenticated_status");
  }
  $page_params["calendar"]=\webdb\forms\get_calendar();
  $output=\webdb\utils\template_fill("page",$page_params);
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

function output_resource_links($buffer,$type)
{
  global $settings;
  $links=array();
  foreach ($settings["links_".$type] as $template_key => $link)
  {
    $links[]=$link;
  }
  $links=implode(PHP_EOL,$links);
  $buffer=explode("%%page_links_".$type."%%",$buffer,2);
  return implode($links,$buffer);
}

#####################################################################################################

function ob_postprocess($buffer)
{
  global $settings;
  $buffer=\webdb\csrf\fill_csrf_token($buffer);
  $buffer=\webdb\utils\output_resource_links($buffer,"css");
  $buffer=\webdb\utils\output_resource_links($buffer,"js");
  if (isset($settings["system_message"])==false)
  {
    if ($settings["check_templates"]==true)
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
  }
  else
  {
    $buffer=$settings["system_message"];
  }
  # TODO: CALL AUTOMATED W3C VALIDATION HERE
  global $start_time; # debug
  global $stop_time; # debug
  #$stop_time=microtime(true); # debug
  #return round($stop_time-$start_time,5); # debug
  if (isset($_SERVER["HTTP_ACCEPT_ENCODING"])==true)
  {
    if (strpos($_SERVER["HTTP_ACCEPT_ENCODING"],"gzip")!==false)
    {
      $buffer=gzencode($buffer);
      header("Content-Encoding: gzip");
    }
  }
  if (\webdb\cli\is_cli_mode()==false)
  {
    $msg="REQUEST_COMPLETED";
    $settings["logs"]["auth"][]=$msg;
    $settings["logs"]["sql"][]=$msg;
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
      $key=str_replace("\\","/",$fn);
      $result[$key]=trim(file_get_contents($full));
    }
    else
    {
      $result=$result+\webdb\utils\load_files($full.DIRECTORY_SEPARATOR,$root,$ext,$trim_ext);
    }
  }
  return $result;
}

#####################################################################################################

function trim_suffix($value,$suffix)
{
  $suffix_len=strlen($suffix);
  $test=substr($value,-$suffix_len);
  if ($test==$suffix)
  {
    $value=substr($value,0,strlen($value)-$suffix_len);
  }
  return $value;
}

#####################################################################################################

function make_singular($plural)
{
  global $settings;
  $replaces=array(
    "children"=>"child",
    "geese"=>"goose",
    "men"=>"man",
    "teeth"=>"tooth",
    "feet"=>"foot",
    "mice"=>"mouse",
    "people"=>"person",
    "a"=>"on",
    "i"=>"us",
    "yses"=>"ysis",
    "pses"=>"psis",
    "ies"=>"y",
    "ses"=>"s",
    "shes"=>"sh",
    "ches"=>"ch",
    "xes"=>"x",
    "zes"=>"z",
    "ves"=>"f",
    "oes"=>"o",
    "s"=>"");
  $priority=array();
  foreach ($settings["irregular_plurals"] as $loop_singular => $loop_plural)
  {
    $priority[$loop_plural]=$loop_singular;
  }
  $replaces=array_merge($priority,$replaces);
  foreach ($settings["irregular_plurals"] as $loop_singular => $loop_plural)
  {
    $replaces[$loop_plural]=$loop_singular;
  }
  return \webdb\utils\replace_suffix($plural,$replaces,\webdb\utils\singular_plurals());
}

#####################################################################################################

function singular_plurals()
{
  return array(
    "aircraft",
    "deer",
    "series",
    "species",
    "sheep",
    "fish",
    "equipment");
}

#####################################################################################################

function make_plural($singular)
{
  global $settings;
  $replaces=array(
    "child"=>"children",
    "goose"=>"geese",
    "man"=>"men",
    "tooth"=>"teeth",
    "foot"=>"feet",
    "mouse"=>"mice",
    "person"=>"people",
    "ion"=>"ions",
    "on"=>"a",
    "us"=>"i",
    "is"=>"es",
    "oto"=>"otos",
    "ano"=>"anos",
    "alo"=>"alos",
    "o"=>"oes",
    "ay"=>"ays",
    "ey"=>"eys",
    "iy"=>"iys",
    "oy"=>"oys",
    "uy"=>"uys",
    "y"=>"ies",
    "as"=>"asses",
    "s"=>"ses",
    "sh"=>"shes",
    "ch"=>"ches",
    "x"=>"xes",
    "ez"=>"ezzes",
    "z"=>"zes",
    "ife"=>"ives",
    "fe"=>"ves",
    "lf"=>"lves");
  $priority=array();
  foreach ($settings["irregular_plurals"] as $loop_singular => $loop_plural)
  {
    $priority[$loop_singular]=$loop_plural;
  }
  $replaces=array_merge($priority,$replaces);
  foreach ($settings["irregular_plurals"] as $loop_singular => $loop_plural)
  {
    $replaces[$loop_singular]=$loop_plural;
  }
  return \webdb\utils\replace_suffix($singular,$replaces,\webdb\utils\singular_plurals(),"s");
}

#####################################################################################################

function replace_suffix($subject,$replaces,$unchanged,$default_append=false)
{
  $subject_parts=str_replace(" ","_",$subject);
  $subject_parts=explode("_",$subject);
  $last_part=array_pop($subject_parts);
  if (in_array($last_part,$unchanged)==true)
  {
    return $subject;
  }
  foreach ($replaces as $old => $new)
  {
    $test=substr($last_part,-strlen($old));
    if ($test==$old)
    {
      $last_part_new=substr($last_part,0,strlen($last_part)-strlen($old)).$new;
      return substr($subject,0,strlen($subject)-strlen($last_part)).$last_part_new;
    }
  }
  if ($default_append!==false)
  {
    return $subject.$default_append;
  }
  return $subject;
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

function link_app_resource($name,$type)
{
  global $settings;
  #return false; # TODO: pre-load file modified times into $settings
  $filename=$settings["app_resources_path"].$name.".".$type;
  if (file_exists($filename)==true)
  {
    $params=array();
    $params["name"]=$name;
    $params["modified"]=filemtime($filename);
    return \webdb\utils\template_fill("app_resource_".$type,$params);
  }
  return false;
}

#####################################################################################################

function resource_links($template_key)
{
  global $settings;
  $link=\webdb\utils\link_app_resource($template_key,"css");
  if ($link!==false)
  {
    $settings["links_css"][$template_key]=$link;
  }
  $link=\webdb\utils\link_app_resource($template_key,"js");
  if ($link!==false)
  {
    $settings["links_js"][$template_key]=$link;
  }
}

#####################################################################################################

function check_user_app_permission()
{
  global $settings;
  $user_groups=$settings["logged_in_user_groups"];
  for ($i=0;$i<count($settings["app_group_access"]);$i++)
  {
    $allowed_group_name=$settings["app_group_access"][$i];
    if ($allowed_group_name==="*")
    {
      return;
    }
    for ($j=0;$j<count($user_groups);$j++)
    {
      $user_group=$user_groups[$j]["group_name"];
      if ($user_group==$allowed_group_name)
      {
        return;
      }
    }
  }
  \webdb\utils\error_message("You do not have required permission to access this application.");
}

#####################################################################################################

function check_user_form_permission($page_id,$permission)
{
  global $settings;
  $whitelisted=false;
  foreach ($settings["permissions"] as $group_name_iterator => $group_permissions)
  {
    if (isset($group_permissions["forms"][$page_id])==true)
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
  if (isset($settings["permissions"]["*"]["forms"][$page_id])==true)
  {
    $permissions=$settings["permissions"]["*"]["forms"][$page_id];
    if (strpos($permissions,$permission)!==false)
    {
      return true;
    }
  }
  $user_groups=$settings["logged_in_user_groups"];
  for ($i=0;$i<count($user_groups);$i++)
  {
    $user_group=$user_groups[$i]["group_name"];
    if (isset($settings["permissions"][$user_group]["forms"][$page_id])==true)
    {
      $permissions=$settings["permissions"][$user_group]["forms"][$page_id];
      if (strpos($permissions,$permission)!==false)
      {
        return true;
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
  if (isset($settings["permissions"]["*"]["templates"][$template_name])==true)
  {
    $substitute_template=$settings["permissions"]["*"]["templates"][$template_name];
    if ($substitute_template=="")
    {
      return $template_name;
    }
    return $substitute_template;
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
  foreach ($settings["constants"] as $key => $value)
  {
    $placeholder='??'.$key.'??';
    if (strpos($result,$placeholder)===false)
    {
      continue;
    }
    $result=str_replace($placeholder,$value,$result);
  }
  foreach ($settings as $key => $value)
  {
    $placeholder='$$'.$key.'$$';
    if (strpos($result,$placeholder)===false)
    {
      continue;
    }
    $result=str_replace($placeholder,$value,$result);
  }
  if ($params!==false)
  {
    foreach ($params as $key => $value)
    {
      $placeholder='%%'.$key.'%%';
      if (strpos($result,$placeholder)===false)
      {
        continue;
      }
      if (is_array($value)==false)
      {
        $result=str_replace($placeholder,$value,$result);
      }
    }
  }
  if (($custom_templates===false) and ($template_key<>"app_resource_styles") and ($template_key<>"app_resource_script"))
  {
    \webdb\utils\resource_links($template_key);
  }
  foreach ($template_array as $key => $value)
  {
    $placeholder='@@'.$key.'@@';
    if (strpos($result,$placeholder)===false)
    {
      continue;
    }
    $value=\webdb\utils\template_fill($key,$params,$tracking,$custom_templates);
    $result=str_replace($placeholder,$value,$result);
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
  global $settings;
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
  if (file_exists($filename)==false)
  {
    return;
  }
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
  #return "error"; # TODO: pre-load into $settings
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
    \webdb\utils\error_message("error: array expected with parent key: ".$parent_key);
  }
  $child_keys=array_keys($array[$parent_key]);
  if (count($child_keys)<>1)
  {
    \webdb\utils\error_message("error: invalid child array key count: ".$parent_key);
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

function wildcard_compare($compare_value,$wildcard_value)
{
  # wildcard is * character
  $compare_length=strlen($compare_value);
  $wildcard_length=strlen($wildcard_value);
  if ($wildcard_length>$compare_length)
  {
    return false;
  }
  $is_wildcard=false;
  $wildcard_index=0;
  for ($i=0;$i<count($compare_length);$i++)
  {
    if ($wildcard_index>($wildcard_length-1))
    {
      return false;
    }
    if ($wildcard_value[$wildcard_index]=="*")
    {
      $is_wildcard=true;
      $wildcard_index++;
      continue;
    }
    if (($wildcard_value[$wildcard_index]==$compare_value[$i]) and ($is_wildcard==true))
    {
      $is_wildcard=false;
      $wildcard_index++;
      continue;
    }
    if ($wildcard_value[$wildcard_index]<>$compare_value[$i])
    {
      return false;
    }
    if ($is_wildcard==false)
    {
      $wildcard_index++;
    }
  }
  return true;
}

#####################################################################################################

function save_log($key)
{
  global $settings;
  $lines=$settings["logs"][$key];
  $path=$settings[$key."_log_path"];
  if ((file_exists($path)==false) or (is_dir($path)==false))
  {
    return;
  }
  $fn=$path.$key."_".date("Ymd").".log";
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

#####################################################################################################

function save_logs()
{
  global $settings;
  foreach ($settings["logs"] as $key => $lines)
  {
    if ($settings[$key."_log_enabled"]==true)
    {
      \webdb\utils\save_log($key);
    }
  }
}

#####################################################################################################

function init_webdb_schema()
{
  \webdb\sql\file_execute_prepare("webdb_schema",array(),true);
}

#####################################################################################################

function init_app_schema()
{
  global $settings;
  $filename=$settings["app_sql_path"]."schema.sql";
  if (file_exists($filename)==true)
  {
    $sql=trim(file_get_contents($filename));
    \webdb\sql\execute_prepare($sql,array(),"",true);
    return true;
  }
  else
  {
    return false;
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
