<?php

namespace webdb\utils;

#####################################################################################################

function simple_app_settings()
{
  global $settings;
  $settings["database_enable"]=false;
  $settings["auth_enable"]=false;
  $settings["app_date_format"]="j-M-y";
  $settings["default_timezone"]="Australia/Sydney";
  $settings["login_check_address"]=false;
  $settings["login_check_agent"]=false;
  $settings["chat_global_enable"]=false;
  $settings["ip_whitelist_enabled"]=false;
  $settings["ip_blacklist_enabled"]=false;
  $settings["check_ua"]=false;
  $settings["check_templates"]=false;
  $settings["email_enabled"]=false;
  $settings["file_upload_mode"]="rename"; # rename | ftp
  $settings["header_template"]="";
  $settings["links_template"]="";
  $settings["footer_template"]="";
  $settings["login_notice_template"]="";
}

#####################################################################################################

function system_message($message)
{
  global $settings;
  if (\webdb\cli\is_cli_mode()==true)
  {
    \webdb\cli\term_echo(\webdb\utils\webdb_str_replace(PHP_EOL," ",$message),33);
    die;
  }
  $settings["unauthenticated_content"]=true;
  $settings["system_message"]=$message;
  die;
}

#####################################################################################################

function webdb_debug_backtrace()
{
  global $settings;
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  # TODO: DEBUG INFO ONLY (SECURITY RISK) - UNCOMMENT FOLLOWING LINE FOR PRODUCTION
  return "";
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $backtrace=json_encode(debug_backtrace()); # can't have pretty print indents for replacing settings
  $settings_json=json_encode($settings);
  $backtrace=\webdb\utils\webdb_str_replace($settings_json,"{\"settings\":\"...\"}",$backtrace);
  $backtrace=json_decode($backtrace);
  $backtrace=json_encode($backtrace,JSON_PRETTY_PRINT);
  if (\webdb\cli\is_cli_mode()==true)
  {
    return $backtrace;
  }
  $params=array();
  $params["backtrace"]=\webdb\utils\webdb_htmlspecialchars($backtrace);
  return \webdb\utils\template_fill("debug_backtrace",$params);
}

#####################################################################################################

function info_message($message,$show_backtrace=false,$home_link=true)
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
  if ($home_link==true)
  {
    $content=\webdb\utils\template_fill("error_message",$params);
  }
  else
  {
    $params["alert_message_styles_modified"]=\webdb\utils\resource_modified_timestamp("alert_message.css");
    $content=\webdb\utils\template_fill("alert_message",$params);
  }
  if ($show_backtrace==true)
  {
    $content.=\webdb\utils\webdb_debug_backtrace();
  }
  \webdb\utils\system_message($content);
}

#####################################################################################################

function error_message($message)
{
  global $settings;
  if (isset($_GET["ajax"])==true)
  {
    $message.=\webdb\utils\webdb_debug_backtrace();
  }
  $username="";
  if (isset($settings["logged_in_username"])==true)
  {
    $username=$settings["logged_in_username"];
  }
  elseif (isset($_COOKIE[$settings["username_cookie"]])==true)
  {
    $username=$_COOKIE[$settings["username_cookie"]];
  }
  $func_name=$settings["error_event_handler"];
  if (function_exists($func_name)==true)
  {
    call_user_func($func_name,$username,$message);
  }
  $email_message="username: ".$username.PHP_EOL.PHP_EOL.$message;
  $subject=$settings["app_name"]." \\webdb\\utils\\error_message";
  \webdb\utils\send_email($settings["admin_email"],"",$subject,$email_message);
  \webdb\utils\info_message($message,true);
}

#####################################################################################################

function debug_var_dump($data,$backtrace=false)
{
  global $settings;
  $settings["unauthenticated_content"]=true;
  $settings["check_templates"]=false;
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
  #\webdb\utils\load_test_settings();
}

#####################################################################################################

function build_settings()
{
  global $settings;
  \webdb\utils\initialize_settings();
  \webdb\utils\load_webdb_settings();
  \webdb\utils\load_application_settings();
  if ($settings["database_enable"]==true)
  {
    \webdb\utils\load_credentials("db_admin");
    \webdb\utils\load_credentials("db_user");
  }
  if ($settings["file_upload_mode"]=="ftp")
  {
    \webdb\utils\load_credentials("ftp_credentials",true);
  }
  if ($settings["enable_pwd_file_encrypt"]==true)
  {
    if (file_exists($settings["encrypt_key_file"])==false)
    {
      \webdb\utils\error_message("error: encrypt key file not found");
    }
    $key=trim(file_get_contents($settings["encrypt_key_file"]));
    $settings["db_admin_password"]=\webdb\encrypt\webdb_decrypt($settings["db_admin_password"],$key);
    $settings["db_user_password"]=\webdb\encrypt\webdb_decrypt($settings["db_user_password"],$key);
    $settings["ftp_credentials_password"]=\webdb\encrypt\webdb_decrypt($settings["ftp_credentials_password"],$key);
    # ~~~~~~~~~~~~~~~~~~~
    #$test=array();
    #$test["encrypted"]=\webdb\encrypt\webdb_encrypt("test",$key);
    #$test["decrypted"]=\webdb\encrypt\webdb_decrypt($test["encrypted"],$key);
    # ~~~~~~~~~~~~~~~~~~~
    sodium_memzero($key);
    #\webdb\utils\debug_var_dump($test);
  }
  $settings["webdb_templates"]=\webdb\utils\load_files($settings["webdb_templates_path"],"","htm",true);
  $settings["app_templates"]=\webdb\utils\load_files($settings["app_templates_path"],"","htm",true);
  $settings["templates"]=array_merge($settings["webdb_templates"],$settings["app_templates"]);
  $settings["env_templates"]=array();
  if (file_exists($settings["env_templates_path"])==true)
  {
    if (is_dir($settings["env_templates_path"])==true)
    {
      $settings["env_templates"]=\webdb\utils\load_files($settings["env_templates_path"],"","htm",true);
      $settings["templates"]=array_merge($settings["templates"],$settings["env_templates"]);
    }
  }
  $settings["initialized_templates"]=array();
  \webdb\utils\initialize_format_tags();
  if ($settings["database_enable"]==true)
  {
    $settings["webdb_sql_common"]=\webdb\utils\load_files($settings["webdb_sql_common_path"],"","sql",true);
    $settings["webdb_sql_engine"]=\webdb\utils\load_files($settings["webdb_sql_engine_path"],"","sql",true);
    $settings["webdb_sql"]=array_merge($settings["webdb_sql_common"],$settings["webdb_sql_engine"]);
    $settings["app_sql_common"]=\webdb\utils\load_files($settings["app_sql_common_path"],"","sql",true);
    $settings["app_sql_engine"]=\webdb\utils\load_files($settings["app_sql_engine_path"],"","sql",true);
    $settings["app_sql"]=array_merge($settings["app_sql_common"],$settings["app_sql_engine"]);
    $settings["sql"]=array_merge($settings["webdb_sql"],$settings["app_sql"]);
  }
  $settings["forms"]=array();
  \webdb\forms\load_form_defs();
  $settings["app_group_access"]=\webdb\utils\webdb_explode(",",$settings["app_group_access"]);
}

