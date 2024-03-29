<?php

namespace webdb\stubs;

#####################################################################################################

function update_user_details($form_config,$event_params,$event_name)
{
  global $settings;
  # entire event handler not really needed as process_form_data_fields only loads fields contained in list file anyway, but just in case
  $permitted_fields=array("user_rank_id","organisation_position_id","subject_matter_id","initials","notes");
  foreach ($event_params["value_items"] as $field_name => $value)
  {
    if (in_array($field_name,$permitted_fields)==false)
    {
      $event_params["handled"]=true; # <= not really needed as \webdb\utils\error_message kills script anyway
      \webdb\utils\error_message("error: invalid update request (unexpected/forbidden field name encountered)");
    }
  }
  return $event_params;
}

#####################################################################################################

function output_filter_select($form_config,$filter_select_template,$blank_option,$active_template,$select_all_template,$deselect_all_template)
{
  $params=array();
  $page_id=$form_config["page_id"];
  $selected_filter=$form_config["default_filter"];
  if (isset($form_config["selected_filter"])==true)
  {
    $selected_filter=$form_config["selected_filter"];
  }
  if (isset($_GET["new_filter"])==true)
  {
    $selected_filter=$_GET["new_filter"];
  }
  foreach ($form_config["filter_options"] as $filter_name => $sql_condition)
  {
    $params["active_filter_".$filter_name]="";
  }
  if ($selected_filter<>"")
  {
    $params["active_filter_".$selected_filter]=\webdb\utils\template_fill($active_template);
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
  $data=array();
  $required_params=array("new_filter");
  \webdb\stubs\check_get_parameters_exist($required_params);
  $new_filter=$_GET["new_filter"];
  $subform=false;
  if (isset($_GET["subform"])==true)
  {
    if ($form_config["page_id"]<>$_GET["subform"])
    {
      $subform=true;
    }
  }
  if ($subform==true)
  {
    $required_params=array("id");
    \webdb\stubs\check_get_parameters_exist($required_params);
    $subform=$_GET["subform"];
    $id=$_GET["id"];
    $subform_config=\webdb\forms\get_form_config($subform,false);
    $subform_config["default_filter"]=$new_filter;
    $data["html"]=\webdb\forms\get_subform_content($subform_config,$subform_config["parent_key"],$id,true,$form_config);
    $data["subform"]=$subform_config["page_id"];
  }
  else
  {
    $form_config["default_filter"]=$new_filter;
    $data["html"]=\webdb\forms\list_form_content($form_config);
  }
  $data["html"]=\webdb\utils\string_template_fill($data["html"]);
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
    \webdb\utils\error_message("error: record update permission denied for form '".$form_config["page_id"]."'");
  }
  $data=array();
  $value_items=\webdb\forms\process_form_data_fields($form_config,"");
  if (count($value_items)==0)
  {
    \webdb\stubs\stub_error("error: no field data to insert");
  }
  $insert_default_params=\webdb\forms\insert_default_url_params();
  if (count($insert_default_params)>0)
  {
    foreach ($insert_default_params as $param_name => $param_value)
    {
      $value_items[$param_name]=$param_value;
    }
    $event_params=array();
    $event_params["handled"]=false;
    $event_params["value_items"]=$value_items;
    $event_params["new_record_id"]=0;
    $event_params=\webdb\forms\handle_form_config_event($form_config,$event_params,"on_insert_record");
    $value_items=$event_params["value_items"];
    if ($event_params["handled"]===false)
    {
      \webdb\forms\check_required_values($form_config,$value_items);
      \webdb\sql\sql_insert($value_items,$form_config["table"],$form_config["database"],false,$form_config);
      $id=\webdb\sql\sql_last_insert_autoinc_id();
      \webdb\forms\upload_files($form_config,$id);
    }
    else
    {
      $id=$event_params["new_record_id"];
    }
    $parent_form_config=false;
    if (isset($form_config["parent_form_config"])==true)
    {
      $parent_form_config=$form_config["parent_form_config"];
    }
    \webdb\forms\upload_files($form_config,"");
    $data["html"]=\webdb\forms\get_subform_content($form_config,$param_name,$param_value,true,$parent_form_config);
    $data["div_id"]="subform_table_".$form_config["page_id"];
  }
  else
  {
    $event_params=array();
    $event_params["handled"]=false;
    $event_params["value_items"]=$value_items;
    $event_params["new_record_id"]=0;
    $event_params=\webdb\forms\handle_form_config_event($form_config,$event_params,"on_insert_record");
    $value_items=$event_params["value_items"];
    if ($event_params["handled"]===false)
    {
      \webdb\forms\check_required_values($form_config,$value_items);
      \webdb\sql\sql_insert($value_items,$form_config["table"],$form_config["database"],false,$form_config);
      $id=\webdb\sql\sql_last_insert_autoinc_id();
      \webdb\forms\upload_files($form_config,$id);
    }
    else
    {
      $id=$event_params["new_record_id"];
    }
    \webdb\forms\upload_files($form_config,"");
    $data["html"]=\webdb\forms\list_form_content($form_config);
    $data["div_id"]="list_content";
  }
  $event_params=array();
  $event_params["value_items"]=$value_items;
  $event_params["new_record_id"]=$id;
  $event_params=\webdb\forms\handle_form_config_event($form_config,$event_params,"on_after_insert_record");
  $data["html"]=\webdb\utils\string_template_fill($data["html"]);
  $data=json_encode($data);
  die($data);
}

