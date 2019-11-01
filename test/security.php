<?php

namespace webdb\test\security;

define("webdb\\test\\security\\ERROR_COLOR",31);
define("webdb\\test\\security\\SUCCESS_COLOR",32);
define("webdb\\test\\security\\INFO_COLOR",94);
define("webdb\\test\\security\\DUMP_COLOR",35);

#####################################################################################################

function start()
{
  \webdb\test\security\test_info_message("STARTING SECURITY TESTS...");

  \webdb\test\security\remote_address_change();

  \webdb\test\security\test_info_message("FINISHED SECURITY TESTS");
}

#####################################################################################################

function test_error_message($message)
{
  \webdb\cli\term_echo($message,\webdb\test\security\ERROR_COLOR);
  \webdb\test\security\finish_test_user();
  \webdb\test\security\delete_test_config();
  die;
}

#####################################################################################################

function test_info_message($message)
{
  \webdb\cli\term_echo($message,\webdb\test\security\INFO_COLOR);
}

#####################################################################################################

function test_success_message($message)
{
  \webdb\cli\term_echo($message,\webdb\test\security\SUCCESS_COLOR);
}

#####################################################################################################

function test_dump_message($message)
{
  \webdb\cli\term_echo($message,\webdb\test\security\DUMP_COLOR);
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
  \webdb\test\security\write_file($settings["security_test_fudge_file"],$content);
  \webdb\test\security\test_info_message("TEST CONFIG FILE WRITTEN");
}

#####################################################################################################

function delete_test_config()
{
  global $settings;
  \webdb\test\security\delete_file($settings["security_test_fudge_file"]);
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

function wget($uri,$cookie_header="")
{
  $headers="POST $uri HTTP/1.0\r\n";
  $headers.="Host: localhost\r\n";
  if ($cookie_header<>"")
  {
    $headers.=$cookie_header."\r\n";
  }
  $headers.="Connection: Close\r\n\r\n";
  return \webdb\test\security\submit_request($headers);
}

#####################################################################################################

function wpost($uri,$params,$cookie_header="")
{
  $encoded_params=array();
  foreach ($params as $key => $value)
  {
    $encoded_params[]=$key."=".rawurlencode($value);
  }
  $content=implode("&",$encoded_params);
  $headers="POST $uri HTTP/1.0\r\n";
  $headers.="Host: localhost\r\n";
  $headers.="Content-Type: application/x-www-form-urlencoded\r\n";
  if ($cookie_header<>"")
  {
    $headers.=$cookie_header."\r\n";
  }
  $headers.="Content-Length: ".strlen($content)."\r\n";
  $headers.="Connection: Close\r\n\r\n";
  $request=$headers.$content;
  return \webdb\test\security\submit_request($request);
}

#####################################################################################################

function submit_request($request)
{
  \webdb\test\security\test_info_message("ATTEMPTING TO CONNECT TO SERVER AND SUBMIT REQUEST...");
  $errno=0;
  $errstr="";
  $fp=stream_socket_client("tcp://localhost:80",$errno,$errstr,10);
  if ($fp===false)
  {
    \webdb\test\security\test_error_message("ERROR CONNECTING TO LOCALHOST ON PORT 80");
  }
  #\webdb\test\security\test_dump_message($request);
  fwrite($fp,$request);
  $response="";
  while (!feof($fp))
  {
    $response.=fgets($fp,1024);
  }
  fclose($fp);
  \webdb\test\security\test_info_message("REQUEST COMPLETED");
  #\webdb\test\security\test_dump_message($response);
  return $response;
}

#####################################################################################################

function start_test_user()
{
  if (\webdb\test\security\get_test_user()===false)
  {
    \webdb\test\security\create_test_user();
    if (\webdb\test\security\get_test_user()===false)
    {
      \webdb\test\security\test_error_message("ERROR STARTING TEST USER: USER NOT FOUND AFTER INSERT");
    }
  }
  \webdb\test\security\test_info_message("TEST USER STARTED");
}

#####################################################################################################

function finish_test_user()
{
  if (\webdb\test\security\get_test_user()===false)
  {
    \webdb\test\security\test_error_message("ERROR FINISHING TEST USER: USER NOT FOUND");
  }
  \webdb\test\security\delete_test_user();
  if (\webdb\test\security\get_test_user()!==false)
  {
    \webdb\test\security\test_error_message("ERROR FINISHING TEST USER: ERROR DELETING");
  }
  \webdb\test\security\test_info_message("TEST USER FINISHED");
}

#####################################################################################################

function get_test_user()
{
  $sql_params=array();
  $sql_params["username"]="test_user";
  $sql="SELECT * FROM webdb.users WHERE username=:username";
  $records=\webdb\sql\fetch_prepare($sql,$sql_params);
  if (count($records)==1)
  {
    return $records[0];
  }
  return false;
}

#####################################################################################################

function create_test_user()
{
  $items=array();
  $items["username"]="test_user";
  $items["enabled"]=1;
  $items["email"]="";
  $items["pw_hash"]="\$2y\$13\$Vn8rJB73AHq56cAqbBwkEuKrQt3lSdoA3sDmKULZEgQLE4.nmsKzW"; # 'password'
  $items["pw_change"]=0;
  \webdb\sql\sql_insert($items,"users","webdb");
}

#####################################################################################################

function delete_test_user()
{
  $sql_params=array();
  $sql_params["username"]="test_user";
  \webdb\sql\sql_delete($sql_params,"users","webdb");
}

#####################################################################################################

function write_file($filename,$content)
{
  if (file_exists($filename)==true)
  {
    \webdb\test\security\test_info_message("OVERWRITING EXISTING FILE: ".$filename);
  }
  $result=file_put_contents($filename,$content);
  if ($result==false)
  {
    \webdb\test\security\test_error_message("ERROR WRITING FILE: ".$filename);
  }
  if (file_exists($filename)==false)
  {
    \webdb\test\security\test_error_message("ERROR WRITING FILE (FILE NOT FOUND): ".$filename);
  }
}

#####################################################################################################

function delete_file($filename)
{
  if (file_exists($filename)==false)
  {
    \webdb\test\security\test_info_message("UNABLE TO DELETE FILE (FILE NOT FOUND): ".$filename);
    return;
  }
  $result=unlink($filename);
  if ($result==false)
  {
    \webdb\test\security\test_error_message("ERROR DELETING FILE: ".$filename);
  }
}

#####################################################################################################

function check_authentication_status($response)
{
  $params=array();
  $params["username"]="test_user";
  $authenticated_status=\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."authenticated_status",$params);
  $unauthenticated_status=\webdb\utils\template_fill("global".DIRECTORY_SEPARATOR."unauthenticated_status");
  if (strpos($response,$authenticated_status)!==false)
  {
    return true;
  }
  if (strpos($response,$unauthenticated_status)!==false)
  {
    return false;
  }
  \webdb\test\security\test_error_message("AUTHENTICATION STATUS NOT FOUND IN PAGE CONTENT");
}

