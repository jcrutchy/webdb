<?php

namespace webdb\wiki_utils;

#####################################################################################################

function get_article_record()
{
  global $settings;
  $user_wiki_settings=\webdb\wiki_utils\get_user_wiki_settings();
  $title=$user_wiki_settings["home_article"];
  if (isset($_GET["article"])==true)
  {
    $title=$_GET["article"];
  }
  return \webdb\wiki_utils\get_article_record_by_title($title);
}

#####################################################################################################

function view_fixed_article_file($form_config,$title)
{
  global $settings;
  $filename=strtolower(str_replace(":","_",$title));
  $template="wiki/fixed_articles/".$filename;
  if (isset($settings["templates"][$template])==false)
  {
    return;
  }
  $page_params["wiki_styles_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki.css");
  $page_params["wiki_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki_print.css");
  $page_params["url_title"]=urlencode($filename);
  $page_params["content"]=\webdb\wiki_utils\wikitext_to_html($settings["templates"][$template]);
  $page_params["title"]=$title;
  $content=\webdb\utils\template_fill("wiki/locked_article_view",$page_params);
  \webdb\utils\output_page($content,$form_config["title"].": ".$title);
}

#####################################################################################################

function get_article_history($article_id)
{
  $where_items=array();
  $where_items["article_id"]=$article_id;
  return \webdb\sql\file_fetch_prepare("wiki/get_article_history",$where_items);
}

#####################################################################################################

function get_article_record_by_title($title)
{
  $where_items=array();
  $where_items["title"]=$title;
  $records=\webdb\sql\file_fetch_prepare("wiki/get_article_record_by_title",$where_items);
  if (count($records)==1)
  {
    return $records[0];
  }
  return false;
}

#####################################################################################################

function get_file_record_by_title($title)
{
  $where_items=array();
  $where_items["title"]=$title;
  $records=\webdb\sql\file_fetch_prepare("wiki/get_file_record_by_title",$where_items);
  if (count($records)==1)
  {
    return $records[0];
  }
  return false;
}

#####################################################################################################

function wikitext_to_html($content)
{
  $break=\webdb\utils\template_fill("break");

  $escape_pairs=array();
  # order is important
  $escape_pairs["comment"]=array("<!--","-->");
  $escape_pairs["nowiki"]=array("<nowiki>","</nowiki>");
  $escape_pairs["code"]=array("<code>","</code>");

  foreach ($escape_pairs as $key => $pair)
  {
    $content=\webdb\wiki_utils\wikitext_escape_pair($content,$pair[0],$pair[1]);
  }

  $content=str_replace("\r\n",$break,$content);
  $content=str_replace("\r",$break,$content);
  $content=str_replace("\n",$break,$content);

  $content=\webdb\wiki_utils\wikitext_to_html__toc($content);

  $content=\webdb\wiki_utils\wikitext_to_html__subheadings($content);
  $content=\webdb\wiki_utils\wikitext_to_html__headings($content);

  $content=\webdb\wiki_utils\wikitext_to_html__file($content);
  $content=\webdb\wiki_utils\wikitext_to_html__internal_article_link($content);
  $content=\webdb\wiki_utils\wikitext_to_html__external_article_link($content);

  $pair=$escape_pairs["code"];
  $content=\webdb\wiki_utils\wikitext_unescape_pair($content,$pair[0],$pair[1]);
  $content=\webdb\wiki_utils\wikitext_to_html__code_block($content);

  $pair=$escape_pairs["nowiki"];
  $content=\webdb\wiki_utils\wikitext_unescape_pair($content,$pair[0],$pair[1]);
  $content=\webdb\wiki_utils\wikitext_to_html__nowiki_block($content);

  $pair=$escape_pairs["comment"];
  $content=\webdb\wiki_utils\wikitext_unescape_pair($content,$pair[0],$pair[1]);

  $content=\webdb\utils\string_template_fill($content);

  return $content;
}

#####################################################################################################

function wikitext_escape_pair($content,$open_tok,$close_tok)
{
  $parts=explode($open_tok,$content);
  for ($i=1;$i<count($parts);$i++)
  {
    $part=$parts[$i];
    $tokens=explode($close_tok,$part);
    if (count($tokens)<2)
    {
      continue;
    }
    $escape=array_shift($tokens);
    $parts[$i]=$open_tok.rawurlencode($escape).$close_tok.implode($close_tok,$tokens);
  }
  return implode("",$parts);
}

#####################################################################################################

function wikitext_unescape_pair($content,$open_tok,$close_tok)
{
  $parts=explode($open_tok,$content);
  for ($i=1;$i<count($parts);$i++)
  {
    $part=$parts[$i];
    $tokens=explode($close_tok,$part);
    if (count($tokens)<2)
    {
      continue;
    }
    $escape=array_shift($tokens);
    $parts[$i]=$open_tok.rawurldecode($escape).$close_tok.implode($close_tok,$tokens);
  }
  return implode("",$parts);
}

