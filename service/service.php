<?php

namespace webdb\service;

#####################################################################################################

function service_main()
{
  set_time_limit(0);
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
          $client_key=array_search($client,$sockets,true);
          \webdb\service\client_connect($sockets,$client_key);
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

function client_connect(&$sockets,$client_key)
{
}

#####################################################################################################

function error_handler($errno,$errstr,$errfile,$errline)
{
  $message="[".date("Y-m-d, H:i:s T",time())."] ".$errstr." in \"".$errfile."\" on line ".$errline;
  \webdb\utils\email_admin($message,"service error");
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

/*function broadcast_to_all($msg)
{
  global $connections;
  foreach ($connections as $key => $conn)
  {
    if ($conn["state"]=="OPEN")
    {
      show_message("sending to client socket ".$key.": ".$msg,true);
      $frame=encode_text_data_frame($msg);
      do_reply($key,$frame);
    }
  }
}*/

#####################################################################################################

/*function do_reply($client_key,$msg)
{
  global $sockets;
  $total_sent=0;
  while ($total_sent<strlen($msg))
  {
    $buf=substr($msg,$total_sent);
    try
    {
      $written=fwrite($sockets[$client_key],$buf,min(strlen($buf),8192));
    }
    catch (Exception $e)
    {
      $err_msg="an exception occurred when attempting to write to client socket $client_key";
      send_email(ADMINISTRATOR_EMAIL,"WEBSOCKET SERVER EXCEPTION (do_reply)",$err_msg);
      close_client($client_key);
      return;
    }
    if (($written===false) or ($written<=0))
    {
      close_client($client_key);
      return;
    }
    $total_sent+=$written;
  }
}*/

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
        $socket=stream_socket_client("tcp://localhost:50000",$err_no,$err_msg);
        fwrite($socket,$directive);
        fclose($socket);
        $response[]="service quit";
        break;
      case "status":
        $response[]=\webdb\service\get_process_status();
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
  $cmd="wmic process where \"name='php.exe'\" get Commandline /format:LIST 2>&1";
  return shell_exec($cmd);
}

#####################################################################################################

function run_service()
{
  global $settings;
  return;
  #$output=\webdb\service\get_process_status();
  # test in git-bash: start "" /d/dev/public/webdb/service/win_start.bat "D:\dev\public\env\"
  #if ((strpos($output,"php ")===false) or (strpos($output,"index.php")===false) or (strpos($output,"alert_service")===false))
  #{
    #$cmd=$settings["webdb_root_path"]."service".DIRECTORY_SEPARATOR."win_start.bat \"".$settings["env_root_path"]."\"";
    #shell_exec("start \"\" ".$cmd);
  #}
}

#####################################################################################################
