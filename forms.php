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
        $data["individual_delete"]=true;
        $data["individual_edit"]=true;
        $data["insert_new"]=true;
        $settings["forms"][$data["url_page"]]=$data;
        break;
    }
  }
}

#####################################################################################################

function output_list_form($form_name)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  $form_params=array();
  $form_params["form_styles_modified"]=\webdb\utils\webdb_resource_modified_timestamp("list.css");
  $form_params["url_page"]=$form_config["url_page"];
  $field_headers="";
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    $header_params=array();
    $header_params["field_name"]=$field_name;
    $field_headers.=\webdb\forms\form_template_fill("list_field_header",$header_params);
  }
  $form_params["check_head"]="";
  if ($form_config["multi_row_delete"]==true)
  {
    $form_params["check_head"]=\webdb\forms\form_template_fill("list_check_head");
  }
  $controls_count=0;
  if ($form_config["individual_edit"]==true)
  {
    $controls_count++;
  }
  if ($form_config["individual_delete"]==true)
  {
    $controls_count++;
  }
  $form_params["controls_count"]=$controls_count;
  $form_params["field_headers"]=$field_headers;
  $rows="";
  $sql=\webdb\utils\sql_fill("form_list_fetch_all",$form_config);
  $records=\webdb\sql\fetch_query($sql);
  for ($i=0;$i<count($records);$i++)
  {
    $record=$records[$i];
    $row_params=array();
    $row_params["id"]=$record[$form_config["db_id_field_name"]];
    $fields="";
    foreach ($form_config["control_types"] as $field_name => $control_type)
    {
      $field_params=array();
      $field_params["value"]=htmlspecialchars($record[$field_name]);
      $fields.=\webdb\forms\form_template_fill("list_field",$field_params);
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
    $row_params["fields"]=$fields;
    $rows.=\webdb\forms\form_template_fill("list_row",$row_params);
  }
  $form_params["rows"]=$rows;
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
  $record=\webdb\forms\get_record_by_id($form_name,$id);
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

function process_form_data_fields($form_name)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  $value_items=array();
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    switch ($control_type)
    {
      case "text":
        $value_items[$field_name]=$_POST[$field_name];
        break;
      case "checkbox":
        if (isset($_POST[$field_name])==true)
        {
          $value_items[$field_name]=1;
        }
        else
        {
          $value_items[$field_name]=0;
        }
        break;
    }
  }
  return $value_items;
}

#####################################################################################################

function insert_record($form_name)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  $value_items=\webdb\forms\process_form_data_fields($form_name);
  \webdb\sql\sql_insert($value_items,$form_config["table"],$form_config["database"]);
  \webdb\forms\cancel($form_name);
}

#####################################################################################################

function update_record($form_name,$id)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  $value_items=\webdb\forms\process_form_data_fields($form_name);
  $where_items=array();
  $where_items[$form_config["db_id_field_name"]]=$id;
  \webdb\sql\sql_update($value_items,$where_items,$form_config["table"],$form_config["database"]);
  \webdb\forms\cancel($form_name);
}

#####################################################################################################

function get_record_by_id($form_name,$id)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  $sql_params=array();
  $sql_params["id"]=$id;
  $sql=\webdb\utils\sql_fill("form_list_fetch_by_id",$form_config);
  $records=\webdb\sql\fetch_prepare($sql,$sql_params);
  if (count($records)<>1)
  {
    \webdb\utils\show_message("error: id '".$id."' is not unique for query: ".$sql);
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
  global $settings;
  $form_config=$settings["forms"][$form_name];
  $sql_params=array();
  $sql_params[$form_config["db_id_field_name"]]=$id;
  \webdb\sql\sql_delete($sql_params,$form_config["table"],$form_config["database"]);
  \webdb\forms\cancel($form_name);
}

#####################################################################################################

function delete_selected_confirmation($form_name)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  if (isset($_POST["list_select"])==false)
  {
    $message_params["message"]="No records selected.";
    $message_params["url_page"]=$form_config["url_page"];
    \webdb\utils\show_message(\webdb\forms\form_template_fill("page_message",$message_params));
  }
  $rows="";
  foreach ($_POST["list_select"] as $id => $value)
  {
    $row_params=array();
    $record=get_record_by_id($form_name,$id);
    $fields="";
    foreach ($record as $field_name => $field_value)
    {
      $field_params=array();
      $field_params["value"]=htmlspecialchars($field_value);
      $fields.=\webdb\forms\form_template_fill("list_field",$field_params);
    }
    $row_params["id"]=$record[$form_config["db_id_field_name"]];
    $row_params["fields"]=$fields;
    $rows.=\webdb\forms\form_template_fill("list_del_selected_confirm_row",$row_params);
  }
  $form_params=array();
  $form_params["rows"]=$rows;
  $form_params["url_page"]=$form_config["url_page"];
  $content=\webdb\forms\form_template_fill("list_del_selected_confirm",$form_params);
  $title=$form_name.": confirm selected deletion";
  \webdb\utils\output_page($content,$title);
  \webdb\forms\cancel($form_name);
}

#####################################################################################################

function delete_selected_records($form_name)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  foreach ($_POST["id"] as $id => $value)
  {
    $sql_params=array();
    $sql_params[$form_config["db_id_field_name"]]=$id;
    \webdb\sql\sql_delete($sql_params,$form_config["table"],$form_config["database"]);
  }
  \webdb\forms\cancel($form_name);
}

#####################################################################################################
