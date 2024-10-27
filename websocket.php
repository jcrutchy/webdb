<?php

namespace webdb\websocket;

#####################################################################################################

function ws_default_settings()
{
  global $settings;
  $settings["ws_log_path"]="";
  $settings["ws_browser_listening_address"]="127.0.0.1";
  $settings["ws_browser_listening_port"]=50000;
  $settings["ws_app_listening_address"]="127.0.0.1";
  $settings["ws_app_listening_port"]=50001;
  $settings["ws_connection_timeout_sec"]=10;
  $settings["ws_server_header"]="webdb_ws";
  $settings["ws_browser_server"]=false;
  $settings["ws_app_server"]=false;
  $settings["ws_browser_sockets"]=array();
  $settings["ws_browser_connections"]=array();
  $settings["ws_on_browser_server_msg"]="";
  $settings["ws_on_app_server_msg"]="";
  $settings["ws_on_server_loop"]="";
}

#####################################################################################################

function server_start()
{
  global $settings;
  \webdb\cli\term_echo("websocket server started",33);
  $conn_str="tcp://".$settings["ws_browser_listening_address"].":".$settings["ws_browser_listening_port"];
  $settings["ws_browser_server"]=stream_socket_server($conn_str,$err_no,$err_msg);
  if ($settings["ws_browser_server"]===false)
  {
    \webdb\cli\term_echo("error: could not bind browser server to ".$conn_str,31);
    \webdb\cli\term_echo("  ".$err_msg,31);
    return;
  }
  \webdb\cli\term_echo("browser server bound to ".$conn_str,32);
  stream_set_blocking($settings["ws_browser_server"],0);
  $settings["ws_browser_sockets"][]=$settings["ws_browser_server"];
  $conn_str="tcp://".$settings["ws_app_listening_address"].":".$settings["ws_app_listening_port"];
  $settings["ws_app_server"]=stream_socket_server($conn_str,$err_no,$err_msg);
  if ($settings["ws_app_server"]===false)
  {
    \webdb\cli\term_echo("error: could not bind app server to ".$conn_str,31);
    \webdb\cli\term_echo("  ".$err_msg,31);
    return;
  }
  \webdb\cli\term_echo("app server bound to ".$conn_str,32);
  stream_set_blocking($settings["ws_app_server"],0);
  while (true)
  {
    usleep(0.05e6); # 0.05 second to prevent cpu flogging
    if (function_exists($settings["ws_on_server_loop"])==True)
    {
      call_user_func($settings["ws_on_server_loop"]);
    }
    $read=array($settings["ws_app_server"]);
    $write=null;
    $except=null;
    $change_count=stream_select($read,$write,$except,0);
    if ($change_count===false)
    {
      \webdb\cli\term_echo("error: stream_select on app server failed",31);
      break;
    }
    if ($change_count>=1)
    {
      $client=stream_socket_accept($settings["ws_app_server"],120);
      if (($client===false) or ($client==null))
      {
        \webdb\cli\term_echo("error: app server stream_socket_accept error/timeout",31);
        continue;
      }
      \webdb\cli\term_echo("app client connected",32);
      $data="";
      do
      {
        $buffer=fread($client,1024);
        if ($buffer===false)
        {
          \webdb\cli\term_echo("error: app client socket read error",31);
          continue 2;
        }
        $data.=$buffer;
      }
      while (strlen($buffer)>0);
      if ($data<>"")
      {
        \webdb\cli\term_echo("message received from app client socket",32);
        \webdb\cli\term_echo("  ".$data,32);
        if (function_exists($settings["ws_on_app_server_msg"])==true)
        {
          $reply=call_user_func($settings["ws_on_app_server_msg"],$data);
          if ($reply<>"")
          {
            $total_sent=0;
            while ($total_sent<strlen($reply))
            {
              $buf=substr($reply,$total_sent);
              try
              {
                $written=fwrite($client,$buf,min(strlen($buf),8192));
              }
              catch (exception $e)
              {
                $err_msg="an exception occurred when attempting to write to app client socket";
                \webdb\cli\term_echo("error: ".$err_msg,31);
                break;
              }
              if (($written===false) or ($written<=0))
              {
                \webdb\cli\term_echo("error: error writing to app client socket",31);
                break;
              }
              $total_sent+=$written;
            }
          }
        }
      }
      stream_socket_shutdown($client,STREAM_SHUT_RDWR);
      fclose($client);
    }
    $read=$settings["ws_browser_sockets"];
    $write=null;
    $except=null;
    $change_count=stream_select($read,$write,$except,0);
    if ($change_count===false)
    {
      \webdb\cli\term_echo("error: stream_select on browser sockets failed",31);
      break;
    }
    if ($change_count>=1)
    {
      foreach ($read as $read_key => $read_socket)
      {
        if ($read[$read_key]===$settings["ws_browser_server"])
        {
          $client=stream_socket_accept($settings["ws_browser_server"],120);
          if (($client===false) or ($client==null))
          {
            \webdb\cli\term_echo("error: browser server stream_socket_accept error/timeout",31);
            continue;
          }
          stream_set_blocking($client,0);
          $settings["ws_browser_sockets"][]=$client;
          $client_key=array_search($client,$settings["ws_browser_sockets"],true);
          $new_connection=array();
          $new_connection["peer_name"]=stream_socket_get_name($client,true);
          $new_connection["last_rec"]=microtime(true);
          $new_connection["state"]="CONNECTING";
          $new_connection["ping_time"]=false;
          $new_connection["pong_time"]=false;
          $settings["ws_browser_connections"][$client_key]=$new_connection;
          \webdb\cli\term_echo("browser client connected",32);
        }
        else
        {
          $client_key=array_search($read[$read_key],$settings["ws_browser_sockets"],true);
          $data="";
          do
          {
            $buffer=fread($settings["ws_browser_sockets"][$client_key],1024);
            if ($buffer===false)
            {
              \webdb\cli\term_echo("error: browser client socket ".$client_key." read error",31);
              \webdb\websocket\close_browser_client($client_key);
              continue 2;
            }
            $data.=$buffer;
          }
          while (strlen($buffer)>0);
          if (strlen($data)==0)
          {
            $settings["ws_browser_connections"][$client_key]["state"]="REMOTE TERMINATED";
            \webdb\cli\term_echo("browser client socket ".$client_key." terminated connection",32);
            \webdb\websocket\close_browser_client($client_key);
            continue;
          }
          if (\webdb\websocket\on_browser_msg($client_key,$data)=="quit")
          {
            break 2;
          }
        }
      }
    }
    else
    {
      foreach ($settings["ws_browser_connections"] as $client_key => $connection)
      {
        if ($connection["state"]<>"OPEN")
        {
          continue;
        }
        if (($connection["ping_time"]!==false) and ($connection["pong_time"]!==false))
        {
          $delta=$connection["pong_time"]-$connection["ping_time"];
          if ($delta>$settings["ws_connection_timeout_sec"])
          {
            \webdb\cli\term_echo("error: browser client latency is ".$delta." sec, which exceeds limit - closing connection",31);
            \webdb\websocket\close_browser_client($client_key);
            continue;
          }
          else
          {
            $delta=microtime(true)-$connection["ping_time"];
            if ($delta>$settings["ws_connection_timeout_sec"])
            {
              $settings["ws_browser_connections"][$client_key]["pong_time"]=false;
              $settings["ws_browser_connections"][$client_key]["ping_time"]=false;
            }
          }
        }
        else
        {
          $settings["ws_browser_connections"][$client_key]["ping_time"]=microtime(true);
          $ping_frame=\webdb\websocket\encode_frame(9);
          \webdb\websocket\do_browser_reply($client_key,$ping_frame);
          \webdb\cli\term_echo("pinging client ".$client_key,32);
        }
      }
    }
  }
  \webdb\websocket\shutdown_server();
}

