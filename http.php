<?php

namespace webdb\http;

#####################################################################################################

function request($url,$peer_name,$request,$ignore_verify=false,$return_error=false,$timeout=20)
{
  global $settings;
  $url_parts=parse_url($url);
  $host=$url_parts["host"];
  $context_options=array(
    "http"=>array(
      "user_agent"=>$settings["http_user_agent"],
      "max_redirects"=>10,
      "timeout"=>$timeout));
  if (isset($url_parts["scheme"])==false)
  {
    $url_parts["scheme"]="https";
  }
  if ($url_parts["scheme"]=="https")
  {
    $port=443;
    $protocol="tls";
    if (($ignore_verify==false) and ($peer_name<>"") and (file_exists($settings["ssl_cafile"])==true))
    {
      $context_options["ssl"]=array(
        "peer_name"=>$peer_name,
        "verify_peer"=>true,
        "verify_peer_name"=>true,
        "allow_self_signed"=>false,
        "verify_depth"=>5,
        "cafile"=>$settings["ssl_cafile"],
        "disable_compression"=>false,
        "SNI_enabled"=>true,
        "ciphers"=>"DEFAULT");
    }
    else
    {
      $context_options["ssl"]=array(
        "verify_peer"=>false,
        "verify_peer_name"=>false);
    }
  }
  else
  {
    $port=80;
    $protocol="tcp";
  }
  $context=stream_context_create($context_options);
  $errno=0;
  $errstr="";
  $fp=stream_socket_client($protocol."://".$host.":".$port,$errno,$errstr,$timeout,STREAM_CLIENT_CONNECT,$context);
  if ($fp===false)
  {
    if ($return_error==true)
    {
      return false;
    }
    else
    {
      \webdb\utils\error_message("Error connecting to '".$host."'.");
    }
  }
  fwrite($fp,$request);
  $chunksize=1024;
  $response="";
  while (feof($fp)===false)
  {
    $response.=fgets($fp,$chunksize);
  }
  fclose($fp);
  return $response;
}

#####################################################################################################

function wget($url,$peer_name,&$cookie_jar,$headers=false,$ignore_verify=false,$return_error=false,$timeout=20)
{
  global $settings;
  $url_parts=parse_url($url);
  $host=$url_parts["host"];
  $uri=$url_parts["path"];
  if (isset($url_parts["query"])==true)
  {
    $uri.="?".$url_parts["query"];
  }
  $request="GET ".$uri." HTTP/1.0\r\n";
  $request.="Host: ".$host."\r\n";
  $request.="User-Agent: ".$settings["http_user_agent"]."\r\n";
  if ($headers===false)
  {
    $request.="Accept: text/html; charset=utf-8\r\n";
  }
  else
  {
    $request.=implode("\r\n",$headers)."\r\n";
  }
  $request=\webdb\http\cookie_header($request,$cookie_jar);
  $request.="Connection: Close\r\n\r\n";
  $response=\webdb\http\request($url,$peer_name,$request,$ignore_verify,$return_error,$timeout);
  $headers=\webdb\http\get_headers($response);
  \webdb\http\update_cookies($cookie_jar,$headers);
  $result=\webdb\http\search_headers($headers,"location");
  if (count($result)>0)
  {
    $redirect=$result[0];
    $url_parts=parse_url($redirect);
    if (isset($url_parts["host"])==false)
    {
      $redirect="https://".$host.$redirect;
    }
    $response=\webdb\http\wget($redirect,$peer_name,$cookie_jar,false,$ignore_verify,$return_error,$timeout);
  }
  return $response;
}

#####################################################################################################

