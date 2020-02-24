<?php

namespace webdb\stubs;

#####################################################################################################

function output_filter_select($form_config,$filter_select_template,$blank_option,$active_template,$select_all_template,$deselect_all_template)
{
  $params=array();
  $page_id=$form_config["page_id"];
  $filter=$form_config["default_filter"];
  if (isset($_GET["filters"])==true)
  {
    $filters=json_decode($_GET["filters"],true);
    if (isset($filters[$page_id])==true)
    {
      $filter=$filters[$page_id];
    }
  }
  if (isset($_GET["new_filter"])==true)
  {
    $filter=$_GET["new_filter"];
  }
  foreach ($form_config["filter_options"] as $filter_name => $sql_condition)
  {
    $params["active_filter_".$filter_name]="";
  }
  if ($filter<>"")
  {
    $params["active_filter_".$filter]=\webdb\utils\template_fill($active_template);
    $params[$blank_option]=$deselect_all_template;
  }
  else
  {
    $params[$blank_option]=$select_all_template;
  }
  $params["subform"]=$page_id;
  return \webdb\utils\template_fill($filter_select_template,$params);
}

#####################################################################################################

function filter_select_change($form_config)
{
  $required_params=array("new_filter","id","redirect","subform");
  \webdb\stubs\check_get_parameters_exist($required_params);
  $filter=$_GET["new_filter"];
  $subform=$_GET["subform"];
  $data=array();
  $id=$_GET["id"];
  $subform_config=\webdb\forms\get_form_config($subform,false);
  $subform_config["default_filter"]=$filter;
  $data["html"]=\webdb\forms\get_subform_content($subform_config,$subform_config["parent_key"],$id,true,$form_config);
  $data["subform"]=$subform_config["page_id"];
  $data=json_encode($data);
  die($data);
}

#####################################################################################################

function stub_error($error_msg)
{
  $data=array();
  $data["error"]=$error_msg;
  $data=json_encode($data);
  die($data);
}

#####################################################################################################

function check_get_parameters_exist($required_params)
{
  for ($i=0;$i<count($required_params);$i++)
  {
    $param_name=$required_params[$i];
    if (isset($_GET[$param_name])==false)
    {
      \webdb\stubs\stub_error("missing parameter: ".$param_name);
    }
  }
}

#####################################################################################################

function get_unique_stub_record($id_field_name,$id,$sql_stub_name,$return_field_name)
{
  if (is_numeric($id)==false)
  {
    return false;
  }
  $sql_params=array();
  $sql_params[$id_field_name]=$id;
  $records=\webdb\sql\file_fetch_prepare($sql_stub_name,$sql_params);
  if (count($records)<>1)
  {
    \webdb\stubs\stub_error("record with specified ".$id_field_name." not found or not unique");
  }
  return $records[0][$return_field_name];
}

#####################################################################################################

function list_insert($form_config)
{
  global $settings;
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"i")==false)
  {
    \webdb\utils\error_message("error: record update permission denied for form '".$page_id."'");
  }
  if ((isset($_GET["parent_form"])==true) and (isset($_GET["parent_id"])==true))
  {
    $form_config["parent_form_config"]=\webdb\forms\get_form_config($_GET["parent_form"],true);
    if ($form_config["parent_form_config"]!==false)
    {
      $form_config["parent_form_id"]=$_GET["parent_id"];
    }
  }
  $data=array();
  $params=\webdb\forms\process_form_data_fields($form_config);
  if (count($params)==0)
  {
    \webdb\stubs\stub_error("error: no field data to insert");
  }
  $data["page_id"]=$form_config["page_id"];
  $insert_default_params=array();
  foreach ($_GET as $param_name => $param_value)
  {
    switch ($param_name)
    {
      case "page":
      case "cmd":
      case "redirect":
      case "filters":
      case "ajax":
        break;
      default:
        $insert_default_params[$param_name]=$param_value;
    }
  }
  if (count($insert_default_params)>0)
  {
    foreach ($insert_default_params as $param_name => $param_value)
    {
      $params[$param_name]=$param_value;
      \webdb\forms\check_required_values($form_config,$params);
      \webdb\sql\sql_insert($params,$form_config["table"],$form_config["database"],false,$form_config);
      $parent_form_config=false;
      if (isset($form_config["parent_form_config"])==true)
      {
        $parent_form_config=$form_config["parent_form_config"];
      }
      $data["html"]=\webdb\forms\get_subform_content($form_config,$param_name,$param_value,true,$parent_form_config);
      break;
    }
  }
  else
  {
    \webdb\forms\check_required_values($form_config,$params);
    \webdb\sql\sql_insert($params,$form_config["table"],$form_config["database"],false,$form_config);
    $data["html"]=\webdb\forms\list_form_content($form_config);
  }
  $data=json_encode($data);
  die($data);
}