#####################################################################################################

function wikitext_to_html__nowiki_block($content)
{
  $parts=explode("<nowiki>",$content);
  for ($i=1;$i<count($parts);$i++)
  {
    $part=$parts[$i];
    $tokens=explode("</nowiki>",$part);
    if (count($tokens)<2)
    {
      continue;
    }
    $nowiki=array_shift($tokens);
    $parts[$i]=$nowiki.implode("</nowiki>",$tokens);
  }
  return implode("",$parts);
}

#####################################################################################################

function wikitext_to_html__toc($content)
{
  $toc_parts=explode("<toc>",$content);
  $toc_data=array();
  $levels=array("2"=>"===","1"=>"==");
  $caption="";
  $active_level="";
  for ($i=0;$i<strlen($content);$i++)
  {
    foreach ($levels as $level => $delim)
    {
      $test=substr($content,$i,strlen($delim));
      if ($test==$delim)
      {
        if ($active_level==$level)
        {
          $data=array();
          $data["caption"]=$caption;
          $data["level"]=$level;
          $toc_data[]=$data;
          $caption="";
          $level="";
        }
        $i+=strlen($delim)-1;
        $active_level=$level;
        continue;
      }
      if ($active_level==$level)
      {
        $caption.=$content[$i];
        continue;
      }
    }
  }
  $toc_html="";
  for ($i=0;$i<count($toc_data);$i++)
  {
    $data=$toc_data[$i];
    switch ($data["level"])
    {
      case "1":
        $toc_html.=\webdb\utils\template_fill("wiki/toc_heading",$data);
        break;
      case "2":
        $toc_html.=\webdb\utils\template_fill("wiki/toc_subheading",$data);
        break;
    }
  }
  $params=array();
  $params["list_rows"]= $toc_html;
  $toc_html=\webdb\utils\template_fill("wiki/toc",$params);
  return implode($toc_html,$toc_parts);
}

#####################################################################################################

function wikitext_to_html__headings($content)
{
  $parts=explode("==",$content);
  for ($i=1;$i<count($parts);$i++)
  {
    $heading=$parts[$i];
    $params=array();
    $params["heading"]=$heading;
    $heading=\webdb\utils\template_fill("wiki/heading",$params);
    $parts[$i]=$heading;
    $i++;
  }
  return implode("",$parts);
}

#####################################################################################################

function wikitext_to_html__subheadings($content)
{
  $parts=explode("===",$content);
  for ($i=1;$i<count($parts);$i++)
  {
    $subheading=$parts[$i];
    $params=array();
    $params["subheading"]=$subheading;
    $subheading=\webdb\utils\template_fill("wiki/subheading",$params);
    $parts[$i]=$subheading;
    $i++;
  }
  return implode("",$parts);
}

#####################################################################################################

function wikitext_to_html__code_block($content)
{
  $parts=explode("<code>",$content);
  for ($i=1;$i<count($parts);$i++)
  {
    $part=$parts[$i];
    $tokens=explode("</code>",$part);
    if (count($tokens)<2)
    {
      continue;
    }
    $code=array_shift($tokens);
    $firstchar=substr($code,0,1);
    switch ($firstchar)
    {
      case "\r\n":
        $code=substr($code,2);
        break;
      case "\r":
      case "\n":
        $code=substr($code,1);
        break;
    }
    $code_params=array();
    $code_params["code"]=$code;
    $code=\webdb\utils\template_fill("wiki/code_block",$code_params);
    $parts[$i]=$code.implode("</code>",$tokens);
  }
  return implode("",$parts);
}

#####################################################################################################