#####################################################################################################

function subform_edit($data,$subform_config,$subform_id,$parent_form_config,$parent_id,$post_override,$record,&$link_record,&$merged_record)
{
  # TODO: HANDLE ROW LOCKING
  $data["div_id"]="subform_table_".$subform_config["page_id"];
  $subform_config=\webdb\forms\override_delete_config($subform_config);
  $primary_key_items=\webdb\forms\config_id_conditions($subform_config,$subform_id,"primary_key");
  if ($subform_config["checklist"]==true)
  {
    $conditions=array();
    $fieldname=$subform_config["parent_key"];
    $conditions[$fieldname]=$parent_id;
    $fieldname=$subform_config["link_key"];
    $conditions[$fieldname]=$primary_key_items[$fieldname];
    $sql_params=array();
    $sql_params["database"]=$subform_config["link_database"];
    $sql_params["table"]=$subform_config["link_table"];
    $sql_params["where_conditions"]=\webdb\sql\build_prepared_where($conditions);
    $sql=\webdb\utils\sql_fill("form_list_fetch_by_id",$sql_params);
    $records=\webdb\sql\fetch_prepare($sql,$conditions,"form_list_fetch_by_id",false,$sql_params["table"],$sql_params["database"],$subform_config);
    $link_record=$records[0];
    $merged_record=array_merge($record,$link_record);
  }
  if ($post_override===false)
  {
    $post_override=$_POST;
  }
  if (count($post_override)>0)
  {
    $subform_page_id=$subform_config["page_id"];
    $fieldname=$parent_form_config["edit_subforms"][$subform_page_id];
    if ($subform_config["checklist"]==true)
    {
      $where_items=array();
      $fieldname=$subform_config["parent_key"];
      $where_items[$fieldname]=$parent_id;
      $fieldname=$subform_config["link_key"];
      $where_items[$fieldname]=$primary_key_items[$fieldname];
      $value_items=\webdb\forms\process_form_data_fields($subform_config,$subform_id,$post_override);
      $subform_config_link=$subform_config;
      $subform_config_link["table"]=$subform_config["link_table"];
      $subform_config_link["database"]=$subform_config["link_database"];
      \webdb\forms\update_record($subform_config_link,$subform_id,$value_items,$where_items,true);
      $data["html"]=\webdb\forms\get_subform_content($subform_config,$fieldname,$parent_id,true,$parent_form_config);
    }
    else
    {
      $value_items=\webdb\forms\process_form_data_fields($subform_config,$subform_id,$post_override);
      \webdb\forms\update_record($subform_config,$subform_id,$value_items,$primary_key_items,true);
      $data["html"]=\webdb\forms\get_subform_content($subform_config,$fieldname,$parent_id,true,$parent_form_config);
    }
    $data["html"]=\webdb\utils\string_template_fill($data["html"]);
    $data=json_encode($data);
    die($data);
  }
}