#####################################################################################################

function initialize_settings()
{
  global $settings;
  $includes=get_included_files();
  $settings["env_root_path"]=dirname($includes[0]).DIRECTORY_SEPARATOR;
  $settings["env_root_path"]=str_replace("\\","/",$settings["env_root_path"]);
  $settings["links_css"]=array();
  $settings["links_js"]=array();
  $settings["logs"]=array();
  $settings["logs"]["sql"]=array();
  $settings["logs"]["auth"]=array();
  $settings["logs"]["sql_change"]=array();
  $settings["login_cookie_unset"]=false;
  $settings["sql_check_post_params_override"]=false;
  $settings["sql_database_change"]=false;
  $settings["calendar_fields"]=array();
  $settings["permissions"]=array();
  $settings["templates"]=array();
  $settings["webdb_parent_path"]=dirname(__DIR__).DIRECTORY_SEPARATOR;
  $settings["webdb_parent_path"]=str_replace("\\","/",$settings["webdb_parent_path"]);
  $settings["webdb_root_path"]=__DIR__.DIRECTORY_SEPARATOR;
  $settings["webdb_root_path"]=str_replace("\\","/",$settings["webdb_root_path"]);
  $settings["webdb_directory_name"]=basename($settings["webdb_root_path"]);
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
    \webdb\utils\system_message("error: application settings file not found: ".$settings_filename);
  }
  require_once($settings_filename);
}

#####################################################################################################

function database_connect()
{
  global $settings;
  if ($settings["database_enable"]==false)
  {
    return;
  }
  $db_database="";
  if ($settings["db_database"]<>"")
  {
    $db_database=";".$settings["db_database"];
  }
  $settings["unauthenticated_content"]=true;
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
  $cookie_name=\webdb\utils\convert_to_cookie_name($name);
  # TODO: instead of using expires, try max-age (in seconds) but probably need to use header() function instead of setcookie()
  setcookie($cookie_name,$value,$expiry,"/",$_SERVER["HTTP_HOST"],false,$http_only);
}

#####################################################################################################

function webdb_unsetcookie_raw($name,$http_only=true)
{
  $cookie_name=\webdb\utils\convert_to_cookie_name($name);
  unset($_COOKIE[$cookie_name]);
  setcookie($cookie_name,"",1,"/",$_SERVER["HTTP_HOST"],false,$http_only);
}

#####################################################################################################

function webdb_unsetcookie($setting_key,$http_only=true)
{
  global $settings;
  \webdb\utils\webdb_unsetcookie_raw($settings[$setting_key],$http_only);
}

#####################################################################################################

function convert_to_cookie_name($name)
{
  return \webdb\utils\webdb_str_replace(" ","_",$name);
}

#####################################################################################################

function output_page($content,$title)
{
  global $settings;
  header("Cache-Control: no-cache");
  header("Expires: -1");
  header("Pragma: no-cache");
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
  $page_params["app_name"]=$settings["app_name"];
  $page_params["additional_head_html"]=$settings["additional_head_html"];
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
    $lines=\webdb\utils\webdb_explode(PHP_EOL,$data);
    for ($i=0;$i<count($lines);$i++)
    {
      $parts=\webdb\utils\webdb_explode("=",trim($lines[$i]));
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
  $buffer=\webdb\utils\webdb_explode("%%page_links_".$type."%%",$buffer,2);
  return implode($links,$buffer);
}

#####################################################################################################

function ob_postprocess($buffer)
{
  global $settings;
  #\webdb\utils\email_admin(print_r($_COOKIE,true),"ob_postprocess"); # DEBUG
  if (isset($settings["system_message"])==true)
  {
    return $settings["system_message"];
  }
  if ((isset($_GET["ajax"])==true) or (isset($_GET["update_oul"])==true))
  {
    if ((\webdb\utils\compare_template("login_form",$buffer)==true) or (\webdb\utils\check_csrf_error($buffer)==true))
    {
      return "redirect_to_login_form";
    }
  }
  if (isset($settings["ignore_ob_postprocess"])==true)
  {
    if ($settings["ignore_ob_postprocess"]==true)
    {
      return $buffer;
    }
  }
  $buffer=\webdb\csrf\fill_csrf_token($buffer);
  $buffer=\webdb\utils\output_resource_links($buffer,"css");
  $buffer=\webdb\utils\output_resource_links($buffer,"js");
  $buffer=\webdb\utils\string_template_fill($buffer);
  if ($settings["check_templates"]==true)
  {
    foreach ($settings as $key => $value)
    {
      if (strpos($buffer,'$$'.$key.'$$')!==false)
      {
        $buffer="error: unassigned $ template '".$key."' found: ".\webdb\utils\webdb_htmlspecialchars($buffer);
        break;
      }
    }
    foreach ($settings["templates"] as $key => $value)
    {
      if (strpos($buffer,'@@'.$key.'@@')!==false)
      {
        $buffer="error: unassigned @ template '".$key."' found: ".\webdb\utils\webdb_htmlspecialchars($buffer);
        break;
      }
    }
  }
  if ($settings["auth_enable"]==true)
  {
    if ((isset($settings["unauthenticated_content"])==false) and (isset($settings["user_record"])==false))
    {
      $buffer="error: authentication failure";
    }
  }
  if (\webdb\cli\is_cli_mode()==false)
  {
    $msg="REQUEST_COMPLETED";
    $settings["logs"]["auth"][]=$msg;
    $settings["logs"]["sql"][]=$msg;
    #$settings["logs"]["sql_change"][]=$msg;
  }
  \webdb\utils\save_logs();
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
  if ($buffer=="")
  {
    return "[buffer empty]";
  }
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

function get_base_url()
{
  global $settings;
  $url="http://";
  if (isset($_SERVER["HTTPS"])==true)
  {
    if (($_SERVER["HTTPS"]<>"") and ($_SERVER["HTTPS"]=="on"))
    {
      $url="https://";
    }
  }
  return $url.$_SERVER["HTTP_HOST"].$settings["app_web_index"];
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
      $key=\webdb\utils\webdb_str_replace("\\","/",$fn);
      $result[$key]=trim(file_get_contents($full));
    }
    else
    {
      $result=array_merge($result,\webdb\utils\load_files($full.DIRECTORY_SEPARATOR,$root,$ext,$trim_ext));
    }
  }
  return $result;
}