function wikitext_to_html__file($content)
{
  $parts=explode("[[File:",$content);
  for ($i=1;$i<count($parts);$i++)
  {
    $part=$parts[$i];
    $tokens=explode("]]",$part);
    if (count($tokens)<2)
    {
      continue;
    }
    $args=array_shift($tokens);
    $args=explode("|",$args);
    $params=array();
    $title=array_shift($args); # first arg is always title, remaining args can be in any order
    $params["title"]=$title;
    $params["url_title"]=urlencode($title);
    $params["width"]="";
    $params["height"]="";
    $params["align"]="";
    $params["handlers"]="";
    for ($j=0;$j<count($args);$j++)
    {
      $arg=$args[$j];
      $arg=explode(":",$arg);
      $arg_key=trim(array_shift($arg));
      $arg_val=trim(implode(":",$arg));
      $params[$arg_key]=$arg_val;
    }
    if ($params["width"]<>"")
    {
      $params["width"]="width: ".$params["width"].";";
    }
    if ($params["height"]<>"")
    {
      $params["height"]="height: ".$params["height"].";";
    }
    if ($params["align"]<>"")
    {
      $params["align"]="text-align: ".$params["align"].";";
    }
    $html=\webdb\utils\template_fill("wiki/file_not_found",$params);
    $file_record=\webdb\wiki_utils\get_file_record_by_title($title);
    if ($file_record!==false)
    {
      $file_data=\webdb\wiki_utils\get_file_data($file_record);
      $file_ext=$file_record["file_ext"];
      switch ($file_ext)
      {
        case "png":
        case "gif":
        case "jpg":
        case "jpeg":
          $params["type"]=$file_ext;
          $params["image_html"]=\webdb\wiki_utils\base64_image_encode($file_data,$params);
          $html=\webdb\utils\template_fill("wiki/image_view_link",$params);
          break;
        default:
          $params["content_html"]=\webdb\utils\template_fill("wiki/file_content",$params);
          $html=\webdb\utils\template_fill("wiki/file_content_view_link",$params);
      }
    }
    $parts[$i]=$html.implode("]]",$tokens);
  }
  return implode("",$parts);
}

#####################################################################################################

function wikitext_to_html__internal_article_link($content)
{
  $parts=explode("[[",$content);
  for ($i=1;$i<count($parts);$i++)
  {
    $part=$parts[$i];
    $tokens=explode("]]",$part);
    if (count($tokens)<2)
    {
      continue;
    }
    $token=array_shift($tokens);
    $link=explode("|",$token);
    $link_params=array();
    $link_params["url_title"]=array_shift($link);
    $link_params["caption"]=$link_params["url_title"];
    if (count($link)>0)
    {
      $link_params["caption"]=implode(" ",$link);
    }
    $link=\webdb\utils\template_fill("wiki/internal_article_link",$link_params);
    $parts[$i]=$link.implode("]]",$tokens);
  }
  return implode("",$parts);
}

#####################################################################################################

/*function wikitext_to_html__file($content)
{
  # todo: \webdb\graphics\scale_img($buffer,$scale,$w,$h);
  # see https://en.wikipedia.org/wiki/Help:Wikitext#Images
  $parts=explode("[[File:",$content);
  for ($i=1;$i<count($parts);$i++)
  {
    $part=$parts[$i];
    $tokens=explode("]]",$part);
    if (count($tokens)<2)
    {
      continue;
    }
    $token=array_shift($tokens);
    $file=explode("|",$token);
    $title=array_shift($file);
    $file_record=\webdb\wiki_utils\get_file_record_by_title($title);
    if ($file_record!==false)
    {

      #$buffer=imagecreatetruecolor($w,$h);
      #$bg_color=imagecolorallocate($buffer,255,0,255); # magenta
      #imagecolortransparent($buffer,$bg_color);
      #imagefill($buffer,0,0,$bg_color);
      #$line_color=imagecolorallocate($buffer,0,0,0); # black

    }
    else
    {
      # file record not found
    }
    $href=false;
    if (count($file)>0)
    {
      $link=implode(" ",$file);
      $link=explode("=",$link);
      $link_key=trim(strtolower(array_shift($link)));
      if ((count($link)>0) and ($link_key=="link"))
      {
        $href=implode("=",$link);
      }
    }
    $file_params=array();
    $file_params["img_data"]="";
    $file_params["img_data"]=\webdb\graphics\base64_image($buffer,"png");
    if ($href!==false)
    {
      $file_params["href"]=$href;
      $file=\webdb\utils\template_fill("wiki/file_link",$file_params);
    }
    else
    {
      $file=\webdb\utils\template_fill("wiki/file",$file_params);
    }
    $parts[$i]=$file.implode("]]",$tokens);
  }
  return implode("",$parts);
}*/

#####################################################################################################

function wikitext_to_html__external_article_link($content)
{
  $parts=explode("[",$content);
  for ($i=1;$i<count($parts);$i++)
  {
    $part=$parts[$i];
    $tokens=explode("]",$part);
    if (count($tokens)<2)
    {
      continue;
    }
    $token=array_shift($tokens);
    $link=explode(" ",$token);
    $link_params=array();
    $link_params["url"]=array_shift($link);
    $link_params["caption"]=$link_params["url"];
    if (count($link)>0)
    {
      $link_params["caption"]=implode(" ",$link);
    }
    $link=\webdb\utils\template_fill("wiki/external_article_link",$link_params);
    $parts[$i]=$link.implode("]",$tokens);
  }
  return implode("",$parts);
}

#####################################################################################################

