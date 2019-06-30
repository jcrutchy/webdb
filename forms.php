<?php

namespace webdb\forms;

#####################################################################################################

function form_template_fill($name,$params=false)
{
  return \webdb\utils\template_fill("forms".DIRECTORY_SEPARATOR.$name,$params);
}

#####################################################################################################

function load_form_defs()
{
  global $settings;
  $file_list=scandir($settings["app_forms_path"]);
  for ($i=0;$i<count($file_list);$i++)
  {
    $fn=$file_list[$i];
    if (($fn==".") or ($fn==".."))
    {
      continue;
    }
    $full=$settings["app_forms_path"].$fn;
    $data=trim(file_get_contents($full));
    $info=pathinfo($fn);
    switch ($info["extension"])
    {
      case "list":
        $data=json_decode($data,true);
        $data["url_page"]=$info["filename"];
        $data["form_type"]="list";
        $data["header_rows_template"]=$info["filename"].DIRECTORY_SEPARATOR."list_header_rows";
        $data["select_sql_file"]=$info["filename"]."_list";
        $data["individual_delete"]=true;
        $data["individual_edit"]=true;
        $data["insert_new"]=true;
        $cols=array();
        foreach ($data["control_types"] as $field_name => $control_type)
        {
          $cols[]=\webdb\forms\generate_standard_list_form_column($field_name,$control_type);
        }
        $data["data_columns"]=$cols;
        $settings["forms"][$data["url_page"]]=$data;
        break;
    }
  }
}

#####################################################################################################

function generate_standard_list_form_column($field_name,$control_type)
{
  $col=array();
  $ctl=array();
  $ctl["db_field_name"]=$field_name;
  $ctl["html_id"]=$ctl["db_field_name"];
  $ctl["type"]=$control_type;
  switch ($control_type)
  {
    case "text":
      $ctl["html_name"]=$ctl["db_field_name"];
      break;
    case "span":
      break;
    case "checkbox":
      $ctl["html_name"]=$ctl["db_field_name"];
      break;
    default:
      return false;
  }
  $col[]=$ctl;
  return $col;
}

#####################################################################################################

function output_list_form($form_name)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  $form_params=array();
  $form_params["header_rows_template"]=\webdb\utils\template_fill($form_config["header_rows_template"]);
  $form_params["form_styles_modified"]=\webdb\utils\webdb_resource_modified_timestamp("list.css");
  $form_params["url_page"]=$form_config["url_page"];
  $form_params["rows"]="";
  $records=\webdb\sql\file_fetch_query($form_config["select_sql_file"]);
  for ($i=0;$i<count($records);$i++)
  {
    $record=$records[$i];
    $row_params=array();
    $row_params["cols"]="";
    $row_params["id"]=$record[$form_config["db_id_field_name"]];
    for ($j=0;$j<count($form_config["data_columns"]);$j++)
    {
      $column_config=$form_config["data_columns"][$j];
      $col_params=array();
      $col_params["content"]="";
      for ($k=0;$k<count($column_config);$k++)
      {
        $field_config=$column_config[$k];
        $field_params=array();
        $field_name=$field_config["db_field_name"];
        $field_params["value"]=htmlspecialchars($record[$field_name]);
        $col_params["content"].=\webdb\forms\form_template_fill("list_field",$field_params);
      }
      $row_params["cols"].=\webdb\forms\form_template_fill("list_col",$col_params);
      $row_params["check"]="";
      if ($form_config["multi_row_delete"]==true)
      {
        $row_params["check"]=\webdb\forms\form_template_fill("list_check",$row_params);
      }
      $row_params["controls"]="";
      if ($form_config["individual_edit"]==true)
      {
        $row_params["controls"]=\webdb\forms\form_template_fill("list_row_edit",$row_params);
      }
      if ($form_config["individual_delete"]==true)
      {
        $row_params["controls"].=\webdb\forms\form_template_fill("list_row_del",$row_params);
      }
    }
    $form_params["rows"].=\webdb\forms\form_template_fill("list_row",$row_params);
  }
  $form_params["insert_control"]="";
  if ($form_config["insert_new"]==true)
  {
    $form_params["insert_control"]=\webdb\forms\form_template_fill("list_insert");
  }
  $form_params["delete_selected_control"]="";
  if ($form_config["multi_row_delete"]==true)
  {
    $form_params["delete_selected_control"]=\webdb\forms\form_template_fill("list_del_selected");
  }
  $content=\webdb\forms\form_template_fill("list",$form_params);
  \webdb\utils\output_page($content,$form_name);
}