#####################################################################################################

function remote_address_change()
{
  global $settings;
  \webdb\test\security\test_info_message("TEST CASE: if any of the higher 3 octets of the user's remote address change, invalidate cookie login (require password)");
  \webdb\test\security\start_test_user();
  $params=array();
  $params["login_username"]="test_user";
  $params["login_password"]="password";
  $response=\webdb\test\security\wpost($settings["app_web_root"],$params);
  $headers=\webdb\test\security\extract_http_headers($response);
  $result=\webdb\test\security\search_http_headers($headers,"location");
  $cookie_jar=\webdb\test\security\search_http_headers($headers,"set-cookie");
  if (count($result)<>1)
  {
    \webdb\test\security\test_error_message("SERVER RETURNED NO COOKIES");
  }
  $uri=$result[0];
  $cookie_jar[]="webdb_username=test_user";
  $cookie_header=\webdb\test\security\construct_cookie_header($cookie_jar);
  $test_count=5;
  for ($i=1;$i<=$test_count;$i++)
  {
    $response=\webdb\test\security\wget($uri,$cookie_header);
    if (\webdb\test\security\check_authentication_status($response)==true)
    {
      \webdb\test\security\test_success_message("AUTHENTICATION SUCCESS");
    }
    else
    {
      \webdb\test\security\test_error_message("AUTHENTICATION FAILED");
    }
  }
  $test_settings=array();
  $test_settings["changed_remote_addr"]="::2";
  \webdb\test\security\write_test_config($test_settings);
  \webdb\test\security\test_info_message("changing remote address from ::1 to ::2");
  $response=\webdb\test\security\wget($uri,$cookie_header);
  \webdb\test\security\delete_test_config();
  if (\webdb\test\security\check_authentication_status($response)==false)
  {
    \webdb\test\security\test_success_message("REMOTE ADDRESS CHANGE TEST SUCCESS");
  }
  else
  {
    \webdb\test\security\test_error_message("REMOTE ADDRESS CHANGE TEST FAILED");
  }
  \webdb\test\security\finish_test_user();
}

#####################################################################################################
