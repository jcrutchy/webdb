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
  echo "\033[".\webdb\test\utils\TEST_CASE_COLOR."m".$test_case.": \033[0m\033[";
  if ($result==true)
  {
    echo \webdb\test\utils\SUCCESS_COLOR."mSUCCESS\033[0m".PHP_EOL;
  }
  else
  {
    echo \webdb\test\utils\ERROR_COLOR."mFAILED\033[0m".PHP_EOL;
    \webdb\test\utils\handle_error();
  }
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
  foreach ($settings["test_cookie_jar"] as $key => $value)
  {
    $parts=explode(";",$value);
    $cookies[]=$key."=".array_shift($parts);
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
  $headers=\webdb\test\utils\extract_http_headers($response);
  #\webdb\test\utils\test_dump_message($headers.PHP_EOL.PHP_EOL);
  \webdb\test\utils\append_cookie_jar($headers);
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
    $value=implode("=",$parts);
    $settings["test_cookie_jar"][$key]=$value;
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

function extract_text($text,$delim1,$delim2)
{
  $i=strpos(strtolower($text),strtolower($delim1));
  if ($i===false)
  {
    \webdb\test\utils\test_error_message("extract_text error: delim1 not found");
  }
  $text=substr($text,$i+strlen($delim1));
  $i=strpos($text,$delim2);
  if ($i===false)
  {
    \webdb\test\utils\test_error_message("extract_text error: delim2 not found");
  }
  $text=substr($text,0,$i);
  return trim($text);
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
