<?php

namespace webdb\forms;

#####################################################################################################

function get_form_config($page_id,$return=false,$bypass_auth=false)
{
  global $settings;
  foreach ($settings["forms"] as $key => $form_config)
  {
    if ($form_config["enabled"]==false)
    {
      continue;
    }
    if ($page_id===$form_config["page_id"])
    {
      if ($bypass_auth==false)
      {
        if (\webdb\utils\check_user_form_permission($page_id,"r")==false)
        {
          if ($return!==false)
          {
            return false;
          }
          \webdb\utils\error_message("error: form read permission denied: ".$page_id);
        }
      }
      return $form_config;
    }
  }
  if ($return!==false)
  {
    return false;
  }
  \webdb\utils\error_message("error: form config not found: ".$page_id);
}

#####################################################################################################

function set_confirm_status_cookie($form_config,$status_message)
{
  global $settings;
  $cookie_name=$settings["app_name"].":confirm_status:".$form_config["page_id"];
  #setcookie($cookie_name,$status_message,0,"/",$_SERVER["HTTP_HOST"],false,false);
  \webdb\utils\webdb_setcookie_raw($cookie_name,$status_message,0,false);
}

#####################################################################################################

function file_field_view($form_config)
{
  global $settings;
  $parts=\webdb\utils\webdb_explode(":",$_GET["file_view"]);
  if (count($parts)<>3)
  {
    \webdb\utils\error_message("invalid 'file_view' parameter");
  }
  $page_id=$parts[0];
  $record_id=$parts[1];
  $field_name=$parts[2];
  $file_form_config=\webdb\forms\get_form_config($page_id,false);
  $conditions=\webdb\forms\config_id_conditions($file_form_config,$record_id,"primary_key");
  $sql_params=array();
  $sql_params["database"]=$file_form_config["database"];
  $sql_params["table"]=$file_form_config["table"];
  $sql_params["where_conditions"]=\webdb\sql\build_prepared_where($conditions);
  $sql=\webdb\utils\sql_fill("form_list_fetch_by_id",$sql_params);
  $records=\webdb\sql\fetch_prepare($sql,$conditions,"form_list_fetch_by_id",false,$sql_params["table"],$sql_params["database"],$file_form_config);
  $record_filename=$records[0][$field_name];
  $ext=pathinfo($record_filename,PATHINFO_EXTENSION);
  if (isset($settings["permitted_upload_types"][\webdb\utils\webdb_strtolower($ext)])==false)
  {
    \webdb\utils\error_message("error: file type not permitted");
  }
  $target_filename=\webdb\forms\get_uploaded_full_filename($file_form_config,$record_id,$field_name);
  $settings["ignore_ob_postprocess"]=true;
  ob_end_clean(); # discard buffer & disable output buffering (\webdb\utils\ob_postprocess function is still called)
  header("Cache-Control: no-cache");
  header("Expires: -1");
  header("Pragma: no-cache");
  header("Accept-Ranges: bytes");
  header("Content-Type: ".$settings["permitted_upload_types"][\webdb\utils\webdb_strtolower($ext)]);
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

function file_field_delete($form_config)
{
  global $settings;
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"u")==false)
  {
    \webdb\stubs\stub_error("error: record update permission denied for form '".$form_config["page_id"]."'");
  }
  $record_id=false;
  $post_name=$_GET["file_delete"];
  $file_delete=\webdb\utils\webdb_explode(":",$post_name);
  $page_id=array_shift($file_delete);
  if ($page_id<>$form_config["page_id"])
  {
    \webdb\stubs\stub_error("page_id mismatch");
  }
  $tag=array_shift($file_delete);
  if (($tag=="edit_control") and (count($file_delete)==2))
  {
    $record_id=$file_delete[0];
    $field_name=$file_delete[1];
  }
  if ($record_id===false)
  {
    \webdb\stubs\stub_error("missing record id parameter");
  }
  if (\webdb\forms\delete_file($form_config,$record_id,$field_name)==false)
  {
    \webdb\stubs\stub_error("error deleting file");
  }
  $post_override=array();
  $post_override[$post_name]=-1;
  $settings["sql_check_post_params_override"]=true;
  \webdb\stubs\list_edit($record_id,$form_config,$post_override);
}

#####################################################################################################

function delete_file($form_config,$record_id,$field_name)
{
  global $settings;
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"u")==false)
  {
    \webdb\stubs\stub_error("error: record update permission denied for form '".$form_config["page_id"]."'");
  }
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"d")==false)
  {
    \webdb\stubs\stub_error("error: record delete permission denied for form '".$form_config["page_id"]."'");
  }
  $target_filename=\webdb\forms\get_uploaded_full_filename($form_config,$record_id,$field_name);
  switch ($settings["file_upload_mode"])
  {
    case "rename":
      if (file_exists($target_filename)==true)
      {
        unlink($target_filename);
        return true;
      }
      break;
    case "ftp":
      $connection=\webdb\utils\webdb_ftp_login();
      $file_list=ftp_nlist($connection,$settings["ftp_app_target_path"]);
      $filename=\webdb\forms\get_uploaded_filename($form_config,$record_id,$field_name);
      if (in_array($filename,$file_list)==false)
      {
        return false;
      }
      ftp_delete($connection,$target_filename);
      ftp_close($connection);
      return true;
  }
  return false;
}

#####################################################################################################

function form_dispatch($page_id)
{
  global $settings;
  $form_config=\webdb\forms\get_form_config($page_id,false);
  if (isset($_GET["file_view"])==true)
  {
    \webdb\forms\file_field_view($form_config);
  }
  if (($form_config["default_cmd_override"]<>"") and (isset($_GET["cmd"])==false))
  {
    $_GET["cmd"]=$form_config["default_cmd_override"];
  }
  if ($form_config["on_open_stub"]<>"")
  {
    if (function_exists($form_config["on_open_stub"])==true)
    {
      call_user_func($form_config["on_open_stub"],$form_config);
    }
  }
  if (isset($_GET["ajax"])==true)
  {
    switch ($_GET["ajax"])
    {
      case "favourite":
      case "unfavourite":
        \webdb\chat\page_favorite_ajax_stub();
    }
    if (isset($_GET["file_delete"])==true)
    {
      \webdb\forms\file_field_delete($form_config);
    }
  }
  if ((isset($_GET["ajax"])==true) and (isset($_GET["field_name"])==true))
  {
    $field_name=$_GET["field_name"];
    $event_type=$_GET["ajax"];
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
      if ((count($form_config["format_stubs"])>0) and (isset($_GET["format"])==true))
      {
        $format_stubs=$form_config["format_stubs"];
        $format=$_GET["format"];
        if (isset($format_stubs[$format])==true)
        {
          $stub=$format_stubs[$format];
          if (function_exists($stub)==true)
          {
            echo call_user_func($stub,$form_config);
            die;
          }
          else
          {
            \webdb\utils\error_message("error: unhandled format stub");
          }
        }
      }
      \webdb\forms\add_resource_includes($form_config);
      if ((($form_config["database"]=="") or ($form_config["table"]=="")) and ($form_config["records_sql"]==""))
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
          case "insert_confirm":
            \webdb\forms\insert_record($form_config);
          case "edit_confirm":
            $id=\webdb\utils\get_child_array_key($_POST["form_cmd"],"edit_confirm");
            $checklist_subforms=array();
            foreach ($_POST as $name => $value)
            {
              $name_parts=\webdb\utils\webdb_explode(":",$name);
              if (count($name_parts)<=1)
              {
                continue;
              }
              $checklist_page_id=trim($name_parts[1]);
              if ((trim($name_parts[0])=="is_checklist") and (in_array($checklist_page_id,$checklist_subforms)==false))
              {
                if ($value=="1")
                {
                  $checklist_subforms[]=$checklist_page_id;
                }
              }
            }
            for ($i=0;$i<count($checklist_subforms);$i++)
            {
              $subform_config=\webdb\forms\get_form_config($checklist_subforms[$i],false);
              \webdb\forms\checklist_update($subform_config,$id);
            }
            \webdb\forms\update_record($form_config,$id);
          case "delete":
            $cmd_page_id=\webdb\utils\get_child_array_key($_POST["form_cmd"],"delete");
            $subform_list=array_keys($form_config["edit_subforms"]);
            if (in_array($cmd_page_id,$subform_list)==true)
            {
              $_GET["redirect"]=\webdb\utils\get_url();
              $form_config=\webdb\forms\get_form_config($cmd_page_id,false);
            }
            $id=\webdb\utils\get_child_array_key($_POST["form_cmd"]["delete"],$cmd_page_id);
            $data=\webdb\forms\delete_confirmation($form_config,$id);
            $data["content"].=\webdb\forms\output_html_includes($form_config);
            \webdb\utils\output_page($data["content"],$data["title"]);
          case "delete_confirm":
            $id=\webdb\utils\get_child_array_key($_POST["form_cmd"],"delete_confirm");
            \webdb\forms\delete_record($form_config,$id);
          case "delete_selected":
            $cmd_page_id=\webdb\utils\get_child_array_key($_POST["form_cmd"],"delete_selected");
            $subform_list=array_keys($form_config["edit_subforms"]);
            if (in_array($cmd_page_id,$subform_list)==true)
            {
              $_GET["redirect"]=\webdb\utils\get_url();
              $form_config=\webdb\forms\get_form_config($cmd_page_id,false);
            }
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
              if (($_GET["ajax"]=="chat_update") and ($form_config["chat_enabled"]==true))
              {
                \webdb\chat\chat_dispatch($_GET["id"],$form_config);
              }
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
          case "delete":
            if (isset($_GET["id"])==false)
            {
              \webdb\utils\error_message("error: missing id parameter");
            }
            $data=\webdb\forms\delete_confirmation($form_config,$_GET["id"]);
            $data["content"].=\webdb\forms\output_html_includes($form_config);
            \webdb\utils\output_page($data["content"],$data["title"]);
          case "advanced_search":
            $data=\webdb\forms\advanced_search($form_config);
            $data["content"].=\webdb\forms\output_html_includes($form_config);
            \webdb\utils\output_page($data["content"],$data["title"]);
        }
      }
      if ($form_config["filter_cookie"]==false)
      {
        $cookie_name=$settings["app_name"].":filters:".$form_config["page_id"];
        \webdb\utils\webdb_unsetcookie_raw($cookie_name,false);
      }
      $list_params=array();
      $list_params["page_id"]=$page_id;
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
      \webdb\forms\handle_links_menu($form_config,$list_params);
      $list_params["title"]=$form_config["title"];
      if ($form_config["report_content_only"]==true)
      {
        $content=\webdb\forms\form_template_fill("report_page",$list_params);
      }
      else
      {
        $content=\webdb\forms\form_template_fill("list_page",$list_params);
      }
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

function handle_links_menu($form_config,&$list_params)
{
  global $settings;
  if (($settings["enable_page_links_templates"]==true) and ($form_config!==false))
  {
    if ($form_config["links_template"]<>"")
    {
      $list_params["header_links_bar"]=\webdb\utils\template_fill($form_config["links_template"]);
    }
    elseif ($settings["links_template"]<>"")
    {
      $list_params["header_links_bar"]=\webdb\utils\template_fill($settings["links_template"]);
    }
  }
  else
  {
    if ($settings["links_template"]<>"")
    {
      $list_params["header_links_bar"]=\webdb\utils\template_fill($settings["links_template"]);
    }
  }
}

#####################################################################################################

function process_sort_sql(&$form_config)
{
  global $settings;
  $engine_key="sort_sql_".$settings["db_engine"];
  if (($form_config["sort_sql"]=="") and ($form_config[$engine_key]<>""))
  {
    $form_config["sort_sql"]=$form_config[$engine_key];
  }
  if ($form_config["sort_sql"]<>"")
  {
    $sql_params=array();
    $sql_params["sort_sql"]=$form_config["sort_sql"];
    $form_config["sort_sql"]=\webdb\utils\sql_fill("sort_clause",$sql_params);
  }
}

#####################################################################################################