#####################################################################################################

function list_edit($id,$form_config)
{
  global $settings;
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"u")==false)
  {
    \webdb\utils\error_message("error: record update permission denied for form '".$page_id."'");
  }
  if ((isset($_GET["parent_form"])==true) and (isset($_GET["parent_id"])==true))
  {
    $form_config["parent_form_config"]=\webdb\forms\get_form_config($_GET["parent_form"],true);
    if ($form_config["parent_form_config"]!==false)
    {
      $form_config["parent_form_id"]=$_GET["parent_id"];
    }
  }
  $data=array();
  $data["page_id"]=$form_config["page_id"];
  $column_format=\webdb\forms\get_column_format_data($form_config);
  $record=\webdb\forms\get_record_by_id($form_config,$id,"primary_key");
  $record=\webdb\forms\process_computed_fields($form_config,$record);
  if (count($_POST)>0)
  {
    $post_fields=array();
    foreach ($_POST as $key => $value)
    {
      $parts=explode(":",$key);
      if (count($parts)<4)
      {
        continue;
      }
      $field_name=$parts[3];
      $post_fields[$field_name]=$value;
      if ($parts[0]=="iso_edit_control")
      {
        $field_name="iso_".$field_name;
        $post_fields[$field_name]=$value;
      }
    }
    if (isset($_GET["parent_form"])==true)
    {
      $parent_page_id=$_GET["parent_form"];
      $parent_form_config=\webdb\forms\get_form_config($parent_page_id);
      $subform_page_id=$_GET["subform"];
      if (\webdb\utils\check_user_form_permission($subform_page_id,"u")==false)
      {
        \webdb\utils\error_message("error: record update permission denied for subform '".$subform_page_id."'");
      }
      $subform_form_config=\webdb\forms\get_form_config($subform_page_id);
      $value_items=\webdb\forms\process_form_data_fields($subform_form_config,$post_fields);
      \webdb\forms\check_required_values($subform_form_config,$value_items);
      $where_items=\webdb\forms\config_id_conditions($subform_form_config,$id,"primary_key");
      $event_params=array();
      $event_params["handled"]=false;
      $event_params["form_config"]=$subform_form_config;
      $event_params["id"]=$id;
      $event_params["where_items"]=$where_items;
      $event_params["value_items"]=$value_items;
      $event_params=\webdb\forms\handle_update_record_event($event_params);
      if ($event_params["handled"]==false)
      {
        \webdb\sql\sql_update($value_items,$where_items,$subform_form_config["table"],$subform_form_config["database"],false,$subform_form_config);
      }
    }
    else
    {
      if (\webdb\utils\check_user_form_permission($form_config["page_id"],"u")==false)
      {
        \webdb\utils\error_message("error: record update permission denied for form '".$page_id."'");
      }
      $value_items=\webdb\forms\process_form_data_fields($form_config,$post_fields);
      \webdb\forms\check_required_values($form_config,$value_items);
      $where_items=\webdb\forms\config_id_conditions($form_config,$id,"primary_key");
      $event_params=array();
      $event_params["handled"]=false;
      $event_params["form_config"]=$form_config;
      $event_params["id"]=$id;
      $event_params["where_items"]=$where_items;
      $event_params["value_items"]=$value_items;
      $event_params=\webdb\forms\handle_update_record_event($event_params);
      if ($event_params["handled"]==false)
      {
        \webdb\sql\sql_update($value_items,$where_items,$form_config["table"],$form_config["database"],false,$form_config);
      }
    }
    $data=json_encode($data);
    die($data);
  }
  if (isset($_GET["reset"])==true)
  {
    $row_spans=array();
    $lookup_records=\webdb\forms\lookup_records($form_config);
    $data["html"]=\webdb\forms\list_row($form_config,$record,$column_format,$row_spans,$lookup_records,0);
    $data["primary_key"]=$id;
    $data["calendar_fields"]=json_encode(array());
    $data["edit_fields"]=json_encode(array());
    $data=json_encode($data);
    die($data);
  }
  $edit_fields=array();
  $field_name_prefix="edit_control:".$form_config["page_id"].":".$id.":";
  $data["html"]=\webdb\forms\list_row_controls($form_config,$edit_fields,"edit",$column_format,$record,$field_name_prefix);
  $data["primary_key"]=$id;
  for ($i=0;$i<count($settings["calendar_fields"]);$i++)
  {
    $settings["calendar_fields"][$i]=\webdb\forms\js_date_field($field_name_prefix.$settings["calendar_fields"][$i]);
  }
  $data["calendar_fields"]=json_encode($settings["calendar_fields"]);
  $data["edit_fields"]=json_encode($edit_fields);
  $data=json_encode($data);
  die($data);
}

#####################################################################################################
