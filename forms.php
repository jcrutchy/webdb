<?php

namespace webdb\forms;

#####################################################################################################

function get_form_config($page_id,$return=false)
{
  global $settings;
  foreach ($settings["forms"] as $key => $form_config)
  {
    if ($page_id===$form_config["page_id"])
    {
      if (\webdb\utils\check_user_form_permission($page_id,"r")==false)
      {
        \webdb\utils\error_message("error: form read permission denied");
      }
      return $form_config;
    }
  }
  if ($return!==false)
  {
    return false;
  }
  \webdb\utils\error_message("error: form config not found");
}

#####################################################################################################

function form_dispatch($page_id)
{
  global $settings;
  $form_config=\webdb\forms\get_form_config($page_id,false);
  if ($page_id=="users")
  {
    $admin_auth=false;
    if (isset($settings["user_record"])==true)
    {
      if ($settings["user_record"]["username"]=="admin")
      {
        $admin_auth=true;
      }
    }
    if ($admin_auth==false)
    {
      \webdb\utils\error_message("error: users form not permitted from non-admin account");
    }
  }
  if (($form_config["default_cmd_override"]<>"") and (isset($_GET["cmd"])==false))
  {
    $_GET["cmd"]=$form_config["default_cmd_override"];
  }
  if ((isset($_GET["ajax"])==true) and (isset($_GET["field_name"])==true))
  {
    $event_type=$_GET["ajax"];
    $field_name=$_GET["field_name"];
    if (isset($form_config["js_events"][$field_name][$event_type])==true)
    {
      $event_data=$form_config["js_events"][$field_name][$event_type];
      $func_name=$event_data["ajax_stub"];
      if (function_exists($func_name)==true)
      {
        call_user_func($func_name,$form_config,$field_name,$event_type,$event_data);
      }
    }
    $data=array();
    $data["error"]="unhandled ajax call";
    $data=json_encode($data);
    die($data);
  }
  switch ($form_config["form_type"])
  {
    case "list":
      if ($form_config["generate_stub"]<>"")
      {
        if (function_exists($form_config["generate_stub"])==true)
        {
          echo call_user_func($form_config["generate_stub"],$form_config);
          die;
        }
        else
        {
          \webdb\utils\error_message("error: unhandled generate stub");
        }
      }
      \webdb\forms\output_resource_includes($form_config,"css");
      \webdb\forms\output_resource_includes($form_config,"js");
      if (($form_config["records_sql"]=="") or ($form_config["checklist"]==true))
      {
        if (($form_config["database"]=="") or ($form_config["table"]==""))
        {
          if (isset($form_config["event_handlers"]["on_list"])==false)
          {
            return \webdb\utils\template_fill($page_id);
          }
        }
        if (isset($_POST["form_cmd"])==true)
        {
          $cmd=\webdb\utils\get_child_array_key($_POST,"form_cmd");
          switch ($cmd)
          {
            case "checklist_update":
              \webdb\forms\checklist_update($form_config);
            case "insert_confirm":
              \webdb\forms\insert_record($form_config);
            case "edit_confirm":
              $id=\webdb\utils\get_child_array_key($_POST["form_cmd"],"edit_confirm");
              \webdb\forms\update_record($form_config,$id);
            case "delete":
              $id=\webdb\utils\get_child_array_key($_POST["form_cmd"],"delete");
              $data=\webdb\forms\delete_confirmation($form_config,$id);
              $data["content"].=\webdb\forms\output_html_includes($form_config);
              \webdb\utils\output_page($data["content"],$data["title"]);
            case "delete_confirm":
              $id=\webdb\utils\get_child_array_key($_POST["form_cmd"],"delete_confirm");
              \webdb\forms\delete_record($form_config,$id);
            case "delete_selected":
              \webdb\forms\delete_selected_confirmation($form_config);
            case "delete_selected_confirm":
              \webdb\forms\delete_selected_records($form_config);
          }
        }
        if (isset($_GET["cmd"])==true)
        {
          switch ($_GET["cmd"])
          {
            case "edit":
              if (isset($_GET["id"])==false)
              {
                \webdb\utils\error_message("error: missing id parameter");
              }
              if (isset($_GET["ajax"])==true)
              {
                \webdb\stubs\list_edit($_GET["id"],$form_config);
              }
              $data=\webdb\forms\edit_form($form_config,$_GET["id"]);
              $data["content"].=\webdb\forms\output_html_includes($form_config);
              \webdb\utils\output_page($data["content"],$data["title"]);
            case "insert":
              if (isset($_GET["ajax"])==true)
              {
                \webdb\stubs\list_insert($form_config);
              }
              $data=\webdb\forms\insert_form($form_config);
              $data["content"].=\webdb\forms\output_html_includes($form_config);
              \webdb\utils\output_page($data["content"],$data["title"]);
            case "advanced_search":
              $data=\webdb\forms\advanced_search($form_config);
              $data["content"].=\webdb\forms\output_html_includes($form_config);
              \webdb\utils\output_page($data["content"],$data["title"]);
          }
        }
      }
      $list_params=array();
      $event_params=array();
      $event_params["page_id"]=$page_id;
      $event_params["form_config"]=$form_config;
      $event_params["custom_list_content"]=false;
      $event_params["records"]=false;
      $event_params["content"]="";
      if (isset($form_config["event_handlers"]["on_list"])==true)
      {
        $func_name=$form_config["event_handlers"]["on_list"];
        if (function_exists($func_name)==true)
        {
          $event_params=call_user_func($func_name,$event_params);
        }
      }
      if ($event_params["custom_list_content"]==false)
      {
        $list_params["list"]=\webdb\forms\list_form_content($form_config,$event_params["records"]);
      }
      else
      {
        $list_params["list"]=$event_params["content"];
      }
      $list_params["form_script_modified"]=\webdb\utils\resource_modified_timestamp("list.js");
      $list_params["form_styles_modified"]=\webdb\utils\resource_modified_timestamp("list.css");
      $list_params["form_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("list_print.css");
      $list_params["title"]=$form_config["title"];
      $content=\webdb\forms\form_template_fill("list_page",$list_params);
      $content.=\webdb\forms\output_html_includes($form_config);
      $title=$page_id;
      if ($form_config["title"]<>"")
      {
        $title=$form_config["title"];
      }
      \webdb\utils\output_page($content,$title);
  }
}

#####################################################################################################

function checklist_update($form_config)
{
  global $settings;
  $page_id=$form_config["page_id"];
  $parent_id=$_POST["parent_id:".$page_id];
  $link_database=$form_config["link_database"];
  $link_table=$form_config["link_table"];
  $parent_key=$form_config["parent_key"];
  $link_key=$form_config["link_key"];
  $link_fields=$form_config["link_fields"];
  $list_records=false;
  \webdb\forms\process_filter_sql($form_config);
  if ($form_config["selected_filter_sql"]<>"")
  {
    if ($form_config["sort_sql"]<>"")
    {
      $form_config["sort_sql"]=\webdb\utils\sql_fill("sort_clause",$form_config);
    }
    $sql=\webdb\utils\sql_fill("form_list_fetch_all",$form_config);
    $list_records=\webdb\sql\fetch_prepare($sql,array(),"form_list_fetch_all",false,$form_config["table"],$form_config["database"],$form_config);
  }
  $sql_params=array();
  $sql_params["link_database"]=$link_database;
  $sql_params["link_table"]=$link_table;
  $sql_params["parent_key"]=$parent_key;
  $sql_params["link_key"]=$link_key;
  $sql_params["database"]=$form_config["database"];
  $sql_params["table"]=$form_config["table"];
  $sql_params["selected_filter_condition"]=$form_config["selected_filter_condition"];
  $sql=\webdb\utils\sql_fill("checklist_link_records",$sql_params);
  $sql_params=array();
  $sql_params["parent_key"]=$parent_id;
  $exist_parent_link_records=\webdb\sql\fetch_prepare($sql,$sql_params,"checklist_link_records",false,$link_table,$link_database,$form_config);
  $sql_params=array();
  $sql_params["link_database"]=$link_database;
  $sql_params["link_table"]=$link_table;
  $sql_params["parent_key"]=$parent_key;
  $sql_params["link_key"]=$link_key;
  $sql=\webdb\utils\sql_fill("checklist_exist_links",$sql_params);
  for ($i=0;$i<count($exist_parent_link_records);$i++)
  {
    $link=$exist_parent_link_records[$i];
    $child_id=$link[$link_key];
    $results=\webdb\utils\search_sql_records($list_records,$link_key,$child_id);
    if (count($results)==0)
    {
      continue;
    }
    if (isset($_POST["list_select"][$child_id])==false)
    {
      $where_items=array();
      $where_items[$parent_key]=$parent_id;
      $where_items[$link_key]=$child_id;
      if (\webdb\utils\check_user_form_permission($page_id,"d")==false)
      {
        \webdb\utils\error_message("error: form record(s) delete permission denied");
      }
      \webdb\sql\sql_delete($where_items,$link_table,$link_database,false,$form_config);
    }
  }
  if (isset($_POST["list_select"])==true)
  {
    foreach ($_POST["list_select"] as $child_id => $check_value)
    {
      $where_items=array();
      $where_items[$parent_key]=$parent_id;
      $where_items[$link_key]=$child_id;
      $value_items=array();
      for ($i=0;$i<count($link_fields);$i++)
      {
        $field_name=$link_fields[$i];
        $field_id=$parent_id.\webdb\index\CONFIG_ID_DELIMITER.$child_id;
        if (isset($_POST[$field_name][$field_id])==true)
        {
          $value_items[$field_name]=$_POST[$field_name][$field_id];
        }
      }
      $records=\webdb\sql\fetch_prepare($sql,$where_items,"checklist_exist_links",false,$link_table,$link_database,$form_config);
      if (count($records)==1)
      {
        $record=$records[0];
        foreach ($value_items as $field_name => $field_value)
        {
          if ($record[$field_name]==$field_value)
          {
            unset($value_items[$field_name]);
          }
        }
        if (count($value_items)>0)
        {
          if (\webdb\utils\check_user_form_permission($page_id,"u")==false)
          {
            \webdb\utils\error_message("error: form record(s) update permission denied");
          }
          \webdb\forms\check_required_values($form_config,$value_items);
          \webdb\sql\sql_update($value_items,$where_items,$link_table,$link_database,false,$form_config);
        }
      }
      else
      {
        if (\webdb\utils\check_user_form_permission($page_id,"i")==false)
        {
          \webdb\utils\error_message("error: form record(s) insert permission denied");
        }
        $value_items+=$where_items;
        \webdb\forms\check_required_values($form_config,$value_items);
        \webdb\sql\sql_insert($value_items,$link_table,$link_database,false,$form_config);
      }
    }
  }
  $params=array();
  $params["update"]=$form_config["page_id"];
  \webdb\forms\page_redirect(false,$params);
}

#####################################################################################################

function output_html_includes($form_config)
{
  $result="";
  for ($i=0;$i<count($form_config["html_includes"]);$i++)
  {
    $result.=\webdb\utils\template_fill($form_config["html_includes"][$i]);
  }
  return $result;
}

#####################################################################################################

function output_resource_includes($form_config,$type)
{
  global $settings;
  for ($i=0;$i<count($form_config[$type."_includes"]);$i++)
  {
    $key=$form_config[$type."_includes"][$i];
    $link=\webdb\utils\link_app_resource($key,$type);
    if ($link!==false)
    {
      $settings["links_".$type][$key]=$link;
    }
  }
}

#####################################################################################################

function form_template_fill($name,$params=false)
{
  return \webdb\utils\template_fill("forms".DIRECTORY_SEPARATOR.$name,$params);
}

#####################################################################################################

function process_filter_sql(&$form_config)
{
  global $settings;
  if (isset($_GET["filters"])==true)
  {
    $filters=json_decode($_GET["filters"],true);
    $page_id=$form_config["page_id"];
    if (isset($filters[$page_id])==true)
    {
      $form_config["default_filter"]=$filters[$page_id];
    }
  }
  $form_config["selected_filter_sql"]="";
  $form_config["selected_filter_condition"]="";
  if ($form_config["default_filter"]<>"")
  {
    $filter_name=$form_config["default_filter"];
    if (isset($form_config["filter_options"][$filter_name])==true)
    {
      $form_config["selected_filter_condition"]=$form_config["filter_options"][$filter_name];
      $where_params=array();
      $where_params["where_items"]=$form_config["selected_filter_condition"];
      if ($form_config["selected_filter_condition"]<>"")
      {
        $form_config["selected_filter_condition"]="AND ".$form_config["selected_filter_condition"];
      }
      $form_config["selected_filter_sql"]=\webdb\utils\sql_fill("where_clause",$where_params);
    }
  }
}

#####################################################################################################

function get_subform_content($subform_config,$subform_link_field,$id,$list_only=false,$parent_form_config=false)
{
  global $settings;
  $subform_config["advanced_search"]=false;
  if ($subform_config["checklist"]==true)
  {
    $subform_config["multi_row_delete"]=false;
    $subform_config["delete_cmd"]=false;
    $subform_config["insert_new"]=false;
    $subform_config["insert_row"]=false;
    \webdb\forms\process_filter_sql($subform_config);
    if ($subform_config["records_sql"]=="")
    {
      if ($subform_config["sort_sql"]<>"")
      {
        $subform_config["sort_sql"]=\webdb\utils\sql_fill("sort_clause",$subform_config);
      }
      $sql=\webdb\utils\sql_fill("form_list_fetch_all",$subform_config);
      $records=\webdb\sql\fetch_prepare($sql,array(),"form_list_fetch_all",false,$subform_config["table"],$subform_config["database"],$subform_config);
    }
    else
    {
      $records=\webdb\sql\file_fetch_prepare($subform_config["records_sql"],array(),false,"","",$subform_config);
    }
  }
  else
  {
    if ($subform_config["records_sql"]=="")
    {
      $sql_params=array();
      $sql_params["database"]=$subform_config["database"];
      $sql_params["table"]=$subform_config["table"];
      $sql_params["sort_sql"]=$subform_config["sort_sql"];
      $sql_params["link_field_name"]=$subform_link_field;
      if ($sql_params["sort_sql"]<>"")
      {
        $sql_params["sort_sql"]=\webdb\utils\sql_fill("sort_clause",$sql_params);
      }
      $sql_filename="subform_list_fetch";
      $database=$sql_params["database"];
      $table=$sql_params["table"];
      $sql=\webdb\utils\sql_fill("subform_list_fetch",$sql_params);
    }
    else
    {
      $sql_filename=$subform_config["records_sql"];
      $database="";
      $table="";
      $sql=\webdb\utils\sql_fill($subform_config["records_sql"]);
    }
    $sql_params=array();
    $sql_params["id"]=$id;
    $records=\webdb\sql\fetch_prepare($sql,$sql_params,$sql_filename,false,$table,$database,$subform_config);
  }
  $checklist_link_records=false;
  if ($subform_config["checklist"]==true)
  {
    $sql=\webdb\utils\sql_fill("checklist_link_records",$subform_config);
    $sql_params=array();
    $sql_params["parent_key"]=$id;
    $checklist_link_records=\webdb\sql\fetch_prepare($sql,$sql_params,"checklist_link_records",false,"","",$subform_config);
  }
  $subform_params=array();
  $url_params=array();
  $url_params[$subform_link_field]=$id;
  $subform_config["parent_form_id"]=$id;
  $subform_config["parent_form_config"]=$parent_form_config;
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $event_params=array();
  $event_params["custom_list_content"]=false;
  $event_params["content"]="";
  $event_params["parent_form_config"]=$parent_form_config;
  $event_params["subform_config"]=$subform_config;
  $event_params["parent_id"]=$id;
  if (isset($subform_config["event_handlers"]["on_list"])==true)
  {
    $func_name=$subform_config["event_handlers"]["on_list"];
    if (function_exists($func_name)==true)
    {
      $event_params=call_user_func($func_name,$event_params);
    }
  }
  if ($event_params["custom_list_content"]==false)
  {
    $subform_params["subform"]=list_form_content($subform_config,$records,$url_params,$checklist_link_records);
  }
  else
  {
    $subform_params["subform"]=$event_params["content"];
  }
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $subform_params["subform_style"]="";
  $subform_params["page_id"]=$subform_config["page_id"];
  if ($parent_form_config!==false)
  {
    $subform_page_id=$subform_config["page_id"];
    if (isset($parent_form_config["edit_subforms_styles"][$subform_page_id])==true)
    {
      $subform_params["subform_style"]=$parent_form_config["edit_subforms_styles"][$subform_page_id];
    }
  }
  $subform_params["title"]=$subform_config["title"];
  if ($list_only==true)
  {
    return $subform_params["subform"];
  }
  return \webdb\forms\form_template_fill("subform",$subform_params);
}

#####################################################################################################

function get_calendar()
{
  global $settings;
  $field_names=$settings["calendar_fields"];
  for ($i=0;$i<count($field_names);$i++)
  {
    $field_names[$i]="'".\webdb\forms\js_date_field($field_names[$i])."'";
  }
  $calendar_params=array();
  $calendar_params["calendar_inputs"]=implode(",",$field_names);
  $calendar_params["app_date_format"]=$settings["app_date_format"];
  $calendar_params["calendar_styles_modified"]=\webdb\utils\resource_modified_timestamp("calendar.css");
  $calendar_params["calendar_script_modified"]=\webdb\utils\resource_modified_timestamp("calendar.js");
  return \webdb\forms\form_template_fill("calendar",$calendar_params);
}

#####################################################################################################

function js_date_field($fieldname)
{
  return "date_field__".$fieldname;
}

#####################################################################################################

function load_form_defs()
{
  global $settings;
  $webdb_files=\webdb\utils\load_files($settings["webdb_forms_path"],"","",false);
  $webdb_forms=array();
  foreach ($webdb_files as $fn => $data)
  {
    $data=json_decode($data,true);
    if (isset($data["form_version"])==false)
    {
      \webdb\utils\error_message("error: invalid webdb form def (missing form_version): ".$fn);
    }
    if (isset($data["form_type"])==false)
    {
      \webdb\utils\error_message("error: invalid webdb form def (missing form_type): ".$fn);
    }
    if (isset($data["enabled"])==false)
    {
      \webdb\utils\error_message("error: invalid webdb form def (missing enabled): ".$fn);
    }
    if (isset($data["page_id"])==false)
    {
      \webdb\utils\error_message("error: invalid webdb form def (missing page_id): ".$fn);
    }
    if ($data["enabled"]==false)
    {
      continue;
    }
    $data["basename"]=$fn;
    $full=$settings["webdb_forms_path"].$fn;
    $data["filename"]=$full;
    if ($fn==($settings["webdb_default_form"].".".$data["form_type"]))
    {
      $settings["form_defaults"][$data["form_type"]]=$data;
    }
    else
    {
      $page_id=$data["page_id"];
      $webdb_forms[$page_id]=$data;
    }
  }
  foreach ($webdb_forms as $page_id => $data)
  {
    $form_type=$data["form_type"];
    if (isset($settings["form_defaults"][$form_type])==false)
    {
      \webdb\utils\error_message("error: invalid webdb form def (invalid form_type): ".$data["basename"]);
    }
    $default=$settings["form_defaults"][$form_type];
    if (isset($settings["forms"][$page_id])==true)
    {
      \webdb\utils\error_message("error: form '".$page_id."' already exists: ".$data["basename"]);
    }
    $settings["forms"][$page_id]=array_merge($default,$data);
  }
  if (\webdb\utils\is_app_mode()==true)
  {
    $app_files=\webdb\utils\load_files($settings["app_forms_path"],"","",false);
    foreach ($app_files as $fn => $data)
    {
      $data=json_decode($data,true);
      if (isset($data["form_version"])==false)
      {
        \webdb\utils\error_message("error: invalid app form def (missing form_version): ".$fn);
      }
      if ($data["form_version"]<>$settings["form_defaults"][$data["form_type"]]["form_version"])
      {
        \webdb\utils\error_message("error: invalid form def (incompatible version number): ".$fn);
      }
      if (isset($data["form_type"])==false)
      {
        \webdb\utils\error_message("error: invalid app form def (missing form_type): ".$fn);
      }
      if (isset($data["enabled"])==false)
      {
        \webdb\utils\error_message("error: invalid app form def (missing enabled): ".$fn);
      }
      if (isset($data["page_id"])==false)
      {
        \webdb\utils\error_message("error: invalid app form def (missing page_id): ".$fn);
      }
      if ($data["enabled"]==false)
      {
        continue;
      }
      $form_type=$data["form_type"];
      $fext=pathinfo($fn,PATHINFO_EXTENSION);
      if ($form_type<>$fext)
      {
        \webdb\utils\error_message("error: invalid app form def (form type mismatch): ".$fn);
      }
      if (isset($settings["form_defaults"][$form_type])==false)
      {
        \webdb\utils\error_message("error: invalid app form def (invalid form_type): ".$fn);
      }
      $data["basename"]=$fn;
      $full=$settings["app_forms_path"].$fn;
      $data["filename"]=$full;
      $page_id=$data["page_id"];
      $default=$settings["form_defaults"][$form_type];
      if (isset($settings["forms"][$page_id])==true)
      {
        \webdb\utils\error_message("error: form '".$page_id."' already exists: ".$fn);
      }
      $settings["forms"][$page_id]=array_merge($default,$data);
    }
  }
}

#####################################################################################################

function header_row($form_config)
{
  $params=array();
  $params["check_head"]=\webdb\forms\check_column($form_config,"list_check_head");
  $controls_count=0;
  if ($form_config["edit_cmd"]==true)
  {
    $controls_count++;
  }
  if (($form_config["delete_cmd"]==true) and ($form_config["records_sql"]==""))
  {
    $controls_count++;
  }
  $params["controls_count"]=$controls_count;
  return $params;
}

#####################################################################################################

function config_id_url_value($form_config,$record,$config_key)
{
  if (isset($form_config[$config_key])==false)
  {
    return "";
  }
  if ($form_config[$config_key]=="")
  {
    return "";
  }
  $key_fields=explode(\webdb\index\CONFIG_ID_DELIMITER,$form_config[$config_key]);
  $values=array();
  for ($i=0;$i<count($key_fields);$i++)
  {
    if (isset($record[$key_fields[$i]])==true)
    {
      $values[]=$record[$key_fields[$i]];
    }
    else
    {
      $values[]="";
    }
  }
  return implode(\webdb\index\CONFIG_ID_DELIMITER,$values);
}

#####################################################################################################

function list_row($form_config,$record,$column_format,$row_spans,$lookup_records,$record_index,$checklist_link_record=false)
{
  global $settings;
  $rotate_group_borders=$column_format["rotate_group_borders"];
  $row_params=array();
  $row_params["page_id"]=$form_config["page_id"];
  $row_params["primary_key"]=\webdb\forms\config_id_url_value($form_config,$record,"primary_key");
  $row_params["edit_cmd_id"]=$row_params["primary_key"];
  if ($form_config["edit_cmd"]<>"inline")
  {
    $row_params["edit_cmd_id"]=\webdb\forms\config_id_url_value($form_config,$record,"edit_cmd_id");
  }
  $checklist_row_linked=false;
  if ($form_config["checklist"]==true)
  {
    if ($checklist_link_record!==false)
    {
      $checklist_row_linked=true;
    }
  }
  $fields="";
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    if ($form_config["visible"][$field_name]==false)
    {
      continue;
    }
    if ($control_type=="hidden")
    {
      continue;
    }
    $field_params=array();
    $field_params["primary_key"]=$row_params["primary_key"];
    $field_params["page_id"]=$row_params["page_id"];
    $display_record=$record;
    if (($form_config["checklist"]==true) and (in_array($field_name,$form_config["link_fields"])==true))
    {
      $display_record=$checklist_link_record;
      $field_params["primary_key"]=\webdb\forms\config_id_url_value($form_config,$checklist_link_record,"link_key");
    }
    $field_params["border_color"]=$settings["list_border_color"];
    $field_params["border_width"]=$settings["list_border_width"];
    if (isset($rotate_group_borders[$field_name])==true)
    {
      $field_params["border_color"]=$settings["list_group_border_color"];
      $field_params["border_width"]=$settings["list_group_border_width"];
    }
    if ($control_type=="lookup")
    {
      $field_params["value"]=\webdb\utils\template_fill("empty_cell");
    }
    else
    {
      if ($display_record[$field_name]==="")
      {
        $field_params["value"]=\webdb\utils\template_fill("empty_cell");
      }
      else
      {
        $field_params["value"]=htmlspecialchars($display_record[$field_name]);
      }
    }
    $field_params["field_name"]=$field_name;
    if ((isset($form_config["parent_form_id"])==true) and ($form_config["checklist"]==true))
    {
      $field_params["field_name"].="[".$form_config["parent_form_id"].\webdb\index\CONFIG_ID_DELIMITER.$field_params["primary_key"]."]";
    }
    $field_params["page_id"]=$form_config["page_id"];
    $field_params["group_span"]="";
    $field_params["handlers"]="";
    $field_params["table_cell_style"]="";
    if (isset($form_config["table_cell_styles"][$field_name])==true)
    {
      $field_params["table_cell_style"]=$form_config["table_cell_styles"][$field_name];
    }
    if (isset($record["foreign_key_used"])==true)
    {
      if ($record["foreign_key_used"]>0)
      {
        $field_params["table_cell_style"].=\webdb\forms\form_template_fill("delete_selected_foreign_key_used_style");
      }
    }
    if ($checklist_row_linked==true)
    {
      $field_params["table_cell_style"].=\webdb\forms\form_template_fill("checklist_row_linked_style");
    }
    if (($form_config["edit_cmd"]=="row") or ($form_config["edit_cmd"]=="inline"))
    {
      $field_params["edit_cmd_id"]=$row_params["edit_cmd_id"];
      if ($form_config["checklist"]==false)
      {
        $field_params["handlers"]=\webdb\forms\form_template_fill("list_field_handlers",$field_params);
      }
    }
    $skip_field=false;
    if (in_array($field_name,$form_config["group_by"])==true)
    {
      if ($row_spans[$record_index]==0)
      {
        $skip_field=true;
      }
      else
      {
        $group_span_params=array();
        $group_span_params["row_span"]=$row_spans[$record_index];
        $field_params["group_span"]=\webdb\forms\form_template_fill("group_span",$group_span_params);
      }
    }
    if ($skip_field==false)
    {
      if (($checklist_row_linked==true) and ($form_config["checklist"]==true) and (in_array($field_name,$form_config["link_fields"])==true))
      {
        $control_type_suffix="";
        if ($control_type=="check")
        {
          $control_type_suffix="_check";
        }
        $submit_fields=array();
        $field_params["value"]=output_editable_field($field_params,$display_record,$field_name,$control_type,$form_config,$lookup_records,$submit_fields);
        $fields.=\webdb\forms\form_template_fill("list_field".$control_type_suffix,$field_params);
      }
      else
      {
        $fields.=output_readonly_field($field_params,$control_type,$form_config,$field_name,$lookup_records,$display_record,$checklist_link_record);
      }
    }
    $row_params["default_checked"]="";
    if ($checklist_row_linked==true)
    {
      $row_params["default_checked"]=\webdb\utils\template_fill("checkbox_checked");
    }
    $row_params["check"]=\webdb\forms\check_column($form_config,"list_check",$row_params);
    $row_params["controls"]="";
    if (($form_config["edit_cmd"]=="button") or ($form_config["edit_cmd"]=="inline"))
    {
      if ($form_config["edit_button_caption"]<>"")
      {
        $control_params=$row_params;
        $control_params["button_caption"]=$form_config["edit_button_caption"];
        $control_params["edit_cmd_id"]=\webdb\forms\config_id_url_value($form_config,$record,"edit_cmd_id");
        $row_params["controls"]=\webdb\forms\form_template_fill("list_row_edit",$control_params);
      }
    }
    if (($form_config["delete_cmd"]==true) and ($form_config["records_sql"]==""))
    {
      $row_params["controls"].=\webdb\forms\form_template_fill("list_row_del",$row_params);
    }
    $row_params["controls_min_width"]=$column_format["controls_min_width"];
  }
  $row_params["fields"]=$fields;
  $row_params["last_group_border_width"]=$settings["list_border_width"];
  $row_params["last_group_border_color"]=$settings["list_border_color"];
  if ($column_format["group_border_last"]==true)
  {
    $row_params["last_group_border_width"]=$settings["list_group_border_width"];
    $row_params["last_group_border_color"]=$settings["list_group_border_color"];
  }
  return \webdb\forms\form_template_fill("list_row",$row_params);
}

