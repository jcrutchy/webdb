<?php

namespace webdb\service;

#####################################################################################################

function service_main()
{
  global $settings;
  $enable_filename=__DIR__.DIRECTORY_SEPARATOR."enable=1";
  if (file_exists($enable_filename)==false)
  {
    return;
  }
  if (function_exists($settings["service_loop_event_handler"])==false)
  {
    return;
  }
  set_time_limit(0);
  \webdb\utils\email_admin("","service started");
  $start_time=filemtime(__FILE__);
  $stop_status="";
  while (true)
  {
    if (file_exists($enable_filename)==false)
    {
      $stop_status="enable file not found";
      break;
    }
    if (function_exists($settings["service_loop_event_handler"])==false)
    {
      $stop_status="service loop event handler not found";
      break;
    }
    if (filemtime(__FILE__)<>$start_time)
    {
      $stop_status="service file changed";
      break;
    }
    call_user_func($settings["service_loop_event_handler"]);
    usleep(0.1*1e6);
  }
  \webdb\utils\email_admin("","service stopped: ".$stop_status);
  die;
}

#####################################################################################################

function client_message($parts)
{
  $response=array();
  if (\webdb\users\logged_in_user_in_group("admin")==true)
  {
    $directive=array_shift($parts);
    $trailing=implode(" ",$parts);
    switch ($directive)
    {
      case "status":
        $response[]=\webdb\service\get_process_status();
        break;
      case "kill":
        $response[]=shell_exec("taskkill /f /t /im php.exe 2>&1");
        break;
      default:
        $response[]="error: unknown directive";
    }
  }
  else
  {
    $response[]="error: command not permitted";
  }
  \webdb\chat\private_notice($response);
}

#####################################################################################################

function get_process_status()
{
  global $settings;
  $tmp=array("tmp"=>$settings["service_cmd_status"]);
  $cmd=\webdb\utils\template_fill("tmp",false,array(),$tmp);
  return shell_exec($cmd);
}

#####################################################################################################

function run_service()
{
  global $settings;
  $output=\webdb\service\get_process_status();
  if (strpos($output,"php")===false)
  {
    $tmp=array("tmp"=>$settings["service_cmd_run"]);
    $cmd=\webdb\utils\template_fill("tmp",false,array(),$tmp);
    pclose(popen($cmd,"r"));
  }
}

#####################################################################################################
