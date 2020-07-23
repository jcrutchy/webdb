<?php

namespace webdb\service;

#####################################################################################################

function service_main()
{
  global $settings;
  if (file_exists(__DIR__.DIRECTORY_SEPARATOR."enable=1")==false)
  {
    return;
  }
  set_time_limit(0);
  \webdb\utils\email_admin("","service started");
  $sockets=array();
  $server=stream_socket_server("tcp://localhost:50000",$err_no,$err_msg);
  if ($server===false)
  {
    # error: could not bind to socket
    return;
  }
  stream_set_blocking($server,0);
  $sockets[]=$server;
  while (true)
  {
    $read=$sockets;
    $write=null;
    $except=null;
    $change_count=stream_select($read,$write,$except,0);
    if ($change_count===false)
    {
      # error: stream_select on sockets failed
      break;
    }
    if ($change_count>=1)
    {
      foreach ($read as $read_key => $read_socket)
      {
        if ($read[$read_key]===$server)
        {
          $client=stream_socket_accept($server,120);
          if (($client===false) or ($client==null))
          {
            # error: stream_socket_accept error/timeout
            continue;
          }
          stream_set_blocking($client,0);
          $sockets[]=$client;
        }
        else
        {
          $client_key=array_search($read[$read_key],$sockets,true);
          $data="";
          do
          {
            $buffer=fread($sockets[$client_key],1024);
            if ($buffer===false)
            {
              # error: socket read error
              \webdb\service\close_client($sockets,$client_key);
              continue 2;
            }
            $data.=$buffer;
          }
          while (strlen($buffer)>0);
          if (strlen($data)==0)
          {
            # client socket terminated connection
            \webdb\service\close_client($sockets,$client_key);
            continue;
          }
          $data=trim($data);
          if ($data==="quit")
          {
            break 2;
          }
          \webdb\service\on_msg($sockets,$client_key,$data);
        }
      }
    }
    usleep(0.1*1e6);
  }
  foreach ($sockets as $key => $socket)
  {
    if ($sockets[$key]!==$server)
    {
      \webdb\service\close_client($sockets,$key);
    }
  }
  stream_socket_shutdown($server,STREAM_SHUT_RDWR);
  fclose($server);
  die;
}

#####################################################################################################

function on_msg($client_key,$data)
{
  \webdb\utils\email_admin($data,"service client message received");
}

#####################################################################################################

function close_client(&$sockets,$client_key)
{
  stream_socket_shutdown($sockets[$client_key],STREAM_SHUT_RDWR);
  fclose($sockets[$client_key]);
  unset($sockets[$client_key]);
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
      case "quit":
        $output=\webdb\service\get_process_status();
        if (strpos($output,"php")!==false)
        {
          $socket=stream_socket_client("tcp://localhost:50000",$err_no,$err_msg);
          fwrite($socket,$directive);
          fclose($socket);
          $response[]="service quit";
        }
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
  $cmd="wmic process where \"name='php.exe'\" get Caption /format:LIST 2>&1";
  return shell_exec($cmd);
}

#####################################################################################################

function run_service()
{
  global $settings;
  $output=\webdb\service\get_process_status();
  if (strpos($output,"php")===false)
  {
    /*$settings["run_service_cmd"]='start "webdb_service" /B "C:\Program Files (x86)\PHP\php.exe" $$env_root_path$$index.php alert_service';
    $tmp=array("tmp"=>$settings["run_service_cmd"]);
    $cmd=\webdb\utils\template_fill("tmp",false,array(),$tmp);
    pclose(popen($cmd,"r"));*/
  }
}

#####################################################################################################