#####################################################################################################

function lookup_field_display_value($lookup_config,$lookup_record)
{
  $display_field_names=explode(",",$lookup_config["display_field"]);
  $display_values=array();
  for ($i=0;$i<count($display_field_names);$i++)
  {
    $display_field_name=$display_field_names[$i];
    if (isset($lookup_record[$display_field_name])==false)
    {
      \webdb\utils\error_message("error: lookup display field not found: ".$display_field_name);
    }
    $display_values[]=$lookup_record[$display_field_name];
  }
  if (isset($lookup_config["display_format"])==true)
  {
    $format=trim($lookup_config["display_format"]);
    if ($format<>"")
    {
      return vsprintf($format,$display_values);
    }
  }
  return implode(\webdb\index\LOOKUP_DISPLAY_FIELD_DELIM,$display_values);
}

#####################################################################################################

function output_readonly_field($field_params,$control_type,$form_config,$field_name,$lookup_records,$display_record,$checklist_link_record=false)
{
  global $settings;
  switch ($control_type)
  {
    case "hidden":
      return "";
    case "lookup":
      $field_params["value"]="";
      $lookup_config=$form_config["lookups"][$field_name];
      $key_field_name=$lookup_config["key_field"];
      $sibling_field_name=$lookup_config["sibling_field"];
      if (($form_config["checklist"]==false) or (array_key_exists($sibling_field_name,$display_record)==true))
      {
        for ($i=0;$i<count($lookup_records[$field_name]);$i++)
        {
          $lookup_record=$lookup_records[$field_name][$i];
          $key_value=$lookup_record[$key_field_name];
          if ($display_record[$sibling_field_name]==$key_value)
          {
            $field_params["value"]=\webdb\forms\lookup_field_display_value($lookup_config,$lookup_record);
            break;
          }
        }
      }
      else
      {
        if ($checklist_link_record!==false)
        {
          $key_value=$checklist_link_record[$key_field_name];
          $sibling_value=$display_record[$sibling_field_name];
          for ($i=0;$i<count($lookup_records[$field_name]);$i++)
          {
            $lookup_record=$lookup_records[$field_name][$i];
            if (($lookup_record[$key_field_name]==$key_value) and ($lookup_record[$sibling_field_name]==$sibling_value))
            {
              $field_params["value"]=\webdb\forms\lookup_field_display_value($lookup_config,$lookup_record);
              break;
            }
          }
        }
      }
      $field_params["value"]=htmlspecialchars(str_replace(\webdb\index\LINEBREAK_DB_DELIM,\webdb\index\LINEBREAK_PLACEHOLDER,$field_params["value"]));
      $field_params["value"]=str_replace(\webdb\index\LINEBREAK_PLACEHOLDER,\webdb\utils\template_fill("break"),$field_params["value"]);
      return \webdb\forms\form_template_fill("list_field",$field_params);
    case "memo":
      $field_params["value"]=htmlspecialchars(str_replace(\webdb\index\LINEBREAK_DB_DELIM,\webdb\index\LINEBREAK_PLACEHOLDER,$display_record[$field_name]));
      $field_params["value"]=str_replace(\webdb\index\LINEBREAK_PLACEHOLDER,\webdb\utils\template_fill("break"),$field_params["value"]);
      $field_params["value"]=\webdb\forms\memo_field_formatting($field_params["value"]);
      return \webdb\forms\form_template_fill("list_field",$field_params);
    case "span":
    case "file":
    case "text":
      $field_params["value"]=htmlspecialchars(str_replace(\webdb\index\LINEBREAK_DB_DELIM,\webdb\index\LINEBREAK_PLACEHOLDER,$display_record[$field_name]));
      $field_params["value"]=str_replace(\webdb\index\LINEBREAK_PLACEHOLDER,\webdb\utils\template_fill("break"),$field_params["value"]);
      return \webdb\forms\form_template_fill("list_field",$field_params);
    case "combobox":
    case "listbox":
    case "radiogroup":
      $field_params["value"]=htmlspecialchars($display_record[$field_name]);
      $lookup_config=$form_config["lookups"][$field_name];
      for ($i=0;$i<count($lookup_records[$field_name]);$i++)
      {
        $key_field_name=$lookup_config["key_field"];
        $display_value=\webdb\forms\lookup_field_display_value($lookup_config,$lookup_records[$field_name][$i]);
        $key_value=$lookup_records[$field_name][$i][$key_field_name];
        if ($display_record[$field_name]==$key_value)
        {
          $field_params["value"]=htmlspecialchars($display_value);
          break;
        }
      }
      return \webdb\forms\form_template_fill("list_field",$field_params);
    case "date":
      if ($display_record[$field_name]==null)
      {
        $field_params["value"]=\webdb\utils\template_fill("empty_cell");
      }
      else
      {
        $field_params["value"]=date($settings["app_date_format"],strtotime($display_record[$field_name]));
      }
      return \webdb\forms\form_template_fill("list_field",$field_params);
    case "checkbox":
      if ($display_record[$field_name]==true)
      {
        $field_params["check_tick_class"]="check_tick";
        if (isset($form_config["table_cell_styles"][$field_name])==true)
        {
          $field_params["check_tick_class"]="check_tick_override";
        }
        $field_params["value"]=\webdb\forms\form_template_fill("check_tick",$field_params);
      }
      else
      {
        $field_params["check_cross_class"]="check_cross";
        if (isset($form_config["table_cell_styles"][$field_name])==true)
        {
          $field_params["check_cross_class"]="check_cross_override";
        }
        $field_params["value"]=\webdb\forms\form_template_fill("check_cross",$field_params);
      }
      return \webdb\forms\form_template_fill("list_field_check",$field_params);
  }
  return "";
}

