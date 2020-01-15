<?php

/*if (isset($argv[1])==true)
{
  $source_url=$argv[1];
  echo "validating: ".$source_url.PHP_EOL;
  $page_content=file_get_contents($source_url);
  \webdb\utils\debug_var_dump($page_content);
  $is_valid=validator($page_content);
  \webdb\utils\debug_var_dump($is_valid);
}*/

#####################################################################################################

function validator($page_content)
{
  $host="localhost";
  $uri="/check?;output=xml";
  $error_hint="";
  $fp=fsockopen($host,80,$errno,$errstr,30);
  if (!$fp)
  {
    echo "error connecting to host".PHP_EOL;
    return false;
  }
  else
  {
    $html=rawurlencode($page_content);
    $out="POST ".$uri." HTTP/1.0\r\n";
    $out=$out."Host: $host\r\n";
    $out=$out."Content-type: application/x-www-form-urlencoded\r\n";
    $out=$out."Content-length: ".(strlen($html)+strlen("fragment="))."\r\n";
    $out=$out."Connection: Close\r\n\r\n";
    fwrite($fp,$out."fragment=".$html);
    $response="";
    while (!feof($fp))
    {
      $response=$response.fgets($fp,1024);
    }
    fclose($fp);
    $status_key="X-W3C-Validator-Status:";
    $i=strpos($response,$status_key);
    if ($i>0)
    {
      $j=strpos($response,"\r\n",$i);
      if ($j>0)
      {
        if (strtolower(trim(substr($response,$i+strlen($status_key),$j-$i-strlen($status_key))))=="valid")
        {
          return true;
        }
      }
    }
    $i=strpos($response,"Validation Output:");
    if ($i>0)
    {
      $error_hint=substr($response,$i,strlen($response)-$i);
    }
    $i=strpos($error_hint,"<ol id=");
    if ($i>0)
    {
      $error_hint=substr($error_hint,$i,strlen($error_hint)-$i);
    }
    $i=strpos($error_hint,"</ol>");
    if ($i>0)
    {
      $error_hint=substr($error_hint,0,$i);
    }
    $error_hint=strip_tags($error_hint);
    $error_lines=explode(PHP_EOL,$error_hint);
    $error_hint="";
    $dump_line_start="&#";
    for ($i=0;$i<count($error_lines);$i++)
    {
      $s=trim($error_lines[$i]);
      if (strlen($s)>0)
      {
        if (substr($s,0,strlen($dump_line_start))<>$dump_line_start)
        {
          $error_hint=$error_hint.$s.PHP_EOL;
        }
      }
    }
  }
  if (strlen($error_hint)>0)
  {
    echo $error_hint.PHP_EOL;
  }
  return false;
}

#####################################################################################################
