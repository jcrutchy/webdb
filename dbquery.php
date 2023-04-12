<?php

namespace webdb\dbquery;

#####################################################################################################

function dbquery_page_stub($form_config)
{
  global $settings;
  # log separately
  $tables=\webdb\dbquery\get_table_names();
  $page_params=array();
  $page_params["database"]=$settings["dbquery_database"];
  $page_params["dbq_select"]="*";
  $page_params["dbq_from_list"]=$tables[0];
  $page_params["dbq_from_text"]="";
  $page_params["dbq_where"]="";
  $page_params["dbq_group"]="";
  $page_params["dbq_order"]="";
  $results_head_row="";
  $results_rows="";
  $sql="";
  if (isset($_POST["execute_query"])==true)
  {
    $page_params["dbq_select"]=trim($_POST["dbq_select"]);
    if ($page_params["dbq_select"]=="")
    {
      \webdb\utils\error_message("invalid dbq_select");
    }
    if (in_array($_POST["dbq_from_list"],$tables)==false)
    {
      \webdb\utils\error_message("invalid dbq_from_select");
    }
    $page_params["dbq_from_list"]=$_POST["dbq_from_list"];
    $page_params["dbq_from_text"]=trim($_POST["dbq_from_text"]);
    $page_params["dbq_where"]=trim($_POST["dbq_where"]);
    $page_params["dbq_group"]=trim($_POST["dbq_group"]);
    $page_params["dbq_order"]=trim($_POST["dbq_order"]);
    $page_params["where"]="";
    $page_params["group"]="";
    $page_params["order"]="";
    if ($page_params["dbq_where"]<>"")
    {
      $page_params["where"]=\webdb\utils\sql_fill("dbquery/where",$page_params);
    }
    if ($page_params["dbq_group"]<>"")
    {
      $page_params["group"]=\webdb\utils\sql_fill("dbquery/group",$page_params);
    }
    if ($page_params["dbq_order"]<>"")
    {
      $page_params["order"]=\webdb\utils\sql_fill("dbquery/order",$page_params);
    }
    $sql=\webdb\utils\sql_fill("dbquery/query",$page_params);
    $records=\webdb\sql\fetch_prepare($sql);
    for ($i=0;$i<count($records);$i++)
    {
      $record=$records[$i];
      $fields="";
      foreach ($record as $fieldname => $value)
      {
        if ($i==0)
        {
          $head_params=array();
          $head_params["fieldname"]=$fieldname;
          $results_head_row.=\webdb\utils\template_fill("dbquery/result_head_field",$head_params);
        }
        $field_params=array();
        $field_params["value"]=$value;
        $fields.=\webdb\utils\template_fill("dbquery/result_field",$field_params);
      }
      $row_params=array();
      $row_params["fields"]=$fields;
      $row=\webdb\utils\template_fill("dbquery/result_row",$row_params);
      $results_rows.=$row;
    }
    if ($results_head_row<>"")
    {
      $head_params=array();
      $head_params["fields"]=$results_head_row;
      $results_head_row=\webdb\utils\template_fill("dbquery/result_row",$head_params);
    }
    if ($results_rows=="")
    {
      $results_rows=\webdb\utils\template_fill("dbquery/no_results");
    }
  }
  $page_params["sql"]=htmlspecialchars($sql);
  $page_params["results_head_row"]=$results_head_row;
  $page_params["results_rows"]=$results_rows;
  $options="";
  for ($i=0;$i<count($tables);$i++)
  {
    $table_name=$tables[$i];
    $option_params=array();
    $option_params["value"]=$table_name;
    $option_params["caption"]=$table_name;
    if ($table_name==$page_params["dbq_from_list"])
    {
      $options.=\webdb\utils\template_fill("select_option_selected",$option_params);
    }
    else
    {
      $options.=\webdb\utils\template_fill("select_option",$option_params);
    }
  }
  $page_params["from_options"]=$options;
  $content=\webdb\utils\template_fill("dbquery/page",$page_params);
  \webdb\utils\output_page($content,$form_config["title"]);
}

#####################################################################################################

function get_table_names()
{
  global $settings;
  $sql_params=array();
  $sql_params["database"]=$settings["dbquery_database"];
  $sql=\webdb\utils\sql_fill("dbquery/table_list",$sql_params);
  $records=\webdb\sql\fetch_prepare($sql);
  $result=array();
  for ($i=0;$i<count($records);$i++)
  {
    $result[]=$records[$i]["TABLE_NAME"];
  }
  return $result;
}

#####################################################################################################