function checklist_update($form_config,$parent_id)
{
  global $settings;
  $page_id=$form_config["page_id"];
  $checklist_enabled_fieldname=$form_config["checklist_enabled_fieldname"];
  $link_database=$form_config["link_database"];
  $link_table=$form_config["link_table"];
  $parent_key=$form_config["parent_key"];
  $link_key=$form_config["link_key"];
  $link_fields=$form_config["link_fields"];
  $list_records=array();
  \webdb\forms\process_filter_sql($form_config);
  \webdb\forms\process_sort_sql($form_config);
  $sql_params=array();
  $sql_params["database"]=$form_config["database"];
  $sql_params["table"]=$form_config["table"];
  $sql_params["selected_filter_sql"]=$form_config["selected_filter_sql"];
  $sql_params["sort_sql"]=$form_config["sort_sql"];
  $sql=\webdb\utils\sql_fill("form_list_fetch_all",$sql_params);
  $list_records=\webdb\sql\fetch_prepare($sql,array(),"form_list_fetch_all",false,$form_config["table"],$form_config["database"],$form_config);
  $sql_params["link_database"]=$link_database;
  $sql_params["link_table"]=$link_table;
  $sql_params["parent_key"]=$parent_key;
  $sql_params["link_key"]=$link_key;
  $sql_params["selected_filter_condition"]=$form_config["selected_filter_condition"];
  $sql=\webdb\utils\sql_fill("link_records",$sql_params);
  $sql_params=array();
  $sql_params["parent_key"]=$parent_id;
  $exist_parent_link_records=\webdb\sql\fetch_prepare($sql,$sql_params,"link_records",false,$link_table,$link_database,$form_config);
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
    if (isset($_POST[$page_id.":list_select"][$child_id])==false)
    {
      if (\webdb\utils\check_user_form_permission($page_id,"d")==false)
      {
        \webdb\utils\error_message("error: form record(s) delete permission denied");
      }
      $where_items=array();
      $where_items[$parent_key]=$parent_id;
      $where_items[$link_key]=$child_id;
      if ($checklist_enabled_fieldname=="")
      {
        \webdb\sql\sql_delete($where_items,$link_table,$link_database,false,$form_config);
      }
      else
      {
        $value_items=array();
        $value_items[$checklist_enabled_fieldname]=false;
        \webdb\sql\sql_update($value_items,$where_items,$link_table,$link_database,false,$form_config);
      }
    }
  }
  if (isset($_POST[$page_id.":list_select"])==true)
  {    
    foreach ($_POST[$page_id.":list_select"] as $child_id => $check_value)
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
        if ($checklist_enabled_fieldname<>"")
        {
          if ($record[$checklist_enabled_fieldname]==false)
          {
            $value_items[$checklist_enabled_fieldname]=true;
          }
        }
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
            \webdb\utils\error_message("error: form record(s) update permission denied: ".$page_id);
          }
          \webdb\forms\check_required_values($form_config,$value_items);
          \webdb\sql\sql_update($value_items,$where_items,$link_table,$link_database,false,$form_config);
        }
      }
      else
      {
        if (\webdb\utils\check_user_form_permission($page_id,"i")==false)
        {
          \webdb\utils\error_message("error: form record(s) insert permission denied: ".$page_id);
        }
        $value_items+=$where_items;
        $event_params=array();
        $event_params["handled"]=false;
        $event_params["value_items"]=$value_items;
        $event_params=\webdb\forms\handle_form_config_event($form_config,$event_params,"on_checklist_insert");
        if ($event_params["handled"]===false)
        {
          $value_items=$event_params["value_items"];
          \webdb\forms\check_required_values($form_config,$value_items);
          \webdb\sql\sql_insert($value_items,$link_table,$link_database,false,$form_config);
        }
      }
    }
  }
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

function add_resource_includes($form_config)
{
  global $settings;
  for ($i=0;$i<count($form_config["css_includes"]);$i++)
  {
    $key=$form_config["css_includes"][$i];
    \webdb\utils\add_resource_link($key,"css");
  }
  for ($i=0;$i<count($form_config["js_includes"]);$i++)
  {
    $key=$form_config["js_includes"][$i];
    \webdb\utils\add_resource_link($key,"js");
  }
}

#####################################################################################################

function form_template_fill($name,$params=false)
{
  return \webdb\utils\template_fill("forms/".$name,$params);
}

#####################################################################################################

function process_filter_sql(&$form_config)
{
  global $settings;
  $page_id=$form_config["page_id"];
  $cookie_name=\webdb\utils\convert_to_cookie_name($settings["app_name"].":filters:".$page_id);
  $form_config["selected_filter"]=$form_config["default_filter"];
  if (isset($_COOKIE[$cookie_name])==true)
  {
    $form_config["selected_filter"]=$_COOKIE[$cookie_name];
  }
  if (isset($_POST["selected_filter:".$page_id])==true)
  {
    $form_config["selected_filter"]=$_POST["selected_filter:".$page_id];
  }
  $form_config["selected_filter_sql"]="";
  $form_config["selected_filter_condition"]="";
  $filter_name=$form_config["selected_filter"];
  if (isset($form_config["filter_options"][$filter_name])==true)
  {
    $where_params=array();
    $where_params["where_items"]=$form_config["filter_options"][$filter_name];
    $form_config["selected_filter_sql"]=\webdb\utils\sql_fill("where_clause",$where_params);
  }
}

#####################################################################################################