function wpost($url,$content,$peer_name,&$cookie_jar,$headers=false,$ignore_verify=false,$return_error=false,$timeout=20,$method="POST",$ua_override=false)
{
  global $settings;
  $content_type="application/x-www-form-urlencoded";
  if (is_array($content)==true)
  {
    $encoded_params=array();
    foreach ($content as $key => $value)
    {
      $encoded_params[]=$key."=".rawurlencode($value);
    }
    $content=implode("&",$encoded_params);
  }
  else
  {
    if (json_decode($content,true)!==false)
    {
      $content_type="application/json";
    }
  }
  $url_parts=parse_url($url);
  $host=$url_parts["host"];
  $uri=$url_parts["path"];
  if (isset($url_parts["query"])==true)
  {
    $uri.="?".$url_parts["query"];
  }
  $request=$method." ".$uri." HTTP/1.0\r\n";
  $request.="Host: ".$host."\r\n";
  if ($ua_override===false)
  {
    $request.="User-Agent: ".$settings["http_user_agent"]."\r\n";
  }
  if ($headers===false)
  {
    $request.="Accept: text/html; charset=utf-8\r\n";
    $request.="Content-Type: ".$content_type."\r\n";
  }
  else
  {
    $request.=implode("\r\n",$headers)."\r\n";
  }
  $request=\webdb\http\cookie_header($request,$cookie_jar);
  $request.="Content-Length: ".strlen($content)."\r\n";
  $request.="Connection: Close\r\n\r\n";
  $request=$request.$content;
  $response=\webdb\http\request($url,$peer_name,$request,$ignore_verify,$return_error,$timeout);
  $headers=\webdb\http\get_headers($response);
  \webdb\http\update_cookies($cookie_jar,$headers);
  $result=\webdb\http\search_headers($headers,"location");
  if (count($result)>0)
  {
    $redirect=$result[0];
    $url_parts=parse_url($redirect);
    if (isset($url_parts["host"])==false)
    {
      $redirect="https://".$host.$redirect;
    }
    $response=\webdb\http\wget($redirect,$peer_name,$cookie_jar,false,$ignore_verify,$return_error,$timeout);
  }
  return $response;
}

#####################################################################################################

function get_headers($response)
{
  $i=strpos($response,"\r\n\r\n");
  return trim(substr($response,0,$i));
}

#####################################################################################################

function get_content($response)
{
  $i=strpos($response,"\r\n\r\n");
  return substr($response,$i+4);
}

#####################################################################################################

function cookie_header($request,$cookie_jar)
{
  if (count($cookie_jar)>0)
  {
    $cookies=array();
    foreach ($cookie_jar as $key => $cookie)
    {
      $parts=\webdb\utils\webdb_explode(";",$cookie);
      $value=urlencode(array_shift($parts));
      $cookies[]=$key."=".$value;
    }
    $request.="Cookie: ".implode("; ",$cookies)."\r\n";
  }
  return $request;
}

#####################################################################################################

function update_cookies(&$cookie_jar,$headers)
{
  $cookie_headers=\webdb\http\search_headers($headers,"set-cookie");
  for ($i=0;$i<count($cookie_headers);$i++)
  {
    $header=$cookie_headers[$i];
    $parts=\webdb\utils\webdb_explode("=",$header);
    $key=array_shift($parts);
    $value=urldecode(implode("=",$parts));
    $cookie_parts=\webdb\utils\webdb_explode(";",$value);
    $cookie_value=urlencode(array_shift($cookie_parts));
    if ($cookie_value=="deleted")
    {
      if (isset($cookie_jar[$key])==true)
      {
        unset($cookie_jar[$key]);
      }
    }
    else
    {
      $cookie_jar[$key]=$value;
    }
  }
}

#####################################################################################################

function search_headers($headers,$search_key)
{
  $result=array();
  $lines=\webdb\utils\webdb_explode("\n",$headers);
  for ($i=0;$i<count($lines);$i++)
  {
    $line=trim($lines[$i]);
    $parts=\webdb\utils\webdb_explode(":",$line);
    if (count($parts)>=2)
    {
      $key=trim(array_shift($parts));
      $value=trim(implode(":",$parts));
      if (\webdb\utils\webdb_strtolower($key)==\webdb\utils\webdb_strtolower($search_key))
      {
        $result[]=$value;
      }
    }
  }
  return $result;
}

#####################################################################################################
