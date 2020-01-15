<?php

namespace webdb\test\utils;

#####################################################################################################

define("webdb\\test\\utils\\ERROR_COLOR","1;31");
define("webdb\\test\\utils\\SUCCESS_COLOR","1;32");
define("webdb\\test\\utils\\INFO_COLOR",94);
define("webdb\\test\\utils\\DUMP_COLOR",35);
define("webdb\\test\\utils\\TEST_CASE_COLOR",36);

#####################################################################################################

function test_error_message($message)
{
  \webdb\cli\term_echo($message,\webdb\test\utils\ERROR_COLOR);
  \webdb\test\utils\handle_error();
}

#####################################################################################################

function handle_error()
{
  global $settings;
  if (isset($settings["test_error_handler"])==true)
  {
    if (function_exists($settings["test_error_handler"])==true)
    {
      call_user_func($settings["test_error_handler"]);
    }
  }
  \webdb\test\utils\delete_test_config();
  die;
}

#####################################################################################################

function test_case_message($message)
{
  \webdb\cli\term_echo($message,\webdb\test\utils\TEST_CASE_COLOR);
}

#####################################################################################################

function test_info_message($message)
{
  \webdb\cli\term_echo($message,\webdb\test\utils\INFO_COLOR);
}

#####################################################################################################

function test_success_message($message)
{
  \webdb\cli\term_echo($message,\webdb\test\utils\SUCCESS_COLOR);
}

#####################################################################################################

function test_dump_message($message)
{
  \webdb\cli\term_echo($message,\webdb\test\utils\DUMP_COLOR);
}

#####################################################################################################

function test_result_message($test_case,$result)
{
  $prefix="["."\033[";
  $suffix="\033[0m"."] "."\033[".\webdb\test\utils\TEST_CASE_COLOR."m".$test_case."\033[0m".PHP_EOL;
  if ($result==true)
  {
    echo $prefix.\webdb\test\utils\SUCCESS_COLOR."m"."SUCCESS".$suffix;
  }
  else
  {
    echo $prefix.\webdb\test\utils\ERROR_COLOR."m"."FAILED".$suffix;
    \webdb\test\utils\handle_error();
  }
}

#####################################################################################################

function initialize_webdb_schema()
{
  global $settings;
  $result=shell_exec("php ".$settings["app_root_path"]."index.php init_webdb_schema");
  #\webdb\test\utils\test_info_message(trim($result));
}

#####################################################################################################

function test_cleanup()
{
  \webdb\test\utils\clear_cookie_jar();
  \webdb\test\utils\delete_test_config();
  \webdb\test\utils\initialize_webdb_schema();
}

#####################################################################################################

function test_server_setting($key,$value,$message)
{
  $test_settings=array();
  $test_settings[$key]=$value;
  \webdb\test\utils\write_test_config($test_settings);
  #\webdb\test\utils\test_info_message($message);
}

#####################################################################################################

function test_server_settings($settings,$message)
{
  \webdb\test\utils\write_test_config($settings);
  #\webdb\test\utils\test_info_message($message);
}

#####################################################################################################

function write_test_config($test_settings)
{
  global $settings;
  $content=array();
  foreach ($test_settings as $key => $value)
  {
    $content[]=$key."=".$value;
  }
  $content=implode(PHP_EOL,$content);
  \webdb\test\utils\write_file($settings["test_settings_file"],$content);
  #\webdb\test\utils\test_info_message("TEST CONFIG FILE WRITTEN");
}

#####################################################################################################

function delete_test_config()
{
  global $settings;
  \webdb\test\utils\delete_file($settings["test_settings_file"]);
}

#####################################################################################################

function write_file($filename,$content)
{
  if (file_exists($filename)==true)
  {
    #\webdb\test\utils\test_info_message("OVERWRITING EXISTING FILE: ".$filename);
  }
  $result=file_put_contents($filename,$content);
  if ($result==false)
  {
    \webdb\test\utils\test_error_message("ERROR WRITING FILE: ".$filename);
  }
  if (file_exists($filename)==false)
  {
    \webdb\test\utils\test_error_message("ERROR WRITING FILE (FILE NOT FOUND): ".$filename);
  }
}

#####################################################################################################

function delete_file($filename)
{
  if (file_exists($filename)==false)
  {
    #\webdb\test\utils\test_info_message("UNABLE TO DELETE FILE (FILE NOT FOUND): ".$filename);
    return;
  }
  $result=unlink($filename);
  if ($result==false)
  {
    \webdb\test\utils\test_error_message("ERROR DELETING FILE: ".$filename);
  }
}

