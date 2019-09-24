<?php

namespace webdb\manage;

# todo: add form config elements: custom_interfaces, html_includes, edit_subforms_styles, individual_delete_url_page

#####################################################################################################

function manager_page()
{
  \webdb\manage\select_form_list();
}

#####################################################################################################

function select_form_list()
{
  global $settings;
  $title="WebDB Form Config";
  $page_params=array();
  $items="";
  foreach ($settings["forms"] as $form_name => $form_config)
  {
    $item_params=array();
    $item_params["form_name"]=$form_name;
    $item_params["app_name"]="WebDB";
    $test=substr($form_config["filename"],0,strlen($settings["app_forms_path"]));
    if ($test==$settings["app_forms_path"])
    {
      $item_params["app_name"]=$settings["app_name"];
    }
    $items.=\webdb\utils\template_fill("manage/form_item",$item_params);
  }
  $page_params["form_list"]=$items;
  $page_content=\webdb\utils\template_fill("manage/form_select",$page_params);
  \webdb\utils\output_page($page_content,$title);
}

#####################################################################################################

function form_config__event_handler__on_list($event_params)
{
  global $settings;
  if (isset($event_params["manage_form"])==false)
  {
    \webdb\utils\show_message("error: missing manage_form parameter");
  }
  $form_config=$settings["forms"][$event_params["manage_form"]];
  $records=array();
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    $record=\webdb\manage\form_config__record_template();
    $record["field_name"]=$field_name;
    $record["control_type"]=$control_type;
    $record["caption"]=$form_config["captions"][$field_name];
    $record["visible"]=$form_config["visible"][$field_name];
    if (isset($form_config["default_values"][$field_name])==true)
    {
      $record["default_value"]=$form_config["default_values"][$field_name];
    }
    if (isset($form_config["computed_values"][$field_name])==true)
    {
      $record["computed_value"]=$form_config["computed_values"][$field_name];
    }
    if (isset($form_config["table_cell_styles"][$field_name])==true)
    {
      $record["table_cell_styles"]=$form_config["table_cell_styles"][$field_name];
    }
    if (isset($form_config["control_styles"][$field_name])==true)
    {
      $record["control_styles"]=$form_config["control_styles"][$field_name];
    }
    $records[]=$record;
  }
  $event_params["records"]=$records;
  return $event_params;
}

#####################################################################################################

function form_config__event_handler__on_get_record_by_id($event_params)
{
  $form_config=\webdb\forms\get_form_config($_GET["manage"]);
  $field_name=$event_params["id"];
  $record=\webdb\manage\form_config__record_template();
  $record["field_name"]=$field_name;
  $record["control_type"]=$form_config["control_types"][$field_name];
  $record["caption"]=$form_config["captions"][$field_name];
  $record["visible"]=$form_config["visible"][$field_name];
  if (isset($form_config["default_values"][$field_name])==true)
  {
    $record["default_value"]=$form_config["default_values"][$field_name];
  }
  if (isset($form_config["computed_values"][$field_name])==true)
  {
    $record["computed_value"]=$form_config["computed_values"][$field_name];
  }
  if (isset($form_config["table_cell_style"][$field_name])==true)
  {
    $record["table_cell_style"]=$form_config["table_cell_styles"][$field_name];
  }
  if (isset($form_config["control_style"][$field_name])==true)
  {
    $record["control_style"]=$form_config["control_styles"][$field_name];
  }
  return $record;
}

#####################################################################################################

function form_config__event_handler__on_update_record($form_name,$id,$where_items,$value_items,$form_config)
{
  global $settings;
  if (isset($_GET["manage"])==false)
  {
    \webdb\utils\show_message("error: missing manage parameter in url");
  }
  $form_config=$settings["forms"][$_GET["manage"]];
  $field_name=$value_items["field_name"];
  $form_config["control_types"][$field_name]=$value_items["control_type"];
  $form_config["captions"][$field_name]=$value_items["caption"];
  if (isset($value_items["visible"])==true)
  {
    $form_config["visible"][$field_name]=$value_items["visible"];
  }
  else
  {
    $form_config["visible"][$field_name]=false;
  }
  $form_config["default_values"][$field_name]=$value_items["default_value"];
  $form_config["computed_values"][$field_name]=$value_items["computed_value"];
  $form_config["table_cell_styles"][$field_name]=$value_items["table_cell_style"];
  $form_config["control_styles"][$field_name]=$value_items["control_style"];

  $form_config["form_version"]=$_POST["form_version"];
  $form_config["form_type"]=$_POST["form_type"];
  $form_config["title"]=$_POST["title"];
  $form_config["url_page"]=$_POST["url_page"];
  $form_config["return_link_url_page"]=$_POST["return_link_url_page"];
  $form_config["return_link_caption"]=$_POST["return_link_caption"];
  $form_config["primary_key"]=$_POST["primary_key"];
  $form_config["command_caption_noun"]=$_POST["command_caption_noun"];
  $form_config["custom_form_above_template"]=$_POST["custom_form_above_template"];
  $form_config["custom_form_below_template"]=$_POST["custom_form_below_template"];
  $form_config["database"]=$_POST["database"];
  $form_config["table"]=$_POST["table"];
  $form_config["sort_sql"]=$_POST["sort_sql"];
  $form_config["individual_edit_url_page"]=$_POST["individual_edit_url_page"];
  $form_config["individual_edit_id"]=$_POST["individual_edit_id"];
  $form_config["individual_edit"]=$_POST["individual_edit"];
  $form_config["records_sql"]=$_POST["records_sql"];
  $form_config["enabled"]=\webdb\manage\read_checkbox_post_value("enabled");
  $form_config["multi_row_delete"]=\webdb\manage\read_checkbox_post_value("multi_row_delete");
  $form_config["individual_delete"]=\webdb\manage\read_checkbox_post_value("individual_delete");
  $form_config["insert_new"]=\webdb\manage\read_checkbox_post_value("insert_new");
  $form_config["insert_row"]=\webdb\manage\read_checkbox_post_value("insert_row");
  $form_config["advanced_search"]=\webdb\manage\read_checkbox_post_value("advanced_search");

  # todo: write a decent help document on how to use this form and what the fields mean
  # todo: write functions that read in the textarea ini format post fields and convert them to arrays

  $filename=$form_config["filename"];
  unset($form_config["filename"]);
  $data=json_encode($form_config,JSON_PRETTY_PRINT);
  if (file_put_contents($filename,$data)==false)
  {
    \webdb\utils\show_message("error: error writing file '".$filename."'");
  }
  return true;
}

