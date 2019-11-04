<?php

namespace webdb\test\utils;

#####################################################################################################

define("webdb\\test\\utils\\ERROR_COLOR",31);
define("webdb\\test\\utils\\SUCCESS_COLOR",32);
define("webdb\\test\\utils\\INFO_COLOR",94);
define("webdb\\test\\utils\\DUMP_COLOR",35);
define("webdb\\test\\utils\\TEST_CASE_COLOR",95);

#####################################################################################################

function test_error_message($message)
{
  global $settings;
  \webdb\cli\term_echo($message,\webdb\test\utils\ERROR_COLOR);
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

function test_server_setting($key,$value,$message)
{
  $test_settings=array();
  $test_settings[$key]=$value;
  \webdb\test\utils\write_test_config($test_settings);
  \webdb\test\utils\test_info_message($message);
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
    \webdb\test\utils\test_info_message("UNABLE TO DELETE FILE (FILE NOT FOUND): ".$filename);
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
  return substr($response,0,$i);
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

function construct_cookie_header($cookie_jar)
{
  $cookies=array();
  for ($i=0;$i<count($cookie_jar);$i++)
  {
    $parts=explode(";",$cookie_jar[$i]);
    $cookies[]=array_shift($parts);
  }
  return "Cookie: ".implode("; ",$cookies);
}

#####################################################################################################

function wget($uri)
{
  global $settings;
  $headers="POST $uri HTTP/1.0\r\n";
  $headers.="Host: localhost\r\n";
  if (isset($settings["test_login_cookie_header"])==true)
  {
    $headers.=$settings["test_login_cookie_header"]."\r\n";
  }
  $headers.="Connection: Close\r\n\r\n";
  return \webdb\test\utils\submit_request($headers);
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
  $headers.="Content-Type: application/x-www-form-urlencoded\r\n";
  if (isset($settings["test_login_cookie_header"])==true)
  {
    $headers.=$settings["test_login_cookie_header"]."\r\n";
  }
  $headers.="Content-Length: ".strlen($content)."\r\n";
  $headers.="Connection: Close\r\n\r\n";
  $request=$headers.$content;
  return \webdb\test\utils\submit_request($request);
}

#####################################################################################################

function submit_request($request)
{
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
  return $response;
}

#####################################################################################################

function compare_template_exluding_percents($template,$response)
{
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
    if (strpos($response,$excluded[$i])===false)
    {
      return false;
    }
  }
  return true;
}

#####################################################################################################