#####################################################################################################

function memo_field_formatting($value)
{
  global $settings;
  $subdir=$settings["format_tag_templates_subdirectory"];
  $template_prefix=$subdir.DIRECTORY_SEPARATOR;
  $format_tag_templates=array();
  foreach ($settings["templates"] as $name => $content)
  {
    if (substr($name,0,strlen($template_prefix))==$template_prefix)
    {
      $name=substr($name,strlen($template_prefix));
      $name_parts=explode("_",$name);
      $position=array_pop($name_parts);
      $tag=trim(implode("_",$name_parts));
      if (($position<>"open") and ($position<>"close"))
      {
        continue;
      }
      if ($tag=="")
      {
        continue;
      }
      if (isset($format_tag_templates[$tag])==false)
      {
        $format_tag_templates[$tag]=array();
      }
      $format_tag_templates[$tag][$position]=$content;
    }
  }
  foreach ($format_tag_templates as $tag => $positions)
  {
    if ((isset($positions["open"])==false) or (isset($positions["close"])==false))
    {
      continue;
    }
    $open_markup="&lt;".$tag."&gt;";
    $close_markup="&lt;/".$tag."&gt;";
    $value=str_replace($open_markup,$positions["open"],$value);
    $value=str_replace($close_markup,$positions["close"],$value);
  }
  return $value;
}

#####################################################################################################

function check_column($form_config,$template,$params=array())
{
  if ($form_config["checklist"]==true)
  {
    return \webdb\forms\form_template_fill($template,$params);
  }
  if (($form_config["records_sql"]=="") and ($form_config["multi_row_delete"]==true))
  {
    return \webdb\forms\form_template_fill($template,$params);
  }
  return "";
}

#####################################################################################################