#####################################################################################################

function insert_form($form_name)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  $record=$form_config["default_values"];
  \webdb\forms\output_editor($form_name,$record,"new","Insert");
}

#####################################################################################################

function edit_form($form_name,$id)
{
  $record=get_record_by_id($form_name,$id);
  \webdb\forms\output_editor($form_name,$record,"edit","Update",$id);
}

#####################################################################################################

function output_editor($form_name,$record,$command,$verb,$id=0)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  $rows="";
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    $field_value=$record[$field_name];
    $field_params=array();
    $field_params["field_name"]=$field_name;
    switch ($control_type)
    {
      case "span":
        break;
      case "text":
        break;
      case "checkbox":
        $field_params["checked"]="";
        if ($field_value==1)
        {
          $field_params["checked"]=\webdb\utils\template_fill("checkbox_checked");
        }
        break;
      default:
        \webdb\utils\show_message("error: invalid control type '".$control_type."' for field '".$field_name."' on form '".$form_name."'");
    }
    $field_params["field_value"]=htmlspecialchars($field_value);
    $row_params=array();
    $row_params["field_name"]=$field_name;
    $row_params["field_value"]=\webdb\forms\form_template_fill("field_edit_".$control_type,$field_params);
    $rows.=\webdb\forms\form_template_fill("field_row",$row_params);
  }
  $form_params=array();
  $form_params["rows"]=$rows;
  $form_params["url_page"]=$form_config["url_page"];
  $form_params["id"]=$id;
  $form_params["command_caption_noun"]=$form_config["command_caption_noun"];
  $form_params["confirm_caption"]=$verb." ".$form_config["command_caption_noun"];
  $content=\webdb\forms\form_template_fill($command,$form_params);
  $title=$form_name.": ".$command;
  \webdb\utils\output_page($content,$title);
}

#####################################################################################################

function insert_record($form_name)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  # TODO
  die("insert_record");
}

#####################################################################################################

function update_record($form_name,$id)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  # TODO
  die("update_record");
}

#####################################################################################################

function get_record_by_id($form_name,$id)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  $sql_params=array();
  $sql_params["id"]=$id;
  $sql_file=$form_name."_get_by_id";
  $records=\webdb\sql\file_fetch_prepare($sql_file,$sql_params);
  if (count($records)<>1)
  {
    \webdb\utils\show_message("error: id '".$id."' is not unique for query '".$sql_file."'");
  }
  return $records[0];
}

#####################################################################################################

function delete_confirmation($form_name,$id)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  $record=get_record_by_id($form_name,$id);
  $rows="";
  foreach ($record as $field_name => $field_value)
  {
    $field_params=array();
    $field_params["field_name"]=$field_name;
    $field_params["field_value"]=htmlspecialchars($field_value);
    $rows.=\webdb\forms\form_template_fill("field_row",$field_params);
  }
  $form_params=array();
  $form_params["rows"]=$rows;
  $form_params["url_page"]=$form_config["url_page"];
  $form_params["id"]=$id;
  $content=\webdb\forms\form_template_fill("delete_confirm",$form_params);
  $title=$form_name.": confirm deletion";
  \webdb\utils\output_page($content,$title);
}

#####################################################################################################

function cancel($form_name)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  $url_params=array();
  $url_params["url_page"]=$form_config["url_page"];
  $url=\webdb\forms\form_template_fill("page_url",$url_params);
  \webdb\utils\redirect($url);
}

#####################################################################################################

function delete_record($form_name,$id)
{
  $sql_params=array();
  $sql_params["id"]=$id;
  $sql_file=$form_name."_delete_by_id";
  \webdb\sql\file_execute_prepare($sql_file,$sql_params);
  \webdb\forms\cancel($form_name);
}

#####################################################################################################

function delete_selected_confirmation($form_name)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  # TODO
  die("delete_selected");
}

#####################################################################################################