#####################################################################################################

function list_edit($id,$form_config,$post_override=false)
{
  global $settings;
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"u")==false)
  {
    \webdb\utils\error_message("error: record update permission denied for form '".$form_config["page_id"]."'");
  }
  # TODO: HANDLE ROW LOCKING
  $data=array();
  $data["page_id"]=$form_config["page_id"];
  $data["primary_key"]=$id;
  $column_format=\webdb\forms\get_column_format_data($form_config);
  $record=\webdb\forms\get_record_by_id($form_config,$id,"primary_key");
  $record=\webdb\forms\process_computed_fields($form_config,$record);
  $link_record=false;
  $merged_record=false;
  if ((isset($_GET["parent_form"])==true) and (isset($_GET["parent_id"])==true))
  {
    $form_config=\webdb\forms\override_delete_config($form_config);
    $parent_form_config=\webdb\forms\get_form_config($_GET["parent_form"]);
    $form_config["parent_form_id"]=$_GET["parent_id"];
    $form_config["parent_form_config"]=$parent_form_config;
    \webdb\stubs\subform_edit($data,$form_config,$id,$parent_form_config,$_GET["parent_id"],$post_override,$record,$link_record,$merged_record);
  }
  if ($post_override===false)
  {
    $post_override=$_POST;
  }
  if (count($post_override)>0)
  {
    $value_items=\webdb\forms\process_form_data_fields($form_config,$id,$post_override);
    $where_items=\webdb\forms\config_id_conditions($form_config,$id,"primary_key");
    $event_params=array();
    $event_params["handled"]=false;
    $event_params["form_config"]=$form_config;
    $event_params["record_id"]=$id;
    $event_params["where_items"]=$where_items;
    $event_params["value_items"]=$value_items;
    $event_params=\webdb\forms\handle_form_config_event($form_config,$event_params,"on_update_record");
    $where_items=$event_params["where_items"];
    $value_items=$event_params["value_items"];
    if ($event_params["handled"]==false)
    {
      \webdb\forms\check_required_values($form_config,$value_items);
      \webdb\sql\sql_update($value_items,$where_items,$form_config["table"],$form_config["database"],false,$form_config);
      \webdb\forms\upload_files($form_config,$id,$value_items);
    }
    $data=json_encode($data);
    die($data);
  }
  if (isset($_GET["reset"])==true)
  {
    $row_spans=array();
    $lookup_records=\webdb\forms\lookup_records($form_config);
    $data["html"]=\webdb\forms\list_row($form_config,$record,$column_format,$row_spans,$lookup_records,0,$link_record);
    $data["html"]=\webdb\utils\string_template_fill($data["html"]);
    $data["calendar_fields"]=json_encode(array());
    $data["edit_fields"]=json_encode(array());
    $data=json_encode($data);
    die($data);
  }
  if ($merged_record!==false)
  {
    $record=$merged_record;
  }
  $edit_fields=array();
  $data["html"]=\webdb\forms\list_row_controls($form_config,$edit_fields,"edit",$column_format,$record);
  $data["html"]=\webdb\utils\string_template_fill($data["html"]);
  for ($i=0;$i<count($settings["calendar_fields"]);$i++)
  {
    $settings["calendar_fields"][$i]=\webdb\forms\js_date_field($settings["calendar_fields"][$i]);
  }
  $data["calendar_fields"]=json_encode($settings["calendar_fields"]);
  $data["edit_fields"]=json_encode($edit_fields);
  $data=json_encode($data);
  die($data);
}

#####################################################################################################
