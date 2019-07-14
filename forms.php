<?php

namespace webdb\forms;

#####################################################################################################

function form_template_fill($name,$params=false)
{
  return \webdb\utils\template_fill("forms".DIRECTORY_SEPARATOR.$name,$params);
}

#####################################################################################################

function get_calendar($field_names)
{
  if (count($field_names)>0)
  {
    for ($i=0;$i<count($field_names);$i++)
    {
      $input_params=array();
      $input_params["field_name"]=$field_names[$i];
      $field_names[$i]=\webdb\forms\form_template_fill("calendar_input_array_item",$input_params);
    }
    $calendar_params=array();
    $calendar_params["calendar_inputs"]=implode(",",$field_names);
    $calendar_params["calendar_styles_modified"]=\webdb\utils\webdb_resource_modified_timestamp("calendar.css");
    $calendar_params["calendar_script_modified"]=\webdb\utils\webdb_resource_modified_timestamp("calendar.js");
    return \webdb\forms\form_template_fill("calendar",$calendar_params);
  }
  return "";
}

#####################################################################################################

function load_form_defs()
{
  global $settings;
  $webdb_file_list=scandir($settings["webdb_forms_path"]);
  for ($i=0;$i<count($webdb_file_list);$i++)
  {
    $fn=$webdb_file_list[$i];
    if (($fn==".") or ($fn==".."))
    {
      continue;
    }
    $full=$settings["webdb_forms_path"].$fn;
    $data=trim(file_get_contents($full));
    $data=json_decode($data,true);
    $settings["form_defaults"][$data["form_type"]]=$data;
  }
  $app_file_list=scandir($settings["app_forms_path"]);
  for ($i=0;$i<count($app_file_list);$i++)
  {
    $fn=$app_file_list[$i];
    if (($fn==".") or ($fn==".."))
    {
      continue;
    }
    $full=$settings["app_forms_path"].$fn;
    $data=trim(file_get_contents($full));
    $data=json_decode($data,true);
    if (isset($data["form_type"])==false)
    {
      \webdb\utils\show_message("error: invalid form def (missing form_type): ".$fn);
    }
    if (isset($settings["form_defaults"][$data["form_type"]])==false)
    {
      \webdb\utils\show_message("error: invalid form def (invalid form_type): ".$fn);
    }
    $default=$settings["form_defaults"][$data["form_type"]];
    $settings["forms"][$data["url_page"]]=array_merge($default,$data);
  }
}

#####################################################################################################

