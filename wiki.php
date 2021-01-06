<?php

namespace webdb\wiki;

#####################################################################################################

function wiki_page_stub($form_config)
{
  global $settings;
  $user_wiki_settings=\webdb\wiki\get_user_wiki_settings();
  $title=$user_wiki_settings["home_article"];
  if (isset($_GET["article"])==true)
  {
    $title=$_GET["article"];
  }
  $article_record=\webdb\wiki\get_article_record_by_title($title);
  if (isset($_GET["cmd"])==true)
  {
    if ($_GET["cmd"]=="edit")
    {
      if (isset($_POST["wiki_article_edit_confirm"])==true)
      {
        \webdb\wiki\confirm_article_edit($form_config,$title,$article_record);
      }
      else
      {
        if ($article_record===false)
        {
          \webdb\wiki\edit_new_article($form_config,$title);
        }
        else
        {
          \webdb\wiki\edit_exist_article($form_config,$article_record);
        }
      }
    }
  }
  if ($article_record===false)
  {
    \webdb\wiki\edit_new_article($form_config,$title);
  }
  \webdb\wiki\view_exist_article($form_config,$article_record);
}

#####################################################################################################

function confirm_article_edit($form_config,$title,$article_record)
{
  global $settings;
  $value_items=array();
  $value_items["title"]=trim($_POST["wiki_article_edit_title"]);
  $value_items["content"]=$_POST["wiki_article_edit_content"];
  $value_items["description"]=$_POST["wiki_article_edit_description"];
  $value_items["user_id"]=$settings["logged_in_user_id"];
  if ($article_record===false)
  {
    \webdb\sql\sql_insert($value_items,"wiki_articles",$settings["database_webdb"]);
  }
  else
  {
    $oldversion_values=array();
    $oldversion_values["article_id"]=$article_record["article_id"];
    $oldversion_values["title"]=$article_record["title"];
    $oldversion_values["content"]=$article_record["content"];
    $oldversion_values["user_id"]=$article_record["user_id"];
    $oldversion_values["description"]=$article_record["description"];
    \webdb\sql\sql_insert($oldversion_values,"wiki_article_oldversions",$settings["database_webdb"]);
    $where_items=array("title"=>$title);
    \webdb\sql\sql_update($value_items,$where_items,"wiki_articles",$settings["database_webdb"]);
  }
  $url=\webdb\utils\get_base_url();
  $url.="?page=wiki&article=".urlencode($value_items["title"]);
  \webdb\utils\redirect($url);
}

#####################################################################################################

function edit_new_article($form_config,$title)
{
  $page_params=array();
  $page_params["url_title"]=urlencode($title);
  $page_params["title"]=$title;
  $page_params["content"]="";
  $page_params["description"]="Initial article creation.";
  $page_params["submit_caption"]="Create Article";
  $content=\webdb\utils\template_fill("wiki/article_edit",$page_params);
  \webdb\utils\output_page($content,$form_config["title"].": ".$title." [edit]");
}

#####################################################################################################

function edit_exist_article($form_config,$article_record)
{
  $page_params=$article_record;
  $page_params["description"]="";
  $page_params["url_title"]=urlencode($article_record["title"]);
  $page_params["submit_caption"]="Update Article";
  $content=\webdb\utils\template_fill("wiki/article_edit",$page_params);
  \webdb\utils\output_page($content,$form_config["title"].": ".$article_record["title"]." [edit]");
}

#####################################################################################################

function view_exist_article($form_config,$article_record)
{
  $article_record["url_title"]=urlencode($article_record["title"]);
  $article_record["content"]=\webdb\wiki\wikitext_to_html($article_record["content"]);
  $content=\webdb\utils\template_fill("wiki/article_view",$article_record);
  \webdb\utils\output_page($content,$form_config["title"].": ".$article_record["title"]);
}

#####################################################################################################

function wikitext_to_html($content)
{
  $break=\webdb\utils\template_fill("break");
  $content=str_replace(PHP_EOL,$break,$content);
  $content=str_replace("\r",$break,$content);
  $content=str_replace("\n",$break,$content);
  $content=\webdb\wiki\wikitext_to_html__internal_article_link($content);
  $content=\webdb\wiki\wikitext_to_html__external_article_link($content);
  return $content;
}

#####################################################################################################

function wikitext_to_html__internal_article_link($content)
{
  $parts=explode("[[",$content);
  for ($i=1;$i<count($parts);$i++)
  {
    $part=$parts[$i];
    $tokens=explode("]]",$part);
    if (count($tokens)<>2)
    {
      continue;
    }
    $link=explode("|",$tokens[0]);
    $link_params=array();
    $link_params["url_title"]=urlencode($link[0]);
    $link_params["caption"]=$link[0];
    if (count($link)==2)
    {
      $link_params["caption"]=$link[1];
    }
    $link=\webdb\utils\template_fill("wiki/internal_article_link",$link_params);
    $parts[$i]=$link.$tokens[1];
  }
  return implode("",$parts);
}

#####################################################################################################

function wikitext_to_html__external_article_link($content)
{
  $parts=explode("[",$content);
  for ($i=1;$i<count($parts);$i++)
  {
    $part=$parts[$i];
    $tokens=explode("]",$part);
    if (count($tokens)<>2)
    {
      continue;
    }
    $link=explode(" ",$tokens[0]);
    $link_params=array();
    $link_params["url"]=$link[0];
    $link_params["caption"]=$link[0];
    if (count($link)==2)
    {
      $link_params["caption"]=$link[1];
    }
    $link=\webdb\utils\template_fill("wiki/external_article_link",$link_params);
    $parts[$i]=$link.$tokens[1];
  }
  return implode("",$parts);
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