#####################################################################################################

function shutdown_server()
{
  global $settings;
  foreach ($settings["ws_browser_sockets"] as $key => $socket)
  {
    if ($settings["ws_browser_sockets"][$key]!==$settings["ws_browser_server"])
    {
      \webdb\websocket\close_browser_client($key,1001,"websocket server shutting down");
    }
  }
  stream_socket_shutdown($settings["ws_browser_server"],STREAM_SHUT_RDWR);
  fclose($settings["ws_browser_server"]);
  stream_socket_shutdown($settings["ws_app_server"],STREAM_SHUT_RDWR);
  fclose($settings["ws_app_server"]);
}

#####################################################################################################

function on_browser_msg($client_key,$data)
{
  global $settings;
  if ($settings["ws_browser_connections"][$client_key]["state"]=="CONNECTING")
  {
    # TODO: CHECK "Host" HEADER (COMPARE TO "LOGIN_DOMAINS" SERVER CONFIG SETTING)
    # TODO: CHECK "Origin" HEADER (EXTRACT HOST FROM URL & COMPARE TO "LOGIN_DOMAINS" SERVER CONFIG SETTING)
    # TODO: CHECK "Sec-WebSocket-Version" HEADER (MUST BE 13)
    \webdb\cli\term_echo("from client socket ".$client_key." (connecting):",32);
    \webdb\cli\term_echo(print_r($data,true));
    $headers=\webdb\http\get_headers($data);
    /*$cookies=\webdb\http\search_headers($headers,"Cookie");
    if (count($cookies)==0)
    {
      \webdb\cli\term_echo("error: client socket ".$client_key." login cookie not found",31);
      \webdb\websocket\close_browser_client($client_key);
      return "";
    }
    $user_agent=\webdb\http\search_headers($headers,"User-Agent");
    if ($user_agent===false)
    {
      \webdb\cli\term_echo("error: client socket ".$client_key." user agent header not found",31);
      \webdb\websocket\close_browser_client($client_key);
      return "";
    }*/
    $remote_address=$settings["ws_browser_connections"][$client_key]["peer_name"];
    $i=strrpos($remote_address,":");
    if ($i===false)
    {
      \webdb\cli\term_echo("error: client socket ".$client_key." invalid peer name",31);
      \webdb\websocket\close_browser_client($client_key);
      return "";
    }
    $remote_address=substr($remote_address,0,$i);
    /*if (ws_server_authenticate($settings["ws_browser_connections"][$client_key],$cookies,$user_agent,$remote_address)==false)
    {
      \webdb\cli\term_echo("error: authentication error",31);
      \webdb\websocket\close_browser_client($client_key);
      return "";
    }*/
    $sec_websocket_key=\webdb\http\search_headers($headers,"Sec-WebSocket-Key");
    $sec_websocket_key=$sec_websocket_key[0];
    $sec_websocket_accept=base64_encode(sha1($sec_websocket_key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true));
    $msg="HTTP/1.1 101 Switching Protocols".PHP_EOL;
    $msg.="Server: ".$settings["ws_server_header"].PHP_EOL;
    $msg.="Upgrade: websocket".PHP_EOL;
    $msg.="Connection: Upgrade".PHP_EOL;
    $msg.="Sec-WebSocket-Accept: ".$sec_websocket_accept."\r\n\r\n";
    \webdb\cli\term_echo("client socket ".$client_key." state set to OPEN",32);
    $settings["ws_browser_connections"][$client_key]["state"]="OPEN";
    $settings["ws_browser_connections"][$client_key]["buffer"]=array();
    \webdb\websocket\do_browser_reply($client_key,$msg);
  }
  elseif ($settings["ws_browser_connections"][$client_key]["state"]=="OPEN")
  {
    \webdb\cli\term_echo("command or text message received from OPEN browser client socket ".$client_key,32);
    $frame=\webdb\websocket\decode_frame($data);
    if ($frame===false)
    {
      \webdb\cli\term_echo("error: received illegal frame from browser client socket ".$client_key,31);
      \webdb\websocket\close_browser_client($client_key);
      return "";
    }
    $msg="";
    switch ($frame["opcode"])
    {
      case 0: # continuation frame
        \webdb\cli\term_echo("received continuation frame from browser client socket ".$client_key,33);
        $settings["ws_browser_connections"][$client_key]["buffer"][]=$frame;
        if ($frame["fin"]==true)
        {
          $msg=\webdb\websocket\coalesce_frames($settings["ws_browser_connections"][$client_key]["buffer"]);
          $settings["ws_browser_connections"][$client_key]["buffer"]=array();
          break;
        }
        break;
      case 1: # text frame
        if ($frame["fin"]==true)
        {
          # received single text frame
          $msg=$frame["payload"];
          $settings["ws_browser_connections"][$client_key]["buffer"]=array();
        }
        else
        {
          # received initial frame of a fragmented series
          $settings["ws_browser_connections"][$client_key]["buffer"][]=$frame;
        }
        break;
      case 8: # connection close
        if (isset($frame["close_status"])==true)
        {
          \webdb\cli\term_echo("received close frame - status code ".$frame["close_status"],33);
          \webdb\websocket\close_browser_client($client_key,1000);
        }
        else
        {
          \webdb\cli\term_echo("received close frame from client socket ".$client_key." - unrecognised/missing status code",33);
          \webdb\websocket\close_browser_client($client_key);
        }
        return "";
      case 9: # ping
        $reply_frame=\webdb\websocket\encode_frame(10,$frame["payload"]);
        \webdb\websocket\do_browser_reply($client_key,$reply_frame);
        return "";
      case 10: # pong
        if ($settings["ws_browser_connections"][$client_key]["ping_time"]!==false)
        {
          $settings["ws_browser_connections"][$client_key]["pong_time"]=microtime(true);
          \webdb\cli\term_echo("received pong from client ".$client_key,33);
        }
        return "";
      default:
        \webdb\cli\term_echo("error: ignored frame with unsupported opcode from client socket ".$client_key,31);
        return "";
    }
    if ($msg<>"")
    {
      \webdb\cli\term_echo("text message received from browser client socket ".$client_key,32);
      \webdb\cli\term_echo("  ".$msg,32);
      if (function_exists($settings["ws_on_browser_server_msg"])==true)
      {
        call_user_func($settings["ws_on_browser_server_msg"],$client_key,$msg);
      }
    }
  }
  return "";
}