#####################################################################################################

function check_required_file_exists($filename,$is_path=false)
{
  if (file_exists($filename)==false)
  {
    if ($is_path==true)
    {
      \webdb\test\utils\test_error_message("error: required path not found: ".$filename);
    }
    else
    {
      \webdb\test\utils\test_error_message("error: required file not found: ".$filename);
    }
  }
  if ($is_path==true)
  {
    if (is_dir($filename)==false)
    {
      \webdb\test\utils\test_error_message("error: required path is not a directory: ".$filename);
    }
  }
}

#####################################################################################################

function check_required_setting_exists($key)
{
  global $settings;
  if (isset($settings[$key])==false)
  {
    \webdb\test\utils\test_error_message("error: required setting not found: ".$key);
  }
}

#####################################################################################################

function extract_http_headers($response)
{
  $delim="\r\n\r\n";
  $i=strpos($response,$delim);
  if ($i===false)
  {
    return false;
  }
  return trim(substr($response,0,$i));
}

#####################################################################################################

function strip_http_headers($response)
{
  $delim="\r\n\r\n";
  $i=strpos($response,$delim);
  if ($i===False)
  {
    return False;
  }
  return trim(substr($response,$i+strlen($delim)));
}

#####################################################################################################

function search_http_headers($headers,$search_key)
{
  $result=array();
  $lines=explode("\n",$headers);
  for ($i=0;$i<count($lines);$i++)
  {
    $line=trim($lines[$i]);
    $parts=explode(":",$line);
    if (count($parts)>=2)
    {
      $key=trim(array_shift($parts));
      $value=trim(implode(":",$parts));
      if (strtolower($key)==strtolower($search_key))
      {
        $result[]=$value;
      }
    }
  }
  return $result;
}

#####################################################################################################

function clear_cookie_jar()
{
  global $settings;
  $settings["test_cookie_jar"]=array();
}

#####################################################################################################

function extract_cookie_value($setting_key)
{
  global $settings;
  if (isset($settings["test_cookie_jar"])==false)
  {
    return false;
  }
  $key=$settings[$setting_key];
  if (isset($settings["test_cookie_jar"][$key])==false)
  {
    return false;
  }
  $cookie=$settings["test_cookie_jar"][$key];
  $parts=explode(";",$cookie);
  $value=array_shift($parts);
  if (($value=="deleted") or ($value==""))
  {
    unset($settings["test_cookie_jar"][$key]);
    return false;
  }
  return $value;
}

#####################################################################################################

function construct_cookie_header()
{
  global $settings;
  if (isset($settings["test_cookie_jar"])==false)
  {
    return "";
  }
  if (count($settings["test_cookie_jar"])==0)
  {
    return "";
  }
  $cookies=array();
  foreach ($settings["test_cookie_jar"] as $key => $cookie)
  {
    $parts=explode(";",$cookie);
    $value=urlencode(array_shift($parts));
    $cookies[]=$key."=".$value;
  }
  return "Cookie: ".implode("; ",$cookies)."\r\n";
}

#####################################################################################################

function wget($uri)
{
  global $settings;
  $headers="GET $uri HTTP/1.0\r\n";
  $headers.="Host: localhost\r\n";
  $headers.="User-Agent: ".$settings["test_user_agent"]."\r\n";
  $headers.=\webdb\test\utils\construct_cookie_header();
  $headers.="Connection: Close\r\n\r\n";
  $response=\webdb\test\utils\submit_request($headers);
  #var_dump($headers);
  $headers=\webdb\test\utils\extract_http_headers($response);
  #var_dump($headers);
  #\webdb\test\utils\test_dump_message($headers.PHP_EOL.PHP_EOL);
  \webdb\test\utils\append_cookie_jar($headers);
  #var_dump(\webdb\test\security\utils\extract_csrf_token($response));
  #var_dump($settings["test_cookie_jar"]);
  $response=\webdb\test\utils\process_redirect($response,$headers);
  return $response;
}

#####################################################################################################