#####################################################################################################

function list_files($path,$root="") # path (and root) must have trailing delimiter
{
  if ($root=="")
  {
    $root=$path;
  }
  $result=array();
  $file_list=scandir($path);
  if ($file_list===false)
  {
    return array();
  }
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
      $result[]=$full;
    }
    else
    {
      $result=array_merge($result,\webdb\utils\list_files($full.DIRECTORY_SEPARATOR,$root));
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
  $subject_parts=\webdb\utils\webdb_str_replace(" ","_",$subject);
  $subject_parts=\webdb\utils\webdb_explode("_",$subject);
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
  return \webdb\utils\custom_template_fill($sql_key,$params,array(),$settings["sql"]);
}

#####################################################################################################

function search_sql_records(&$records,$find_column,$find_value) # warning: use sparingly for small datasets only - not very efficient compared to SQL query
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

function get_resource_link($name,$type,$source="app")
{
  global $settings;
  #return false; # TODO: pre-load file modified times into $settings
  $filename=$settings[$source."_resources_path"].$name.".".$type;
  $filename=\webdb\utils\webdb_str_replace("/",DIRECTORY_SEPARATOR,$filename);
  if (file_exists($filename)==true)
  {
    $params=array();
    $params["name"]=$name;
    $params["modified"]=filemtime($filename);
    $params["path"]=$settings[$source."_web_resources"];
    return \webdb\utils\template_fill("resource_".$type,$params);
  }
  return false;
}

#####################################################################################################

function add_resource_link($name,$type)
{
  global $settings;
  $link=\webdb\utils\get_resource_link($name,$type,"webdb");
  if ($link!==false)
  {
    $settings["links_".$type][$name]=$link;
  }
  $link=\webdb\utils\get_resource_link($name,$type);
  if ($link!==false)
  {
    $settings["links_".$type][$name]=$link;
  }
}

#####################################################################################################

function template_resource_links($template_key)
{
  \webdb\utils\add_resource_link($template_key,"css");
  \webdb\utils\add_resource_link($template_key,"js");
}

#####################################################################################################

function check_user_app_permission()
{
  global $settings;
  if ($settings["auth_enable"]==false)
  {
    return;
  }
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
  \webdb\utils\error_message(\webdb\utils\template_fill("app_permission_error"));
}

#####################################################################################################

function check_user_form_permission($page_id,$permission)
{
  global $settings;
  if ($settings["auth_enable"]==false)
  {
    return true;
  }
  $admin_user_locked=array("groups");
  if (in_array($page_id,$admin_user_locked)==true)
  {
    if (isset($settings["user_record"])==false)
    {
      return false;
    }
    if (in_array($settings["user_record"]["user_id"],$settings["group_admin_user_id"])==false)
    {
      return false;
    }
  }
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
    if (isset($_GET["page"])==true)
    {
      if ($_GET["page"]<>$page_id)
      {
        # check that $page_id is a subform of $_GET["page"] and if so use parent permission
        $parent=\webdb\forms\get_form_config($_GET["page"],true,true);
        if (isset($parent["edit_subforms"][$page_id])==true)
        {
          $page_id=$_GET["page"];
        }
      }
    }
    if (isset($_GET["parent_form"])==true)
    {
      if ($_GET["parent_form"]<>$page_id)
      {
        # check that $page_id is a subform of $_GET["parent_form"] and if so use parent permission
        $parent=\webdb\forms\get_form_config($_GET["parent_form"],true,true);
        if (isset($parent["edit_subforms"][$page_id])==true)
        {
          $page_id=$_GET["parent_form"];
        }
      }
    }
    if (isset($_GET["redirect"])==true)
    {
      $redirect_url=$_GET["redirect"];
      $query=parse_url($redirect_url,PHP_URL_QUERY);
      $query_params=\webdb\utils\webdb_explode("&",$query);
      for ($i=0;$i<count($query_params);$i++)
      {
        $query_param=$query_params[$i];
        $parts=\webdb\utils\webdb_explode("=",$query_param);
        $param_key=array_shift($parts);
        if ($param_key=="page")
        {
          $page_id=implode("=",$parts);
        }
      }
    }
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
      # allow view-only
      if ($permission=="r")
      {
        #return true;
      }
      return false;
    }
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
  if ($settings["auth_enable"]==false)
  {
    return $template_name;
  }
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

