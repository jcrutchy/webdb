<?php

namespace webdb\stubs;

#####################################################################################################

function get_checklist_description()
{
  $data=array();
  if ((isset($_GET["ernie_id"])==false) or (isset($_GET["form_id"])==false))
  {
    $data["error"]="missing parameter";
    $data=json_encode($data);
    die($data);
  }
  $data["form_id"]=$_GET["form_id"];

  $ernie_id=$_GET["ernie_id"];
  $checklist_id=$_GET["checklist_id"];

  $data["hazard_no"]="test hazard no";
  $data["hazard_description"]="test description";

  $data=json_encode($data);
  die($data);
}

#####################################################################################################

function list_insert($form_name)
{
  global $settings;
  if (isset($_GET["checklist_id"])==true)
  {
    get_checklist_description();
  }
  if (\webdb\utils\check_user_form_permission($form_name,"i")==false)
  {
    \webdb\utils\show_message("error: form record update permission denied");
  }
  $form_config=$settings["forms"][$form_name];
  $data=array();
  $params=\webdb\forms\process_form_data_fields($form_name);
  if (count($params)==0)
  {
    $data["error"]="error: no field data to insert";
    $data=json_encode($data);
    die($data);
  }
  $data["url_page"]=$form_config["url_page"];
  $insert_default_params=array();
  foreach ($_GET as $param_name => $param_value)
  {
    switch ($param_name)
    {
      case "page":
      case "manage":
      case "cmd":
      case "ajax":
        continue;
      default:
        $insert_default_params[$param_name]=$param_value;
    }
  }
  if (count($insert_default_params)>0)
  {
    foreach ($insert_default_params as $param_name => $param_value)
    {
      $params[$param_name]=$param_value;
      \webdb\sql\sql_insert($params,$form_config["table"],$form_config["database"]);
      $data["html"]=\webdb\forms\get_subform_content($form_name,$param_name,$param_value,true);
      break;
    }
  }
  else
  {
    \webdb\sql\sql_insert($params,$form_config["table"],$form_config["database"]);
    $data["html"]=\webdb\forms\list_form_content($form_name);
  }
  $data=json_encode($data);
  die($data);
}

#####################################################################################################

function list_edit($id,$form_name)
{
  global $settings;
  if (isset($_GET["checklist_id"])==true)
  {
    get_checklist_description();
  }
  if (\webdb\utils\check_user_form_permission($form_name,"u")==false)
  {
    \webdb\utils\show_message("error: form record update permission denied");
  }
  $form_config=$settings["forms"][$form_name];
  $data=array();
  $data["url_page"]=$form_config["url_page"];
  $column_format=\webdb\forms\get_column_format_data($form_name);
  $record=\webdb\forms\get_record_by_id($form_name,$id,"primary_key");
  \webdb\forms\process_computed_fields($form_config,$record);
  if (count($_POST)>0)
  {
    $post_fields=array();
    foreach ($_POST as $key => $value)
    {
      $parts=explode(":",$key);
      $field_name=$parts[3];
      $post_fields[$field_name]=$value;
    }
    if (isset($_GET["parent"])==true)
    {
      $parent_url_page=$_GET["parent"];
      $parent_form_config=\webdb\forms\get_form_config($parent_url_page);
      $parent_form_name=$parent_form_config["form_name"];
      $subform_url_page=$_GET["subform"];
      $subform_form_config=\webdb\forms\get_form_config($subform_url_page);
      $subform_form_name=$subform_form_config["form_name"];
      $value_items=\webdb\forms\process_form_data_fields($subform_form_name,$post_fields);
      $where_items=\webdb\forms\config_id_conditions($subform_form_config,$id,"primary_key");
      $handled=\webdb\forms\handle_update_record_event($subform_form_name,$id,$where_items,$value_items,$subform_form_config);
      if ($handled==false)
      {
        \webdb\sql\sql_update($value_items,$where_items,$subform_form_config["table"],$subform_form_config["database"]);
      }
    }
    else
    {
      if (\webdb\utils\check_user_form_permission($form_name,"u")==false)
      {
        \webdb\utils\show_message("error: form record update permission denied");
      }
      $value_items=\webdb\forms\process_form_data_fields($form_name,$post_fields);
      $where_items=\webdb\forms\config_id_conditions($form_config,$id,"primary_key");
      $handled=\webdb\forms\handle_update_record_event($form_name,$id,$where_items,$value_items,$form_config);
      if ($handled==false)
      {
        \webdb\sql\sql_update($value_items,$where_items,$form_config["table"],$form_config["database"]);
      }
    }
    $data=json_encode($data);
    die($data);
  }
  if (isset($_GET["reset"])==true)
  {
    $row_spans=array();
    $lookup_records=\webdb\forms\lookup_records($form_name);
    $data["html"]=\webdb\forms\list_row($form_config,$record,$column_format,$row_spans,$lookup_records,0);
    $data["primary_key"]=$id;
    $data["calendar_fields"]=json_encode(array());
    $data["edit_fields"]=json_encode(array());
    $data=json_encode($data);
    die($data);
  }
  $calendar_fields=array();
  $edit_fields=array();
  $field_name_prefix="edit_control:".$form_config["url_page"].":".$id.":";
  $data["html"]=\webdb\forms\list_row_controls($form_name,$form_config,$edit_fields,$calendar_fields,"edit",$column_format,$record,$field_name_prefix);
  $data["primary_key"]=$id;
  $data["calendar_fields"]=json_encode($calendar_fields);
  $data["edit_fields"]=json_encode($edit_fields);
  $data=json_encode($data);
  die($data);
}

#####################################################################################################