function list_row_controls($form_config,&$submit_fields,$operation,$column_format,$record,$field_name_prefix="")
{
  global $settings;
  $rotate_group_borders=$column_format["rotate_group_borders"];
  $row_params=array();
  $row_params["primary_key"]=\webdb\forms\config_id_url_value($form_config,$record,"primary_key");
  $row_params["page_id"]=$form_config["page_id"];
  $row_params["check"]=\webdb\forms\check_column($form_config,"list_check_insert");
  $lookup_records=lookup_records($form_config,false);
  $fields="";
  $hidden_fields="";
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    if ($form_config["visible"][$field_name]==false)
    {
      continue;
    }
    $field_params=array();
    $field_params["primary_key"]=$row_params["primary_key"];
    $field_params["page_id"]=$row_params["page_id"];
    $field_params["handlers"]="";
    $field_params["border_color"]=$settings["list_border_color"];
    $field_params["border_width"]=$settings["list_border_width"];
    if (isset($rotate_group_borders[$field_name])==true)
    {
      $field_params["border_color"]=$settings["list_group_border_color"];
      $field_params["border_width"]=$settings["list_group_border_width"];
    }
    $field_params["field_name"]=$field_name_prefix.$field_name;
    $field_params["group_span"]="";
    $field_params["table_cell_style"]="";
    if (isset($form_config["table_cell_styles"][$field_name])==true)
    {
      $field_params["table_cell_style"]=$form_config["table_cell_styles"][$field_name];
    }
    $control_type_suffix="";
    if ($control_type=="check")
    {
      $control_type_suffix="_check";
    }
    if ($control_type<>"lookup")
    {
      $field_params["value"]=output_editable_field($field_params,$record,$field_name,$control_type,$form_config,$lookup_records,$submit_fields);
    }
    else
    {
      $field_params["value"]=$form_config["default_values"][$field_name];
    }
    if ($control_type<>"hidden")
    {
      $fields.=\webdb\forms\form_template_fill("list_field".$control_type_suffix,$field_params);
    }
    else
    {
      $hidden_fields.=$field_params["value"];
    }
  }
  $row_params["controls_min_width"]=$column_format["controls_min_width"];
  $row_params["fields"]=$fields;
  $row_params["hidden_fields"]=$hidden_fields;
  $row_params["last_group_border_width"]=$settings["list_border_width"];
  $row_params["last_group_border_color"]=$settings["list_border_color"];
  if ($column_format["group_border_last"]==true)
  {
    $row_params["last_group_border_width"]=$settings["list_group_border_width"];
    $row_params["last_group_border_color"]=$settings["list_group_border_color"];
  }
  return \webdb\forms\form_template_fill("list_".$operation."_row",$row_params);
}

#####################################################################################################

function output_editable_field(&$field_params,$record,$field_name,$control_type,$form_config,$lookup_records,&$submit_fields)
{
  global $settings;
  $field_params["field_value"]="";
  if (isset($record[$field_name])==true)
  {
    $field_params["field_value"]=htmlspecialchars($record[$field_name]);
  }
  $field_params["page_id"]=$form_config["page_id"];
  $field_params["control_style"]="";
  if (isset($form_config["control_styles"][$field_name])==true)
  {
    $field_params["control_style"]=$form_config["control_styles"][$field_name];
  }
  $field_params["disabled"]=\webdb\forms\field_disabled($form_config,$field_name);
  $field_params["js_events"]=\webdb\forms\field_js_events($form_config,$field_name,$record);
  switch ($control_type)
  {
    case "file":
      # TODO
      break;
    case "lookup":
      $field_params["field_key"]="";
      if (isset($record[$field_name])==true)
      {
        $field_params["field_key"]=$record[$field_name];
      }
      $field_params["field_value"]="";
      $lookup_config=$form_config["lookups"][$field_name];
      $key_field_name=$lookup_config["key_field"];
      $sibling_field_name=$lookup_config["sibling_field"];
      for ($i=0;$i<count($lookup_records[$field_name]);$i++)
      {
        $key_value=$lookup_records[$field_name][$i][$key_field_name];
        $display_value=\webdb\forms\lookup_field_display_value($lookup_config,$lookup_records[$field_name][$i]);
        if ($record[$sibling_field_name]==$key_value)
        {
          $field_params["field_key"]=htmlspecialchars($key_value);
          $field_params["field_value"]=$display_value;
          break;
        }
      }
      $field_params["field_value"]=htmlspecialchars(str_replace(\webdb\index\LINEBREAK_DB_DELIM,\webdb\index\LINEBREAK_PLACEHOLDER,$field_params["field_value"]));
      $field_params["field_value"]=str_replace(\webdb\index\LINEBREAK_PLACEHOLDER,\webdb\utils\template_fill("break"),$field_params["field_value"]);
      break;
    case "span":
      break;
    case "text":
      if (isset($form_config["disabled"][$field_name])==true)
      {
        if ($form_config["disabled"][$field_name]==true)
        {
          $field_params["control_style"].=\webdb\forms\form_template_fill("disabled_text_control_style");
        }
      }
      $submit_fields[]=$field_params["field_name"];
      break;
    case "memo":
      $field_params["field_value"]=htmlspecialchars(str_replace(\webdb\index\LINEBREAK_DB_DELIM,\webdb\index\LINEBREAK_PLACEHOLDER,$record[$field_name]));
      $field_params["field_value"]=str_replace(\webdb\index\LINEBREAK_PLACEHOLDER,PHP_EOL,$field_params["field_value"]);
      $submit_fields[]=$field_params["field_name"];
      break;
    case "combobox":
    case "listbox":
    case "radiogroup":
      $option_template="select";
      if ($control_type=="radiogroup")
      {
        $option_template="radio";
      }
      $option_params=array();
      $option_params["name"]=$field_params["field_name"];
      $option_params["value"]="";
      $option_params["caption"]="";
      $option_params["disabled"]=\webdb\forms\field_disabled($form_config,$field_name);
      $option_params["js_events"]=\webdb\forms\field_js_events($form_config,$field_name,$record);
      $options=\webdb\utils\template_fill($option_template."_option",$option_params);
      $records=\webdb\forms\lookup_field_data($form_config,$field_name);
      $lookup_config=$form_config["lookups"][$field_name];
      $parent_list=false;
      if ((isset($lookup_config["database"])==true) and (isset($lookup_config["table"])==true))
      {
        if (($lookup_config["database"]==$form_config["database"]) and ($lookup_config["table"]==$form_config["table"]))
        {
          $parent_list=true;
        }
      }
      $selected_found=false;
      for ($i=0;$i<count($records);$i++)
      {
        $loop_record=$records[$i];
        $option_params=array();
        $option_params["name"]=$field_name;
        $option_params["value"]=htmlspecialchars($loop_record[$lookup_config["key_field"]]);
        $display_value=\webdb\forms\lookup_field_display_value($lookup_config,$loop_record);
        $option_params["caption"]=htmlspecialchars($display_value);
        $option_params["disabled"]=\webdb\forms\field_disabled($form_config,$field_name);
        $option_params["js_events"]=\webdb\forms\field_js_events($form_config,$field_name,$record);
        $excluded_parent=false;
        if (isset($lookup_config["exclude_parent"])==true)
        {
          if ($lookup_config["exclude_parent"]==true)
          {
            if ($record[$lookup_config["key_field"]]==$option_params["value"])
            {
              $excluded_parent=true;
            }
          }
        }
        if ($excluded_parent==true)
        {
          continue;
        }
        if (($loop_record[$lookup_config["key_field"]]==$record[$field_name]) and ($selected_found==false))
        {
          $options.=\webdb\utils\template_fill($option_template."_option_selected",$option_params);
          $selected_found=true;
        }
        else
        {
          $options.=\webdb\utils\template_fill($option_template."_option",$option_params);
        }
      }
      $field_params["options"]=$options;
      $submit_fields[]=$field_params["field_name"];
      break;
    case "date":
      $settings["calendar_fields"][]=$field_name;
      if ($record[$field_name]==null)
      {
        $field_params["field_value"]="";
        $field_params["iso_field_value"]="";
      }
      else
      {
        $field_params["field_value"]=date($settings["app_date_format"],strtotime($record[$field_name]));
        $field_params["iso_field_value"]=date("Y-m-d",strtotime($field_params["field_value"]));
      }
      $submit_fields[]="iso_".$field_params["field_name"];
      break;
    case "checkbox":
      $field_params["checked"]="";
      if ($record[$field_name]==1)
      {
        $field_params["checked"]=\webdb\utils\template_fill("checkbox_checked");
      }
      $submit_fields[]=$field_params["field_name"];
      break;
    case "hidden":
      $submit_fields[]=$field_params["field_name"];
      break;
    default:
      \webdb\utils\error_message("error: invalid control type '".$control_type."' for field '".$field_name."' on form '".$form_config["page_id"]."'");
  }
  return \webdb\forms\form_template_fill("field_edit_".$control_type,$field_params);
}

#####################################################################################################

function get_column_format_data($form_config)
{
  global $settings;
  $data=array();
  $data["max_field_name_width"]=0;
  $data["visible_cols"]=array();
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    if (isset($form_config["visible"][$field_name])==false)
    {
      \webdb\utils\error_message("error: field visibility not found for '".$field_name."' on form '".$form_config["page_id"]."'");
    }
    if ($form_config["visible"][$field_name]==false)
    {
      continue;
    }
    if ($control_type=="hidden")
    {
      continue;
    }
    $caption=$form_config["captions"][$field_name];
    $lines=explode("@@break@@",$caption);
    for ($i=0;$i<count($lines);$i++)
    {
      $line=htmlspecialchars_decode($lines[$i]);
      $box=\imagettfbbox(10,0,$settings["gd_ttf"],$line); # requires php-gd package
      $width=abs($box[4]-$box[0]);
      if ($width>$data["max_field_name_width"])
      {
        $data["max_field_name_width"]=$width;
      }
    }
    $data["visible_cols"][]=$field_name;
  }
  $data["rotate_span_width"]=$data["max_field_name_width"]+10;
  $data["rotate_height"]=round($data["rotate_span_width"]*0.707)+30;
  $data["group_caption_first_left"]=$data["rotate_height"]+1-20;
  $data["group_caption_left"]=$data["rotate_height"]-20;
  $data["controls_min_width"]=round($data["rotate_span_width"]*0.707)-20;
  $data["rotate_group_borders"]=array();
  $data["left_group_borders"]=array();
  $data["right_group_borders"]=array();
  $data["caption_groups"]="";
  $data["group_border_last"]=false;
  if (count($form_config["caption_groups"])>0)
  {
    $row_params=\webdb\forms\header_row($form_config);
    $field_headers="";
    $in_group=false;
    $first_group=true;
    $finished_group=false;
    $previous_field_name="";
    foreach ($form_config["control_types"] as $field_name => $control_type)
    {
      if ($form_config["visible"][$field_name]==false)
      {
        continue;
      }
      if ($control_type=="hidden")
      {
        continue;
      }
      if ($finished_group==true)
      {
        $data["rotate_group_borders"][$field_name]=true;
      }
      foreach ($form_config["caption_groups"] as $group_name => $group_field_names)
      {
        if ($group_field_names[0]==$field_name)
        {
          # first column of group
          if ($previous_field_name=="")
          {
            $data["left_group_borders"][$field_name]=true;
          }
          else
          {
            if ($finished_group==false)
            {
              $data["right_group_borders"][$previous_field_name]=true;
            }
          }
          $data["rotate_group_borders"][$field_name]=true;
          if ($finished_group==false)
          {
            $first_group=true;
          }
          $finished_group=false;
          $in_group=true;
          $group_params=array();
          $group_params["group_caption"]=$group_name;
          $group_params["field_count"]=count($group_field_names);
          if ($first_group==true)
          {
            $group_params["group_caption_first_left"]=$data["group_caption_first_left"];
            $field_headers.=\webdb\forms\form_template_fill("caption_group_first",$group_params);
          }
          else
          {
            $group_params["group_caption_left"]=$data["group_caption_left"];
            $field_headers.=\webdb\forms\form_template_fill("caption_group",$group_params);
          }
          $first_group=false;
          continue 2;
        }
        if (($group_field_names[count($group_field_names)-1]==$field_name) and ($in_group==true))
        {
          # last column of group
          $in_group=false;
          $finished_group=true;
          $data["right_group_borders"][$field_name]=true;
          continue 2;
        }
      }
      if ($in_group==false)
      {
        $finished_group=false;
        $field_headers.=\webdb\forms\form_template_fill("list_field_header_group");
      }
      $previous_field_name=$field_name;
    }
    $row_params["field_headers"]=$field_headers;
    $data["caption_groups"]=\webdb\forms\form_template_fill("group_header_row",$row_params);
    if ($finished_group==true)
    {
      $data["group_border_last"]=true;
    }
  }
  return $data;
}