function initialize_format_tags()
{
  global $settings;
  $settings["format_tag_templates"]=array();
  $subdir=$settings["format_tag_templates_subdirectory"];
  $template_prefix=$subdir.DIRECTORY_SEPARATOR;
  $format_tag_templates=array();
  foreach ($settings["templates"] as $name => $content)
  {
    if (substr($name,0,strlen($template_prefix))==$template_prefix)
    {
      $name=substr($name,strlen($template_prefix));
      $name_parts=\webdb\utils\webdb_explode("_",$name);
      $position=array_pop($name_parts);
      $tag=trim(implode("_",$name_parts));
      if (($position<>"open") and ($position<>"close"))
      {
        continue;
      }
      if ($tag=="")
      {
        continue;
      }
      if (isset($settings["format_tag_templates"][$tag])==false)
      {
        $settings["format_tag_templates"][$tag]=array();
      }
      $settings["format_tag_templates"][$tag][$position]=$content;
    }
  }
}

#####################################################################################################

function string_template_fill($input,$params=false)
{
  global $settings;
  $key="string_template_fill.tmp";
  $templates=$settings["templates"];
  $templates[$key]=$input;
  return \webdb\utils\custom_template_fill($key,$params,array(),$templates);
}

#####################################################################################################

function template_fill($template_key,$params=false)
{
  global $settings;
  if (isset($settings["initialized_templates"][$template_key])==false)
  {
    $settings["templates"][$template_key]=\webdb\utils\custom_template_fill($template_key);
    $settings["initialized_templates"][$template_key]=true;
  }
  $result=$settings["templates"][$template_key];
  if ($params===false)
  {
    return $result;
  }
  foreach ($params as $key => $value)
  {
    if (is_null($value)==true)
    {
      $value="";
    }
    if (is_scalar($value)==true)
    {
      $result=\webdb\utils\webdb_str_replace('%%'.$key.'%%',$value,$result);
    }
  }
  return $result;
}

#####################################################################################################

function custom_template_fill($template_key,$params=false,$tracking=array(),$custom_templates=false) # tracking array is used internally to limit recursion and should not be manually passed
{
  global $settings;
  if ($template_key=="")
  {
    return "";
  }
  if (isset($settings["templates"])==false)
  {
    \webdb\utils\system_message("error: template '".$template_key."' could not be filled before loading templates");
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
    return custom_template_fill($substitute_template,$params,$tracking,$custom_templates);
  }
  $tracking[]=$template_key;
  $result=$template_array[$template_key];
  foreach ($settings["constants"] as $key => $value)
  {
    $placeholder='??'.$key.'??';
    $result=\webdb\utils\webdb_str_replace($placeholder,$value,$result);
  }
  foreach ($settings as $key => $value)
  {
    $placeholder='$$'.$key.'$$';
    if (is_null($value)==true)
    {
      $value="";
    }
    if (is_scalar($value)==true)
    {
      $result=\webdb\utils\webdb_str_replace($placeholder,$value,$result);
    }
  }
  if ($params!==false)
  {
    foreach ($params as $key => $value)
    {
      $placeholder='%%'.$key.'%%';
      if (is_null($value)==true)
      {
        $value="";
      }
      if (is_scalar($value)==true)
      {
        $result=\webdb\utils\webdb_str_replace($placeholder,$value,$result);
      }
    }
  }
  if (($custom_templates===false) and ($template_key<>"app_resource_styles") and ($template_key<>"app_resource_script"))
  {
    \webdb\utils\template_resource_links($template_key);
  }
  foreach ($template_array as $key => $value)
  {
    $placeholder='@@'.$key.'@@';
    if (strpos($result,$placeholder)===false)
    {
      continue;
    }
    $value=\webdb\utils\custom_template_fill($key,$params,$tracking,$custom_templates);
    $result=\webdb\utils\webdb_str_replace($placeholder,$value,$result);
  }
  return $result;
}

#####################################################################################################

function compare_template($template,$response)
{
  $response=\webdb\utils\strip_http_headers($response);
  if ($response=="")
  {
    return false;
  }
  if ($response==$template)
  {
    return true;
  }
  $template_content=trim(\webdb\utils\template_fill($template));
  $parts=\webdb\utils\webdb_explode("%%",$template_content);
  $excluded=array();
  for ($i=0;$i<count($parts);$i++)
  {
    if (($i%2)==0)
    {
      $excluded[]=$parts[$i];
    }
  }
  for ($i=0;$i<count($excluded);$i++)
  {
    $needle=trim($excluded[$i]);
    if ($needle=="")
    {
      continue;
    }
    $k=strpos($response,$needle);
    if ($k===false)
    {
      return false;
    }
    $response=substr($response,$k+strlen($needle));
  }
  return true;
}

#####################################################################################################

function extract_params($template,$data)
{
  $data=trim($data);
  $template_content=trim(\webdb\utils\template_fill($template));
  $data=\webdb\utils\webdb_str_replace("\r","",$data);
  $template_content=\webdb\utils\webdb_str_replace("\r","",$template_content);
  $parts=\webdb\utils\webdb_explode("%%",$template_content);
  $keys=array();
  $excluded=array();
  for ($i=0;$i<count($parts);$i++)
  {
    if (($i%2)==0)
    {
      $excluded[]=$parts[$i];
    }
    else
    {
      $keys[]=$parts[$i];
    }
  }
  $nk=count($keys);
  $ne=count($excluded);
  if ($nk<>($ne-1))
  {
    return false;
  }
  $params=array();
  for ($i=0;$i<$nk;$i++)
  {
    $compare=$excluded[$i];
    $data_test=substr($data,0,strlen($compare));
    if ($data_test!==$compare)
    {
      return false;
    }
    $data=substr($data,strlen($compare));
    $next=$excluded[$i+1];
    $key=$keys[$i];
    if ($next=="")
    {
      $params[$key]=$data;
      continue;
    }
    $k=strpos($data,$next);
    if ($k===false)
    {
      return false;
      #return $next;
    }
    $params[$key]=trim(substr($data,0,$k));
    $data=substr($data,$k);
  }
  return $params;
}

#####################################################################################################

function strip_http_headers($response)
{
  if (substr($response,0,4)<>"HTTP")
  {
    return $response;
  }
  $delim="\r\n\r\n";
  $i=strpos($response,$delim);
  if ($i===false)
  {
    return $response;
  }
  return trim(substr($response,$i+strlen($delim)));
}

