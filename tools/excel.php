<?php

namespace webdb\excel;

#####################################################################################################

function excel_to_unix_timetamp($excel_timestamp)
{
  return sprintf("%.8f",($excel_timestamp-25569)*60*60*24); # 25569 days between 1970-01-01 and 1900-01-01
}

#####################################################################################################

function sheet_rows($sheet_values)
{
  $result=array();
  foreach ($sheet_values as $c => $rows)
  {
    foreach ($rows as $r => $val)
    {
      if (isset($result[$r])==false)
      {
        $result[$r]=array();
      }
      $result[$r][$c]=$val;
    }
  }
  ksort($result);
  foreach ($result as $r => $cols)
  {
    ksort($result[$r]);
  }
  return $result;
}

#####################################################################################################

function sheet_values($filename,$strings)
{
  $result=array();
  $sheet=simplexml_load_file($filename);
  $sheet=json_encode($sheet);
  $sheet=json_decode($sheet,true);
  if (isset($sheet["sheetData"])==false)
  {
    return $result;
  }
  $rows=$sheet["sheetData"]["row"];
  foreach ($rows as $row)
  {
    if (isset($row["c"])==false)
    {
      continue;
    }
    $row=$row["c"];
    foreach ($row as $cell)
    {
      $ref=\webdb\excel\get_excel_cell_reference($cell);
      $val=\webdb\excel\get_excel_cell_value($cell,$strings);
      if ($val===false)
      {
        continue;
      }
      $c=$ref["col"];
      $r=$ref["row"];
      if (isset($result[$c])==false)
      {
        $result[$c]=array();
      }
      $result[$c][$r]=$val;
    }
  }
  ksort($result);
  foreach ($result as $c => $rows)
  {
    ksort($result[$c]);
  }
  return $result;
}

#####################################################################################################

function sheet_name_map($xml_path)
{
  $rels=simplexml_load_file($xml_path.'\xl\_rels\workbook.xml.rels');
  $rels=json_encode($rels);
  $rels=json_decode($rels,true);
  $rels=$rels["Relationship"];
  $sheet_rels=array();
  for ($i=0;$i<count($rels);$i++)
  {
    $rel=$rels[$i]["@attributes"];
    $type=$rel["Type"];
    $type=explode("/",$type);
    $type=array_pop($type);
    $id=$rel["Id"];
    $sheet_rels[$id]=$rel["Target"];
  }
  $book=simplexml_load_file($xml_path.'\xl\workbook.xml');
  $names=array();
  $sheets=$book->sheets;
  foreach ($sheets->sheet as $sheet)
  {
    $a=$sheet->attributes();
    $name=(string) $a["name"];
    $a=$sheet->attributes("r",true);
    $id=(string) $a["id"];
    $names[$id]=$name;
  }
  $sheet_refs=array();
  foreach ($names as $id => $name)
  {
    $sheet_refs[$name]=$sheet_rels[$id];
  }
  return $sheet_refs;
}

#####################################################################################################

function get_excel_cell_value($cell,&$strings)
{
  if (isset($cell["v"])==false)
  {
    return false;
  }
  $v=$cell["v"];
  if (isset($cell["@attributes"]["t"])==true)
  {
    $t=$cell["@attributes"]["t"];
    switch ($t)
    {
      case "s":
        if (isset($strings["si"][$v])==true)
        {
          if (isset($strings["si"][$v]["t"])==true)
          {
            $v=$strings["si"][$v]["t"];
            if (is_array($v)==true)
            {
              $v=implode("",$v);
            }
          }
          elseif (isset($strings["si"][$v]["r"])==true)
          {
            $lines=$strings["si"][$v]["r"];
            $v=array();
            foreach ($lines as $lk => $lv)
            {
              $vt=$lv["t"];
              if (is_array($vt)==true)
              {
                $vt=implode("",$vt);
              }
              $v[]=$vt;
            }
            $v=implode("\n",$v);
          }
          else
          {
            var_dump($strings["si"][$v]);
            die("error");
          }
        }
        else
        {
          var_dump($cell);
          die("error");
        }
        break;
      case "str":
        break;
    }
  }
  return $v;
}

#####################################################################################################

function get_excel_cell_reference($cell)
{
  if (isset($cell["@attributes"]["r"])==false)
  {
    return false;
  }
  $r=$cell["@attributes"]["r"];
  $result=array();
  $col="";
  $row="";
  for ($i=0;$i<strlen($r);$i++)
  {
    $c=$r[$i];
    if (ctype_alpha($c)==true)
    {
      if (isset($result["col"])==true)
      {
        return false;
      }
      $col.=$c;
    }
    elseif (ctype_digit($c)==true)
    {
      if (isset($result["col"])==false)
      {
        $result["col"]=$col;
      }
      $row.=$c;
    }
    else
    {
      return false;
    }
  }
  if (($col=="") or ($row==""))
  {
    return false;
  }
  $result["row"]=$row;
  return $result;
}

#####################################################################################################