function header_row($form_config)
{
  $params=array();
  $params["check_head"]="";
  if ($form_config["multi_row_delete"]==true)
  {
    $params["check_head"]=\webdb\forms\form_template_fill("list_check_head");
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
  $params["controls_count"]=$controls_count;
  return $params;
}

#####################################################################################################

function list_form_content($form_name)
{
  global $settings;
  $form_config=$settings["forms"][$form_name];
  $form_params=array();
  $form_params["form_script_modified"]=\webdb\utils\webdb_resource_modified_timestamp("list.js");
  $form_params["form_styles_modified"]=\webdb\utils\webdb_resource_modified_timestamp("list.css");
  $form_params["url_page"]=$form_config["url_page"];
  $form_params["detail_page"]=$form_config["detail_page"];
  $form_params["firefox_styles"]="";
  if (strpos(strtolower($settings["user_agent"]),"firefox")!==false)
  {
    $firefox_params=array();
    $firefox_params["styles_modified"]=\webdb\utils\webdb_resource_modified_timestamp("list_firefox.css");
    $form_params["firefox_styles"]=\webdb\forms\form_template_fill("list_firefox",$firefox_params);
  }
  $caption_groups="";
  if (count($form_config["caption_groups"])>0)
  {
    $row_params=header_row($form_config);
    $field_headers="";
    $in_group=false;
    $first_group=true;
    $finished_group=false;
    foreach ($form_config["control_types"] as $field_name => $control_type)
    {
      if ($form_config["visible"][$field_name]==false)
      {
        continue;
      }
      foreach ($form_config["caption_groups"] as $group_name => $field_names)
      {
        if ($field_names[0]==$field_name)
        {
          if ($finished_group==false)
          {
            $first_group=true;
          }
          $finished_group=false;
          $in_group=true;
          $group_params=array();
          $group_params["group_caption"]=$group_name;
          $group_params["field_count"]=count($field_names);
          if ($first_group==true)
          {
            $field_headers.=\webdb\forms\form_template_fill("caption_group_first",$group_params);
          }
          else
          {
            $field_headers.=\webdb\forms\form_template_fill("caption_group",$group_params);
          }
          $first_group=false;
          continue 2;
        }
        if ($field_names[count($field_names)-1]==$field_name)
        {
          $in_group=false;
          $finished_group=true;
          continue 2;
        }
      }
      if ($in_group==false)
      {
        $finished_group=false;
        $field_headers.=\webdb\forms\form_template_fill("list_field_header_group");
      }
    }
    $row_params["field_headers"]=$field_headers;
    $caption_groups=\webdb\forms\form_template_fill("group_header_row",$row_params);
  }
  $form_params["caption_groups"]=$caption_groups;
  $field_headers="";
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    # $form_config["caption_groups"]
    if ($form_config["visible"][$field_name]==false)
    {
      continue;
    }
    $header_params=array();
    $header_params["field_name"]=$form_config["captions"][$field_name];
    $field_headers.=\webdb\forms\form_template_fill("list_field_header",$header_params);
  }
  $head_params=header_row($form_config);
  $form_params=array_merge($form_params,$head_params);
  $form_params["field_headers"]=$field_headers;
  $rows="";
  $sql=\webdb\utils\sql_fill("form_list_fetch_all",$form_config);
  $records=\webdb\sql\fetch_query($sql);
  for ($i=0;$i<count($records);$i++)
  {
    $record=$records[$i];

    # is_row_locked($schema,$table,$key_field,$key_value)

    $row_params=array();
    $row_params["id"]=$record[$form_config["db_id_field_name"]];
    $fields="";
    foreach ($form_config["control_types"] as $field_name => $control_type)
    {
      if ($form_config["visible"][$field_name]==false)
      {
        continue;
      }
      $field_params=array();
      if ($record[$field_name]==="")
      {
        $field_params["value"]=\webdb\utils\template_fill("empty_cell");
      }
      else
      {
        $field_params["value"]=htmlspecialchars($record[$field_name]);
      }
      $field_params["field_name"]=$field_name;
      $field_params["id"]=$row_params["id"];
      $field_params["url_page"]=$form_config["url_page"];
      switch ($control_type)
      {
        case "date":
          if ($record[$field_name]==\webdb\sql\zero_sql_timestamp())
          {
            $field_params["value"]=\webdb\utils\template_fill("empty_cell");
          }
          else
          {
            $field_params["value"]=date("Y-m-d",strtotime($record[$field_name]));
          }
          break;
      }
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
  return \webdb\forms\form_template_fill("list",$form_params);
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
  $calendar_fields=array();
  $rows="";
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    $field_value=$record[$field_name];
    $field_params=array();
    $field_params["field_name"]=$field_name;
    $field_params["field_value"]=htmlspecialchars($field_value);
    switch ($control_type)
    {
      case "span":
        break;
      case "text":
        break;
      case "memo":
        break;
      case "combobox":
      case "listbox":
      case "radiogroup":
        $options="";
        if (isset($form_config["lookups"][$field_name])==false)
        {
          \webdb\utils\show_message("error: invalid lookup config for field '".$field_name."' in form '".$form_name."' (lookup config missing)");
        }
        $lookup_config=$form_config["lookups"][$field_name];
        $config_keys=array("database","table","key_field","display_field");
        for ($i=0;$i<count($config_keys);$i++)
        {
          if (isset($lookup_config[$config_keys[$i]])==false)
          {
            \webdb\utils\show_message("error: invalid lookup config for field '".$field_name."' in form '".$form_name."' (lookup config key '".$config_keys[$i]."' missing)");
          }
          if ($lookup_config[$config_keys[$i]]=="")
          {
            \webdb\utils\show_message("error: invalid lookup config for field '".$field_name."' in form '".$form_name."' (lookup config key '".$config_keys[$i]."' cannot be empty)");
          }
        }
        $sql=\webdb\utils\sql_fill("form_lookup",$lookup_config);
        $records=\webdb\sql\fetch_query($sql);
        $blank_record=array();
        $blank_record[$lookup_config["key_field"]]="";
        $blank_record[$lookup_config["display_field"]]="";
        array_unshift($records,$blank_record);
        for ($i=0;$i<count($records);$i++)
        {
          $loop_record=$records[$i];
          $option_params=array();
          $option_params["name"]=$field_name;
          $option_params["value"]=$loop_record[$lookup_config["key_field"]];
          $option_params["caption"]=$loop_record[$lookup_config["display_field"]];
          $option_template="select";
          if ($control_type=="radiogroup")
          {
            $option_template="radio";
          }
          if ($loop_record[$lookup_config["key_field"]]==$field_value)
          {
            $options.=\webdb\utils\template_fill($option_template."_option_selected",$option_params);
          }
          else
          {
            $options.=\webdb\utils\template_fill($option_template."_option",$option_params);
          }
        }
        $field_params["options"]=$options;
        break;
      case "date":
        $calendar_fields[]=$field_name;
        if (($field_value==\webdb\sql\zero_sql_timestamp()) or ($field_value==""))
        {
          $field_params["field_value"]="";
        }
        else
        {
          $field_params["field_value"]=date("Y-m-d",strtotime($field_value));
        }
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
    $row_params=array();
    $row_params["field_name"]=$field_name;
    $row_params["field_value"]=\webdb\forms\form_template_fill("field_edit_".$control_type,$field_params);
    $rows.=\webdb\forms\form_template_fill("field_row",$row_params);
  }
  $form_params=array();
  $form_params["calendar"]=\webdb\forms\get_calendar($calendar_fields);
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
      case "combobox":
      case "listbox":
      case "radiogroup":
        if ($_POST[$field_name]=="")
        {
          $value_items[$field_name]=null;
        }
        else
        {
          $value_items[$field_name]=$_POST[$field_name];
        }
        break;
      case "memo":
        $value_items[$field_name]=$_POST[$field_name];
        break;
      case "date":
        if ($_POST[$field_name]=="")
        {
          $value_items[$field_name]=\webdb\sql\zero_sql_timestamp();
        }
        else
        {
          $value_items[$field_name]=$_POST[$field_name];
        }
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