#####################################################################################################

function strip_first_tag_attribute(&$html,$tag,$attrib)
{
  $lhtml=strtolower($html);
  $i=strpos($lhtml,"<".$tag);
  $end="</".$tag.">";
  $j=strpos($lhtml,$end);
  if (($i===false) or ($j===false))
  {
    return false;
  }
  $k=strpos($lhtml," ".$attrib,$i+strlen($tag)+1);
  if ($k===false)
  {
    return false;
  }
  $m=strpos($lhtml,"\"",$k+strlen($attrib)+1);
  $n=strpos($lhtml,"\"",$m+1);
  $html=substr($html,0,$k).substr($html,$n+1);
  return true;
}

#####################################################################################################

function strip_all_tag_attribute(&$html,$tag,$attrib)
{
  while (\webdb\utils\strip_first_tag_attribute($html,$tag,$attrib)==true)
  {
  }
}

#####################################################################################################

function strip_first_tag(&$html,$tag,$xml=false)
{
  $lhtml=strtolower($html);
  $i=strpos($lhtml,"<".$tag);
  $end="</".$tag.">";
  if ($xml==true)
  {
    $end=" />";
  }
  $j=strpos($lhtml,$end,$i);
  if (($i===false) or ($j===false))
  {
    return false;
  }
  $html=substr($html,0,$i).substr($html,$j+strlen($end));
  return true;
}

#####################################################################################################

function strip_all_tag(&$html,$tag,$xml=false)
{
  while (\webdb\utils\strip_first_tag($html,$tag,$xml)==true)
  {
  }
}

#####################################################################################################

function check_csrf_error($response)
{
  if (\webdb\utils\compare_template("csrf_error_unauth",$response)==true)
  {
    return true;
  }
  if (\webdb\utils\compare_template("csrf_error_auth",$response)==true)
  {
    return true;
  }
  return false;
}

#####################################################################################################

function error_handler($errno,$errstr,$errfile,$errline)
{
  global $settings;
  $username="";
  if (isset($settings["logged_in_username"])==true)
  {
    $username=$settings["logged_in_username"];
  }
  elseif (isset($_COOKIE[$settings["username_cookie"]])==true)
  {
    $username=$_COOKIE[$settings["username_cookie"]];
  }
  $message="[".date("Y-m-d, H:i:s T",time())."] ".$errstr." in \"".$errfile."\" on line ".$errline;
  $func_name=$settings["error_event_handler"];
  if (function_exists($func_name)==true)
  {
    call_user_func($func_name,$username,$message);
  }
  $message="username: ".$username.PHP_EOL.PHP_EOL.$message;
  \webdb\utils\email_admin($message,$settings["app_name"]." error_handler");
  \webdb\utils\system_message($message);
}

#####################################################################################################

function email_admin($message,$subject)
{
  global $settings;
  if (isset($settings["templates"])==false)
  {
    return;
  }
  $user_record=false;
  if (isset($settings["user_record"])==true)
  {
    $user_record=$settings["user_record"];
    \webdb\users\obfuscate_hashes($user_record);
  }
  $message.=PHP_EOL.print_r($user_record);
  \webdb\utils\email_group($settings["admin_group_id"],$subject,$message);
}

#####################################################################################################

function email_group($group_id,$subject,$message)
{
  global $settings;
  $sql_params=array();
  $sql_params["group_id"]=$group_id;
  if (isset($settings["pdo_user"])==true)
  {
    $group_users=\webdb\sql\file_fetch_prepare("group_users",$sql_params);
    for ($i=0;$i<count($group_users);$i++)
    {
      \webdb\utils\send_email($group_users[$i]["email"],"",$subject,$message);
    }
  }
  else
  {
    \webdb\utils\send_email($settings["admin_email"],"",$subject,$message);
  }
}

#####################################################################################################

function exception_handler($exception)
{
  global $settings;
  $message="[".date("Y-m-d, H:i:s T",time())."] ".$exception->getMessage()." in \"".$exception->getFile()."\" on line ".$exception->getLine();
  $username="";
  if (isset($settings["logged_in_username"])==true)
  {
    $username=$settings["logged_in_username"];
  }
  elseif (isset($_COOKIE[$settings["username_cookie"]])==true)
  {
    $username=$_COOKIE[$settings["username_cookie"]];
  }
  $func_name=$settings["error_event_handler"];
  if (function_exists($func_name)==true)
  {
    call_user_func($func_name,$username,$message);
  }
  $message="username: ".$username.PHP_EOL.PHP_EOL.$message;
  \webdb\utils\email_admin($message,$settings["app_name"]." exception_handler");
  \webdb\utils\system_message($message);
}

#####################################################################################################

function redirect($url="",$clean_buffer=true)
{
  global $settings;
  if ($url=="")
  {
    $url=\webdb\utils\get_url();
  }
  $settings["ignore_ob_postprocess"]=true;
  if ($clean_buffer==true)
  {
    ob_end_clean(); # discard buffer
  }
  header("Location: ".$url);
  die;
}

#####################################################################################################

function load_credentials($prefix,$optional=false)
{
  global $settings;
  $settings[$prefix."_username"]="";
  $settings[$prefix."_password"]="";
  $filename=$settings[$prefix."_file"];
  if (file_exists($filename)==false)
  {
    if ($optional==false)
    {
      \webdb\utils\system_message("error: credentials file not found: ".$filename);
    }
    else
    {
      return;
    }
  }
  $data=file_get_contents($filename);
  if ($data===false)
  {
    if ($optional==false)
    {
      \webdb\utils\system_message("error: unable to read credentials file: ".$filename);
    }
    else
    {
      return;
    }
  }
  $data=trim($data);
  $data=\webdb\utils\webdb_explode("\n",$data);
  if (count($data)<>2)
  {
    if ($optional==false)
    {
      \webdb\utils\system_message("error: invalid credentials file: ".$filename);
    }
    else
    {
      return;
    }
  }
  $settings[$prefix."_username"]=trim($data[0]);
  $settings[$prefix."_password"]=trim($data[1]);
}