#####################################################################################################

function close_browser_client($client_key,$status_code=false,$reason="")
{
  global $settings;
  if ($status_code!==false)
  {
    \webdb\cli\term_echo("closing client socket ".$client_key." connection (cleanly)",33);
    $reply_frame=encode_frame(8,$reason,$status_code);
    \webdb\websocket\do_browser_reply($client_key,$reply_frame);
  }
  else
  {
    \webdb\cli\term_echo("closing client socket ".$client_key." connection (uncleanly)",33);
  }
  stream_socket_shutdown($settings["ws_browser_sockets"][$client_key],STREAM_SHUT_RDWR);
  fclose($settings["ws_browser_sockets"][$client_key]);
  unset($settings["ws_browser_sockets"][$client_key]);
  unset($settings["ws_browser_connections"][$client_key]);
}

#####################################################################################################

function broadcast_to_all($msg)
{
  global $settings;
  foreach ($settings["ws_browser_connections"] as $key => $conn)
  {
    if ($conn["state"]=="OPEN")
    {
      #\webdb\cli\term_echo("sending to browser client socket ".$key.": ".$msg,33);
      \webdb\websocket\send_browser_text($key,$msg);
    }
  }
}

#####################################################################################################

function send_browser_text($client_key,$msg)
{
  global $settings;
  if ($settings["ws_browser_connections"][$client_key]["state"]<>"OPEN")
  {
    return false;
  }
  \webdb\cli\term_echo("sending to browser client socket ".$client_key.": ".$msg,33);
  $frame=\webdb\websocket\encode_text_data_frame($msg);
  \webdb\websocket\do_browser_reply($client_key,$frame);
  return true;
}

