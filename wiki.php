<?php

namespace webdb\wiki;

#####################################################################################################

function wiki_page_stub($form_config)
{
  global $settings;
  $user_wiki_settings=\webdb\wiki_utils\get_user_wiki_settings();
  if (isset($_GET["search"])==true)
  {
    \webdb\wiki\search_results($form_config,$_GET["search"]);
  }
  $content_type="article";
  if (isset($_GET["file"])==true)
  {
    $title=$_GET["file"];
    $file_record=\webdb\wiki_utils\get_file_record_by_title($title);
    if (isset($_GET["cmd"])==true)
    {
      if ($_GET["cmd"]=="output")
      {
        \webdb\wiki\output_file($form_config,$file_record,$title);
      }
      if ($_GET["cmd"]=="history")
      {
        \webdb\wiki\file_history($form_config,$file_record);
      }
      if ($_GET["cmd"]=="edit")
      {
        if (isset($_POST["wiki_file_edit_confirm"])==true)
        {
          \webdb\wiki\confirm_file_edit($form_config,$title,$file_record);
        }
        else
        {
          if ($file_record===false)
          {
            \webdb\wiki\edit_new_file($form_config,$title);
          }
          else
          {
            \webdb\wiki\edit_exist_file($form_config,$file_record);
          }
        }
      }
    }
    if ($file_record===false)
    {
      \webdb\wiki\edit_new_file($form_config,$title);
    }
    \webdb\wiki\view_exist_file($form_config,$file_record);
  }
  $title=$user_wiki_settings["home_article"];
  if (isset($_GET["article"])==true)
  {
    $title=$_GET["article"];
  }
  \webdb\wiki_utils\view_fixed_article_file($form_config,$title);
  $article_record=\webdb\wiki_utils\get_article_record_by_title($title);
  if (isset($_GET["cmd"])==true)
  {
    if ($_GET["cmd"]=="history")
    {
      \webdb\wiki\article_history($form_config,$article_record);
    }
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

function search_results($form_config,$query)
{
  global $settings;
  $page_params=array();
  $page_params["query"]=htmlspecialchars($query);
  $page_params["content"]="todo";
  $page_params["wiki_styles_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki.css");
  $page_params["wiki_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki_print.css");
  $content=\webdb\utils\template_fill("wiki/search_results",$page_params);
  \webdb\utils\output_page($content,$form_config["title"]." [search: \"".htmlspecialchars($query)."\"]");
}

#####################################################################################################

function article_history($form_config,$article_record)
{
  global $settings;
  if (isset($_GET["rev"])==true)
  {
    $page_params=\webdb\sql\lookup("wiki_article_oldversions",$settings["database_webdb"],"article_revision_id",$_GET["rev"]);
    $page_params["content"]=\webdb\wiki_utils\wikitext_to_html($page_params["content"]);
  }
  else
  {
    $page_params=$article_record;
    $article_user=\webdb\sql\lookup("users",$settings["database_webdb"],"user_id",$article_record["user_id"]);
    $page_params["fullname"]=$article_user["fullname"];
    $records=\webdb\wiki_utils\get_article_history($article_record["article_id"]);
    $rows="";
    for ($i=0;$i<count($records);$i++)
    {
      $record=$records[$i];
      $record["url_title"]=urlencode($article_record["title"]);
      $rows.=\webdb\utils\template_fill("wiki/article_history_row",$record);
    }
    $page_params["rows"]=$rows;
    $page_params["content"]=\webdb\utils\template_fill("wiki/article_history_table",$page_params);
  }
  $page_params["wiki_styles_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki.css");
  $page_params["wiki_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki_print.css");
  $page_params["url_title"]=urlencode($page_params["title"]);
  $content=\webdb\utils\template_fill("wiki/article_history",$page_params);
  \webdb\utils\output_page($content,$form_config["title"].": ".$article_record["title"]." [history]");
}

#####################################################################################################

function file_history($form_config,$file_record)
{
  die("todo");
}

#####################################################################################################

function output_file($form_config,$file_record,$title)
{
  global $settings;
  $settings["ignore_ob_postprocess"]=true;
  ob_end_clean(); # discard buffer & disable output buffering (\webdb\utils\ob_postprocess function is still called)
  $file_data=\webdb\wiki_utils\get_file_data($file_record);
  $file_ext=$file_record["file_ext"]; # excludes period
  $out_filename=\webdb\utils\strip_text($file_record["title"],"_").".".$file_ext;
  header("Cache-Control: no-cache");
  header("Expires: -1");
  header("Pragma: no-cache");
  header("Accept-Ranges: bytes");
  header("Content-Type: ".$settings["permitted_upload_types"][$file_ext]);
  header("Content-Disposition: inline; filename=\"".$out_filename."\"");
  echo $file_data;
  die;
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
  $url.="?page=".$form_config["page_id"]."&article=".urlencode($value_items["title"]);
  \webdb\utils\redirect($url);
}

#####################################################################################################

function edit_new_article($form_config,$title)
{
  $page_params=array();
  $page_params["wiki_styles_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki.css");
  $page_params["wiki_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki_print.css");
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
  $page_params["wiki_styles_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki.css");
  $page_params["wiki_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki_print.css");
  $page_params["description"]="";
  $page_params["url_title"]=urlencode($article_record["title"]);
  $page_params["submit_caption"]="Update Article";
  $content=\webdb\utils\template_fill("wiki/article_edit",$page_params);
  \webdb\utils\output_page($content,$form_config["title"].": ".$article_record["title"]." [edit]");
}

#####################################################################################################

function view_exist_article($form_config,$article_record)
{
  $page_params=$article_record;
  $page_params["wiki_styles_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki.css");
  $page_params["wiki_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki_print.css");
  $page_params["url_title"]=urlencode($article_record["title"]);
  $page_params["content"]=\webdb\wiki_utils\wikitext_to_html($article_record["content"]);
  $content=\webdb\utils\template_fill("wiki/article_view",$page_params);
  \webdb\utils\output_page($content,$form_config["title"].": ".$article_record["title"]);
}

#####################################################################################################

function confirm_file_edit($form_config,$title,$file_record)
{
  global $settings;
  $value_items=array();
  $value_items["title"]=trim($_POST["wiki_file_edit_title"]);
  $upload_data=$_FILES["wiki_file_upload"];
  $upload_filename=$upload_data["name"];
  $file_ext=strtolower(pathinfo($upload_filename,PATHINFO_EXTENSION)); # excludes period
  if (isset($settings["permitted_upload_types"][$file_ext])==false)
  {
    \webdb\utils\error_message("error: file type not permitted");
  }
  $value_items["notes"]=$_POST["wiki_file_edit_notes"];
  $value_items["description"]=$_POST["wiki_file_edit_description"];
  $value_items["user_id"]=$settings["logged_in_user_id"];
  $value_items["file_ext"]=$file_ext;
  if ($file_record===false)
  {
    \webdb\sql\sql_insert($value_items,"wiki_files",$settings["database_webdb"]);
    $file_id=\webdb\sql\sql_last_insert_autoinc_id();
    $target_filename=\webdb\wiki_utils\get_target_filename($file_id,$file_ext);
    \webdb\wiki_utils\wiki_upload_file("wiki_file_upload",$target_filename);
  }
  else
  {
    $oldversion_values=array();
    $oldversion_values["file_id"]=$file_record["file_id"];
    $oldversion_values["title"]=$file_record["title"];
    $oldversion_values["user_id"]=$file_record["user_id"];
    $oldversion_values["notes"]=$file_record["notes"];
    $oldversion_values["description"]=$file_record["description"];
    $oldversion_values["file_ext"]=$file_record["file_ext"];
    $where_items=array("file_id"=>$file_record["file_id"]);
    \webdb\sql\sql_update($value_items,$where_items,"wiki_files",$settings["database_webdb"]);
    \webdb\sql\sql_insert($oldversion_values,"wiki_file_oldversions",$settings["database_webdb"]);
    $old_file_ext=$file_record["file_ext"]; # excludes period
    $file_id=$file_record["file_id"];
    $target_filename=\webdb\wiki_utils\get_target_filename($file_id,$old_file_ext);
    $file_revision_id=\webdb\sql\sql_last_insert_autoinc_id();
    $oldversion_filename=\webdb\wiki_utils\get_oldversion_filename($file_revision_id,$old_file_ext);
    \webdb\wiki_utils\wiki_rename_file($target_filename,$oldversion_filename);
    $target_filename=\webdb\wiki_utils\get_target_filename($file_id,$file_ext);
    \webdb\wiki_utils\wiki_upload_file("wiki_file_upload",$target_filename);
  }
  $url=\webdb\utils\get_base_url();
  $url.="?page=".$form_config["page_id"]."&file=".urlencode($value_items["title"]);
  \webdb\utils\redirect($url);
}

#####################################################################################################

function edit_new_file($form_config,$title)
{
  $page_params=array();
  $page_params["wiki_styles_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki.css");
  $page_params["wiki_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki_print.css");
  $page_params["url_title"]=urlencode($title);
  $page_params["title"]=$title;
  $page_params["content"]="";
  $page_params["notes"]="";
  $page_params["description"]="Initial file upload.";
  $page_params["submit_caption"]="Create File";
  $content=\webdb\utils\template_fill("wiki/file_edit",$page_params);
  \webdb\utils\output_page($content,$form_config["title"].": ".$title." [edit]");
}

#####################################################################################################

function edit_exist_file($form_config,$file_record)
{
  $page_params=$file_record;
  $page_params["wiki_styles_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki.css");
  $page_params["wiki_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki_print.css");
  $page_params["description"]="";
  $page_params["url_title"]=urlencode($file_record["title"]);
  $page_params["submit_caption"]="Update File";
  $params=array();
  $params["title"]=$file_record["title"];
  $params["url_title"]=$page_params["url_title"];
  $params["width"]="";
  $params["height"]="";
  $params["align"]="";
  $params["handlers"]=\webdb\utils\template_fill("wiki/file_content_handlers");
  $page_params["content"]=\webdb\utils\template_fill("wiki/file_content",$params);
  $content=\webdb\utils\template_fill("wiki/file_edit",$page_params);
  \webdb\utils\output_page($content,$form_config["title"].": ".$file_record["title"]." [edit]");
}

#####################################################################################################

function view_exist_file($form_config,$file_record)
{
  $page_params=$file_record;
  $page_params["wiki_styles_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki.css");
  $page_params["wiki_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("wiki/wiki_print.css");
  $page_params["url_title"]=urlencode($file_record["title"]);
  $page_params["notes"]=$file_record["notes"];
  $params=array();
  $params["title"]=$file_record["title"];
  $params["url_title"]=$page_params["url_title"];
  $params["width"]="";
  $params["height"]="";
  $params["align"]="";
  $params["handlers"]=\webdb\utils\template_fill("wiki/file_content_handlers");
  $page_params["content"]=\webdb\utils\template_fill("wiki/file_content",$params);
  $content=\webdb\utils\template_fill("wiki/file_view",$page_params);
  \webdb\utils\output_page($content,$form_config["title"].": ".$file_record["title"]);
}

#####################################################################################################