function wpost($uri,$params)
{
  global $settings;
  $encoded_params=array();
  foreach ($params as $key => $value)
  {
    $encoded_params[]=$key."=".rawurlencode($value);
  }
  $content=implode("&",$encoded_params);
  $headers="POST $uri HTTP/1.0\r\n";
  $headers.="Host: localhost\r\n";
  if ($settings["test_user_agent"]<>"")
  {
    $headers.="User-Agent: ".$settings["test_user_agent"]."\r\n";
  }
  $headers.="Content-Type: application/x-www-form-urlencoded\r\n";
  $headers.=\webdb\test\utils\construct_cookie_header();
  $headers.="Content-Length: ".strlen($content)."\r\n";
  $headers.="Connection: Close\r\n\r\n";
  $request=$headers.$content;
  $response=\webdb\test\utils\submit_request($request);
  $headers=\webdb\test\utils\extract_http_headers($response);
  #\webdb\test\utils\test_dump_message($headers.PHP_EOL.PHP_EOL);
  \webdb\test\utils\append_cookie_jar($headers);
  #var_dump($request);
  #var_dump($headers);
  #var_dump(\webdb\test\security\utils\extract_csrf_token($response));
  #var_dump($settings["test_cookie_jar"]);
  $response=\webdb\test\utils\process_redirect($response,$headers);
  return $response;
}

#####################################################################################################

function append_cookie_jar($headers)
{
  global $settings;
  $cookie_headers=\webdb\test\utils\search_http_headers($headers,"set-cookie");
  if (isset($settings["test_cookie_jar"])==false)
  {
    $settings["test_cookie_jar"]=array();
  }
  for ($i=0;$i<count($cookie_headers);$i++)
  {
    $header=$cookie_headers[$i];
    $parts=explode("=",$header);
    $key=array_shift($parts);
    $value=urldecode(implode("=",$parts));
    $cookie_parts=explode(";",$value);
    $cookie_value=urlencode(array_shift($cookie_parts));
    if ($cookie_value=="deleted")
    {
      unset($settings["test_cookie_jar"][$key]);
    }
    else
    {
      $settings["test_cookie_jar"][$key]=$value;
    }
  }
}

#####################################################################################################

function process_redirect($response,$headers)
{
  $result=\webdb\test\utils\search_http_headers($headers,"location");
  if (count($result)>0)
  {
    $redirect=$result[0];
    $response=\webdb\test\utils\wget($redirect);
  }
  return $response;
}

#####################################################################################################

function submit_request($request)
{
  global $settings;
  #\webdb\test\utils\test_info_message("ATTEMPTING TO CONNECT TO SERVER AND SUBMIT REQUEST...");
  $errno=0;
  $errstr="";
  $fp=stream_socket_client("tcp://localhost:80",$errno,$errstr,10);
  if ($fp===false)
  {
    \webdb\test\utils\test_error_message("ERROR CONNECTING TO LOCALHOST ON PORT 80");
  }
  #\webdb\test\utils\test_dump_message($request);
  fwrite($fp,$request);
  $response="";
  while (!feof($fp))
  {
    $response.=fgets($fp,1024);
  }
  fclose($fp);
  #\webdb\test\utils\test_info_message("REQUEST COMPLETED");
  #\webdb\test\utils\test_dump_message($response);
  $template=\webdb\utils\template_fill("debug_backtrace");
  $parts=explode("%%",$template);
  $prefix=array_shift($parts);
  $parts=explode($prefix,$response);
  if (isset($settings["test_include_backtrace"])==true)
  {
    $n=count($parts);
    if ($n>1)
    {
      $parts[$n-1]=html_entity_decode($parts[$n-1]);
      $response=implode($prefix,$parts);
    }
  }
  else
  {
    $response=array_shift($parts);
  }
  return $response;
}

#####################################################################################################

function extract_text($text,$delim1,$delim2)
{
  $i=strpos(strtolower($text),strtolower($delim1));
  if ($i===false)
  {
    return "";
  }
  $text=substr($text,$i+strlen($delim1));
  $i=strpos($text,$delim2);
  if ($i===false)
  {
    return "";
  }
  $text=substr($text,0,$i);
  return trim($text);
}

#####################################################################################################

function compare_template($template,$response)
{
  if ($response=="")
  {
    return false;
  }
  if ($response==$template)
  {
    return true;
  }
  $template_content=\webdb\utils\template_fill($template);
  $parts=explode("%%",$template_content);
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
    if (strpos($response,$needle)===false)
    {
      return false;
    }
  }
  return true;
}

#####################################################################################################

function compare_form_template($template,$response)
{
  return \webdb\test\utils\compare_template("forms".DIRECTORY_SEPARATOR.$template,$response);
}

#####################################################################################################