#####################################################################################################

function send_email($recipient,$cc,$subject,$message,$from="",$reply_to="",$bounce_to="")
{
  global $settings;
  if ($recipient=="")
  {
    return;
  }
  if ($from=="")
  {
    $from=$settings["server_email_from"];
  }
  if ($reply_to=="")
  {
    $reply_to=$settings["server_email_reply_to"];
  }
  if ($bounce_to=="")
  {
    $bounce_to=$settings["server_email_bounce_to"];
  }
  if ($settings["dev_env"]==true)
  {
    $recipient=$settings["dev_env_email"];
    $cc="";
    $bounce_to="";
  }
  $headers=array();
  if ($from<>"")
  {
    $headers[]="From: ".$from;
  }
  if ($cc<>"")
  {
    $headers[]="Cc: ".$cc;
  }
  /*$headers[]="Reply-To: ".$reply_to;
  $headers[]="X-Sender: ".$from;
  $headers[]="X-Mailer: PHP/".phpversion();*/
  $headers[]="MIME-Version: 1.0";
  $headers[]="Content-Type: text/html; charset=iso-8859-1";
  if ($settings["email_enabled"]==true)
  {
    #mail($recipient,$subject,$message,implode("\r\n",$headers),"-f ".$bounce_to); # LINUX
    mail($recipient,$subject,$message,implode("\r\n",$headers)); # WINDOWS PROD
  }
  if (($settings["email_file_log_enabled"]==true) and (isset($settings["templates"])==true))
  {
    $i=1;
    do
    {
      $filename=$settings["email_file_log_path"].date("Ymd_His")."_".sprintf("%02d",$i)."_".\webdb\utils\strip_text($subject).".txt";
      $i++;
    }
    while (file_exists($filename)==true);
    $log_params=array();
    $log_params["recipient"]=$recipient;
    $log_params["cc"]=$cc;
    $log_params["subject"]=$subject;
    $log_params["message"]=$message;
    $log_data=\webdb\utils\template_fill("email_log",$log_params);
    file_put_contents($filename,$log_data);
  }
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

function get_upload_path()
{
  global $settings;
  switch ($settings["file_upload_mode"])
  {
    case "rename":
      return $settings["app_file_uploads_path"];
    case "ftp":
      return $settings["ftp_app_target_path"];
  }
  \webdb\utils\error_message("error: invalid file upload mode");
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

function webdb_ftp_login()
{
  global $settings;
  $connection=ftp_connect($settings["ftp_address"],$settings["ftp_port"],$settings["ftp_timeout"]);
  if ($connection===false)
  {
    \webdb\utils\error_message("error: unable to connect to the FTP server");
  }
  if (ftp_login($connection,$settings["ftp_credentials_username"],$settings["ftp_credentials_password"])==false)
  {
    \webdb\utils\error_message("error: unable to login to the FTP server");
  }
  if (ftp_pasv($connection,true)==false)
  {
    \webdb\utils\error_message("error: unable to enable FTP passive mode");
  }
  return $connection;
}

#####################################################################################################

function wildcard_compare($compare_value,$wildcard_value)
{
  if ($compare_value=="hpsm_dta/auth_20200310")
  {
    var_dump($compare_value);
    var_dump($wildcard_value);
  }
  $wildcard_parts=\webdb\utils\webdb_explode("*",$wildcard_value);
  $compare_parts=array();
  for ($i=0;$i<count($wildcard_parts);$i++)
  {
    if ($wildcard_parts[$i]=="")
    {
      continue;
    }
    $n=strpos($compare_value,$wildcard_parts[$i]);
    if ($n===false)
    {
      return false;
    }
    $compare_parts[]=substr($compare_value,0,$n);
    $compare_parts[]=substr($compare_value,$n,strlen($wildcard_parts[$i]));
    $compare_value=substr($compare_value,$n+strlen($wildcard_parts[$i]));
  }
  if ($compare_value<>"")
  {
    $compare_parts[]=$compare_value;
  }
  $compare_index=0;
  $n=count($wildcard_parts);
  for ($i=0;$i<$n;$i++)
  {
    if ($wildcard_parts[$i]=="")
    {
      $compare_index++;
      continue;
    }
    if ($compare_index>($n-1))
    {
      return false;
    }
    if ($wildcard_parts[$i]==$compare_parts[$compare_index])
    {
      $compare_index++;
      $compare_index++;
      continue;
    }
  }
  if ($compare_value=="hpsm_dta/auth_20200310")
  {
    var_dump($compare_parts);
    var_dump($wildcard_parts);
    die;
  }
  return true;
}

#####################################################################################################

function append_file($fn,$data)
{
  $fp=fopen($fn,"a");
  stream_set_blocking($fp,false);
  if (flock($fp,LOCK_EX)==true)
  {
    fwrite($fp,$data);
  }
  flock($fp,LOCK_UN);
  fclose($fp);
  #file_put_contents($fn,$data,FILE_APPEND);
}

#####################################################################################################

function save_log($key)
{
  global $settings;
  $path=$settings[$key."_log_path"];
  if ((file_exists($path)==false) or (is_dir($path)==false))
  {
    return;
  }
  $lines=$settings["logs"][$key];
  $data=PHP_EOL.PHP_EOL.trim(implode(PHP_EOL,$lines));
  $fn=$path.$key."_".date("Ymd").".log";
  \webdb\utils\append_file($fn,$data);
}

#####################################################################################################

function save_logs()
{
  global $settings;
  foreach ($settings["logs"] as $key => $lines)
  {
    if (($settings[$key."_log_enabled"]==true) and (count($lines)>0))
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
  \webdb\sql\file_execute_prepare("schema",array(),true);
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

function filename_replace_chars($filename)
{
  # TODO
  return $filename;
}

#####################################################################################################

function contains_number($value)
{
  $num_chars="0123456789";
  for ($i=0;$i<strlen($value);$i++)
  {
    if (strpos($num_chars,$value[$i])!==false)
    {
      return true;
    }
  }
  return false;
}

#####################################################################################################

function color_blend($R1,$G1,$B1,$R2,$G2,$B2,$increment_fraction)
{
  $result=array();
  $result["R"]=\webdb\utils\color_value_blend($R1,$R2,$increment_fraction);
  $result["G"]=\webdb\utils\color_value_blend($G1,$G2,$increment_fraction);
  $result["B"]=\webdb\utils\color_value_blend($B1,$B2,$increment_fraction);
  return \webdb\utils\template_fill("rgb_css",$result);
}

#####################################################################################################

function color_value_blend($a,$b,$t)
{
  # https://stackoverflow.com/questions/726549/algorithm-for-additive-color-mixing-for-rgb-values
  return round(sqrt((1-$t)*pow($a,2)+$t*pow($b,2)));
}

#####################################################################################################

function force_rmdir($dir)
{
  if (substr($dir,-1)==DIRECTORY_SEPARATOR)
  {
    $dir=substr($dir,0,-1);
  }
  if (is_dir($dir)==false)
  {
    return;
  }
  $objects=scandir($dir);
  foreach ($objects as $object)
  {
    if (($object==".") or ($object==".."))
    {
      continue;
    }
    $child=$dir.DIRECTORY_SEPARATOR.$object;
    if (is_dir($child)==true)
    {
      \webdb\utils\force_rmdir($child);
    }
    else
    {
      unlink($child);
    }
  }
  usleep(500);
  $objects=scandir($dir);
  if (count($objects)==2)
  {
    rmdir($dir);
  }
}

#####################################################################################################

function purge_closed_page_row_locks($online_users)
{
  global $settings;
  return false;
  $this_user=$settings["user_record"];
  $active_row_locks=array();
  $lock_records=\webdb\utils\purge_expired_row_locks();
  foreach ($online_users as $nick => $urls)
  {
    $sql_params=array();
    $sql_params["username"]=$nick;
    $user_records=\webdb\sql\file_fetch_prepare("user_get_by_username",$sql_params);
    if (count($user_records)<>1)
    {
      continue;
    }
    $user_record=$user_records[0];
    foreach ($urls as $url => $page_data)
    {
      $form_config=\webdb\forms\get_form_config($page_data["page_id"],true);
      if ($form_config===false)
      {
        continue;
      }
      $id=$page_data["record_id"];
      for ($i=0;$i<count($lock_records);$i++)
      {
        $lock_record=$lock_records[$i];
        if (in_array($lock_record["lock_id"],$active_row_locks)==true)
        {
          continue;
        }
        if (($lock_record["user_id"]==$user_record["user_id"]) and ($lock_record["lock_table"]==$form_config["table"]) and ($lock_record["lock_key_value"]==$id))
        {
          $lock_id=$lock_record["lock_id"];
          $lock_record["page_id"]=$page_data["page_id"];
          $active_row_locks[$lock_id]=$lock_record;
          break;
        }        
      }
    }
  }
  for ($i=0;$i<count($lock_records);$i++)
  {
    $lock_record=$lock_records[$i];
    $lock_id=$lock_record["lock_id"];
    if (isset($active_row_locks[$lock_id])==false)
    {
      $where_items=array();
      $where_items["lock_id"]=$lock_record["lock_id"];
      $settings["sql_check_post_params_override"]=true;
      \webdb\sql\sql_delete($where_items,"row_locks",$settings["database_webdb"]);
    }
  }
  if (isset($_GET["page"])==false)
  {
    return false; # current page unlocked for editing (unknown page_id)
  }
  if (isset($_GET["id"])==false)
  {
    if ($_GET["page"]=="wiki")
    {
      $article_record=\webdb\wiki_utils\get_article_record();
      $id=$article_record["article_id"];
    }
    else
    {
      return false; # current page unlocked for editing (unknown record id)
    }
  }
  else
  {
    $id=$_GET["id"];
  }
  for ($i=0;$i<count($active_row_locks);$i++)
  {
    $lock_record=$active_row_locks[$i];
    /*if ($lock_record["user_id"]==$this_user["user_id"])
    {
      continue;
    }*/
    if ($lock_record["page_id"]<>$_GET["page"])
    {
      continue;
    }
    if ($lock_record["lock_key_value"]<>$id)
    {
      continue;
    }
    return true; # current page still locked for editing
  }
  return false; # current page unlocked for editing

  return \webdb\utils\get_lock($schema,$table,$key_field,$key_value);
}

#####################################################################################################

function purge_expired_row_locks()
{
  global $settings;
  return false;
  $records=\webdb\sql\fetch_all_records("row_locks",$settings["database_webdb"]);
  for ($i=0;$i<count($records);$i++)
  {
    $record=$records[$i];
    $t=\webdb\utils\webdb_strtotime($record["created_timestamp"]);
    $delta=time()-$t;
    if ($delta>$settings["row_lock_expiration"])
    {
      $where_items=array();
      $where_items["lock_id"]=$record["lock_id"];
      $settings["sql_check_post_params_override"]=true;
      \webdb\sql\sql_delete($where_items,"row_locks",$settings["database_webdb"]);
      unset($records[$i]);
    }
  }
  return array_values($records);
}

#####################################################################################################

function get_lock($schema,$table,$key_field,$key_value)
{
  global $settings;
  return false;
  $records=\webdb\utils\purge_expired_row_locks();
  for ($i=0;$i<count($records);$i++)
  {
    $record=$records[$i];
    if ($record["lock_schema"]<>$schema)
    {
      continue;
    }
    if ($record["lock_table"]<>$table)
    {
      continue;
    }
    if ($record["lock_key_field"]<>$key_field)
    {
      continue;
    }
    if ($record["lock_key_value"]<>$key_value)
    {
      continue;
    }
    if ($settings["user_record"]["user_id"]==$record["user_id"])
    {
      $where_items=array();
      $where_items["lock_id"]=$record["lock_id"];
      $settings["sql_check_post_params_override"]=true;
      \webdb\sql\sql_delete($where_items,"row_locks",$settings["database_webdb"]);
      continue;
    }
    return \webdb\utils\append_lock_details($record);
  }
  $items=array();
  $items["user_id"]=$settings["user_record"]["user_id"];
  $items["lock_schema"]=$schema;
  $items["lock_table"]=$table;
  $items["lock_key_field"]=$key_field;
  $items["lock_key_value"]=$key_value;
  $settings["sql_check_post_params_override"]=true;
  \webdb\sql\sql_insert($items,"row_locks",$settings["database_webdb"]);
  $where_items=array();
  $where_items["lock_id"]=\webdb\sql\sql_last_insert_autoinc_id();
  $records=\webdb\sql\get_exist_records($settings["database_webdb"],"row_locks",$where_items);
  if (count($records)==1)
  {
    return \webdb\utils\append_lock_details($records[0]);
  }
  return false;
}

#####################################################################################################

function append_lock_details($lock)
{
  global $settings;
  return false;
  $lock["locked_time"]=\webdb\utils\webdb_strtotime($lock["created_timestamp"]);
  $where_items=array();
  $where_items["user_id"]=$lock["user_id"];
  $users=\webdb\sql\get_exist_records($settings["database_webdb"],"users",$where_items);
  if (count($users)<>1)
  {
    return false;
  }
  $user=$users[0];
  $lock["username"]=$user["username"];
  $lock["fullname"]=$user["fullname"];
  return $lock;
}

#####################################################################################################

function net_path_connect($path,$domain,$username,$password)
{
  $cmd='net use "'.$path.'" /u:'.$domain.'\\'.$username." ".$password.' 2>&1';
  return shell_exec($cmd);
}

#####################################################################################################

function net_path_disconnect($path)
{
  return shell_exec('net use "'.$path.'" /delete 2>&1');
}

#####################################################################################################

function change_filename_ext($filename,$new_ext,$delim=".")
{
  $parts=\webdb\utils\webdb_explode($delim,$filename);
  array_pop($parts);
  return implode($delim,$parts).$delim.$new_ext;
}

#####################################################################################################

function webdb_array_key_first($a) # for PHP version < 7.3
{
  if (is_array($a)==false)
  {
    return null;
  }
  if (count($a)==0)
  {
    return null;
  }
  return array_keys($a)[0];
}

#####################################################################################################

function webdb_array_key_last($a) # for PHP version < 7.3
{
  if (is_array($a)==false)
  {
    return null;
  }
  if (count($a)==0)
  {
    return null;
  }
  return array_keys($a)[count($a)-1];
}

#####################################################################################################

function webdb_file_put_contents($filename,$content)
{
  return file_put_contents($filename,"\xEF\xBB\xBF".$content); # force utf8
}

#####################################################################################################

function webdb_file_get_contents($filename)
{
  $content=file_get_contents($filename);
  $needle="\xEF\xBB\xBF";
  $i=strpos($content,$needle);
  if ($i!==false)
  {
    if ($i===0)
    {
      $content=substr($content,strlen($needle));
    }
  }
  return trim($content);
}

#####################################################################################################

function webdb_htmlspecialchars($val)
{
  if ($val===null)
  {
    return "";
  }
  return htmlspecialchars($val);
}

#####################################################################################################

function webdb_str_replace($search,$replace,$subject)
{
  if ($subject===null)
  {
    return "";
  }
  return str_replace($search,$replace,$subject);
}

#####################################################################################################

function formatted_date_to_unix($format,$date_str)
{
  if ($date_str===null)
  {
    return false;
  }
  $x=date_create_from_format($format,$date_str);
  if ($x===false)
  {
    return false;
  }
  return date_timestamp_get($x);
}


#####################################################################################################

function webdb_explode($delimiter,$string,$limit=PHP_INT_MAX)
{
  if (($delimiter===null) or ($delimiter===""))
  {
    return false;
  }
  if ($string===null)
  {
    $string="";
  }
  return explode($delimiter,$string,$limit);
}

#####################################################################################################

function webdb_strtolower($string)
{
  if ($string===null)
  {
    return "";
  }
  return strtolower($string);
}

#####################################################################################################

function webdb_strtoupper($string)
{
  if ($string===null)
  {
    return "";
  }
  return strtoupper($string);
}

#####################################################################################################

function webdb_strtotime($time,$now=null)
{
  if ($time===null)
  {
    return "";
  }
  if ($now===null)
  {
    return strtotime($time);
  }
  return strtotime($time,intval(round($now)));
}

#####################################################################################################

function webdb_strlen($string)
{
  if ($string===null)
  {
    return 0;
  }
  return strlen($string);
}

#####################################################################################################

function load_bytes_from_file($filename)
{
  $fhandle=fopen($filename,"rb");
  $fsize=filesize($filename);
  $contents=fread($fhandle,$fsize);
  $result=unpack("C*",$contents);
  fclose($fhandle);
  return $result;
}

#####################################################################################################

function forced_download($full_filename,$name_override=false)
{
  global $settings;
  $settings["ignore_ob_postprocess"]=true;
  header("Content-Description: File Transfer");
  header("Content-Type: application/octet-stream");
  if ($name_override===false)
  {
    header("Content-Disposition: attachment; filename=\"".basename($full_filename)."\"");
  }
  else
  {
    header("Content-Disposition: attachment; filename=\"".$name_override."\"");
  }
  header("Content-Transfer-Encoding: binary");
  header("Cache-Control: no-cache, no-store, must-revalidate");
  header("Pragma: no-cache");
  header("Expires: 0");
  header("Content-Length: ".filesize($full_filename));
  ob_end_clean();
  flush();
  readfile($full_filename);
  exit;
}

#####################################################################################################

function get_array_address($address,&$array,$delim=".")
{
  $keys=explode($delim,$address);
  $key=array_shift($keys);
  if (isset($array[$key])==false)
  {
    return false;
  }
  if (count($keys)==0)
  {
    return $array[$key];
  }
  return \webdb\utils\get_array_address(implode($delim,$keys),$array[$key],$delim);
}

#####################################################################################################