#####################################################################################################

function lookup_records($form_config,$include_lookups=true)
{
  global $settings;
  $lookup_records=array();
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    if ($include_lookups==false)
    {
      if ($control_type=="lookup")
      {
        continue;
      }
    }
    if (isset($form_config["lookups"][$field_name])==true)
    {
      $lookup_records[$field_name]=\webdb\forms\lookup_field_data($form_config,$field_name);
    }
  }
  return $lookup_records;
}

#####################################################################################################

function list_form_content($form_config,$records=false,$insert_default_params=false,$checklist_link_records=false)
{
  global $settings;
  if (($form_config["records_sql"]<>"") and ($records===false))
  {
    $sql=\webdb\utils\sql_fill($form_config["records_sql"]);
    $records=\webdb\sql\fetch_prepare($sql,array(),$form_config["records_sql"],false,"","",$form_config);
  }
  $form_params=array();
  $form_params["page_id"]=$form_config["page_id"];
  $form_params["edit_cmd_page_id"]=$form_config["edit_cmd_page_id"];
  $form_params["insert_cmd_page_id"]=$form_config["page_id"];
  if ($form_config["insert_cmd_page_id"]<>"")
  {
    $form_params["insert_cmd_page_id"]=$form_config["insert_cmd_page_id"];
  }
  $form_params["insert_default_params"]="";
  if ($insert_default_params!==false)
  {
    foreach ($insert_default_params as $param_name => $param_value)
    {
      $form_params["insert_default_params"].="&".urlencode($param_name)."=".urlencode($param_value);
    }
  }
  $column_format=\webdb\forms\get_column_format_data($form_config);
  $left_group_borders=$column_format["left_group_borders"];
  $right_group_borders=$column_format["right_group_borders"];
  $rotate_group_borders=$column_format["rotate_group_borders"];
  $form_params["caption_groups"]=$column_format["caption_groups"];
  $field_headers="";
  $z_index=901;
  $last_visible_col=end($column_format["visible_cols"]);
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    if ($form_config["visible"][$field_name]==false)
    {
      continue;
    }
    if ($control_type=="hidden")
    {
      continue;
    }
    $header_params=array();
    $header_params["z_index"]=$z_index;
    $z_index--;
    $header_params["rotate_div_translate"]=1;
    $header_params["field_name"]=$form_config["captions"][$field_name];
    $header_params["rotate_border_color"]=$settings["list_diagonal_border_color"];
    $header_params["left_border_color"]=$settings["list_border_color"];
    $header_params["right_border_color"]=$settings["list_border_color"];
    $header_params["left_border_width"]=$settings["list_border_width"];
    $header_params["rotate_height"]=$column_format["rotate_height"];
    $header_params["rotate_span_width"]=$column_format["rotate_span_width"];
    $header_params["rotate_border_width"]=$settings["list_border_width"];
    if (isset($rotate_group_borders[$field_name])==true)
    {
      $header_params["rotate_border_color"]=$settings["list_group_border_color"];
      $header_params["rotate_border_width"]=$settings["list_group_border_width"];
    }
    if (isset($left_group_borders[$field_name])==true)
    {
      $header_params["rotate_border_color"]=$settings["list_group_border_color"];
      $header_params["rotate_border_width"]=$settings["list_group_border_width"];
    }
    if (isset($right_group_borders[$field_name])==true)
    {
      $header_params["right_border_color"]=$settings["list_group_border_color"];
      $header_params["right_border_width"]=$settings["list_group_border_width"];
    }
    $header_params["border_left"]=0;
    $header_params["border_right"]=0;
    if (strtolower($settings["browser_info"]["browser"])=="firefox")
    {
      $header_params["rotate_bottom_border"]=$settings["list_border_width"]+1;
      $header_params["right_border_width"]=$settings["list_border_width"];
      if ($field_name==$last_visible_col)
      {
        $header_params["right_border_width"]="0";
      }
      if (isset($right_group_borders[$field_name])==true)
      {
        $header_params["right_border_width"]=$settings["list_group_border_width"];
      }
      $header_params["border_left"]=-1;
      if (isset($left_group_borders[$field_name])==true)
      {
        $header_params["border_left"]=-1;
      }
      if (isset($right_group_borders[$field_name])==true)
      {
        $header_params["border_right"]=-1;
      }
    }
    else # chrome
    {
      $header_params["rotate_bottom_border"]=$settings["list_border_width"];
      $header_params["right_border_width"]="0";
      if (isset($right_group_borders[$field_name])==true)
      {
        $header_params["right_border_width"]=$settings["list_group_border_width"];
      }
      $header_params["border_left"]=0;
      if (isset($left_group_borders[$field_name])==true)
      {
        $header_params["border_right"]=-1;
      }
      if (isset($right_group_borders[$field_name])==true)
      {
        $header_params["border_right"]=-1;
      }
    }
    if (($column_format["group_border_last"]==true) and ($field_name==$last_visible_col))
    {
      $header_params["right_border_color"]=$settings["list_group_border_color"];
      $header_params["right_border_width"]=$settings["list_group_border_width"];
    }
    $field_headers.=\webdb\forms\form_template_fill("list_field_header",$header_params);
  }
  if ($column_format["group_border_last"]==true)
  {
    $header_params=array();
    $header_params["z_index"]=$z_index;
    $header_params["rotate_div_translate"]=1;
    if (strtolower($settings["browser_info"]["browser"])=="firefox")
    {
      $header_params["rotate_div_translate"]=2;
    }
    $header_params["field_name"]="";
    $header_params["rotate_height"]=$column_format["rotate_height"];
    $header_params["left_border_color"]=$settings["list_group_border_color"];
    $header_params["left_border_width"]=$settings["list_group_border_width"];
    $header_params["rotate_bottom_border"]=0;
    $header_params["right_border_width"]=0;
    $header_params["border_left"]=-1;
    $header_params["border_right"]=0;
    $header_params["rotate_span_width"]=$column_format["rotate_span_width"];
    $header_params["right_border_color"]=$settings["list_border_color"];
    $header_params["rotate_border_width"]=$settings["list_group_border_width"];
    $header_params["rotate_border_color"]=$settings["list_group_border_color"];
    $field_headers.=\webdb\forms\form_template_fill("list_field_header",$header_params);
  }
  $head_params=\webdb\forms\header_row($form_config);
  $form_params=array_merge($form_params,$head_params);
  $form_params["field_headers"]=$field_headers;
  $rows="";
  if ($records===false)
  {
    if (isset($_GET["sort"])==true)
    {
      $sort_field=$_GET["sort"];
      if (isset($form_config["control_types"][$sort_field])==true)
      {
        $sort_field_params=array();
        $sort_field_params["field_name"]=$sort_field;
        $sort_field_params["direction"]="ASC";
        if (isset($_GET["dir"])==true)
        {
          $dir=strtoupper($_GET["dir"]);
          if (($dir=="ASC") or ($dir=="DESC"))
          {
            $sort_field_params["direction"]=$dir;
          }
        }
        $sort_sql=\webdb\utils\sql_fill("sort_field",$sort_field_params);
        $form_config["sort_sql"]=$sort_sql;
      }
    }
    \webdb\forms\process_filter_sql($form_config);
    if ($form_config["sort_sql"]<>"")
    {
      $form_config["sort_sql"]=\webdb\utils\sql_fill("sort_clause",$form_config);
    }
    $sql=\webdb\utils\sql_fill("form_list_fetch_all",$form_config);
    $records=\webdb\sql\fetch_prepare($sql,array(),"form_list_fetch_all",false,"","",$form_config);
  }
  $previous_group_by_fields=false;
  $row_spans=array();
  $current_group=0;
  for ($i=0;$i<count($records);$i++)
  {
    $record=$records[$i];
    $group_by_fields=\webdb\utils\group_by_fields($form_config,$record);
    if ($previous_group_by_fields===$group_by_fields)
    {
      $row_spans[$i]=0;
      $row_spans[$current_group]=$row_spans[$current_group]+1;
    }
    else
    {
      $row_spans[$i]=1;
      $current_group=$i;
      $previous_group_by_fields=$group_by_fields;
    }
  }
  $lookup_records=lookup_records($form_config);
  if ($form_config["checklist"]==true)
  {
    # arrange checklist records with checked first
    $primary_key=$form_config["primary_key"];
    $link_key=$form_config["link_key"];
    $checked_records=array();
    $unchecked_records=array();
    for ($i=0;$i<count($records);$i++)
    {
      $record=$records[$i];
      for ($j=0;$j<count($checklist_link_records);$j++)
      {
        $test_link_record=$checklist_link_records[$j];
        if ($record[$primary_key]==$test_link_record[$link_key])
        {
          $checked_records[]=$record;
          continue 2;
        }
      }
      $unchecked_records[]=$record;
    }
    $records=array_merge($checked_records,$unchecked_records);
  }
  for ($i=0;$i<count($records);$i++)
  {
    $record=$records[$i];
    # TODO: is_row_locked($schema,$table,$key_field,$key_value)
    $checklist_link_record=false;
    if ($form_config["checklist"]==true)
    {
      for ($j=0;$j<count($checklist_link_records);$j++)
      {
        $test_link_record=$checklist_link_records[$j];
        $primary_key=$form_config["primary_key"];
        $link_key=$form_config["link_key"];
        if ($record[$primary_key]==$test_link_record[$link_key])
        {
          $checklist_link_record=$test_link_record;
          break;
        }
      }
    }
    \webdb\forms\process_computed_fields($form_config,$record);
    $rows.=\webdb\forms\list_row($form_config,$record,$column_format,$row_spans,$lookup_records,$i,$checklist_link_record);
  }
  $form_params["insert_row_controls"]="";
  if (($form_config["insert_row"]==true) and ($form_config["records_sql"]==""))
  {
    $insert_fields=array();
    $default_values=\webdb\forms\default_values($form_config);
    $rows.=\webdb\forms\list_row_controls($form_config,$insert_fields,"insert",$column_format,$default_values);
    for ($i=0;$i<count($insert_fields);$i++)
    {
      $insert_fields[$i]="'".$insert_fields[$i]."'";
    }
    $form_params["insert_row_controls"]=implode(",",$insert_fields);
  }
  $form_params["rows"]=$rows;
  $form_params["advanced_search_control"]="";
  $form_params["sort_field_control"]="";
  $form_params["insert_control"]="";
  $form_params["delete_selected_control"]="";
  $form_params["checklist_update_control"]="";
  if (($form_config["records_sql"]=="") or ($form_config["checklist"]==true))
  {
    if ($form_config["advanced_search"]==true)
    {
      $form_params["advanced_search_control"]=\webdb\forms\form_template_fill("list_advanced_search",$form_config);
    }
    if ($form_config["insert_new"]==true)
    {
      $form_params["insert_control"]=\webdb\forms\form_template_fill("list_insert",$form_config);
    }
    if ($form_config["multi_row_delete"]==true)
    {
      $form_params["delete_selected_control"]=\webdb\forms\form_template_fill("list_del_selected");
    }
    if ($form_config["checklist"]==true)
    {
      $form_config["checklist_update_control_filtered"]="";
      if ($form_config["default_filter"]<>"")
      {
        $form_config["checklist_update_control_filtered"]="Filtered ";
      }
      $form_params["checklist_update_control"]=\webdb\forms\form_template_fill("checklist_update",$form_config);
    }
  }
  $form_params["row_edit_mode"]=$form_config["edit_cmd"];
  $form_params["custom_form_above"]="";
  $form_params["custom_form_below"]="";
  if ($form_config["custom_form_above_template"]<>"")
  {
    $form_params["custom_form_above"]=\webdb\forms\form_template_fill($form_config["custom_form_above_template"]);
  }
  if ($form_config["custom_form_below_template"]<>"")
  {
    $form_params["custom_form_below"]=\webdb\forms\form_template_fill($form_config["custom_form_below_template"]);
  }
  $form_params=\webdb\forms\handle_custom_form_above_event($form_config,$form_params);
  $form_params=\webdb\forms\handle_custom_form_below_event($form_config,$form_params);
  $form_params["redirect"]="";
  $form_params["parent_id"]="";
  $form_params["parent_form_page_id"]="";
  if ((isset($form_config["parent_form_id"])==true) and (isset($form_config["parent_form_config"])==true))
  {
    $form_params["parent_id"]=$form_config["parent_form_id"];
    $form_params["parent_form_page_id"]=$form_config["parent_form_config"]["page_id"];
    $url_params=array();
    if (isset($_GET["redirect"])==true)
    {
      $url_params["redirect_url"]=urlencode($_GET["redirect"]);
    }
    else
    {
      $url_params["redirect_url"]=urlencode(\webdb\utils\get_url());
    }
    $form_params["redirect"]=\webdb\forms\form_template_fill("redirect_url_param",$url_params);
  }
  $form_params["filters"]="";
  if (isset($_GET["filters"])==true)
  {
    $form_params["filters"]="&filters=".urlencode($_GET["filters"]);
  }
  if (($form_params["insert_control"]=="") and ($form_params["delete_selected_control"]=="") and ($form_params["advanced_search_control"]=="") and ($form_params["sort_field_control"]=="") and ($form_params["checklist_update_control"]==""))
  {
    $form_params["delete_selected_control"]=\webdb\utils\template_fill("break");
  }
  return \webdb\forms\form_template_fill("list",$form_params);
}