function get_subform_content($subform_config,$subform_link_field,$id,$list_only=false,$parent_form_config=false)
{
  global $settings;
  if ($subform_config["filter_cookie"]==false)
  {
    $cookie_name=$settings["app_name"].":filters:".$subform_config["page_id"];
    \webdb\utils\webdb_unsetcookie_raw($cookie_name,false);
  }
  $subform_config["advanced_search"]=false;
  $subform_config["insert_new"]=false;
  $subform_config=\webdb\forms\override_delete_config($subform_config);
  $records=false;
  $event_params=array();
  $event_params["handled"]=false;
  $event_params["custom_list_content"]=false;
  $event_params["content"]="";
  $event_params["parent_form_config"]=$parent_form_config;
  $event_params["subform_config"]=$subform_config;
  $event_params["parent_id"]=$id;
  $event_params["records"]=$records;
  if (isset($subform_config["event_handlers"]["on_list"])==true)
  {
    $func_name=$subform_config["event_handlers"]["on_list"];
    if (function_exists($func_name)==true)
    {
      $event_params=call_user_func($func_name,$event_params);
    }
  }
  $subform_config=$event_params["subform_config"];
  $records=$event_params["records"];
  $link_records=false;
  if ($event_params["handled"]==false)
  {
    \webdb\forms\process_filter_sql($subform_config);
    if (count($subform_config["link_fields"])>0)
    {
      $sql=\webdb\utils\sql_fill("link_records",$subform_config);
      $sql_params=array();
      $sql_params["parent_key"]=$id;
      $link_records=\webdb\sql\fetch_prepare($sql,$sql_params,"link_records",false,"","",$subform_config);
    }
    if ($subform_config["checklist"]==true)
    {
      $subform_config["multi_row_delete"]=false;
      $subform_config["delete_cmd"]=false;
      $subform_config["insert_row"]=false;
      if ($subform_config["records_sql"]=="")
      {
        \webdb\forms\process_sort_sql($subform_config);
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
      if ((count($subform_config["link_fields"])>0) and ($subform_config["records_sql"]==""))
      {
        $subform_config["insert_row"]=false;
        $subform_config["multi_row_delete"]=false;
        $subform_config["delete_cmd"]=false;
        $sql=\webdb\utils\sql_fill("link_records",$subform_config);
        $sql_params=array();
        $sql_params["parent_key"]=$id;
        $subform_config_swapped=$subform_config;
        $subform_config_swapped["database"]=$subform_config["link_database"];
        $subform_config_swapped["table"]=$subform_config["link_table"];
        $subform_config_swapped["link_database"]=$subform_config["database"];
        $subform_config_swapped["link_table"]=$subform_config["table"];
        $records=\webdb\sql\fetch_prepare($sql,$sql_params,"link_records",false,"","",$subform_config_swapped);
      }
      else
      {
        if ($subform_config["records_sql"]=="")
        {
          $sql_params=array();
          $sql_params["database"]=$subform_config["database"];
          $sql_params["table"]=$subform_config["table"];
          \webdb\forms\process_sort_sql($subform_config);
          $sql_params["sort_sql"]=$subform_config["sort_sql"];
          if ($subform_config["selected_filter_sql"]=="")
          {
            $where_params=array();
            $where_params["where_items"]=$subform_link_field."=:id";
            $sql_params["selected_filter_sql"]=\webdb\utils\sql_fill("where_clause",$where_params);
          }
          else
          {
            $sql_params["selected_filter_sql"]=$subform_config["selected_filter_sql"]." AND (".$subform_link_field."=:id)";
          }
          $sql_filename="form_list_fetch_all";
          $database=$sql_params["database"];
          $table=$sql_params["table"];
          $sql=\webdb\utils\sql_fill($sql_filename,$sql_params);
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
    }
  }
  $subform_params=array();
  $url_params=array();
  $url_params[$subform_link_field]=$id;
  $subform_config["parent_form_id"]=$id;
  $subform_config["parent_form_config"]=$parent_form_config;
  $subform_params["subform_style"]="";
  $subform_params["page_id"]=$subform_config["page_id"];
  if ($event_params["custom_list_content"]==false)
  {
    $subform_params["subform"]=\webdb\forms\list_form_content($subform_config,$records,$url_params,$link_records);
  }
  else
  {
    $subform_params["subform"]=$event_params["content"];
  }
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

function override_delete_config($subform_config)
{
  if ($subform_config["delete_button_caption"]=="")
  {
    $subform_config["delete_button_caption"]="Delete";
  }
  $key_fieldnames=\webdb\utils\webdb_explode(\webdb\index\CONFIG_ID_DELIMITER,$subform_config["primary_key"]);
  if (count($key_fieldnames)>1)
  {
    $additional_fields=false;
    foreach ($subform_config["control_types"] as $field_name => $control_type)
    {
      if (in_array($field_name,$key_fieldnames)==false)
      {
        if ($control_type<>"lookup")
        {
          $additional_fields=true;
          break;
        }
      }
    }
    if ($additional_fields==false)
    {
      if ($subform_config["delete_button_caption"]=="Delete")
      {
        $subform_config["delete_button_caption"]="Remove Link";
      }
      if ($subform_config["multi_row_delete_button_caption"]=="Delete Selected")
      {
        $subform_config["multi_row_delete_button_caption"]="Remove Selected Links";
      }
    }
    else
    {
      $subform_config["multi_row_delete"]=false;
      if ($subform_config["delete_button_caption"]=="Delete")
      {
        $subform_config["delete_button_caption"]="Remove Link Data";
      }
    }
  }
  else
  {
    $subform_config["multi_row_delete"]=false;
  }
  return $subform_config;
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
      \webdb\utils\system_message("error: invalid webdb form def (missing form_version): ".$fn);
    }
    if (isset($data["form_type"])==false)
    {
      \webdb\utils\system_message("error: invalid webdb form def (missing form_type): ".$fn);
    }
    if (isset($data["enabled"])==false)
    {
      \webdb\utils\system_message("error: invalid webdb form def (missing enabled): ".$fn);
    }
    if (isset($data["page_id"])==false)
    {
      \webdb\utils\system_message("error: invalid webdb form def (missing page_id): ".$fn);
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
      \webdb\utils\system_message("error: invalid webdb form def (invalid form_type): ".$data["basename"]);
    }
    $default=$settings["form_defaults"][$form_type];
    if (isset($settings["forms"][$page_id])==true)
    {
      \webdb\utils\system_message("error: form '".$page_id."' already exists: ".$data["basename"]);
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
        \webdb\utils\system_message("error: invalid app form def (missing form_version): ".$fn);
      }
      if ($data["form_version"]<>$settings["form_defaults"][$data["form_type"]]["form_version"])
      {
        \webdb\utils\system_message("error: invalid form def (incompatible version number): ".$fn);
      }
      if (isset($data["form_type"])==false)
      {
        \webdb\utils\system_message("error: invalid app form def (missing form_type): ".$fn);
      }
      if (isset($data["enabled"])==false)
      {
        \webdb\utils\system_message("error: invalid app form def (missing enabled): ".$fn);
      }
      if (isset($data["page_id"])==false)
      {
        \webdb\utils\system_message("error: invalid app form def (missing page_id): ".$fn);
      }
      if ($data["enabled"]==false)
      {
        continue;
      }
      $form_type=$data["form_type"];
      $fext=pathinfo($fn,PATHINFO_EXTENSION);
      if ($form_type<>$fext)
      {
        \webdb\utils\system_message("error: invalid app form def (form type mismatch): ".$fn);
      }
      if (isset($settings["form_defaults"][$form_type])==false)
      {
        \webdb\utils\system_message("error: invalid app form def (invalid form_type): ".$fn);
      }
      $data["basename"]=$fn;
      $full=$settings["app_forms_path"].$fn;
      $data["filename"]=$full;
      $page_id=$data["page_id"];
      $default=$settings["form_defaults"][$form_type];
      if (isset($settings["forms"][$page_id])==true)
      {
        \webdb\utils\system_message("error: form '".$page_id."' already exists: ".$fn);
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
  $key_fields=\webdb\utils\webdb_explode(\webdb\index\CONFIG_ID_DELIMITER,$form_config[$config_key]);
  $values=array();
  for ($i=0;$i<count($key_fields);$i++)
  {
    if (isset($record[$key_fields[$i]])==false)
    {
      return "";
    }
    $value=$record[$key_fields[$i]];
    if ($value=="")
    {
      return "";
    }
    $values[]=$value;
  }
  return implode(\webdb\index\CONFIG_ID_DELIMITER,$values);
}

#####################################################################################################

function list_row($form_config,$record,$column_format,$row_spans,$lookup_records,$record_index,$link_record=false)
{
  global $settings;
  # ~~~~~~~~~~~~~~~~~~~~~~~
  $event_params=array();
  $event_params["form_config"]=$form_config;
  $event_params["record"]=$record;
  $event_params["link_record"]=$link_record;
  if (isset($form_config["event_handlers"]["on_before_list_row"])==true)
  {
    $func_name=$form_config["event_handlers"]["on_before_list_row"];
    if (function_exists($func_name)==true)
    {
      $event_params=call_user_func($func_name,$event_params);
      $form_config=$event_params["form_config"];
      $record=$event_params["record"];
      $link_record=$event_params["link_record"];
    }
  }
  # ~~~~~~~~~~~~~~~~~~~~~~~
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
    if ($link_record!==false)
    {
      $checklist_row_linked=true;
      $checklist_enabled_fieldname=$form_config["checklist_enabled_fieldname"];
      if ($checklist_enabled_fieldname<>"")
      {
        if ($link_record[$checklist_enabled_fieldname]==false)
        {
          $link_record=false;
          $checklist_row_linked=false;
        }
      }
    }
  }
  $fields=array();
  $empty_cell=\webdb\utils\template_fill("empty_cell");
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
    if ($control_type=="default")
    {
      $record[$field_name]=\webdb\forms\default_value($form_config,$field_name);
      $control_type="span";
    }
    $field_params=array();
    $field_params["border_right_width"]="0";
    $field_params["border_right_color"]="initial";
    if (($checklist_row_linked==false) and ($form_config["checklist"]==true))
    {
      $field_params["border_right_width"]=$settings["list_border_width"];
      $field_params["border_right_color"]=$settings["list_border_color"];
    }
    $field_params["primary_key"]=$row_params["primary_key"];
    $field_params["page_id"]=$row_params["page_id"];
    $display_record=$record;
    if (in_array($field_name,$form_config["link_fields"])==true)
    {
      $display_record=$link_record;
      $field_params["primary_key"]=\webdb\forms\config_id_url_value($form_config,$link_record,"link_key");
    }
    if (is_array($display_record)==false)
    {
      $display_record[$field_name]="";
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
      $field_params["value"]=$empty_cell;
    }
    else
    {
      if ($display_record[$field_name]==="")
      {
        $field_params["value"]=$empty_cell;
      }
      else
      {
        $field_params["value"]=\webdb\utils\webdb_htmlspecialchars($display_record[$field_name]);
      }
    }
    $field_params["field_name"]=$field_name;
    if (isset($form_config["parent_form_id"])==true)
    {
      $field_params["field_name"].="[".$form_config["parent_form_id"].\webdb\index\CONFIG_ID_DELIMITER.$field_params["primary_key"]."]";
    }
    $field_params["page_id"]=$form_config["page_id"];
    $field_params["group_span_style"]="";
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
    if ($form_config["edit_cmd"]=="inline")
    {
      if (($form_config["checklist"]==false) or ($checklist_row_linked==true))
      {
        $field_params["table_cell_style"].=\webdb\forms\form_template_fill("inline_edit_cell_style");
      }
    }
    if (($form_config["edit_cmd"]=="row") or ($form_config["edit_cmd"]=="inline"))
    {
      $field_params["edit_cmd_id"]=$row_params["edit_cmd_id"];
      if (($form_config["checklist"]==false) or (($checklist_row_linked==true) and (count($form_config["link_fields"])>0)))
      {
        $field_params["handlers"]=\webdb\forms\form_template_fill("list_field_handlers",$field_params);
      }
    }
    $skip_field=false;
    if (in_array($field_name,$form_config["group_by"])==true)
    {
      if ($row_spans[$record_index]==0)
      {
        if (isset($_GET["format"])==true)
        {
          $fields[]="";
          continue;
        }
        $skip_field=true;
        if ($record_index>=(count($row_spans)-1))
        {
          $field_params["group_span_style"]=\webdb\forms\form_template_fill("group_style_last");
        }
        else
        {
          $field_params["group_span_style"]=\webdb\forms\form_template_fill("group_style_next");
        }
      }
      else
      {
        if ($record_index>=(count($row_spans)-1))
        {
          $field_params["group_span_style"]=\webdb\forms\form_template_fill("group_style_last");
        }
        else
        {
          $field_params["group_span_style"]=\webdb\forms\form_template_fill("group_style_first");
        }
      }
    }
    if ($skip_field==false)
    {
      $fields[]=\webdb\forms\output_readonly_field($field_params,$control_type,$form_config,$field_name,$lookup_records,$display_record,$link_record);
    }
    else
    {
      $fields[]=\webdb\forms\form_template_fill("list_field_group",$field_params);
    }
  }
  if (isset($_GET["format"])==true)
  {
    switch ($_GET["format"])
    {
      case "csv":
        for ($i=0;$i<count($fields);$i++)
        {
          $fields[$i]=\webdb\utils\webdb_str_replace(","," ",$fields[$i]);
        }
        return implode(",",$fields);
    }
  }
  $fields=implode("",$fields);
  $row_params["list_row_controls"]="";
  $row_params["default_checked"]="";
  $row_params["group_span_style"]="";
  $row_params["controls"]="";
  $row_params["controls_min_width"]=$column_format["controls_min_width"];
  $skip_controls=false;
  if (($checklist_row_linked==false) and ($form_config["checklist"]==true))
  {
    $skip_controls=true;
    $row_params["list_row_controls"]=\webdb\forms\form_template_fill("list_row_controls",$row_params);
  }
  elseif (in_array($form_config["edit_cmd_id"],$form_config["group_by"])==true)
  {
    if ($row_spans[$record_index]==0)
    {
      $skip_controls=true;
    }
  }
  if ($skip_controls==false)
  {
    if ($checklist_row_linked==true)
    {
      $row_params["default_checked"]=\webdb\utils\template_fill("checkbox_checked");
    }
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
    if (($form_config["delete_cmd"]==true) and ($form_config["checklist"]==false))
    {
      $row_params["delete_button_caption"]=$form_config["delete_button_caption"];
      $row_params["controls"].=\webdb\forms\form_template_fill("list_row_del",$row_params);
    }
  }
  $row_params["list_row_controls"]=\webdb\forms\form_template_fill("list_row_controls",$row_params);
  $row_params["check"]=\webdb\forms\check_column($form_config,"list_check",$row_params);
  $row_params["fields"]=$fields;
  $row_params["last_group_border_width"]=$settings["list_border_width"];
  $row_params["last_group_border_color"]=$settings["list_border_color"];
  if ($column_format["group_border_last"]==true)
  {
    $row_params["last_group_border_width"]=$settings["list_group_border_width"];
    $row_params["last_group_border_color"]=$settings["list_group_border_color"];
  }
  # ~~~~~~~~~~~~~~~~~~~~~~~
  $event_params=array();
  $event_params["form_config"]=$form_config;
  $event_params["record"]=$record;
  $event_params["link_record"]=$link_record;
  $event_params["row_params"]=$row_params;
  if (isset($form_config["event_handlers"]["on_list_row"])==true)
  {
    $func_name=$form_config["event_handlers"]["on_list_row"];
    if (function_exists($func_name)==true)
    {
      $event_params=call_user_func($func_name,$event_params);
      $row_params=$event_params["row_params"];
    }
  }
  # ~~~~~~~~~~~~~~~~~~~~~~~
  return \webdb\forms\form_template_fill("list_row",$row_params);
}

#####################################################################################################

function lookup_field_display_value($lookup_config,$lookup_record)
{
  global $settings;
  if (isset($lookup_config["display_field"])==false)
  {
    $lookup_config["display_field"]=$lookup_config["key_field"];
  }
  $display_field_names=\webdb\utils\webdb_explode(",",$lookup_config["display_field"]);
  $display_values=array();
  for ($i=0;$i<count($display_field_names);$i++)
  {
    $display_field_name=$display_field_names[$i];
    if (array_key_exists($display_field_name,$lookup_record)==false) # JC, 28-Nov-22: this fixes a bug when fudging fields outside the basic table, but its probably a dodgy hack
    {
      \webdb\utils\error_message("error: lookup display field not found: ".$display_field_name);
    }
    $display_values[]=$lookup_record[$display_field_name];
  }
  if (isset($lookup_config["display_format"])==true)
  {
    $format=trim($lookup_config["display_format"]);
    switch ($format)
    {
      case "link":
        if (count($display_values)==1)
        {
          $link_params=array();
          $link_params["url"]=$display_values[0];
          return \webdb\forms\form_template_fill("lookup_link",$link_params);
        }
      case "date":
        if (count($display_values)==1)
        {
          if ($display_values[0]<>"")
          {
            return date($settings["app_date_format"],\webdb\utils\webdb_strtotime($display_values[0]));
          }
          else
          {
            return "";
          }
        }
      case "check":
        if (count($display_values)==1)
        {
          $check_params=array();
          if ($display_values[0]==1)
          {
            $check_params["check_tick_class"]="check_tick";
            return \webdb\forms\form_template_fill("check_tick",$check_params);
          }
          else
          {
            $check_params["check_cross_class"]="check_cross";
            return \webdb\forms\form_template_fill("check_cross",$check_params);
          }
        }
      default:
        if ($format<>"")
        {
          return vsprintf($format,$display_values);
        }
    }
  }
  return implode(\webdb\index\LOOKUP_DISPLAY_FIELD_DELIM,$display_values);
}

#####################################################################################################

function get_lookup_field_value($field_name,$form_config,$lookup_records,$display_record,$link_record=false)
{
  $result="";
  $lookup_config=$form_config["lookups"][$field_name];
  if (isset($lookup_config["constant_value"])==true)
  {
    return \webdb\utils\string_template_fill($lookup_config["constant_value"]);
  }
  $key_field_name=$lookup_config["key_field"];
  if (isset($lookup_config["sibling_field"])==false)
  {
    $lookup_config["sibling_field"]=$field_name;
  }
  $sibling_field_name=$lookup_config["sibling_field"];
  if (($form_config["checklist"]==false) or (array_key_exists($sibling_field_name,$display_record)==true))
  {
    $n=count($lookup_records[$field_name]);
    for ($i=0;$i<$n;$i++)
    {
      $lookup_record=$lookup_records[$field_name][$i];
      $key_value=$lookup_record[$key_field_name];
      if (isset($display_record[$sibling_field_name])==false)
      {
        continue;
      }
      if ($display_record[$sibling_field_name]==$key_value)
      {
        $result=\webdb\forms\lookup_field_display_value($lookup_config,$lookup_record);
        break;
      }
    }
  }
  else
  {
    if ($link_record!==false)
    {
      $key_value=$link_record[$key_field_name];
      $sibling_value=$display_record[$sibling_field_name];
      $n=count($lookup_records[$field_name]);
      for ($i=0;$i<$n;$i++)
      {
        $lookup_record=$lookup_records[$field_name][$i];
        if (($lookup_record[$key_field_name]==$key_value) and ($lookup_record[$sibling_field_name]==$sibling_value))
        {
          $result=\webdb\forms\lookup_field_display_value($lookup_config,$lookup_record);
          break;
        }
      }
    }
  }
  if (isset($lookup_config["display_format"])==true)
  {
    $format=$lookup_config["display_format"];
    switch ($format)
    {
      case "link":
        return $result;
    }
  }
  else
  {
    $result=\webdb\utils\webdb_htmlspecialchars(\webdb\utils\webdb_str_replace(\webdb\index\LINEBREAK_DB_DELIM,\webdb\index\LINEBREAK_PLACEHOLDER,$result));
  }
  return \webdb\utils\webdb_str_replace(\webdb\index\LINEBREAK_PLACEHOLDER,\webdb\utils\template_fill("break"),$result);
}

#####################################################################################################

function format_text_output($value)
{
  $break=\webdb\utils\template_fill("break");
  $value=\webdb\utils\webdb_htmlspecialchars(\webdb\utils\webdb_str_replace(\webdb\index\LINEBREAK_DB_DELIM,\webdb\index\LINEBREAK_PLACEHOLDER,$value));
  $value=\webdb\utils\webdb_str_replace(\webdb\index\LINEBREAK_PLACEHOLDER,$break,$value);
  $value=\webdb\utils\webdb_str_replace(PHP_EOL,$break,$value);
  $value=\webdb\utils\webdb_str_replace("\r",$break,$value);
  $value=\webdb\utils\webdb_str_replace("\n",$break,$value);
  $value=\webdb\forms\memo_field_formatting($value);
  return $value;
}

#####################################################################################################

function output_readonly_field($field_params,$control_type,$form_config,$field_name,$lookup_records,$display_record,$link_record=false)
{
  global $settings;
  if (isset($_GET["format"])==true)
  {
    switch ($control_type)
    {
      case "raw":
      case "memo":
      case "file":
      case "span":
      case "text":
      case "checkbox":
        return $display_record[$field_name];
    }
  }
  switch ($control_type)
  {
    case "hidden":
      return "";
    case "raw":
      $field_params["value"]=$display_record[$field_name];
      return \webdb\forms\form_template_fill("list_field_raw",$field_params);
    case "lookup":
      $field_params["value"]=\webdb\forms\get_lookup_field_value($field_name,$form_config,$lookup_records,$display_record,$link_record);
      if (isset($_GET["format"])==true)
      {
        return $field_params["value"];
      }
      return \webdb\forms\form_template_fill("list_field",$field_params);
    case "memo":
      $field_params["value"]=\webdb\forms\format_text_output($display_record[$field_name]);
      return \webdb\forms\form_template_fill("list_field",$field_params);
    case "default":
    case "info":
      $display_record[$field_name]=\webdb\forms\default_value($form_config,$field_name);
      if (isset($_GET["format"])==true)
      {
        return $display_record[$field_name];
      }
      return \webdb\forms\form_template_fill("list_field",$field_params);
    case "file":
      $field_params["image_preview"]="";
      $field_params["no_file_disabled"]="";
      if (array_key_exists($field_name,$display_record)==true)
      {
        if (($display_record[$field_name]=="") or ($display_record[$field_name]==null))
        {
          $field_params["no_file_disabled"]=\webdb\utils\template_fill("disabled_attribute");
        }
        else
        {
          $field_params["image_preview"]=\webdb\forms\image_file_preview($form_config,$display_record,$field_name,$settings["file_field_image_preview_max_pix"]);
        }
      }
      $field_params["field_name_basic"]=$field_name;
      $field_params["value"]=\webdb\utils\webdb_htmlspecialchars($display_record[$field_name]);
      return \webdb\forms\form_template_fill("list_field_file",$field_params);
    case "span":
    case "text":
      $field_params["value"]=\webdb\utils\webdb_htmlspecialchars(\webdb\utils\webdb_str_replace(\webdb\index\LINEBREAK_DB_DELIM,\webdb\index\LINEBREAK_PLACEHOLDER,$display_record[$field_name]));
      $field_params["value"]=\webdb\utils\webdb_str_replace(\webdb\index\LINEBREAK_PLACEHOLDER,\webdb\utils\template_fill("break"),$field_params["value"]);
      return \webdb\forms\form_template_fill("list_field",$field_params);
    case "combobox":
    case "listbox":
    case "radiogroup":
      $field_params["value"]=\webdb\utils\webdb_htmlspecialchars($display_record[$field_name]);
      $lookup_config=$form_config["lookups"][$field_name];
      $n=count($lookup_records[$field_name]);
      for ($i=0;$i<$n;$i++)
      {
        $key_field_name=$lookup_config["key_field"];
        $key_value=$lookup_records[$field_name][$i][$key_field_name];
        if ($display_record[$field_name]==$key_value)
        {
          $display_value=\webdb\forms\lookup_field_display_value($lookup_config,$lookup_records[$field_name][$i]);
          $field_params["value"]=\webdb\utils\webdb_htmlspecialchars($display_value);
          break;
        }
      }
      if (isset($_GET["format"])==true)
      {
        return $field_params["value"];
      }
      return \webdb\forms\form_template_fill("list_field",$field_params);
    case "date":
      if ($display_record[$field_name]==null)
      {
        $field_params["value"]=\webdb\utils\template_fill("empty_cell");
      }
      else
      {
        $field_params["value"]=date($settings["app_date_format"],\webdb\utils\webdb_strtotime($display_record[$field_name]));
      }
      if (isset($_GET["format"])==true)
      {
        return $field_params["value"];
      }
      return \webdb\forms\form_template_fill("list_field",$field_params);
    case "checkbox":
      $params=array();
      if ($display_record[$field_name]==true)
      {
        $params["check_tick_class"]="check_tick";
        if (isset($form_config["table_cell_styles"][$field_name])==true)
        {
          $params["check_tick_class"]="check_tick_override";
        }
        $field_params["value"]=\webdb\forms\form_template_fill("check_tick",$params);
      }
      else
      {
        $params["check_cross_class"]="check_cross";
        if (isset($form_config["table_cell_styles"][$field_name])==true)
        {
          $params["check_cross_class"]="check_cross_override";
        }
        $field_params["value"]=\webdb\forms\form_template_fill("check_cross",$params);
      }
      return \webdb\forms\form_template_fill("list_field_raw",$field_params);
  }
  return "";
}

#####################################################################################################

function image_file_preview($form_config,$record,$field_name,$max_dim_pix=100)
{
  global $settings;
  $fn=$record[$field_name];
  $parts=\webdb\utils\webdb_explode(".",$fn);
  $ext=array_pop($parts);
  $id=\webdb\forms\config_id_url_value($form_config,$record,"primary_key");
  $fn=\webdb\forms\get_uploaded_full_filename($form_config,$id,$field_name);
  $file_exists=true;
  if ($settings["file_upload_mode"]=="ftp")
  {
    $remote=$fn;
    $fn=$settings["global_temp_path"]."preview".$settings["logged_in_user_id"].mt_rand(1,1000);
    $connection=\webdb\utils\webdb_ftp_login();
    $file_list=ftp_nlist($connection,$settings["ftp_app_target_path"]);
    $file_exists=false;
    if (in_array(basename($remote),$file_list)==true)
    {
      $file_exists=ftp_get($connection,$fn,$remote,FTP_BINARY);
    }
    ftp_close($connection);
  }
  else
  {
    if (file_exists($fn)==false)
    {
      return "";
    }
  }
  if ($file_exists==false)
  {
    return "";
  }
  switch ($ext)
  {
    case "jpg":
      $image=imagecreatefromjpeg($fn);
      break;
    case "png":
      $image=imagecreatefrompng($fn);
      break;
    case "gif":
      $image=imagecreatefromgif($fn);
      break;
    default:
      $ext=false;
      break;
  }
  if ($settings["file_upload_mode"]=="ftp")
  {
    unlink($fn);
  }
  if ($ext!==false)
  {
    $w=imagesx($image);
    $h=imagesy($image);
    $scale=$max_dim_pix/max($w,$h); # 100 pixel max preview dimension (default)
    \webdb\graphics\scale_img($image,$scale,$w,$h);
    $result=\webdb\graphics\base64_image($image,$ext,"inline-block","inset 2px");
    imagedestroy($image);
    return $result;
  }
  return "";
}

#####################################################################################################

function memo_field_formatting($value)
{
  global $settings;
  foreach ($settings["format_tag_templates"] as $tag => $positions)
  {
    if ((isset($positions["open"])==false) or (isset($positions["close"])==false))
    {
      continue;
    }
    $open_markup="&lt;".$tag."&gt;";
    $close_markup="&lt;/".$tag."&gt;";
    $value=\webdb\utils\webdb_str_replace($open_markup,$positions["open"],$value);
    $value=\webdb\utils\webdb_str_replace($close_markup,$positions["close"],$value);
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

function list_row_controls($form_config,&$submit_fields,$operation,$column_format,$record)
{
  global $settings;
  # ~~~~~~~~~~~~~~~~~~~~~~~
  $event_params=array();
  $event_params["form_config"]=$form_config;
  $event_params["record"]=$record;
  if (isset($form_config["event_handlers"]["on_list_row_controls"])==true)
  {
    $func_name=$form_config["event_handlers"]["on_list_row_controls"];
    if (function_exists($func_name)==true)
    {
      $event_params=call_user_func($func_name,$event_params);
      $form_config=$event_params["form_config"];
      $record=$event_params["record"];
    }
  }
  # ~~~~~~~~~~~~~~~~~~~~~~~
  $rotate_group_borders=$column_format["rotate_group_borders"];
  $row_params=array();
  $row_params["primary_key"]=\webdb\forms\config_id_url_value($form_config,$record,"primary_key");
  $row_params["page_id"]=$form_config["page_id"];
  $row_params["check"]=\webdb\forms\check_column($form_config,"list_check_insert");
  $lookup_records=\webdb\forms\lookup_records($form_config,true);
  $fields="";
  $hidden_fields="";
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    if ($form_config["visible"][$field_name]==false)
    {
      continue;
    }
    if ($control_type=="default")
    {
      $record[$field_name]=\webdb\forms\default_value($form_config,$field_name);
      $control_type="span";
    }
    $field_params=array();
    $field_params["primary_key"]=$row_params["primary_key"];
    $field_params["page_id"]=$row_params["page_id"];
    $field_params["handlers"]="";
    $field_params["group_span_style"]="";
    $field_params["border_right_width"]="0";
    $field_params["border_right_color"]="initial";
    $field_params["border_color"]=$settings["list_border_color"];
    $field_params["border_width"]=$settings["list_border_width"];
    if (isset($rotate_group_borders[$field_name])==true)
    {
      $field_params["border_color"]=$settings["list_group_border_color"];
      $field_params["border_width"]=$settings["list_group_border_width"];
    }
    $field_params["field_name"]=$field_name;
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
      if (($form_config["checklist"]==true) and (in_array($field_name,$form_config["link_fields"])==false))
      {
        $fields.=\webdb\forms\output_readonly_field($field_params,$control_type,$form_config,$field_name,$lookup_records,$record);
        continue;
      }
      else
      {
        $field_params["value"]=\webdb\forms\output_editable_field($field_params,$record,$field_name,$control_type,$form_config,$lookup_records,$submit_fields);
      }
    }
    else
    {
      $field_params["value"]=\webdb\forms\get_lookup_field_value($field_name,$form_config,$lookup_records,$record);
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
  $row_params["insert_cmd_handler"]="true";
  if ($operation=="insert")
  {
    $cmd_handler="insert_cmd_handler";
    if ($form_config[$cmd_handler]<>"")
    {
      $handler_params=array();
      $handler_params["function_name"]=$form_config[$cmd_handler];
      $row_params["insert_cmd_handler"]=\webdb\forms\form_template_fill("form_cmd_handler_row",$handler_params);
    }
  }
  return \webdb\forms\form_template_fill("list_".$operation."_row",$row_params);
}

#####################################################################################################

function output_editable_field(&$field_params,$record,$field_name,$control_type,$form_config,$lookup_records,&$submit_fields)
{
  global $settings;
  $field_params["primary_key"]=\webdb\forms\config_id_url_value($form_config,$record,"primary_key");
  $field_params["field_value"]="";
  if (isset($record[$field_name])==true)
  {
    $field_params["field_value"]=$record[$field_name];
  }
  $field_params["page_id"]=$form_config["page_id"];
  $field_params["control_style"]="";
  if (isset($form_config["control_styles"][$field_name])==true)
  {
    $field_params["control_style"]=$form_config["control_styles"][$field_name];
  }
  $field_params["disabled"]=\webdb\forms\field_disabled($form_config,$field_name);
  $field_params["js_events"]=\webdb\forms\field_js_events($form_config,$field_name,$record);
  $field_params["placeholder"]="";
  if (isset($form_config["placeholders"][$field_name])==true)
  {
    $field_params["placeholder"]=$form_config["placeholders"][$field_name];
  }
  switch ($control_type)
  {
    case "info":
      $field_params["caption"]=\webdb\forms\default_value($form_config,$field_name);
      break;
    case "file":
      $field_params["no_file_disabled"]="";
      if (array_key_exists($field_name,$record)==true)
      {
        if (($record[$field_name]=="") or ($record[$field_name]==null))
        {
          $field_params["no_file_disabled"]=\webdb\utils\template_fill("disabled_attribute");
        }
      }
      break;
    case "lookup":
      $field_params["field_key"]="";
      if (isset($record[$field_name])==true)
      {
        $field_params["field_key"]=$record[$field_name];
      }
      $field_params["value"]=\webdb\forms\get_lookup_field_value($field_name,$form_config,$lookup_records,$record);
      break;
    case "default":
      $record[$field_name]=\webdb\forms\default_value($form_config,$field_name);
      $control_type="span";
    case "span":
      $field_params["field_value"]=\webdb\utils\webdb_htmlspecialchars(\webdb\utils\webdb_str_replace(\webdb\index\LINEBREAK_DB_DELIM,\webdb\index\LINEBREAK_PLACEHOLDER,$record[$field_name]));
      $field_params["field_value"]=\webdb\utils\webdb_str_replace(\webdb\index\LINEBREAK_PLACEHOLDER,PHP_EOL,$field_params["field_value"]);
      break;
    case "raw":
      $control_type="span";
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
      $field_params["field_value"]=\webdb\utils\webdb_htmlspecialchars(\webdb\utils\webdb_str_replace(\webdb\index\LINEBREAK_DB_DELIM,\webdb\index\LINEBREAK_PLACEHOLDER,$record[$field_name]));
      $field_params["field_value"]=\webdb\utils\webdb_str_replace(\webdb\index\LINEBREAK_PLACEHOLDER,PHP_EOL,$field_params["field_value"]);
      $submit_fields[]=$field_params["field_name"];
      break;
    case "combobox":
    case "listbox":
    case "radiogroup":
      if ($control_type=="radiogroup")
      {
        $option_template="radio";
      }
      else
      {
        $option_template="select";
      }
      $lookup_config=$form_config["lookups"][$field_name];
      $option_params=array();
      $option_params["name"]=$field_params["field_name"];
      $option_params["value"]="";
      $option_params["caption"]="";
      $option_params["disabled"]=\webdb\forms\field_disabled($form_config,$field_name);
      $option_params["js_events"]=\webdb\forms\field_js_events($form_config,$field_name,$record);
      $options=array();
      $exclude_null=false;
      if (isset($lookup_config["exclude_null"])==true)
      {
        if ($lookup_config["exclude_null"]==true)
        {
          $exclude_null=true;
        }
      }
      if ($exclude_null==false)
      {
        $options[]=\webdb\utils\template_fill($option_template."_option",$option_params);
      }
      $records=\webdb\forms\lookup_field_data($form_config,$field_name);
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
        $option_params["name"]=$form_config["page_id"].":edit_control:".$field_params["primary_key"].":".$field_name;
        $option_params["value"]=\webdb\utils\webdb_htmlspecialchars($loop_record[$lookup_config["key_field"]]);
        $display_value=\webdb\forms\lookup_field_display_value($lookup_config,$loop_record);
        $option_params["caption"]=\webdb\utils\webdb_htmlspecialchars($display_value);
        $option_params["disabled"]=\webdb\forms\field_disabled($form_config,$field_name);
        $option_params["js_events"]=\webdb\forms\field_js_events($form_config,$field_name,$record);
        $excluded_parent=false;
        if (isset($lookup_config["exclude_parent"])==true)
        {
          if ($lookup_config["exclude_parent"]==true)
          {
            if (isset($form_config["parent_form_id"])==true)
            {
              # editor subform
              if ($option_params["value"]==$form_config["parent_form_id"])
              {
                $excluded_parent=true;
              }
            }
            else
            {
              # list/editor form
              $primary_key=\webdb\forms\config_id_url_value($form_config,$record,"primary_key");
              if ($option_params["value"]==$primary_key)
              {
                $excluded_parent=true;
              }
            }
          }
        }
        if ($excluded_parent==true)
        {
          continue;
        }
        if (isset($lookup_config["sibling_filter_fields"])==true)
        {
          $sibling_filter_fields=$lookup_config["sibling_filter_fields"];
          $sibling_filter_fields=\webdb\utils\webdb_explode(",",$sibling_filter_fields);
          for ($j=0;$j<count($sibling_filter_fields);$j++)
          {
            $sibling_filter_fieldname=$sibling_filter_fields[$j];
            if ($loop_record[$sibling_filter_fieldname]<>$record[$sibling_filter_fieldname])
            {
              continue 2;
            }
          }
        }
        if (($loop_record[$lookup_config["key_field"]]==$record[$field_name]) and ($selected_found==false))
        {
          $options[]=\webdb\utils\template_fill($option_template."_option_selected",$option_params);
          $selected_found=true;
        }
        else
        {
          $options[]=\webdb\utils\template_fill($option_template."_option",$option_params);
        }
      }
      if ($control_type=="radiogroup")
      {
        $options=implode(\webdb\utils\template_fill("break").PHP_EOL,$options);
      }
      else
      {
        $options=implode(PHP_EOL,$options);
      }
      $field_params["options"]=$options;
      $submit_fields[]=$field_params["field_name"];
      break;
    case "date":
      $settings["calendar_fields"][]=$form_config["page_id"].":edit_control:".$field_params["primary_key"].":".$field_name;
      if ($record[$field_name]==null)
      {
        $field_params["field_value"]="";
        $field_params["iso_field_value"]="";
      }
      else
      {
        $field_params["field_value"]=date($settings["app_date_format"],\webdb\utils\webdb_strtotime($record[$field_name]));
        $field_params["iso_field_value"]=date("Y-m-d",\webdb\utils\webdb_strtotime($field_params["field_value"]));
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
    $caption="&nbsp;&nbsp;".$form_config["captions"][$field_name];
    $lines=\webdb\utils\webdb_explode("@@forms/col_title_break@@",$caption);
    for ($i=0;$i<count($lines);$i++)
    {
      $line=htmlspecialchars_decode($lines[$i]);
      $line=html_entity_decode($line);
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
  $data["controls_min_width"]=round($data["rotate_span_width"]*0.707)-5;
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

function list_form_content($form_config,$records=false,$insert_default_params=false,$link_records=false)
{
  global $settings;
  if (isset($_GET["ajax"])==false)
  {
    if ((isset($_GET["parent_form"])==true) and (isset($_GET["parent_id"])==true))
    {
      # used for outputting subforms as full page reports (for printing typically)
      $parent_form_config=\webdb\forms\get_form_config($_GET["parent_form"]);
      foreach ($parent_form_config["edit_subforms"] as $subform_page_id => $subform_link_field)
      {
        if ($form_config["page_id"]==$subform_page_id)
        {
          $parent_id=$_GET["parent_id"];
          unset($_GET["parent_form"]);
          unset($_GET["parent_id"]);
          $form_config["insert_new"]=false;
          $form_config["advanced_search"]=false;
          $form_config["insert_row"]=false;
          $form_config["delete_cmd"]=false;
          $form_config["multi_row_delete"]=false;
          $lookup_records=\webdb\forms\lookup_records($form_config);
          $parent_key_field=$parent_form_config["primary_key"];
          $lookup_config=$form_config["lookups"][$parent_key_field];
          $lookup_records=$lookup_records[$parent_key_field];
          for ($i=0;$i<count($lookup_records);$i++)
          {
            $lookup_record=$lookup_records[$i];
            if ($lookup_record[$parent_key_field]==$parent_id)
            {
              $lookup_value=\webdb\forms\lookup_field_display_value($lookup_config,$lookup_record);
              $head_params=array();
              $head_params["parent_form_lookup_display_field"]=$lookup_value;
              $head=\webdb\forms\form_template_fill("subform_print_head",$head_params);
              return $head.\webdb\forms\get_subform_content($form_config,$subform_link_field,$parent_id,true,$parent_form_config);
            }
          }
        }
      }
    }
  }
  if (($form_config["records_sql"]<>"") and ($records===false))
  {
    $sql=\webdb\utils\sql_fill($form_config["records_sql"]);
    $records=\webdb\sql\fetch_prepare($sql,array(),$form_config["records_sql"],false,"","",$form_config);
  }
  if ($link_records===false)
  {
    $form_config["checklist"]=false;
  }
  if ($form_config["advanced_search_page_id"]=="")
  {
    $form_config["advanced_search_page_id"]=$form_config["page_id"];
  }
  $form_params=array();
  $form_params["page_id"]=$form_config["page_id"];
  $form_params["edit_cmd_page_id"]=$form_config["page_id"];
  if ($form_config["edit_cmd_page_id"]<>"")
  {
    $form_params["edit_cmd_page_id"]=$form_config["edit_cmd_page_id"];
  }
  $form_params["insert_cmd_page_id"]=$form_config["page_id"];
  if ($form_config["insert_cmd_page_id"]<>"")
  {
    $form_params["insert_cmd_page_id"]=$form_config["insert_cmd_page_id"];
  }
  $form_params["advanced_search_page_id"]=$form_config["page_id"];
  if ($form_config["advanced_search_page_id"]<>"")
  {
    $form_params["advanced_search_page_id"]=$form_config["advanced_search_page_id"];
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
    $header_params["field_name"]="&nbsp;&nbsp;".$form_config["captions"][$field_name];
    $header_params["sort_link"]="";
    if ((isset($_GET["cmd"])==false) and (isset($_GET["ajax"])==false) and ($form_config["sort_enabled"]==true))
    {
      $sort_link_params=array();
      $sort_link_params["page_id"]=$form_config["page_id"];
      $sort_link_params["field_name"]=$field_name;
      $header_params["sort_link"]=\webdb\forms\form_template_fill("sort_link",$sort_link_params);
    }
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
    if (\webdb\utils\webdb_strtolower($settings["browser_info"]["browser"])=="firefox")
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
    if (\webdb\utils\webdb_strtolower($settings["browser_info"]["browser"])=="firefox")
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
    $header_params["sort_link"]="";
    $field_headers.=\webdb\forms\form_template_fill("list_field_header",$header_params);
  }
  $head_params=\webdb\forms\header_row($form_config);
  $form_params=array_merge($form_params,$head_params);
  $form_params["field_headers"]=$field_headers;
  $form_params["selected_filter_input"]="";
  \webdb\forms\process_filter_sql($form_config);
  if (count($form_config["filter_options"])>0)
  {
    #$form_config["insert_row"]=false; # may be needed if filter would not include inserted row (newly inserted row not appearing due to filtering) but generally shouldn't be needed if form config is done carefully - commented out to allow inserting in a subform whose inverse is a checklist with an enabled field (filter needed to hide disabled rows from subform with insert row)
    $form_config["filter_cookie_value"]=0;
    if ($form_config["filter_cookie"]==true)
    {
      $form_config["filter_cookie_value"]=1;
    }
    $params=array();
    $params["page_id"]=$form_config["page_id"];
    $params["selected_filter"]=$form_config["selected_filter"];
    $params["filter_cookie_value"]=$form_config["filter_cookie_value"];
    $form_params["selected_filter_input"]=\webdb\forms\form_template_fill("selected_filter_input",$params);
  }
  if ($records===false)
  {
    if ((isset($_GET["sort"])==true) and ($form_config["sort_enabled"]==true))
    {
      $sort_field=$_GET["sort"];
      if (isset($form_config["control_types"][$sort_field])==true)
      {
        $sort_field_params=array();
        $sort_field_params["field_name"]=$sort_field;
        $sort_field_params["direction"]="ASC";
        if (isset($_GET["dir"])==true)
        {
          $dir=\webdb\utils\webdb_strtoupper($_GET["dir"]);
          if (($dir=="ASC") or ($dir=="DESC"))
          {
            $sort_field_params["direction"]=$dir;
          }
        }
        $sort_sql=\webdb\utils\sql_fill("sort_field",$sort_field_params);
        $form_config["sort_sql"]=$sort_sql;
      }
    }
    \webdb\forms\process_sort_sql($form_config);
    $sql_params=array();
    $sql_params["database"]=$form_config["database"];
    $sql_params["table"]=$form_config["table"];
    $sql_params["selected_filter_sql"]=$form_config["selected_filter_sql"];
    $sql_params["sort_sql"]=$form_config["sort_sql"];
    $sql=\webdb\utils\sql_fill("form_list_fetch_all",$sql_params);
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
  $lookup_records=\webdb\forms\lookup_records($form_config);
  $form_params["is_checklist"]="0";
  if ($form_config["checklist"]==true)
  {
    $form_params["is_checklist"]="1";
    if ($form_config["checklist_sort"]=="top")
    {
      # arrange checklist records with checked first
      $primary_key=$form_config["primary_key"];
      $link_key=$form_config["link_key"];
      $checked_records=array();
      $unchecked_records=array();
      for ($i=0;$i<count($records);$i++)
      {
        $record=$records[$i];
        for ($j=0;$j<count($link_records);$j++)
        {
          $test_link_record=$link_records[$j];
          if ($record[$primary_key]==$test_link_record[$link_key])
          {
            if ($form_config["checklist_enabled_fieldname"]<>"")
            {
              $enabled_fieldname=$form_config["checklist_enabled_fieldname"];
              if ($test_link_record[$enabled_fieldname]==false)
              {
                continue;
              }
            }
            $checked_records[]=$record;
            continue 2;
          }
        }
        $unchecked_records[]=$record;
      }
      $records=array_merge($checked_records,$unchecked_records);
    }
  }
  # ~~~~~~~~~~~~~~~~~~~~~~~
  $event_params=array();
  $event_params["form_config"]=$form_config;
  $event_params["records"]=$records;
  $event_params["link_records"]=$link_records;
  $event_params["lookup_records"]=$lookup_records;
  if (isset($form_config["event_handlers"]["on_before_rows"])==true)
  {
    $func_name=$form_config["event_handlers"]["on_before_rows"];
    if (function_exists($func_name)==true)
    {
      $event_params=call_user_func($func_name,$event_params);
      $records=$event_params["records"];
      $link_records=$event_params["link_records"];
      $form_config=$event_params["form_config"];
      $lookup_records=$event_params["lookup_records"];
    }
  }
  # ~~~~~~~~~~~~~~~~~~~~~~~
  $form_params["record_count"]=count($records);
  $rows=array();
  for ($i=0;$i<count($records);$i++)
  {
    $record=$records[$i];
    # TODO: \webdb\utils\get_lock($schema,$table,$key_field,$key_value)
    $link_record=false;
    if ($link_records!==false)
    {
      for ($j=0;$j<count($link_records);$j++)
      {
        $test_link_record=$link_records[$j];
        $primary_key=$form_config["primary_key"];
        $link_key=$form_config["link_key"];
        if ($record[$primary_key]==$test_link_record[$link_key])
        {
          $link_record=$test_link_record;
          break;
        }
      }
    }
    $record=\webdb\forms\process_computed_fields($form_config,$record);
    $rows[]=\webdb\forms\list_row($form_config,$record,$column_format,$row_spans,$lookup_records,$i,$link_record);
  }
  if (isset($_GET["format"])==true)
  {
    $format_params=array();
    $format_params["data"]=implode(PHP_EOL,$rows);
    $content=\webdb\forms\form_template_fill("format_output",$format_params);
    die($content);
  }
  $rows=implode("",$rows);
  $form_params["insert_row_controls"]="";
  $show_insert_row=false;
  if ($form_config["insert_row"]==true)
  {
    if ($form_config["records_sql"]=="")
    {
      $show_insert_row=true;
    }
    else
    {
      if (isset($form_config["event_handlers"]["on_insert_record"])==true)
      {
        $func_name=$form_config["event_handlers"]["on_insert_record"];
        if (function_exists($func_name)==true)
        {
          $show_insert_row=true;
        }
      }
    }
  }
  if ($show_insert_row==true)
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
  $control_params=array();
  $control_params["page_id"]=$form_config["page_id"];
  if (($form_config["records_sql"]=="") or ($form_config["checklist"]==false))
  {
    if ($form_config["advanced_search"]==true)
    {
      $form_params["advanced_search_control"]=\webdb\forms\form_template_fill("list_advanced_search",$control_params);
    }
    if ($form_config["multi_row_delete"]==true)
    {
      $control_params["multi_row_delete_button_caption"]=$form_config["multi_row_delete_button_caption"];
      $form_params["delete_selected_control"]=\webdb\forms\form_template_fill("list_del_selected",$control_params);
    }
  }
  if (($form_config["insert_new"]==true) and ($form_config["checklist"]==false))
  {
    $form_params["insert_control"]=\webdb\forms\form_template_fill("list_insert",$control_params);
  }
  $form_params["row_edit_mode"]=$form_config["edit_cmd"];
  $form_params["custom_form_above"]="";
  $form_params["custom_form_below"]="";
  $delete_flag=false;
  if (isset($_POST["form_cmd"])==true)
  {
    if ($_POST["form_cmd"]=="delete")
    {
      $delete_flag=true;
    }
  }
  if (isset($_GET["cmd"])==true)
  {
    if ($_GET["cmd"]=="delete")
    {
      $delete_flag=true;
    }
  }
  if ($delete_flag==false)
  {
    if ($form_config["custom_form_above_template"]<>"")
    {
      $form_params["custom_form_above"]=\webdb\utils\template_fill($form_config["custom_form_above_template"],$form_params);
    }
    if ($form_config["custom_form_below_template"]<>"")
    {
      $form_params["custom_form_below"]=\webdb\utils\template_fill($form_config["custom_form_below_template"],$form_params);
    }
  }
  $event_params=array();
  $event_params["handled"]=false;
  $event_params["content"]="";
  $event_params=\webdb\forms\handle_form_config_event($form_config,$event_params,"on_custom_form_above");
  if ($event_params["handled"]==true)
  {
    $form_params["custom_form_above"]=$event_params["content"];
  }
  $event_params["handled"]=false;
  $event_params["content"]="";
  $event_params=\webdb\forms\handle_form_config_event($form_config,$event_params,"on_custom_form_below");
  if ($event_params["handled"]==true)
  {
    $form_params["custom_form_below"]=$event_params["content"];
  }
  $form_params["redirect"]="";
  $form_params["parent_id"]="";
  $form_params["parent_form_page_id"]="";
  if ((isset($form_config["parent_form_id"])==true) and (isset($form_config["parent_form_config"])==true))
  {
    if ($form_config["parent_form_config"]!==false)
    {
      $form_params["parent_id"]=$form_config["parent_form_id"];
      $form_params["parent_form_page_id"]=$form_config["parent_form_config"]["page_id"];
    }
  }
  $form_params["reset_sort_link"]="";
  if ((isset($_GET["cmd"])==false) and (isset($_GET["ajax"])==false) and ($form_config["sort_enabled"]==true))
  {
    $form_params["reset_sort_link"]=\webdb\forms\form_template_fill("reset_sort_link",$form_params);
  }
  return \webdb\forms\form_template_fill("list",$form_params);
}

#####################################################################################################

function advanced_search($form_config)
{
  global $settings;
  $rows="";
  $sql_params=array();
  $null_fields=array();
  $not_null_fields=array();
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
    $field_params["field_value"]=\webdb\utils\webdb_htmlspecialchars($field_value);
    $search_control_type="text";
    switch ($control_type)
    {
      case "checkbox":
        $search_control_type="checkbox";
        $checkbox_operators=array(""=>"","checked"=>"1","unchecked"=>"0");
        $selected_option=$field_value;
        $field_params["options"]="";
        foreach ($checkbox_operators as $caption => $value)
        {
          $option_params=array();
          $option_params["value"]=$value;
          $option_params["caption"]=$caption;
          if ($value===$selected_option)
          {
            $field_params["options"].=\webdb\utils\template_fill("select_option_selected",$option_params);
          }
          else
          {
            $field_params["options"].=\webdb\utils\template_fill("select_option",$option_params);
          }
        }
        switch ($field_value)
        {
          case "0":
            $sql_params[$field_name]=0;
            break;
          case "1":
            $sql_params[$field_name]=1;
            break;
        }
        break;
      case "lookup":
      case "span":
      case "default":
      case "raw":
      case "text":
      case "file":
      case "memo":
      case "combobox":
      case "listbox":
      case "radiogroup":
        $text_operators=array(""=>"","<"=>"<","<="=>"<=","="=>"LIKE",">="=>">=",">"=>">","not"=>"<>","assigned"=>"not_null","unassigned"=>"null");
        $selected_option="";
        if (isset($_POST["search_operator_".$field_name])==true)
        {
          $selected_option=$_POST["search_operator_".$field_name];
        }
        $field_params["options"]="";
        foreach ($text_operators as $caption => $value)
        {
          $option_params=array();
          $option_params["value"]=$value;
          $option_params["caption"]=\webdb\utils\webdb_htmlspecialchars($caption);
          if ($value==$selected_option)
          {
            $field_params["options"].=\webdb\utils\template_fill("select_option_selected",$option_params);
          }
          else
          {
            $field_params["options"].=\webdb\utils\template_fill("select_option",$option_params);
          }
        }
        if ($selected_option<>"")
        {
          switch ($selected_option)
          {
            case "null":
              $null_fields[]=$field_name;
              $field_params["field_value"]="";
              break;
            case "not_null":
              $not_null_fields[]=$field_name;
              $field_params["field_value"]="";
              break;
            default:
              $sql_params[$field_name]=$field_value;
          }
        }
        break;
      case "date":
        if (isset($_POST["iso_".$field_name])==true)
        {
          $field_value=$_POST["iso_".$field_name];
        }
        $date_operators=array(""=>"","<"=>"<","<="=>"<=","="=>"=",">="=>">=",">"=>">","not"=>"<>","assigned"=>"not_null","unassigned"=>"null");
        $selected_option="";
        if (isset($_POST["search_operator_".$field_name])==true)
        {
          $selected_option=$_POST["search_operator_".$field_name];
        }
        $field_params["options"]="";
        foreach ($date_operators as $caption => $value)
        {
          $option_params=array();
          $option_params["value"]=$value;
          $option_params["caption"]=\webdb\utils\webdb_htmlspecialchars($caption);
          if ($value==$selected_option)
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
        if (($field_value=="") or ($selected_option=="null"))
        {
          $field_params["field_value"]="";
          $field_params["iso_field_value"]="";
        }
        else
        {
          $field_params["field_value"]=date($settings["app_date_format"],\webdb\utils\webdb_strtotime($field_value));
          $field_params["iso_field_value"]=date("Y-m-d",\webdb\utils\webdb_strtotime($field_value));
        }
        if ($selected_option<>"")
        {
          switch ($selected_option)
          {
            case "null":
              $null_fields[]=$field_name;
              $field_params["field_value"]="";
              $field_params["iso_field_value"]="";
              break;
            case "not_null":
              $not_null_fields[]=$field_name;
              $field_params["field_value"]="";
              $field_params["iso_field_value"]="";
              break;
            default:
              $sql_params[$field_name]=$field_value;
          }
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
    $rows.=\webdb\forms\form_template_fill("advanced_search_row",$row_params);
  }
  $form_params=array();
  $form_params["rows"]=$rows;
  $form_params["page_id"]=$form_config["page_id"];
  $form_params["where_clause"]="";
  $records=array();
  if ((count($sql_params)>0) or (count($null_fields)>0) or (count($not_null_fields)>0))
  {
    $fieldnames=array_keys($sql_params);
    $values=array_values($sql_params);
    $placeholders=array_map("\webdb\sql\callback_prepare",$fieldnames);
    $quoted_fieldnames=array_map("\webdb\sql\callback_quote",$fieldnames);
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
      $field_name=$fieldnames[$i];
      $control_type=$form_config["control_types"][$field_name];
      $value=$values[$i];
      $operator="";
      switch ($control_type)
      {
        case "checkbox":
          $operator="=";
          break;
        case "lookup":
        case "span":
        case "default":
        case "raw":
        case "text":
        case "file":
        case "memo":
        case "combobox":
        case "listbox":
        case "radiogroup":
          if (isset($_POST["search_operator_".$field_name])==true)
          {
            $operator=$_POST["search_operator_".$field_name];
          }
          break;
        case "date":
          if (isset($_POST["search_operator_".$field_name])==true)
          {
            $operator=$_POST["search_operator_".$field_name];
          }
          break;
      }
      if ($operator=="")
      {
        continue;
      }
      $conditions[]="(".$quoted_fieldnames[$i]." ".$operator." ".$placeholders[$i].")";
    }
    $quoted_fieldnames=array_map("\webdb\sql\callback_quote",$null_fields);
    for ($i=0;$i<count($null_fields);$i++)
    {
      $conditions[]="(".$quoted_fieldnames[$i]."is null)";
    }
    $quoted_fieldnames=array_map("\webdb\sql\callback_quote",$not_null_fields);
    for ($i=0;$i<count($not_null_fields);$i++)
    {
      $conditions[]="(".$quoted_fieldnames[$i]."is not null)";
    }
    $params=array();
    $params["database"]=$form_config["database"];
    $params["table"]=$form_config["table"];
    $params["inner_joins"]=implode(" ",$inner_joins);
    $params["prepared_where"]="WHERE (".implode(" AND ",$conditions).")";
    $form_params["where_clause"]=$params["prepared_where"];
    \webdb\forms\process_sort_sql($form_config);
    $params["sort_sql"]=$form_config["sort_sql"];
    $sql=\webdb\utils\sql_fill("form_list_advanced_search",$params);
    $records=\webdb\sql\fetch_prepare($sql,$sql_params,"form_list_advanced_search",false,$form_config["table"],$form_config["database"],$form_config);
  }
  $search_page_params=array();
  $search_page_params["advanced_search"]=\webdb\forms\form_template_fill("advanced_search",$form_params);
  $form_config["insert_new"]=false;
  $form_config["insert_row"]=false;
  $form_config["advanced_search"]=false;
  $form_config["delete_cmd"]=false;
  $form_config["multi_row_delete"]=false;
  $search_page_params["advanced_search_results"]=\webdb\forms\list_form_content($form_config,$records,false);
  $search_page_params["form_script_modified"]=\webdb\utils\resource_modified_timestamp("list.js");
  $search_page_params["form_styles_modified"]=\webdb\utils\resource_modified_timestamp("list.css");
  $search_page_params["form_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("list_print.css");
  \webdb\forms\handle_links_menu($form_config,$search_page_params);
  $search_page_params["title"]="Advanced Search: ".$form_config["title"];
  $result=array();
  $result["title"]=$form_config["title"].": Advanced Search";
  $result["content"]=\webdb\forms\form_template_fill("advanced_search_page",$search_page_params);
  return $result;
}

#####################################################################################################

function default_value($form_config,$field_name)
{
  return \webdb\utils\string_template_fill($form_config["default_values"][$field_name]);
}

#####################################################################################################

function default_values($form_config)
{
  $conditions=\webdb\forms\config_id_conditions($form_config,"","primary_key");
  $record=$form_config["default_values"];
  foreach ($record as $field_name => $value)
  {
    $record[$field_name]=\webdb\forms\default_value($form_config,$field_name);
  }
  foreach ($conditions as $key => $value)
  {
    $record[$key]=$value;
  }
  return $record;
}

#####################################################################################################

function insert_form($form_config)
{
  global $settings;
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"i")==false)
  {
    \webdb\utils\error_message("error: record insert permission denied for form '".$form_config["page_id"]."'");
  }
  $record=\webdb\forms\default_values($form_config);
  $url_params=\webdb\forms\insert_default_url_params();
  foreach ($url_params as $name => $value)
  {
    $record[$name]=\webdb\utils\webdb_htmlspecialchars($value);
  }
  $id=\webdb\forms\config_id_url_value($form_config,$record,"primary_key");
  if ($id==="")
  {
    $id=false;
  }
  return \webdb\forms\output_editor($form_config,$record,"Insert","Insert",$id);
}

#####################################################################################################

function edit_form($form_config,$id)
{
  global $settings;
  $record=\webdb\forms\get_record_by_id($form_config,$id,"edit_cmd_id");
  $data=\webdb\forms\output_editor($form_config,$record,"Edit","Update",$id);
  $record=$data["record"];
  if ($form_config["edit_title_field"]<>"")
  {
    $title_field_name=$form_config["edit_title_field"];
    $value=$record[$title_field_name];
    if ($value<>"")
    {
      $data["title"].=" ".$value;
    }
  }
  return $data;
}

#####################################################################################################

function output_editor($form_config,$record,$command,$verb,$id=false)
{
  global $settings;
  $record=\webdb\forms\process_computed_fields($form_config,$record);
  $cmd=\webdb\utils\webdb_strtolower($command);
  $form_params=array();
  $form_params["page_id"]=$form_config["page_id"];
  $form_params["form_script_modified"]=\webdb\utils\resource_modified_timestamp("list.js");
  $form_params["form_styles_modified"]=\webdb\utils\resource_modified_timestamp("list.css");
  $form_params["form_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("list_print.css");
  \webdb\forms\handle_links_menu($form_config,$form_params);
  $form_params["command"]=$command;
  $form_params["command_caption_noun"]=$form_config["command_caption_noun"];
  $form_params["cmd"]=$cmd;
  $form_params["id_url_param"]="";
  $form_params["id_cmd_name"]="";
  $form_params["id"]="";
  if ($id!==false)
  {
    $form_params["id"]=$id;
    $id_params=array();
    $id_params["id"]=$id;
    $form_params["id_url_param"]=\webdb\forms\form_template_fill("id_url_param",$id_params);
    $form_params["id_cmd_name"]=\webdb\forms\form_template_fill("id_cmd_name",$id_params);
  }
  #$form_params["confirm_caption"]=$verb." ".$form_config["command_caption_noun"];
  $form_params["confirm_caption"]="Save ".$form_config["command_caption_noun"]; # replace 'insert' and 'update' with 'save'
  $hidden_fields="";
  $event_params=array();
  $event_params["handled"]=false;
  $event_params["custom_content"]=false;
  $event_params["content"]="";
  $event_params["form_config"]=$form_config;
  $event_params["record"]=$record;
  $event_params["hidden_fields"]=$hidden_fields;
  $event_params["record_id"]="";
  $event_params["cmd"]=$cmd;
  if ($id!==false)
  {
    $event_params["record_id"]=$id;
  }
  $event_params=\webdb\forms\handle_form_config_event($form_config,$event_params,"on_".$cmd);
  $record=$event_params["record"];
  $hidden_fields=$event_params["hidden_fields"];
  if ($event_params["handled"]==true)
  {
    $form_params["field_table"]=$event_params["content"];
  }
  else
  {
    $submit_fields=array();
    $lookup_records=\webdb\forms\lookup_records($form_config);
    $rows=array();
    foreach ($form_config["control_types"] as $field_name => $control_type)
    {
      if (isset($form_config["editor_visible"][$field_name])==true)
      {
        if ($form_config["editor_visible"][$field_name]==false)
        {
          continue;
        }
      }
      elseif ($form_config["editor_visible"]===true)
      {
        if ($form_config["visible"][$field_name]==false)
        {
          continue;
        }
      }
      if ($control_type=="title")
      {
        $title_params=array();
        $title_params["title"]=$form_config["captions"][$field_name];
        $rows[$field_name]=\webdb\forms\form_template_fill("editor_title_field",$title_params);
        continue;
      }
      $field_value="";
      if ((isset($record[$field_name])==true) and ($control_type<>"lookup"))
      {
        $field_value=$record[$field_name];
      }
      $field_params=array();
      $field_params["page_id"]=$form_config["page_id"];
      $field_params["disabled"]=\webdb\forms\field_disabled($form_config,$field_name);
      $field_params["js_events"]=\webdb\forms\field_js_events($form_config,$field_name,$record);
      $field_params["field_name"]=$field_name;
      $field_params["field_value"]=$field_value;
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
      $row_params["field_value"]=\webdb\forms\output_editable_field($field_params,$record,$field_name,$control_type,$form_config,$lookup_records,$submit_fields);
      $row_params["interface_button"]=\webdb\forms\get_interface_button($form_config,$record,$field_name,$field_value);
      $row_params["row_attribs"]="";
      if (isset($form_config["editor_row_attribs"][$field_name])==true)
      {
        $row_params["row_attribs"]=$form_config["editor_row_attribs"][$field_name];
      }
      if ($control_type<>"hidden")
      {
        if ($form_config["custom_".\webdb\utils\webdb_strtolower($command)."_template"]=="")
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
    $form_params["rows"]=implode(PHP_EOL,$rows);
    if ($form_config["custom_".$cmd."_template"]=="")
    {
      $form_params["field_table"]=\webdb\forms\form_template_fill("editor_field_table",$form_params);
    }
    else
    {
      $rows["page_id"]=$form_params["page_id"];
      $rows["record_id"]=$id;
      $rows["cmd"]=$cmd;
      $form_params["field_table"]=\webdb\utils\template_fill($form_config["custom_".$cmd."_template"],$rows);
    }
  }
  $form_params["hidden_fields"]=$hidden_fields;
  $subforms="";
  if ($id!==false)
  {
    foreach ($form_config["edit_subforms"] as $subform_page_id => $subform_link_field)
    {
      $subform_config=\webdb\forms\get_form_config($subform_page_id,false);
      $subforms.=\webdb\forms\get_subform_content($subform_config,$subform_link_field,$id,false,$form_config);
    }
  }
  $form_params["chat"]="";
  if (($settings["chat_global_enable"]==true) and ($form_config["chat_enabled"]==true) and (isset($_GET["cmd"])==true) and (empty($form_params["id"])==false))
  {
    if ($_GET["cmd"]=="edit")
    {
      $form_params["chat"]=\webdb\chat\chat_dispatch($form_params["id"],$form_config,$record);
    }
  }
  $form_params["subforms"]=$subforms;
  $form_params["custom_form_above"]="";
  $form_params["custom_form_below"]="";
  if ($form_config["custom_form_above_template"]<>"")
  {
    $form_params["custom_form_above"]=\webdb\utils\template_fill($form_config["custom_form_above_template"],$form_params);
  }
  if ($form_config["custom_form_below_template"]<>"")
  {
    $form_params["custom_form_below"]=\webdb\utils\template_fill($form_config["custom_form_below_template"],$form_params);
  }
  $event_params=array();
  $event_params["handled"]=false;
  $event_params["content"]="";
  $event_params=\webdb\forms\handle_form_config_event($form_config,$event_params,"on_custom_form_above");
  if ($event_params["handled"]==true)
  {
    $form_params["custom_form_above"]=$event_params["content"];
  }
  $event_params["handled"]=false;
  $event_params["content"]="";
  $event_params=\webdb\forms\handle_form_config_event($form_config,$event_params,"on_custom_form_below");
  if ($event_params["handled"]==true)
  {
    $form_params["custom_form_below"]=$event_params["content"];
  }
  \webdb\forms\editor_command_button($form_config,$command,$form_params);
  $content=\webdb\forms\form_template_fill("editor_page",$form_params);
  $title=$form_config["title"].": ".$command;
  $result=array();
  $result["title"]=$title;
  $result["content"]=$content;
  $result["record"]=$record;
  return $result;
}

#####################################################################################################

function editor_command_button($form_config,$command,&$form_params)
{
  $form_params["edit_form_controls"]="";
  $form_params["title"]=$form_config["command_caption_noun"];
  if ($command==="Edit")
  {
    if (\webdb\utils\check_user_form_permission($form_config["page_id"],"u")==false)
    {
      return;
    }
  }
  else
  {
    if (\webdb\utils\check_user_form_permission($form_config["page_id"],"i")==false)
    {
      return;
    }
  }
  $form_params["form_cmd_handler"]="";
  $cmd_handler=\webdb\utils\webdb_strtolower($command)."_cmd_handler";
  if ($form_config[$cmd_handler]<>"")
  {
    $handler_params=array();
    $handler_params["function_name"]=$form_config[$cmd_handler];
    $form_params["form_cmd_handler"]=\webdb\forms\form_template_fill("form_cmd_handler",$handler_params);
  }
  $form_params["edit_form_controls"]=\webdb\forms\form_template_fill("edit_form_controls",$form_params);
  $form_params["title"]=$command." ".$form_config["command_caption_noun"];
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
  if (isset($lookup_config["constant_value"])==true)
  {
    return $lookup_config["constant_value"];
  }
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
  if (isset($lookup_config["where_clause"])==false)
  {
    $lookup_config["where_clause"]="";
  }
  $db_engine=$settings["db_engine"];
  if (isset($lookup_config["where_clause_".$db_engine])==true)
  {
    $lookup_config["where_clause"]=$lookup_config["where_clause_".$db_engine];
  }
  if (isset($lookup_config["display_field"])==false)
  {
    $lookup_config["display_field"]=$lookup_config["key_field"];
  }
  if ($lookup_config["lookup_sql_file"]=="")
  {
    $filename="form_lookup";
    $database=$lookup_config["database"];
    $table=$lookup_config["table"];
    if ($lookup_config["where_clause"]<>"")
    {
      $where_params=array();
      $where_params["where_items"]=$lookup_config["where_clause"];
      $lookup_config["where_clause"]=\webdb\utils\sql_fill("where_clause",$where_params);
    }
    if ($lookup_config["order_by"]=="")
    {
      $display_fields=\webdb\utils\webdb_explode(",",$lookup_config["display_field"]);
      $first_display_field=array_shift($display_fields);
      $lookup_config["order_by"]=$first_display_field." ASC";
    }
    if ($lookup_config["display_field"]==$lookup_config["key_field"])
    {
      $sql=\webdb\utils\sql_fill("form_lookup_key",$lookup_config);
    }
    else
    {
      $sql=\webdb\utils\sql_fill("form_lookup",$lookup_config);
    }
  }
  else
  {
    $filename=$lookup_config["lookup_sql_file"];
    $database="";
    $table="";
    if ($lookup_config["where_clause"]<>"")
    {
      $where_params=array();
      $where_params["where_items"]=$lookup_config["where_clause"];
      $lookup_config["where_clause"]=\webdb\utils\sql_fill("where_clause",$where_params);
    }
    $sql=\webdb\utils\sql_fill($lookup_config["lookup_sql_file"]);
    $sql.=" ".$lookup_config["where_clause"];
    $sql=\webdb\utils\string_template_fill($sql);
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

function upload_file($form_config,$field_name,$record_id)
{
  global $settings;
  if (isset($_FILES)==false)
  {
    return;
  }
  $page_id=$form_config["page_id"];
  $submit_name=$page_id.":edit_control:".$record_id.":".$field_name;
  if (isset($_FILES[$submit_name])==false)
  {
    #\webdb\utils\error_message("error: file upload submit name not found: ".$submit_name);
    return;
  }
  $upload_data=$_FILES[$submit_name];
  $upload_filename=$upload_data["tmp_name"];
  if (file_exists($upload_filename)==false)
  {
    #\webdb\utils\error_message("error: uploaded file not found");
    return;
  }
  if ($record_id=="")
  {
    $record_id=\webdb\sql\sql_last_insert_autoinc_id();
  }
  $target_filename=\webdb\forms\get_uploaded_full_filename($form_config,$record_id,$field_name);
  switch ($settings["file_upload_mode"])
  {
    case "rename":
      rename($upload_filename,$target_filename);
      return;
    case "ftp":
      $connection=\webdb\utils\webdb_ftp_login();
      if (ftp_put($connection,$target_filename,$upload_filename,FTP_BINARY)==false)
      {
        \webdb\utils\error_message("error: unable to upload file to FTP server");
      }
      ftp_close($connection);
      return;
  }
  \webdb\utils\error_message("error: invalid file upload mode");
}

#####################################################################################################

function get_uploaded_full_filename($form_config,$record_id,$field_name)
{
  global $settings;
  $filename=\webdb\forms\get_uploaded_filename($form_config,$record_id,$field_name);
  $path=\webdb\utils\get_upload_path();
  return $path.$filename;
}

#####################################################################################################

function get_uploaded_filename($form_config,$record_id,$field_name)
{
  global $settings;
  $page_id=$form_config["page_id"];
  if ($form_config["file_field_page_id"]<>"")
  {
    $page_id=$form_config["file_field_page_id"];
  }
  $filename=$page_id."__".$record_id."__".$field_name;
  $filename=\webdb\utils\webdb_str_replace(DIRECTORY_SEPARATOR,"_",$filename);
  return \webdb\utils\webdb_str_replace(" ","_",$filename);
}

#####################################################################################################

function upload_files($form_config,$record_id,$value_items=false)
{
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    switch ($control_type)
    {
      case "file":
        \webdb\forms\upload_file($form_config,$field_name,$record_id);
        break;
    }
  }
}

#####################################################################################################

function insert_default_url_params()
{
  $params=array();
  foreach ($_GET as $param_name => $param_value)
  {
    switch ($param_name)
    {
      case "page":
      case "update_oul":
      case "chat_break":
      case "cmd":
      case "redirect":
      case "filters":
      case "ajax":
      case "subform":
      case "parent_form":
      case "parent_id":
      case "sort":
      case "home":
      case "format":
      case "dir":
      case "basic_search":
      case "file":
      case "article":
      case "rev":
      case "mod":
      case "search":
        break;
      default:
        $params[$param_name]=$param_value;
    }
  }
  return $params;
}

#####################################################################################################

function process_form_data_fields($form_config,$record_id,$post_override=false)
{
  global $settings;
  $post_fields=$_POST;
  if ($post_override!==false)
  {
    $post_fields=$post_override;
  }
  $value_items=array();
  $page_id=$form_config["page_id"];
  foreach ($form_config["control_types"] as $field_name => $control_type)
  {
    if (($form_config["checklist"]==true) and (in_array($field_name,$form_config["link_fields"])==false))
    {
      continue;
    }
    switch ($control_type)
    {
      case "lookup":
      case "span":
        continue 2;
      case "default":
        $value_items[$field_name]=\webdb\forms\default_value($form_config,$field_name);
        continue 2;
    }
    $post_name=$page_id.":edit_control:".$record_id.":".$field_name;
    switch ($control_type)
    {
      case "file":
        if (isset($_FILES[$post_name]["name"])==true)
        {
          $filename=$_FILES[$post_name]["name"];
          if ($filename<>"")
          {
            $ext=pathinfo($filename,PATHINFO_EXTENSION);
            if (isset($settings["permitted_upload_types"][\webdb\utils\webdb_strtolower($ext)])==false)
            {
              \webdb\utils\error_message("error: file type not permitted");
            }
            $value_items[$field_name]=$filename;
          }
        }
        elseif (isset($post_fields[$post_name])==true)
        {
          $filename=$post_fields[$post_name];
          if ($filename===-1)
          {
            # file deleted
            $value_items[$field_name]=null;
          }
        }
        break;
      case "checkbox":
        if (isset($post_fields[$post_name])==true)
        {
          if ($post_fields[$post_name]=="false")
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
        $iso_post_name=$page_id.":edit_control:".$record_id.":iso_".$field_name;
        if (isset($post_fields[$iso_post_name])==true)
        {
          $value_items[$field_name]=$post_fields[$iso_post_name];
          if ($post_fields[$iso_post_name]=="")
          {
            $value_items[$field_name]=null;
          }
        }
        break;
    }
    if (array_key_exists($post_name,$post_fields)==false)
    {
      continue;
    }
    switch ($control_type)
    {
      case "text":
      case "hidden":
        $value_items[$field_name]=$post_fields[$post_name];
        if ($value_items[$field_name]=="")
        {
          $value_items[$field_name]=null;
        }
        break;
      case "combobox":
      case "listbox":
      case "radiogroup":
        $value_items[$field_name]=$post_fields[$post_name];
        if ($value_items[$field_name]=="")
        {
          $value_items[$field_name]=null;
        }
        break;
      case "memo":
        $value_items[$field_name]=\webdb\utils\webdb_str_replace(PHP_EOL,\webdb\index\LINEBREAK_DB_DELIM,$post_fields[$post_name]);
        if ($value_items[$field_name]=="")
        {
          $value_items[$field_name]=null;
        }
        break;
      case "raw":
        $value_items[$field_name]=$post_fields[$post_name];
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
    \webdb\utils\error_message("error: record insert permission denied for form '".$form_config["page_id"]."'");
  }
  $id="";
  if (isset($_GET["id"])==true)
  {
    $id=$_GET["id"];
  }
  $value_items=\webdb\forms\process_form_data_fields($form_config,$id);
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
    if ($id==="")
    {
      $id=\webdb\sql\sql_last_insert_autoinc_id();
    }
    \webdb\forms\upload_files($form_config,"");
  }
  else
  {
    $id=$event_params["new_record_id"];
  }
  if (($form_config["edit_cmd_page_id"]=="") or ($form_config["edit_cmd_page_id"]==$form_config["page_id"]))
  {
    \webdb\forms\set_confirm_status_cookie($form_config,"RECORD INSERTED SUCCESSFULLY");
  }
  if ($id=="")
  {
    $id=\webdb\forms\config_id_url_value($form_config,$value_items,"primary_key");
  }
  if ($form_config["edit_cmd_page_id"]<>"")
  {
    $record=\webdb\forms\get_record_by_id($form_config,$id,"primary_key");
    $params=array();
    $params["id"]=\webdb\forms\config_id_url_value($form_config,$record,"edit_cmd_id");
    $params["edit_cmd_page_id"]=$form_config["edit_cmd_page_id"];
    $url=trim(\webdb\forms\form_template_fill("edit_redirect_url",$params));
    \webdb\utils\redirect($url);
  }
  $event_params=array();
  $event_params["value_items"]=$value_items;
  $event_params["new_record_id"]=$id;
  $event_params=\webdb\forms\handle_form_config_event($form_config,$event_params,"on_after_insert_record");
  \webdb\forms\page_redirect(false,false,$id);
}

#####################################################################################################

function process_computed_fields($form_config,$value_items)
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
  return $value_items;
}

#####################################################################################################

function handle_form_config_event($form_config,$event_params,$event_name)
{
  if (isset($form_config["event_handlers"][$event_name])==true)
  {
    $func_name=$form_config["event_handlers"][$event_name];
    if (function_exists($func_name)==true)
    {
      $event_params=call_user_func($func_name,$form_config,$event_params,$event_name);
    }
  }
  return $event_params;
}

#####################################################################################################

function update_record($form_config,$id,$value_items=false,$where_items=false,$return=false)
{
  global $settings;
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"u")==false)
  {
    \webdb\utils\error_message("error: record update permission denied form form '".$form_config["page_id"]."'");
  }
  if ($value_items===false)
  {
    $value_items=\webdb\forms\process_form_data_fields($form_config,$id);
  }
  if ($where_items===false)
  {
    $where_items=\webdb\forms\config_id_conditions($form_config,$id,"edit_cmd_id");
  }
  $event_params=array();
  $event_params["handled"]=false;
  $event_params["form_config"]=$form_config;
  $event_params["record_id"]=$id;
  $event_params["where_items"]=$where_items;
  $event_params["value_items"]=$value_items;
  $event_params=\webdb\forms\handle_form_config_event($form_config,$event_params,"on_update_record");
  $where_items=$event_params["where_items"];
  $value_items=$event_params["value_items"];
  $params=false;
  if ($event_params["handled"]==false)
  {
    \webdb\forms\check_required_values($form_config,$value_items);
    \webdb\sql\sql_update($value_items,$where_items,$form_config["table"],$form_config["database"],false,$form_config);
    \webdb\forms\upload_files($form_config,$id,$value_items);
    $params=array();
    $params["update"]=$form_config["page_id"];
  }
  if ($return==true)
  {
    return;
  }
  if (($form_config["edit_cmd_page_id"]=="") or ($form_config["edit_cmd_page_id"]==$form_config["page_id"]))
  {
    \webdb\forms\set_confirm_status_cookie($form_config,"RECORD UPDATED SUCCESSFULLY");
  }
  if ($form_config["edit_cmd_page_id"]<>"")
  {
    $record=\webdb\forms\get_record_by_id($form_config,$id,"primary_key");
    $params=array();
    $params["id"]=\webdb\forms\config_id_url_value($form_config,$record,"edit_cmd_id");
    $params["edit_cmd_page_id"]=$form_config["edit_cmd_page_id"];
    $url=trim(\webdb\forms\form_template_fill("edit_redirect_url",$params));
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
      \webdb\utils\error_message("error: value required for field `".$form_config["captions"][$field_name]."`");
    }
  }
}

#####################################################################################################

function config_id_conditions($form_config,$id,$config_key)
{
  $fieldnames=\webdb\utils\webdb_explode(\webdb\index\CONFIG_ID_DELIMITER,$form_config[$config_key]);
  $values=\webdb\utils\webdb_explode(\webdb\index\CONFIG_ID_DELIMITER,$id);
  $items=array();
  for ($i=0;$i<count($fieldnames);$i++)
  {
    if (isset($values[$i])==true)
    {
      $items[$fieldnames[$i]]=$values[$i];
    }
    else
    {
      $items[$fieldnames[$i]]="";
    }
  }
  return $items;
}

#####################################################################################################

function get_record_by_id($form_config,$id,$config_key,$error_none=true)
{
  global $settings;
  $event_params=array();
  $event_params["handled"]=false;
  $event_params["form_config"]=$form_config;
  $event_params["record_id"]=$id;
  $event_params["config_key"]=$config_key;
  $event_params["record"]=false;
  $event_params=\webdb\forms\handle_form_config_event($form_config,$event_params,"on_get_record_by_id");
  if ($event_params["handled"]==true)
  {
    return $event_params["record"];
  }
  $items=\webdb\forms\config_id_conditions($form_config,$id,$config_key);
  $form_config["where_conditions"]=\webdb\sql\build_prepared_where($items);
  $sql_params=array();
  $sql_params["database"]=$form_config["database"];
  $sql_params["table"]=$form_config["table"];
  $sql_params["where_conditions"]=$form_config["where_conditions"];
  $sql=\webdb\utils\sql_fill("form_list_fetch_by_id",$sql_params);
  $records=\webdb\sql\fetch_prepare($sql,$items,"form_list_fetch_by_id",false,$form_config["table"],$form_config["database"],$form_config);
  if (count($records)==0)
  {
    if ($error_none==true)
    {
      \webdb\utils\error_message("error: no records found for id '".$id."' in query: ".$sql);
    }
    else
    {
      return false;
    }
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
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"d")==false)
  {
    \webdb\utils\error_message("error: record delete permission denied for form '".$form_config["page_id"]."'");
  }
  $record=\webdb\forms\get_record_by_id($form_config,$id,"primary_key");
  $form_params=array();
  $records=array();
  $records[]=$record;
  $list_form_config=$form_config;
  $list_form_config["multi_row_delete"]=false;
  $list_form_config["delete_cmd"]=false;
  $list_form_config["sort_enabled"]=false;
  $list_form_config["chat_enabled"]=false;
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
  $form_params["title"]="Delete ".$form_config["command_caption_noun"];
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
  $form_params["form_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("list_print.css");
  \webdb\forms\handle_links_menu($form_config,$form_params);
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
    $url_params=array();
    $url_params["page_id"]=$form_config["page_id"];
    $url=\webdb\forms\form_template_fill("form_url",$url_params);
  }
  if ($params!==false)
  {
    $query=parse_url($url,PHP_URL_QUERY);
    $base_url=substr($url,0,strpos($url,$query)); # TODO: maybe use \webdb\utils\get_base_url()
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

function delete_record($form_config,$id,$redirect=true)
{
  global $settings;
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"d")==false)
  {
    \webdb\utils\error_message("error: record delete permission denied for form '".$form_config["page_id"]."'");
  }
  $where_items=\webdb\forms\config_id_conditions($form_config,$id,"primary_key");
  $event_params=array();
  $event_params["handled"]=false;
  $event_params["form_config"]=$form_config;
  $event_params["record_id"]=$id;
  $event_params["where_items"]=$where_items;
  $event_params=\webdb\forms\handle_form_config_event($form_config,$event_params,"on_delete_record");
  $where_items=$event_params["where_items"];
  if ($event_params["handled"]==false)
  {
    \webdb\sql\sql_delete($where_items,$form_config["table"],$form_config["database"],false,$form_config);
    foreach ($form_config["control_types"] as $field_name => $control_type)
    {
      switch ($control_type)
      {
        case "file":
          \webdb\forms\delete_file($form_config,$id,$field_name);
          break;
      }
    }
  }
  if ($redirect==true)
  {
    \webdb\forms\page_redirect();
  }
}

#####################################################################################################

function delete_selected_confirmation($form_config)
{
  global $settings;
  if (\webdb\utils\check_user_form_permission($form_config["page_id"],"d")==false)
  {
    \webdb\utils\error_message("error: record delete permission denied for form '".$form_config["page_id"]."'");
  }
  $list_select_name=$form_config["page_id"].":list_select";
  if (isset($_POST[$list_select_name])==false)
  {
    \webdb\utils\error_message("No records selected.");
  }
  $list_select_array=$_POST[$list_select_name];
  $foreign_key_defs=\webdb\sql\get_foreign_key_defs($form_config["database"],$form_config["table"]);
  $form_params=array();
  $records=array();
  $hidden_id_fields="";
  $foreign_key_used=false;
  foreach ($list_select_array as $id => $value)
  {
    $record=\webdb\forms\get_record_by_id($form_config,$id,"primary_key");
    $record["fk_table_list"]="NONE";
    $record=\webdb\forms\process_computed_fields($form_config,$record);
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
  $list_form_config["sort_enabled"]=false;
  $list_form_config["chat_enabled"]=false;
  $list_form_config["edit_cmd"]="none";
  $list_form_config["insert_new"]=false;
  $list_form_config["insert_row"]=false;
  $list_form_config["advanced_search"]=false;
  $form_params["records"]=\webdb\forms\list_form_content($list_form_config,$records,false);
  $form_params["page_id"]=$form_config["page_id"];
  $form_params["title"]="Delete Selected ".$form_config["command_caption_noun"]."(s)";
  $form_params["delete_all_button"]=\webdb\forms\form_template_fill("delete_selected_cancel_controls",$form_params);
  if ($foreign_key_used==false)
  {
    $form_params["delete_all_button"]=\webdb\forms\form_template_fill("delete_selected_confirm_controls",$form_params);
  }
  $form_params["form_script_modified"]=\webdb\utils\resource_modified_timestamp("list.js");
  $form_params["form_styles_modified"]=\webdb\utils\resource_modified_timestamp("list.css");
  $form_params["form_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("list_print.css");
  \webdb\forms\handle_links_menu($form_config,$form_params);
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
    \webdb\utils\error_message("error: record(s) delete permission denied for form '".$form_config["page_id"]."'");
  }
  foreach ($_POST["id"] as $id => $value)
  {
    \webdb\forms\delete_record($form_config,$id,false);
  }
  \webdb\forms\page_redirect();
}

#####################################################################################################

function basic_search()
{
  global $settings;
  $query=\webdb\utils\webdb_strtolower($_GET["basic_search"]);
  $page_params=array();
  $page_params["title"]="basic search results: ".\webdb\utils\webdb_htmlspecialchars($query);
  $results="";
  for ($i=0;$i<count($settings["basic_search_forms"]);$i++)
  {
    $page_id=$settings["basic_search_forms"][$i];
    $form_config=\webdb\forms\get_form_config($page_id,false);
    if (count($form_config["basic_search_fields"])==0)
    {
      continue;
    }
    $form_config["insert_new"]=false;
    $form_config["insert_row"]=false;
    $form_config["advanced_search"]=false;
    $form_config["delete_cmd"]=false;
    $form_config["multi_row_delete"]=false;
    $form_config["sort_enabled"]=false;
    \webdb\forms\process_sort_sql($form_config);
    \webdb\forms\process_filter_sql($form_config);
    $sql_params=array();
    $sql_params["database"]=$form_config["database"];
    $sql_params["table"]=$form_config["table"];
    $sql_params["selected_filter_sql"]=$form_config["selected_filter_sql"];
    $sql_params["sort_sql"]=$form_config["sort_sql"];
    $sql=\webdb\utils\sql_fill("form_list_fetch_all",$sql_params);
    $records=\webdb\sql\fetch_prepare($sql,array(),"form_list_fetch_all",false,"","",$form_config);
    $lookup_records=\webdb\forms\lookup_records($form_config);
    $results_records=array();
    for ($j=0;$j<count($records);$j++)
    {
      $record=$records[$j];
      for ($k=0;$k<count($form_config["basic_search_fields"]);$k++)
      {
        $search_field_name=$form_config["basic_search_fields"][$k];
        if (isset($form_config["control_types"][$search_field_name])==false)
        {
          \webdb\utils\error_message("basic search error: field '".$search_field_name."' not found in control_types of form config with page_id '".$form_config["page_id"]."'");
        }
        $control_type=$form_config["control_types"][$search_field_name];
        if ($control_type=="checkbox")
        {
          continue;
        }
        $search_field_value="";
        if (isset($record[$search_field_name])==true)
        {
          $search_field_value=$record[$search_field_name];
        }
        switch ($control_type)
        {
          case "lookup":
          case "combobox":
          case "listbox":
          case "radiogroup":
            $search_field_value=\webdb\forms\get_lookup_field_value($search_field_name,$form_config,$lookup_records,$record);
            break;
        }
        $search_field_value=\webdb\utils\webdb_strtolower($search_field_value);
        if (strpos($search_field_value,$query)!==false)
        {
          $results_records[]=$record;
          continue 2;
        }
      }
    }
    $group_params=array();
    $group_params["title"]=$form_config["title"];
    $group_params["page_id"]=$form_config["page_id"];
    $group_params["subform"]=\webdb\forms\list_form_content($form_config,$results_records);
    $results.=\webdb\utils\template_fill("basic_search/result_group",$group_params);
  }
  $page_params["results"]=$results;
  $page_params["form_script_modified"]=\webdb\utils\resource_modified_timestamp("list.js");
  $page_params["form_styles_modified"]=\webdb\utils\resource_modified_timestamp("list.css");
  $page_params["form_styles_print_modified"]=\webdb\utils\resource_modified_timestamp("list_print.css");
  \webdb\forms\handle_links_menu(false,$page_params);
  $content=\webdb\utils\template_fill("basic_search/results",$page_params);
  $title="basic search";
  \webdb\utils\output_page($content,$title);
}

#####################################################################################################
