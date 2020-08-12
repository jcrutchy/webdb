<?php

namespace webdb\tree;

#####################################################################################################

function tree_diagram($nodes)
{
  $root=false;
  for ($i=0;$i<count($nodes);$i++)
  {
    $node=$nodes[$i];
    if ($node["parent_id"]===false)
    {
      $root=$i;
      break;
    }
  }
  if ($root===false)
  {
    return array();
  }
  $root_leaves=\webdb\tree\get_branch_leaves($nodes,$root);
  $nodes[$root]["x_coord"]=count($root_leaves)/2;
  \webdb\tree\recurse_tree($nodes,$root,0);
  return $nodes;
}

#####################################################################################################

function get_branch_leaves(&$nodes,$key)
{
  $leaves=array();
  $children=\webdb\tree\get_tree_children($nodes,$key);
  if (count($children)==0)
  {
    $leaves[]=$key;
  }
  else
  {
    for ($i=0;$i<count($children);$i++)
    {
      $child_leaves=\webdb\tree\get_branch_leaves($nodes,$children[$i]);
      $leaves=array_merge($leaves,$child_leaves);
    }
  }
  return $leaves;
}

#####################################################################################################

function recurse_tree(&$nodes,$key,$level)
{

  $leaves=\webdb\tree\get_branch_leaves($nodes,$key);
  $n=count($leaves);
  $x1=$nodes[$key]["x_coord"]-($n-1)/2;

  $nodes[$key]["y_coord"]=$level;
  $children=\webdb\tree\get_tree_children($nodes,$key);
  $level++;
  for ($i=0;$i<count($children);$i++)
  {
    $child_key=$children[$i];
    /*$child_leaves=\webdb\graphics\get_branch_leaves($nodes,$child_key);
    $n=count($child_leaves);
    $x1=$nodes[$key]["x_coord"]-($n-1)/2;*/
    $nodes[$child_key]["x_coord"]=$x1+$i;
    \webdb\tree\recurse_tree($nodes,$child_key,$level);
  }
}

#####################################################################################################

function get_tree_children(&$nodes,$parent_key)
{
  $children=array();
  for ($i=0;$i<count($nodes);$i++)
  {
    $node=$nodes[$i];
    if (array_key_exists("parent_id",$node)==true)
    {
      if ($node["parent_id"]===false)
      {
        continue;
      }
      if ($node["parent_id"]==$nodes[$parent_key]["id"])
      {
        $children[]=$i;
      }
    }
  }
  return $children;
}

#####################################################################################################