#####################################################################################################

function advanced_search($form_config)
{
  global $settings;
  $rows="";
  $sql_params=array();
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    if ($form_config["visible"][$field_name]==false)
    {
      continue;
    }
    if ($control_type=="hidden")
    {
      continue;
    }
    $field_value="";
    if (isset($_POST[$field_name])==true)
    {
      $field_value=$_POST[$field_name];
    }
    $field_params=array();
    $field_params["field_name"]=$field_name;
    $field_params["control_style"]="";
    $field_params["field_value"]=htmlspecialchars($field_value);
    $search_control_type="text";
    switch ($control_type)
    {
      case "lookup":
        continue 2;
      case "span":
      case "checkbox":
      case "text":
      case "memo":
      case "combobox":
      case "listbox":
      case "radiogroup":
        if ($field_value<>"")
        {
          $sql_params[$field_name]=$field_value;
        }
        break;
      case "date":
        if (isset($_POST["iso_".$field_name])==true)
        {
          $field_value=$_POST["iso_".$field_name];
        }
        $date_operators=array("<","<=","=",">=",">","<>");
        $selected_option="=";
        if (isset($_POST["search_operator_".$field_name])==true)
        {
          $selected_option=$_POST["search_operator_".$field_name];
        }
        $field_params["options"]="";
        for ($i=0;$i<count($date_operators);$i++)
        {
          $option_params=array();
          $option_params["value"]=$date_operators[$i];
          $option_params["caption"]=htmlspecialchars($date_operators[$i]);
          if ($date_operators[$i]==$selected_option)
          {
            $field_params["options"].=\webdb\utils\template_fill("select_option_selected",$option_params);
          }
          else
          {
            $field_params["options"].=\webdb\utils\template_fill("select_option",$option_params);
          }
        }
        $search_control_type="date";
        $settings["calendar_fields"][]=$field_name;
        if ($field_value=="")
        {
          $field_params["field_value"]="";
          $field_params["iso_field_value"]="";
        }
        else
        {
          $field_params["field_value"]=date($settings["app_date_format"],strtotime($field_value));
          $field_params["iso_field_value"]=date("Y-m-d",strtotime($field_value));
        }
        if ($field_value<>"")
        {
          $sql_params[$field_name]=$field_value;
        }
        break;
      default:
        \webdb\utils\error_message("error: invalid control type '".$control_type."' for field '".$field_name."' on form '".$form_config["page_id"]."'");
    }
    $row_params=array();
    $row_params["field_name"]=$form_config["captions"][$field_name];
    foreach ($form_config["caption_groups"] as $group_caption => $group_fields)
    {
      if (in_array($field_name,$group_fields)==true)
      {
        $row_params["field_name"]=$group_caption.": ".$row_params["field_name"];
        break;
      }
    }
    $row_params["field_value"]=\webdb\forms\form_template_fill("advanced_search_".$search_control_type,$field_params);
    $row_params["interface_button"]="";
    $rows.=\webdb\forms\form_template_fill("field_row",$row_params);
  }
  $form_params=array();
  $form_params["title"]=$form_config["title"];
  $form_params["rows"]=$rows;
  $form_params["page_id"]=$form_config["page_id"];
  $search_page_params=array();
  $search_page_params["advanced_search"]=\webdb\forms\form_template_fill("advanced_search",$form_params);
  $fieldnames=array_keys($sql_params);
  $values=array_values($sql_params);
  $placeholders=array_map("\webdb\sql\callback_prepare",$fieldnames);
  $quoted_fieldnames=array_map("\webdb\sql\callback_quote",$fieldnames);
  $records=array();
  $prepared_where="";
  if (count($sql_params)>0)
  {
    $inner_joins=array();
    foreach ($form_config["lookups"] as $field_name => $lookup_data)
    {
      if (isset($sql_params[$field_name])==false)
      {
        continue;
      }
      $join_params=array();
      $join_params["database"]=$lookup_data["database"];
      $join_params["table"]=$lookup_data["table"];
      $join_params["key_field"]=$lookup_data["key_field"];
      $join_params["main_database"]=$form_config["database"];
      $join_params["main_table"]=$form_config["table"];
      $join_params["main_key_field"]=$field_name;
      $inner_joins[]=\webdb\utils\sql_fill("form_list_advanced_search_join",$join_params);
      $key=array_search($field_name,$fieldnames);
      $field_params=array();
      $field_params["database"]=$lookup_data["database"];
      $field_params["table"]=$lookup_data["table"];
      $field_params["field_name"]=$lookup_data["display_field"];
      $full_field_name=\webdb\utils\sql_fill("full_field_name",$field_params);
      $quoted_fieldnames[$key]=$full_field_name;
    }
    $conditions=array();
    for ($i=0;$i<count($fieldnames);$i++)
    {
      $value=$values[$i];
      $operator=substr($value,0,2);
      switch ($operator)
      {
        case ">=":
        case "<=":
        case "<>":
          $sql_params[$fieldnames[$i]]=trim(substr($value,2));
          break;
        default:
          $operator=substr($value,0,1);
          switch ($operator)
          {
            case "<":
            case ">":
            case "=":
              $sql_params[$fieldnames[$i]]=trim(substr($value,1));
              break;
            default:
              $operator=" like ";
              switch ($form_config["control_types"][$fieldnames[$i]])
              {
                case "checkbox":
                  $operator="=";
                  break;
                case "date":
                  $operator=$_POST["search_operator_".$fieldnames[$i]];
                  break;
              }
              break;
          }
      }
      $conditions[]="(".$quoted_fieldnames[$i].$operator.$placeholders[$i].")";
      $prepared_where="WHERE (".implode(" AND ",$conditions).")";
    }
    $params=array();
    $params["database"]=$form_config["database"];
    $params["table"]=$form_config["table"];
    $params["inner_joins"]=implode(" ",$inner_joins);
    $params["prepared_where"]=$prepared_where;
    $params["sort_sql"]=$form_config["sort_sql"];
    $sql=\webdb\utils\sql_fill("form_list_advanced_search",$params);
    $records=\webdb\sql\fetch_prepare($sql,$sql_params,"form_list_advanced_search",false,$form_config["table"],$form_config["database"],$form_config);
  }
  $form_config["insert_new"]=false;
  $form_config["insert_row"]=false;
  $form_config["advanced_search"]=false;
  $form_config["delete_cmd"]=false;
  $form_config["multi_row_delete"]=false;
  $search_page_params["advanced_search_results"]=\webdb\forms\list_form_content($form_config,$records,false);
  $search_page_params["form_script_modified"]=\webdb\utils\resource_modified_timestamp("list.js");
  $search_page_params["form_styles_modified"]=\webdb\utils\resource_modified_timestamp("list.css");
  $search_page_params["title"]=$form_config["title"];
  $result=array();
  $result["title"]=$form_config["title"].": Advanced Search";
  $result["content"]=\webdb\forms\form_template_fill("advanced_search_page",$search_page_params);
  return $result;
}

#####################################################################################################

function default_values($form_config)
{
  global $settings;
  $record=$form_config["default_values"];
  foreach ($record as $fieldname => $value)
  {
    foreach ($settings as $setting_key => $setting_value)
    {
      $template='$$'.$setting_key.'$$';
      if (strpos($value,$template)===false)
      {
        continue;
      }
      $record[$fieldname]=str_replace($template,$setting_value,$value);
    }
    if (isset($_GET[$fieldname])==true)
    {
      $record[$fieldname]=htmlspecialchars($_GET[$fieldname]);
    }
  }
  return $record;
}

#####################################################################################################

function insert_form($form_config)
{
  global $settings;
  $record=\webdb\forms\default_values($form_config);
  $data=\webdb\forms\output_editor($form_config,$record,"Insert","Insert",0);
  $insert_page_params=array();
  $insert_page_params["record_insert_form"]=$data["content"];
  $insert_page_params["form_script_modified"]=\webdb\utils\resource_modified_timestamp("list.js");
  $insert_page_params["form_styles_modified"]=\webdb\utils\resource_modified_timestamp("list.css");
  $insert_page_params["command_caption_noun"]=$form_config["command_caption_noun"];
  $result=array();
  $result["title"]=$data["title"];
  $result["content"]=\webdb\forms\form_template_fill("insert_page",$insert_page_params);
  return $result;
}

#####################################################################################################

function edit_form($form_config,$id)
{
  global $settings;
  $record=\webdb\forms\get_record_by_id($form_config,$id,"edit_cmd_id");
  \webdb\forms\process_computed_fields($form_config,$record);
  $subforms="";
  foreach ($form_config["edit_subforms"] as $subform_page_id => $subform_link_field)
  {
    $subform_config=\webdb\forms\get_form_config($subform_page_id,false);
    $subforms.=\webdb\forms\get_subform_content($subform_config,$subform_link_field,$id,false,$form_config);
  }
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $event_params=array();
  $event_params["custom_content"]=false;
  $event_params["content"]="";
  $event_params["form_config"]=$form_config;
  $event_params["id"]=$id;
  $event_params["record"]=$record;
  if (isset($form_config["event_handlers"]["on_edit"])==true)
  {
    $func_name=$form_config["event_handlers"]["on_edit"];
    if (function_exists($func_name)==true)
    {
      $event_params=call_user_func($func_name,$event_params);
    }
  }
  if ($event_params["custom_content"]==false)
  {
    $data=\webdb\forms\output_editor($form_config,$record,"Edit","Update",$id);
  }
  else
  {
    $data=\webdb\forms\output_editor($form_config,$record,"Edit","Update",$id,$event_params["content"]);
  }
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  $edit_page_params=array();
  $edit_page_params["record_edit_form"]=$data["content"];
  $edit_page_params["subforms"]=$subforms;
  $edit_page_params["form_script_modified"]=\webdb\utils\resource_modified_timestamp("list.js");
  $edit_page_params["form_styles_modified"]=\webdb\utils\resource_modified_timestamp("list.css");
  $edit_page_params["command_caption_noun"]=$form_config["command_caption_noun"];
  $edit_page_params["page_id"]=$form_config["page_id"];
  $edit_page_params["edit_cmd_page_id"]=$form_config["edit_cmd_page_id"];
  $content=\webdb\forms\form_template_fill("edit_page",$edit_page_params);
  $result=array();
  $result["title"]=$data["title"];
  $result["content"]=$content;
  return $result;
}