#####################################################################################################

function encode_text_data_frame($payload)
{
  return \webdb\websocket\encode_frame(1,$payload);
}

#####################################################################################################

function encode_frame($opcode,$payload="",$status=false)
{
  $length=strlen($payload);
  if ($status!==false)
  {
    $length+=2;
  }
  $frame=chr(128|$opcode);
  if ($length<=125)
  {
    $frame.=chr($length);
  }
  elseif ($length<=65535)
  {
    $frame.=chr(126);
    $frame.=chr($length>>8);
    $frame.=chr($length&255);
  }
  else
  {
    $frame.=chr(127);
    $frame.=chr($length>>56);
    $frame.=chr(($length>>48)&255);
    $frame.=chr(($length>>40)&255);
    $frame.=chr(($length>>32)&255);
    $frame.=chr(($length>>24)&255);
    $frame.=chr(($length>>16)&255);
    $frame.=chr(($length>>8)&255);
    $frame.=chr($length&255);
  }
  if ($status!==false)
  {
    $frame.=chr($status>>8);
    $frame.=chr($status&255);
  }
  $frame.=$payload;
  return $frame;
}

#####################################################################################################

function coalesce_frames(&$buffer)
{
  $msg="";
  for ($i=0;$i<count($buffer);$i++)
  {
    $msg.=$buffer[$i]["payload"];
  }
  return $msg;
}

#####################################################################################################

