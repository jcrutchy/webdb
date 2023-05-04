<?php

/*
HOW TO USE:
f url param is root subfolder with 2 files
conf.txt:
Page Title
c|caption|default|value1,value2,value3
t|caption|default
x|caption|default
m|caption|default
(default is optional for t and m field types)
(first field is always unique id)
data.csv:
id,value 1,value 2,etc
*/

#####################################################################################################

$root="D:/dev/public/env/webforms/";

if (isset($_GET["f"])==false)
{
  die("error: no form specified");
}

$conf=trim(file_get_contents($root.$_GET["f"]."/conf.txt"));
$conf=explode("\n",$conf);

$title=trim(array_shift($conf));

$fields=array();
for ($i=0;$i<count($conf);$i++)
{
  $fields[]=explode("|",$conf[$i]);
}

$data_filename=$root.$_GET["f"]."/data.csv";

$data=trim(file_get_contents($data_filename));
$data=explode("\n",$data);
for ($i=0;$i<count($data);$i++)
{
  $line=trim($data[$i]);
  $data[$i]=explode(",",$line);
}

$styles=file_get_contents(__DIR__."/styles.css");

if ((isset($_GET["e"])==true) or (isset($_GET["i"])==true))
{
  $max=1;
  $id=false;
  $record=false;
  $mode="insert";
  if (isset($_GET["e"])==true)
  {
    $id=$_GET["e"];
    $mode="edit";
  }
  for ($i=0;$i<count($data);$i++)
  {
    $record=$data[$i];
    if ($record[0]==$id)
    {
      break;
    }
    if ($record[0]>$max)
    {
      $max=$record[0];
    }
  }
  if (isset($_GET["i"])==true)
  {
    $record=array();
    $id=$max+1;
    $record[]=$id;
    for ($i=0;$i<count($fields);$i++)
    {
      $field=$fields[$i];
      if (isset($field[2])==true)
      {
        $record[]=$field[2];
      }
      else
      {
        $record[]="";
      }
    }
  }
  if ($record===false)
  {
    die("error: record not found");
  }
  #var_dump($id);
  #var_dump($record);
  #die;
  $edit=file_get_contents(__DIR__."/edit.htm");
  $edit=\webdb\utils\webdb_str_replace("%%title%%",$title,$edit);
  $edit=\webdb\utils\webdb_str_replace("%%styles%%",$styles,$edit);
  $edit=\webdb\utils\webdb_str_replace("%%mode%%",$mode,$edit);
  $rows="";
  for ($i=0;$i<count($fields);$i++)
  {
    $field=$fields[$i];
    $k=$i+1; # account for id in values
    $name=$field[1];
    $fieldname=strtolower(\webdb\utils\webdb_str_replace(" ","_",$name));
    $value=$record[$k];
    switch ($field[0])
    {
      case "c":

        break;
      case "m":
        $value=\webdb\utils\webdb_str_replace('\n',"\n",$value);
        $value="<textarea name='".$fieldname."' wrap='soft'>".$value."</textarea>";
        break;
      case "t":
        $value="<input type='text' name='".$fieldname."' value='".$value."'>";
        break;
      case "x":

        break;
    }
    $row="<tr><td>".$name."</td><td>".$value."</td></tr>";
    $rows.=$row;
  }
  $edit=\webdb\utils\webdb_str_replace("%%rows%%",$rows,$edit);
  die($edit);
}

if (isset($_POST["e"])==true)
{

  $out=implode("\n",$data);
  file_put_contents($data_filename,$out);
}

if (isset($_POST["i"])==true)
{

  $out=implode("\n",$data);
  file_put_contents($data_filename,$out);
}

$list=file_get_contents(__DIR__."/list.htm");
$list=\webdb\utils\webdb_str_replace("%%title%%",$title,$list);
$list=\webdb\utils\webdb_str_replace("%%styles%%",$styles,$list);
$head="<th>id</th>";
for ($i=0;$i<count($fields);$i++)
{
  $field=$fields[$i];
  $head.="<th>".$field[1]."</th>";
}
$head.="<th>&nbsp;</th>";
$list=\webdb\utils\webdb_str_replace("%%head%%",$head,$list);
$rows="";
for ($i=0;$i<count($data);$i++)
{
  $record=$data[$i];
  $id=$record[0];
  $row="<tr><td>".$id."</td>";
  for ($j=0;$j<count($fields);$j++)
  {
    $field=$fields[$j];
    $k=$j+1; # account for id in record
    if ($field[0]=="x")
    {
      if ($record[$k]==1)
      {
        $record[$k]="&#10004;";
      }
      else
      {
        $record[$k]="&#10007;";
      }
    }
    if ($field[0]=="m")
    {
      $record[$k]=\webdb\utils\webdb_str_replace('\n',"<br>",$record[$k]);
    }
    $row.="<td>".$record[$k]."</td>";
  }
  $row.="<td><a href='http://".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"]."&e=".$id."'>edit</a></td>";
  $row.="</tr>";
  $rows.=$row;
}
$list=\webdb\utils\webdb_str_replace("%%rows%%",$rows,$list);
die($list);

#####################################################################################################