#####################################################################################################

function lookup_field_data($form_config,$field_name)
{
  global $settings;
  if (isset($form_config["lookups"][$field_name])==false)
  {
    \webdb\utils\error_message("error: invalid lookup config for field '".$field_name."' in form '".$form_config["page_id"]."' (lookup config missing)");
  }
  $lookup_config=$form_config["lookups"][$field_name];
  if (isset($form_config["lookups"][$field_name]["value_list"])==true)
  {
    return $form_config["lookups"][$field_name]["value_list"];
  }
  if (isset($lookup_config["lookup_sql_file"])==false)
  {
    $lookup_config["lookup_sql_file"]="";
  }
  if (isset($lookup_config["order_by"])==false)
  {
    $lookup_config["order_by"]="";
  }
  if ($lookup_config["lookup_sql_file"]=="")
  {
    $filename="form_lookup";
    $database=$lookup_config["database"];
    $table=$lookup_config["table"];
    if ($lookup_config["order_by"]=="")
    {
      $display_fields=explode(",",$lookup_config["display_field"]);
      $first_display_field=array_shift($display_fields);
      $lookup_config["order_by"]=$first_display_field." ASC";
    }
    $sql=\webdb\utils\sql_fill("form_lookup",$lookup_config);
  }
  else
  {
    $filename=$lookup_config["lookup_sql_file"];
    $database="";
    $table="";
    $sql=\webdb\utils\sql_fill($lookup_config["lookup_sql_file"]);
  }
  $where_items=array();
  if ((isset($form_config["parent_form_config"])==true) and (isset($form_config["parent_form_id"])==true))
  {
    if (($form_config["parent_form_config"]!==false) and (isset($lookup_config["parent_key_field"])==true))
    {
      $parent_ids=\webdb\forms\config_id_conditions($form_config["parent_form_config"],$form_config["parent_form_id"],"primary_key");
      foreach ($parent_ids as $parent_field_name => $parent_field_value)
      {
        if ($parent_field_name==$lookup_config["parent_key_field"])
        {
          $where_items[$parent_field_name]=$parent_field_value;
        }
      }
    }
  }
  return \webdb\sql\fetch_prepare($sql,$where_items,$filename,false,$table,$database,$form_config);
}

#####################################################################################################

function get_interface_button($form_config,$record,$field_name,$field_value)
{
  foreach ($form_config["custom_interfaces"] as $interface_function => $interface_field_names)
  {
    if (in_array($field_name,$interface_field_names)==true)
    {
      if (function_exists($interface_function)==true)
      {
        return call_user_func($interface_function,$form_config,$record,$field_name,$field_value);
      }
    }
  }
  return \webdb\utils\template_fill("empty_cell");
}

#####################################################################################################

function output_editor($form_config,$record,$command,$verb,$id,$custom_content=false)
{
  global $settings;
  $submit_fields=array();
  $lookup_records=lookup_records($form_config);
  $rows=array();
  $hidden_fields="";
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    if ($form_config["editor_visible"]==true)
    {
      if ($form_config["visible"][$field_name]==false)
      {
        continue;
      }
    }
    $field_value=$record[$field_name];
    $field_params=array();
    $field_params["page_id"]=$form_config["page_id"];
    $field_params["disabled"]=\webdb\forms\field_disabled($form_config,$field_name);
    $field_params["js_events"]=\webdb\forms\field_js_events($form_config,$field_name,$record);
    $field_params["field_name"]=$field_name;
    $field_params["field_value"]=htmlspecialchars($field_value);
    $row_params=array();
    $row_params["field_name"]=$form_config["captions"][$field_name];
    foreach ($form_config["caption_groups"] as $group_caption => $group_fields)
    {
      if (in_array($field_name,$group_fields)==true)
      {
        $row_params["field_name"]=$group_caption.": ".$row_params["field_name"];
        break;
      }
    }
    $field_params["control_style"]="";
    if (isset($form_config["control_styles"][$field_name])==true)
    {
      $field_params["control_style"]=$form_config["control_styles"][$field_name];
    }
    $row_params["field_value"]=output_editable_field($field_params,$record,$field_name,$control_type,$form_config,$lookup_records,$submit_fields);
    $row_params["interface_button"]=\webdb\forms\get_interface_button($form_config,$record,$field_name,$field_value);
    if ($control_type<>"hidden")
    {
      if ($form_config["custom_".strtolower($command)."_template"]=="")
      {
        $rows[]=\webdb\forms\form_template_fill("field_row",$row_params);
      }
      else
      {
        $rows[$field_name]=$row_params["field_value"];
      }
    }
    else
    {
      $hidden_fields.=$row_params["field_value"];
    }
  }
  $form_params=$form_config;
  $form_params["hidden_fields"]=$hidden_fields;
  $form_params["rows"]=implode("",$rows);
  if ($custom_content===false)
  {
    if ($form_config["custom_".strtolower($command)."_template"]=="")
    {
      $form_params[strtolower($command)."_table"]=\webdb\forms\form_template_fill(strtolower($command)."_table",$form_params);
    }
    else
    {
      $form_params[strtolower($command)."_table"]=\webdb\utils\template_fill($form_config["custom_".strtolower($command)."_template"],$rows);
    }
  }
  else
  {
    $form_params[strtolower($command)."_table"]=$custom_content;
  }
  $form_params["id"]=$id;
  $form_params["confirm_caption"]=$verb." ".$form_config["command_caption_noun"];
  $form_params["custom_form_above"]="";
  $form_params["custom_form_below"]="";
  $form_params=\webdb\forms\handle_custom_form_above_event($form_config,$form_params);
  $form_params=\webdb\forms\handle_custom_form_below_event($form_config,$form_params);
  $content=\webdb\forms\form_template_fill(strtolower($command),$form_params);
  $title=$form_config["title"].": ".$command;
  if ($form_config["edit_title_field"]<>"")
  {
    $value=$record[$form_config["edit_title_field"]];
    if ($value<>"")
    {
      $title.=" ".$value;
    }
  }
  $result=array();
  $result["title"]=$title;
  $result["content"]=$content;
  return $result;
}

#####################################################################################################

function field_disabled($form_config,$field_name)
{
  if (isset($form_config["disabled"][$field_name])==true)
  {
    if ($form_config["disabled"][$field_name]==true)
    {
      return \webdb\utils\template_fill("disabled_control");
    }
  }
  return "";
}

#####################################################################################################

function field_js_events($form_config,$field_name,$record)
{
  if (isset($form_config["js_events"][$field_name])==true)
  {
    $events=$form_config["js_events"][$field_name];
    $result="";
    foreach ($events as $event_type => $event_data)
    {
      if ($event_data["handler"]=="")
      {
        continue;
      }
      $event_data["event_type"]=$event_type;
      $event_data["field_name"]=$field_name;
      $result.=\webdb\forms\form_template_fill("field_js_event",$event_data);
    }
    return $result;
  }
  return "";
}

#####################################################################################################

function process_form_data_fields($form_config,$post_override=false)
{
  global $settings;
  $value_items=array();
  $post_fields=$_POST;
  if ($post_override!==false)
  {
    $post_fields=$post_override;
  }
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    if ($form_config["visible"][$field_name]==false)
    {
      continue;
    }
    switch ($control_type)
    {
      case "lookup":
      case "span":
        continue 2;
    }
    $value_items[$field_name]=null;
    switch ($control_type)
    {
      case "checkbox":
        if (isset($post_fields[$field_name])==true)
        {
          if ($post_fields[$field_name]=="false")
          {
            $value_items[$field_name]=0;
          }
          else
          {
            $value_items[$field_name]=1;
          }
        }
        else
        {
          $value_items[$field_name]=0;
        }
        break;
      case "date":
        $value_items[$field_name]=null;
        if (isset($post_fields["iso_".$field_name])==true)
        {
          if ($post_fields["iso_".$field_name]<>"")
          {
            $value_items[$field_name]=$post_fields["iso_".$field_name];
          }
        }
        break;
    }
    if (isset($post_fields[$field_name])==false)
    {
      continue;
    }
    switch ($control_type)
    {
      case "text":
      case "hidden":
        $value_items[$field_name]=$post_fields[$field_name];
        break;
      case "combobox":
      case "listbox":
      case "radiogroup":
        if ($post_fields[$field_name]<>"")
        {
          $value_items[$field_name]=$post_fields[$field_name];
        }
        break;
      case "memo":
        $value_items[$field_name]=str_replace(PHP_EOL,\webdb\index\LINEBREAK_DB_DELIM,$post_fields[$field_name]);
        break;
    }
  }
  return $value_items;
}

#####################################################################################################

function insert_record($form_config)
{
  global $settings;
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"i")==false)
  {
    \webdb\utils\error_message("error: record insert permission denied for form '".$page_id."'");
  }
  $value_items=\webdb\forms\process_form_data_fields($form_config);
  \webdb\forms\check_required_values($form_config,$value_items);
  $handled=\webdb\forms\handle_insert_record_event($form_config,$value_items);
  if ($handled===false)
  {
    \webdb\sql\sql_insert($value_items,$form_config["table"],$form_config["database"],false,$form_config);
    $id=\webdb\sql\sql_last_insert_autoinc_id();
  }
  else
  {
    $id=$handled;
  }
  if ($form_config["edit_cmd_page_id"]<>"")
  {
    $record=\webdb\forms\get_record_by_id($form_config,$id,"primary_key");
    $form_config["id"]=\webdb\forms\config_id_url_value($form_config,$record,"edit_cmd_id");
    $url=trim(\webdb\forms\form_template_fill("edit_redirect_url",$form_config));
    \webdb\utils\redirect($url);
  }
  \webdb\forms\page_redirect(false,false,$id);
}

#####################################################################################################

function process_computed_fields($form_config,&$value_items)
{
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    if (isset($form_config["computed_values"][$field_name])==false)
    {
      continue;
    }
    $func_name=$form_config["computed_values"][$field_name];
    if (function_exists($func_name)==true)
    {
      $value_items[$field_name]=call_user_func($func_name,$field_name,$value_items);
    }
  }
}

#####################################################################################################

function handle_insert_record_event($form_config,$value_items)
{
  if (isset($form_config["event_handlers"]["on_insert_record"])==true)
  {
    $func_name=$form_config["event_handlers"]["on_insert_record"];
    if (function_exists($func_name)==true)
    {
      # return either false (not handled => runs sql insert) or return record id for edit cmd
      return call_user_func($func_name,$form_config,$value_items);
    }
  }
  return false;
}

#####################################################################################################

function handle_update_record_event($event_params)
{
  $form_config=$event_params["form_config"];
  if (isset($form_config["event_handlers"]["on_update_record"])==true)
  {
    $func_name=$form_config["event_handlers"]["on_update_record"];
    if (function_exists($func_name)==true)
    {
      return call_user_func($func_name,$event_params);
    }
  }
  return $event_params;
}

#####################################################################################################

function handle_delete_record_event($form_config,$id)
{
  # TODO
}

#####################################################################################################

function handle_delete_selected_records_event($form_config,$list_select)
{
  # TODO
}

#####################################################################################################

function handle_custom_form_above_event($form_config,$form_params)
{
  if (isset($form_config["event_handlers"]["on_custom_form_above"])==true)
  {
    $func_name=$form_config["event_handlers"]["on_custom_form_above"];
    if (function_exists($func_name)==true)
    {
      $form_params["custom_form_above"]=call_user_func($func_name,$form_config,$form_params);
    }
  }
  return $form_params;
}

#####################################################################################################

function handle_custom_form_below_event($form_config,$form_params)
{
  if (isset($form_config["event_handlers"]["on_custom_form_below"])==true)
  {
    $func_name=$form_config["event_handlers"]["on_custom_form_below"];
    if (function_exists($func_name)==true)
    {
      $form_params["custom_form_below"]=call_user_func($func_name,$form_config,$form_params);
    }
  }
  return $form_params;
}

#####################################################################################################