#####################################################################################################

function form_config__event_handler__on_insert_record($form_name,$value_items,$form_config)
{
  # todo
  return false;
}

#####################################################################################################

function form_config__event_handler__on_custom_form_above($form_config,$form_params)
{
  global $settings;
  if (isset($_GET["manage"])==false)
  {
    \webdb\utils\show_message("error: missing manage parameter in url");
  }
  $form_config=$settings["forms"][$_GET["manage"]];
  $params=$form_config;
  $params["manage_form"]=$_GET["manage"];
  $params["edit_subforms"]=\webdb\manage\output_edit_subforms($form_config["edit_subforms"]);
  $params["caption_groups"]=\webdb\manage\output_caption_groups($form_config["caption_groups"]);
  $params["lookups"]=\webdb\manage\output_lookups($form_config["lookups"]);
  $params["group_by"]=implode(PHP_EOL,$form_config["group_by"]);
  $params["event_handlers"]=\webdb\manage\output_event_handlers($form_config["event_handlers"]);
  $params["enabled"]=\webdb\manage\check_box("enabled",$form_config["enabled"]);
  $params["multi_row_delete"]=\webdb\manage\check_box("multi_row_delete",$form_config["multi_row_delete"]);
  $params["individual_delete"]=\webdb\manage\check_box("individual_delete",$form_config["individual_delete"]);
  $params["insert_new"]=\webdb\manage\check_box("insert_new",$form_config["insert_new"]);
  $params["insert_row"]=\webdb\manage\check_box("insert_row",$form_config["insert_row"]);
  $params["advanced_search"]=\webdb\manage\check_box("advanced_search",$form_config["advanced_search"]);
  $select_options="";
  $individual_edit_options=array("none","button","row","inline");
  for ($i=0;$i<count($individual_edit_options);$i++)
  {
    $option_params=array();
    $option_params["value"]=$individual_edit_options[$i];
    $option_params["caption"]=$individual_edit_options[$i];
    if ($form_config["individual_edit"]==$individual_edit_options[$i])
    {
      $select_options.=\webdb\utils\template_fill("select_option_selected",$option_params);
    }
    else
    {
      $select_options.=\webdb\utils\template_fill("select_option",$option_params);
    }
  }
  $params["individual_edit_options"]=$select_options;
  return \webdb\utils\template_fill("manage".DIRECTORY_SEPARATOR."form_config_above",$params);
}

#####################################################################################################

function read_checkbox_post_value($name)
{
  if (isset($_POST[$name])==true)
  {
    return true;
  }
  return false;
}

#####################################################################################################

function check_box($name,$checked)
{
  $params=array();
  $params["name"]=$name;
  if ($checked==true)
  {
    $params["checked"]=\webdb\utils\template_fill("checkbox_checked");
  }
  else
  {
    $params["checked"]="";
  }
  return \webdb\utils\template_fill("checkbox_input",$params);
}

#####################################################################################################

function output_lookups($data)
{
  $result="";
  foreach ($data as $lookup_field => $lookup_data)
  {
    $result.="[".$lookup_field."]".PHP_EOL;
    foreach ($lookup_data as $key => $value)
    {
      $result.=$key."=".$value.PHP_EOL;
    }
    $result.=PHP_EOL;
  }
  return trim($result);
}

#####################################################################################################

function output_caption_groups($data)
{
  $result="";
  foreach ($data as $group_name => $group_fields)
  {
    $result.="[".$group_name."]".PHP_EOL;
    for ($i=0;$i<count($group_fields);$i++)
    {
      $result.=$group_fields[$i].PHP_EOL;
    }
    $result.=PHP_EOL;
  }
  return trim($result);
}

#####################################################################################################

function output_edit_subforms($data)
{
  $result="";
  foreach ($data as $key => $value)
  {
    $result.=$key."=".$value.PHP_EOL;
  }
  return trim($result);
}

#####################################################################################################

function output_event_handlers($data)
{
  return \webdb\manage\output_edit_subforms($data);
}

#####################################################################################################

function form_config__event_handler__on_custom_form_below($form_config,$form_params)
{
  return "";
}

#####################################################################################################

function form_config__record_template()
{
  $record=array();
  $record["field_name"]="";
  $record["control_type"]="";
  $record["caption"]="";
  $record["visible"]="";
  $record["default_value"]="";
  $record["computed_value"]="";
  $record["table_cell_style"]="";
  $record["control_style"]="";
  return $record;
}

#####################################################################################################
