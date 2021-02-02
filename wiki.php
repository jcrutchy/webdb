<?php

namespace webdb\wiki;

#####################################################################################################

function wiki_page_stub($form_config)
{
  global $settings;
  $user_wiki_settings=\webdb\wiki_utils\get_user_wiki_settings();
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
      if ($_GET["cmd"]=="edit")
      {
        if (isset($_POST["wiki_file_edit_confirm"])==true)
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

function output_file($form_config,$file_record,$title)
{
  global $settings;
  $settings["ignore_ob_postprocess"]=true;
  ob_end_clean(); # discard buffer & disable output buffering (\webdb\utils\ob_postprocess function is still called)
  header("Cache-Control: no-cache");
  header("Expires: -1");
  header("Pragma: no-cache");
  header("Accept-Ranges: bytes");
  header("Content-Type: ".$settings["permitted_upload_types"][strtolower($ext)]);
  header("Content-Disposition: inline; filename=\"".$record_filename."\"");
  switch ($settings["file_upload_mode"])
  {
    case "rename":
      header("Content-Length: ".filesize($target_filename));
      readfile($target_filename);
      die;
    case "ftp":
      $connection=\webdb\utils\webdb_ftp_login();
      $size=ftp_size($connection,$target_filename);
      header("Content-Length: ".$size);
      ftp_get($connection,"php://output",$target_filename,FTP_BINARY);
      ftp_close($connection);
      die;
  }
  \webdb\utils\error_message("error: invalid file upload mode");
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

  $path=\webdb\utils\get_upload_path().$settings["wiki_file_subdirectory"].DIRECTORY_SEPARATOR;

  $upload_data=$_FILES["wiki_file_upload"];
  $upload_filename=$upload_data["name"];

  $ext=strtolower(pathinfo($upload_filename,PATHINFO_EXTENSION)); # excludes period
  if (isset($settings["permitted_upload_types"][$ext])==false)
  {
    \webdb\utils\error_message("error: file type not permitted");
  }
  /*
  [file_id] INT CHECK ([file_id] > 0) NOT NULL IDENTITY,
  [created_timestamp] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
  [title] VARCHAR(255) DEFAULT NULL,
  [notes] VARCHAR(max) DEFAULT NULL,
  [user_id] INT CHECK ([user_id] > 0) NOT NULL,
  [description] VARCHAR(max) DEFAULT NULL,
  [file_ext] VARCHAR(255) DEFAULT NULL,
  */
  $value_items["notes"]=$_POST["wiki_file_edit_notes"];
  $value_items["description"]=$_POST["wiki_file_edit_description"];
  $value_items["user_id"]=$settings["logged_in_user_id"];
  $value_items["file_ext"]=$ext;
  if ($file_record===false)
  {
    \webdb\sql\sql_insert($value_items,"wiki_files",$settings["database_webdb"]);
    $id=\webdb\sql\sql_last_insert_autoinc_id();

    $target_filename=$path."file_".$id.".".$ext;
    \webdb\wiki_utils\upload_file("wiki_file_upload",$target_filename);

  }
  else
  {
    /*
    [file_revision_id] INT CHECK ([file_revision_id] > 0) NOT NULL IDENTITY,
    [created_timestamp] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
    [title] VARCHAR(255) DEFAULT NULL,
    [notes] VARCHAR(max) DEFAULT NULL,
    [file_id] INT CHECK ([file_id] > 0) NOT NULL,
    [user_id] INT CHECK ([user_id] > 0) NOT NULL,
    [description] VARCHAR(max) DEFAULT NULL,
    [file_ext] VARCHAR(255) DEFAULT NULL,
    */
    $oldversion_values=array();
    $oldversion_values["file_id"]=$file_record["file_id"];
    $oldversion_values["title"]=$file_record["title"];
    $oldversion_values["content"]=$file_record["content"];
    $oldversion_values["user_id"]=$file_record["user_id"];
    $oldversion_values["notes"]=$file_record["notes"];
    $oldversion_values["description"]=$file_record["description"];
    \webdb\sql\sql_insert($oldversion_values,"wiki_file_oldversions",$settings["database_webdb"]);
    $where_items=array("title"=>$title);
    \webdb\sql\sql_update($value_items,$where_items,"wiki_files",$settings["database_webdb"]);
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
  $page_params["description"]="Initial file creation.";
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

  $content_params=array();
  $content_params["notes"]=$file_record["notes"];
  $page_params["content"]=\webdb\utils\template_fill("wiki/file_content",$content_params);

  $content=\webdb\utils\template_fill("wiki/file_view",$page_params);
  \webdb\utils\output_page($content,$form_config["title"].": ".$file_record["title"]);
}

#####################################################################################################