function decode_frame(&$frame_data)
{
  # https://tools.ietf.org/html/rfc6455
  $frame=array();
  $F=unpack("C".min(14,strlen($frame_data)),$frame_data); # first key is 1 (not 0)
  $frame["fin"]=(($F[1]&128)==128);
  $frame["opcode"]=$F[1]&15;
  $frame["mask"]=(($F[2]&128)==128);
  $length=$F[2]&127;
  $L=0; # number of additional bytes for payload length
  if ($length==126)
  {
    # pack 16-bit network byte ordered (big-endian) unsigned int
    $length=($F[3]<<8)+$F[4];
    $L=2;
  }
  elseif ($length==127)
  {
    # pack 64-bit network byte ordered (big-endian) unsigned int
    $length=($F[3]<<56)+($F[4]<<48)+($F[5]<<40)+($F[6]<<32)+($F[7]<<24)+($F[8]<<16)+($F[9]<<8)+$F[10];
    $L=8;
  }
  $frame["mask_key"]=array();
  $offset=2+$L+1; # first payload byte (no mask)
  if ($frame["mask"]==true)
  {
    for ($i=1;$i<=4;$i++)
    {
      $frame["mask_key"][]=$F[2+$L+$i];
    }
    $offset+=4; # first payload byte (with mask)
  }
  $frame["payload"]="";
  if ($length>0)
  {
    if ($frame["mask"]==true)
    {
      for ($i=0;$i<$length;$i++)
      {
        $key=$i+$offset-1;
        if (isset($frame_data[$key])==false)
        {
         \webdb\cli\term_echo("error: decode_frame error: frame_data[key] not found: key=".$key,31);
          return false;
        }
        $frame_data[$key]=chr(ord($frame_data[$key])^$frame["mask_key"][$i%4]);
      }
    }
    $frame["payload"]=substr($frame_data,$offset-1);
    if (($frame["opcode"]==8) and ($length>=2))
    {
      $status=unpack("C2",$frame["payload"]);
      $frame["close_status"]=($status[1]<<8)+$status[2];
      $frame["payload"]=substr($frame["payload"],2);
    }
    # received invalid utf8 frame (according to following preg_match) when sending a basic json-encoded string so there may be a sneaky bug somewhere in this function
    # works (length=92): {"operation":"doc_record_lock","client_id":"clientid_588c81e742a3a","doc_id":"","foo":"bar"}
    # works (length=80): {"operation":"doc_record_lock","client_id":"clientid_588c82769e6c9","doc_id":""}
    # fails (length=85): {"operation":"doc_record_lock","client_id":"clientid_588c83b2c3d1d","doc_id":"32458"} <== string is decoded correctly but 91 chars of jibberish appears after (readable using ISO-8859-14 encoding with mousepad)
    /*$valid_utf8=preg_match("//u",$frame["payload"]);
    if (($valid_utf8===false) or ($valid_utf8==0))
    {
      \webdb\cli\term_echo("error: decode_frame error: invalid utf8 found in payload",31);
      \webdb\cli\term_echo("  ".$frame["payload"],31);
      return false;
    }*/
    # workaround for now is to loop through payload and truncate before first invalid ascii character, which seems to work for the failing data (using ord function doesn't work)
    $valid=" !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~…‘’“”•–—™¢€£§©«®°±²³´µ¶·¹»¼½¾";
    for ($i=0;$i<strlen($frame["payload"]);$i++)
    {
      $c=$frame["payload"][$i];
      if (strpos($valid,$c)!==false)
      {
        continue;
      }
      $frame["payload"]=substr($frame["payload"],0,$i);
      \webdb\cli\term_echo("decode_frame warning: payload truncated: ".$frame["payload"],32);
      break;
    }
  }
  return $frame;
}

#####################################################################################################

function do_browser_reply($client_key,$msg) # $msg is an encoded websocket frame
{
  global $settings;
  if ($settings["ws_browser_connections"][$client_key]["state"]<>"OPEN")
  {
    return;
  }
  $total_sent=0;
  while ($total_sent<strlen($msg))
  {
    $buf=substr($msg,$total_sent);
    try
    {
      $written=fwrite($settings["ws_browser_sockets"][$client_key],$buf,min(strlen($buf),8192));
    }
    catch (exception $e)
    {
      $err_msg="an exception occurred when attempting to write to client socket ".$client_key;
      \webdb\cli\term_echo("error: ".$err_msg,31);
      \webdb\websocket\close_browser_client($client_key);
      return;
    }
    if (($written===false) or ($written<=0))
    {
      \webdb\cli\term_echo("error: error writing to client socket ".$client_key,31);
      \webdb\websocket\close_browser_client($client_key);
      return;
    }
    $total_sent+=$written;
  }
}

#####################################################################################################