function update_record($form_config,$id)
{
  global $settings;
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"u")==false)
  {
    \webdb\utils\error_message("error: record update permission denied form form '".$page_id."'");
  }
  $value_items=\webdb\forms\process_form_data_fields($form_config);
  \webdb\forms\check_required_values($form_config,$value_items);
  $where_items=\webdb\forms\config_id_conditions($form_config,$id,"edit_cmd_id");
  $event_params=array();
  $event_params["handled"]=false;
  $event_params["form_config"]=$form_config;
  $event_params["id"]=$id;
  $event_params["where_items"]=$where_items;
  $event_params["value_items"]=$value_items;
  $event_params=\webdb\forms\handle_update_record_event($event_params);
  $where_items=$event_params["where_items"];
  $value_items=$event_params["value_items"];
  $params=false;
  if ($event_params["handled"]==false)
  {
    \webdb\sql\sql_update($value_items,$where_items,$form_config["table"],$form_config["database"],false,$form_config);
    $params=array();
    $params["update"]=$form_config["page_id"];
  }
  if ($form_config["edit_cmd_page_id"]<>"")
  {
    $record=\webdb\forms\get_record_by_id($form_config,$id,"primary_key");
    $form_config["id"]=\webdb\forms\config_id_url_value($form_config,$record,"edit_cmd_id");
    $url=trim(\webdb\forms\form_template_fill("edit_redirect_url",$form_config));
    \webdb\utils\redirect($url);
  }
  \webdb\forms\page_redirect(false,$params);
}

#####################################################################################################

function check_required_values($form_config,$value_items)
{
  for ($i=0;$i<count($form_config["required_values"]);$i++)
  {
    $field_name=$form_config["required_values"][$i];
    if (empty($value_items[$field_name])==true)
    {
      \webdb\utils\error_message("error: value required for field `".$field_name."`");
    }
  }
}

#####################################################################################################

function config_id_conditions($form_config,$id,$config_key)
{
  $fieldnames=explode(\webdb\index\CONFIG_ID_DELIMITER,$form_config[$config_key]);
  $values=explode(\webdb\index\CONFIG_ID_DELIMITER,$id);
  $items=array();
  for ($i=0;$i<count($fieldnames);$i++)
  {
    $items[$fieldnames[$i]]=$values[$i];
  }
  return $items;
}

#####################################################################################################

function get_record_by_id($form_config,$id,$config_key)
{
  global $settings;
  if (isset($form_config["event_handlers"]["on_get_record_by_id"])==true)
  {
    $func_name=$form_config["event_handlers"]["on_get_record_by_id"];
    if (function_exists($func_name)==true)
    {
      $event_params=array();
      $event_params["page_id"]=$form_config["page_id"];
      $event_params["form_config"]=$form_config;
      $event_params["id"]=$id;
      $event_params["config_key"]=$config_key;
      return call_user_func($func_name,$event_params);
    }
  }
  $items=\webdb\forms\config_id_conditions($form_config,$id,$config_key);
  $form_config["where_conditions"]=\webdb\sql\build_prepared_where($items);
  $sql=\webdb\utils\sql_fill("form_list_fetch_by_id",$form_config);
  $records=\webdb\sql\fetch_prepare($sql,$items,"form_list_fetch_by_id",false,$form_config["table"],$form_config["database"],$form_config);
  if (count($records)==0)
  {
    \webdb\utils\error_message("error: no records found for id '".$id."' in query: ".$sql);
  }
  if (count($records)>1)
  {
    \webdb\utils\error_message("error: id '".$id."' is not unique in query: ".$sql);
  }
  return $records[0];
}

#####################################################################################################

function delete_confirmation($form_config,$id)
{
  global $settings;
  $record=\webdb\forms\get_record_by_id($form_config,$id,"primary_key");
  $form_params=array();
  $records=array();
  $records[]=$record;
  $list_form_config=$form_config;
  $list_form_config["multi_row_delete"]=false;
  $list_form_config["delete_cmd"]=false;
  $list_form_config["edit_cmd"]="none";
  $list_form_config["insert_new"]=false;
  $list_form_config["insert_row"]=false;
  $list_form_config["advanced_search"]=false;
  foreach ($list_form_config["control_types"] as $field_name => $control_type)
  {
    $list_form_config["visible"][$field_name]=true;
  }
  $form_params["list"]=\webdb\forms\list_form_content($list_form_config,$records,false);
  $form_params["page_id"]=$form_config["page_id"];
  $form_params["primary_key"]=$id;
  $form_params["command_caption_noun"]=$form_config["command_caption_noun"];
  $foreign_keys=\webdb\sql\foreign_key_used($form_config["database"],$form_config["table"],$record);
  if ($foreign_keys!==false)
  {
    $table_list=array();
    for ($i=0;$i<count($foreign_keys);$i++)
    {
      $key_def=$foreign_keys[$i]["def"];
      $key_form_config=\webdb\forms\get_form_config($key_def["TABLE_NAME"],true);
      $caption=$key_def["TABLE_SCHEMA"].".".$key_def["TABLE_NAME"];
      if ($key_form_config!==false)
      {
        $caption=$key_form_config["title"];
      }
      $caption.=" (x".count($foreign_keys[$i]["dat"])." records)";
      $table_list[]=$caption;
    }
    $form_params["table_list"]=implode(\webdb\utils\template_fill("break"),$table_list);
    $form_params["delete_button"]=\webdb\forms\form_template_fill("delete_cancel_controls",$form_params);
  }
  else
  {
    $form_params["delete_button"]=\webdb\forms\form_template_fill("delete_confirm_controls",$form_params);
  }
  $form_params["form_script_modified"]=\webdb\utils\resource_modified_timestamp("list.js");
  $form_params["form_styles_modified"]=\webdb\utils\resource_modified_timestamp("list.css");
  $form_params["redirect"]="";
  if (isset($_GET["redirect"])==true)
  {
    $url_params=array();
    $url_params["redirect_url"]=urlencode($_GET["redirect"]);
    $form_params["redirect"]=\webdb\forms\form_template_fill("redirect_url_param",$url_params);
  }
  $result=array();
  $result["title"]=$form_config["page_id"].": confirm deletion";
  $result["content"]=\webdb\forms\form_template_fill("delete_confirm",$form_params);
  return $result;
}

#####################################################################################################

function page_redirect($form_config=false,$params=false,$append="")
{
  if ($form_config===false)
  {
    if (isset($_GET["redirect"])==true)
    {
      $url=$_GET["redirect"];
    }
    else
    {
      $url=\webdb\utils\get_url();
    }
  }
  else
  {
    $url=\webdb\forms\form_url($form_config);
  }
  if ($params!==false)
  {
    $query=parse_url($url,PHP_URL_QUERY);
    $base_url=substr($url,0,strpos($url,$query));
    $query_params=array();
    parse_str($query,$query_params);
    foreach ($params as $param_name => $param_value)
    {
      $query_params[$param_name]=$param_value;
    }
    $params=array();
    foreach ($query_params as $param_name => $param_value)
    {
      if ($param_value=="")
      {
        $params[]=$param_name;
      }
      else
      {
        $params[]=$param_name."=".$param_value;
      }
    }
    $url=$base_url.implode("&",$params);
  }
  \webdb\utils\redirect($url.$append);
}

#####################################################################################################

function form_url($form_config)
{
  global $settings;
  $url_params=array();
  $url_params["page_id"]=$form_config["page_id"];
  return \webdb\forms\form_template_fill("form_url",$url_params);
}

#####################################################################################################

function delete_record($form_config,$id)
{
  global $settings;
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"d")==false)
  {
    \webdb\utils\error_message("error: record delete permission denied for form '".$page_id."'");
  }
  $where_items=\webdb\forms\config_id_conditions($form_config,$id,"primary_key");
  \webdb\sql\sql_delete($where_items,$form_config["table"],$form_config["database"],false,$form_config);
  \webdb\forms\page_redirect();
}

#####################################################################################################

function delete_selected_confirmation($form_config)
{
  global $settings;
  if (isset($_POST["list_select"])==false)
  {
    \webdb\utils\error_message("No records selected.");
  }
  $foreign_key_defs=\webdb\sql\get_foreign_key_defs($form_config["database"],$form_config["table"]);
  $form_params=array();
  $records=array();
  $hidden_id_fields="";
  $foreign_key_used=false;
  foreach ($_POST["list_select"] as $id => $value)
  {
    $record=\webdb\forms\get_record_by_id($form_config,$id,"primary_key");
    $record["fk_table_list"]="NONE";
    \webdb\forms\process_computed_fields($form_config,$record);
    $foreign_keys=\webdb\sql\foreign_key_used($form_config["database"],$form_config["table"],$record,$foreign_key_defs);
    if ($foreign_keys!==false)
    {
      $record["foreign_key_used"]=true;
      $table_list=array();
      for ($i=0;$i<count($foreign_keys);$i++)
      {
        $key_def=$foreign_keys[$i]["def"];
        $key_form_config=get_form_config($key_def["TABLE_NAME"],true);
        $caption=$key_def["TABLE_SCHEMA"].".".$key_def["TABLE_NAME"];
        if ($key_form_config!==false)
        {
          $caption=$key_form_config["title"];
        }
        $caption.=" (x".count($foreign_keys[$i]["dat"])." records)";
        $table_list[]=$caption;
      }
      $record["fk_table_list"]=implode("\\n",$table_list);
      $foreign_key_used=true;
    }
    $id_params=array();
    $id_params["primary_key"]=\webdb\forms\config_id_url_value($form_config,$record,"primary_key");
    $hidden_id_fields.=\webdb\forms\form_template_fill("list_del_selected_hidden_id_field",$id_params);
    $records[]=$record;
  }
  $form_params["hidden_id_fields"]=$hidden_id_fields;
  $list_form_config=$form_config;
  $list_form_config["control_types"]["fk_table_list"]="memo";
  $list_form_config["captions"]["fk_table_list"]="Table References";
  $list_form_config["visible"]["fk_table_list"]=true;
  $list_form_config["table_cell_styles"]["fk_table_list"]=\webdb\forms\form_template_fill("delete_selected_foreign_key_ref_style");
  $list_form_config["multi_row_delete"]=false;
  $list_form_config["delete_cmd"]=false;
  $list_form_config["edit_cmd"]="none";
  $list_form_config["insert_new"]=false;
  $list_form_config["insert_row"]=false;
  $list_form_config["advanced_search"]=false;
  $form_params["records"]=\webdb\forms\list_form_content($list_form_config,$records,false);
  $form_params["page_id"]=$form_config["page_id"];
  $form_params["command_caption_noun"]=$form_config["command_caption_noun"];
  $form_params["delete_all_button"]=\webdb\forms\form_template_fill("delete_selected_cancel_controls",$form_params);
  if ($foreign_key_used==false)
  {
    $form_params["delete_all_button"]=\webdb\forms\form_template_fill("delete_selected_confirm_controls",$form_params);
  }
  $form_params["form_script_modified"]=\webdb\utils\resource_modified_timestamp("list.js");
  $form_params["form_styles_modified"]=\webdb\utils\resource_modified_timestamp("list.css");
  $form_params["redirect"]="";
  if (isset($_GET["redirect"])==true)
  {
    $url_params=array();
    $url_params["redirect_url"]=urlencode($_GET["redirect"]);
    $form_params["redirect"]=\webdb\forms\form_template_fill("redirect_url_param",$url_params);
  }
  $content=\webdb\forms\form_template_fill("list_del_selected_confirm",$form_params);
  $title=$form_config["page_id"].": confirm selected deletion";
  \webdb\utils\output_page($content,$title);
  \webdb\forms\page_redirect();
}

#####################################################################################################

function delete_selected_records($form_config)
{
  global $settings;
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"d")==false)
  {
    \webdb\utils\error_message("error: record(s) delete permission denied for form '".$page_id."'");
  }
  foreach ($_POST["id"] as $id => $value)
  {
    $where_items=\webdb\forms\config_id_conditions($form_config,$id,"primary_key");
    \webdb\sql\sql_delete($where_items,$form_config["table"],$form_config["database"],false,$form_config);
  }
  \webdb\forms\page_redirect();
}

#####################################################################################################