function lock_article($article_record)
{
  global $settings;
  return;
  $lock=\webdb\utils\get_lock($settings["database_webdb"],"wiki_articles","article_id",$article_record["article_id"]);
  if ($lock!==false)
  {
    $msg_params=$lock;
    $msg_params["locked_time"]=date($settings["app_date_format"]." H:i:s",$lock["locked_time"]);
    $expiry=$lock["locked_time"]+$settings["row_lock_expiration"];
    $msg_params["expiry_minutes"]=round(($expiry-$lock["locked_time"])/60);
    $msg_params["expiry_minutes"]=round(($expiry-time())/60);
    $msg_params["expiry_time"]=date($settings["app_date_format"]." H:i:s",$expiry);
    \webdb\utils\info_message(\webdb\utils\template_fill("wiki/article_locked",$msg_params));
  }
}

#####################################################################################################

function base64_image_encode($image_data,$params)
{
  $params["data"]=base64_encode($image_data);
  return \webdb\utils\template_fill("wiki/base64_image",$params);
}

#####################################################################################################

function get_user_wiki_settings()
{
  global $settings;
  $user_record=\webdb\chat\chat_initialize();
  $data=array();
  if ($user_record["json_data"]<>"")
  {
    $data=json_decode($user_record["json_data"],true);
    if (isset($data["wiki"])==true)
    {
      return $data["wiki"];
    }
  }
  $data["wiki"]=array();
  $data["wiki"]["home_article"]=$settings["wiki_home_article"];
  $user_record["json_data"]=json_encode($data);
  \webdb\chat\update_user($user_record);
  return $data["wiki"];
}

#####################################################################################################

function get_target_filename($file_id,$file_ext)
{
  global $settings;
  $file_path=\webdb\utils\get_upload_path().$settings["wiki_file_subdirectory"].DIRECTORY_SEPARATOR;
  return $file_path."file_".$file_id.".".$file_ext;
}

#####################################################################################################

function get_oldversion_filename($file_revision_id,$file_ext)
{
  global $settings;
  $file_path=\webdb\utils\get_upload_path().$settings["wiki_file_subdirectory"].DIRECTORY_SEPARATOR;
  return $file_path."file_revision_".$file_revision_id.".".$file_ext;
}

#####################################################################################################

function get_file_data($file_record)
{
  global $settings;
  $file_ext=$file_record["file_ext"]; # excludes period
  $file_id=$file_record["file_id"];
  $target_filename=\webdb\wiki_utils\get_target_filename($file_id,$file_ext);
  switch ($settings["file_upload_mode"])
  {
    case "rename":
      return file_get_contents($target_filename);
    case "ftp":
      $connection=\webdb\utils\webdb_ftp_login();
      $size=ftp_size($connection,$target_filename);
      if ($size==-1)
      {
        \webdb\utils\error_message("error: file not found: ".$target_filename);
      }
      $stream=fopen("php://temp","r+");
      if (ftp_fget($connection,$stream,$target_filename,FTP_BINARY)==false)
      {
        \webdb\utils\error_message("error: unable to get file: ".$target_filename);
      }
      $fstats=fstat($stream);
      rewind($stream);
      $file_data=fread($stream,$fstats["size"]);
      fclose($stream);
      ftp_close($connection);
      return $file_data;
  }
  \webdb\utils\error_message("error: invalid file upload mode");
}

#####################################################################################################

function wiki_rename_file($target_filename,$oldversion_filename)
{
  global $settings;
  switch ($settings["file_upload_mode"])
  {
    case "rename":
      rename($target_filename,$oldversion_filename);
      return;
    case "ftp":
      $connection=\webdb\utils\webdb_ftp_login();
      if (ftp_rename($connection,$target_filename,$oldversion_filename)==false)
      {
        \webdb\utils\error_message("error: unable to rename file on FTP server");
      }
      ftp_close($connection);
      return;
  }
  \webdb\utils\error_message("error: invalid file upload mode");
}

#####################################################################################################

function wiki_upload_file($submit_name,$target_filename)
{
  global $settings;
  if (isset($_FILES[$submit_name])==false)
  {
    return;
  }
  $upload_data=$_FILES[$submit_name];
  $upload_filename=$upload_data["tmp_name"];
  if (file_exists($upload_filename)==false)
  {
    \webdb\utils\error_message("error: uploaded file not found");
  }
  switch ($settings["file_upload_mode"])
  {
    case "rename":
      rename($upload_filename,$target_filename);
      return;
    case "ftp":
      $connection=\webdb\utils\webdb_ftp_login();
      if (ftp_put($connection,$target_filename,$upload_filename,FTP_BINARY)==false)
      {
        \webdb\utils\error_message("error: unable to upload file to FTP server");
      }
      ftp_close($connection);
      return;
  }
  \webdb\utils\error_message("error: invalid file upload mode");
}

#####################################################################################################
