<?php

namespace webdb\chart;

#####################################################################################################

function ramer_douglas_peucker($points,$epsilon,$x_key,$y_key)
{
  # https://en.wikipedia.org/wiki/Ramer%E2%80%93Douglas%E2%80%93Peucker_algorithm
  # https://www.loughrigg.org/rdp/
  $dmax=0;
  $index=0;
  $n=count($points);
  for ($i=1;$i<($n-1);$i++)
  {
    $p=$points[$i];
    $L1=$points[0];
    $L2=$points[$n-1];
    $d=\webdb\chart\perpendicular_distance($p[$x_key],$p[$y_key],$L1[$x_key],$L1[$y_key],$L2[$x_key],$L2[$y_key]);
    if ($d>$dmax)
    {
      $index=$i;
      $dmax=$d;
    }
  }
  $result=array();
  if ($dmax>$epsilon)
  {
    $list1=array_slice($points,0,$index+1);
    $rec_results1=\webdb\chart\ramer_douglas_peucker($list1,$epsilon,$x_key,$y_key);
    $list2=array_slice($points,$index,$n-$index);
    $rec_results2=\webdb\chart\ramer_douglas_peucker($list2,$epsilon,$x_key,$y_key);
    $list3=array_slice($rec_results1,0,count($rec_results1)-1);
    $list4=array_slice($rec_results2,0,count($rec_results2));
    $result=array_merge($list3,$list4);
  }
  else
  {
    $result=array($points[0],$points[$n-1]);
  }
  return $result;
}

#####################################################################################################

function gantt($data,$callbacks=false)
{
  global $settings;

  $today=time();

  /*$data=array();

  $task=array();
  $task["name"]="test task 1";
  $task["start"]=\webdb\utils\webdb_strtotime("-1 months",$today);
  $task["finish"]=\webdb\utils\webdb_strtotime("+1 months",$today);
  $data[]=$task;

  $task=array();
  $task["name"]="test task 2";
  $task["start"]=\webdb\utils\webdb_strtotime("-1 months",$today);
  $task["finish"]=\webdb\utils\webdb_strtotime("+1 months",$today);
  $data[]=$task;*/

  $line_height=30; # pixels
  $bar_thickness=0.5; # real y

  $task_count=count($data);

  $chart_data=\webdb\chart\initilize_chart();
  $chart_data["h"]=$line_height*($task_count+1);

  $chart_data["y_min"]=0;
  $chart_data["y_max"]=$task_count+1;
  $chart_data["grid_y"]=1;
  $chart_data["x_title"]="";
  $chart_data["y_title"]="";

  $chart_data["y_captions"]=array();
  $chart_data["y_captions"][]="";

  $font_size=10; # pt
  $text_file=$settings["gd_ttf"];
  $tick_length=5; # pixels
  $label_space=4; # pixels
  $y_margin=10; # pixels

  $max_text_w=0;

  $x_min=PHP_INT_MAX;
  $x_max=PHP_INT_MIN;

  for ($i=0;$i<$task_count;$i++)
  {
    $task=$data[$i];
    $y=$i+1;

    $bbox=imagettfbbox($font_size,0,$text_file,$task["name"]);
    $text_w=$bbox[2]-$bbox[0];
    $max_text_w=max($max_text_w,$text_w);

    $x_min=min($x_min,$task["start"]);
    $x_min=min($x_min,$task["finish"]);

    $x_max=max($x_max,$task["start"]);
    $x_max=max($x_max,$task["finish"]);

    $chart_data["y_captions"][$y]=$task["name"];

    $records=array();
    $records[]=array($task["start"],$y+$bar_thickness/3);
    $records[]=array($task["finish"],$y-$bar_thickness/3);
    $chart_data=\webdb\chart\assign_plot_data($chart_data,$records,0,1,"teal","",false,true,$task["name"],"bar");

  }

  $chart_data["y_captions"][]="";

  $chart_data["x_min"]=$x_min;
  $chart_data["x_max"]=$x_max;

  \webdb\chart\get_time_captions("month",$chart_data,"M-y");

  $chart_data["today_mark"]="red";
  $chart_data["today_override"]=$today;

  $chart_data["left"]=$max_text_w+$tick_length+$label_space+$y_margin;

  if ($callbacks!==false)
  {
    foreach ($callbacks as $event_type => $event_data)
    {
      $chart_data[$event_type]=$event_data["callback_function"];
      $chart_data["user_data"][$event_type]=$event_data["user_data"];
    }
  }

  return \webdb\chart\output_chart($chart_data);
}

#####################################################################################################

function linear_regression($coords)
{
  # https://www.vedantu.com/maths/linear-regression
  $n=count($coords);
  $x_sum=0;
  $y_sum=0;
  $xx_sum=0;
  $yy_sum=0;
  $xy_sum=0;
  foreach ($coords as $i => $p)
  {
    $x_sum+=$p[0];
    $y_sum+=$p[1];
    $xx_sum+=pow($p[0],2);
    $yy_sum+=pow($p[1],2);
    $xy_sum+=$p[0]*$p[1];
  }
  $d=$n*$xx_sum-pow($x_sum,2);
  $a=0;
  if ($d<>0)
  {
    $a=($y_sum*$xx_sum-$x_sum*$xy_sum)/$d;
  }
  $b=0;
  $d=$n*$xx_sum-pow($x_sum,2);
  if ($d<>0)
  {
    $b=($n*$xy_sum-$x_sum*$y_sum)/$d;
  }
  $result=array();
  $result["m"]=$b;
  $result["c"]=$a;
  return $result;
}

#####################################################################################################

function chart_draw_3d_plot(&$data,$series)
{

  $data["grid_x"]=1;
  $data["grid_y"]=1;
  $data["x_min"]=-10;
  $data["x_max"]=10;
  $data["y_min"]=-10;
  $data["y_max"]=10;

  $color=$data["colors"]["black"];
  $line_color=imagecolorallocate($data["buffer"],$color[0],$color[1],$color[2]);

  $x1=\webdb\chart\chart_to_pixel_x($data["x_min"],$data);
  $y1=\webdb\chart\chart_to_pixel_y(0,$data);
  $x2=\webdb\chart\chart_to_pixel_x($data["x_max"],$data);
  $y2=\webdb\chart\chart_to_pixel_y(0,$data);
  imageline($data["buffer"],$x1,$y1,$x2,$y2,$line_color);

  $x1=\webdb\chart\chart_to_pixel_x(0,$data);
  $y1=\webdb\chart\chart_to_pixel_y($data["y_min"],$data);
  $x2=\webdb\chart\chart_to_pixel_x(0,$data);
  $y2=\webdb\chart\chart_to_pixel_y($data["y_max"],$data);
  imageline($data["buffer"],$x1,$y1,$x2,$y2,$line_color);

  $color=$series["color"];
  $color=$data["colors"][$color];
  $line_color=imagecolorallocate($data["buffer"],$color[0],$color[1],$color[2]);

  $vertices=$series["vertices"];
  $edges=$series["edges"];
  $f=$data["3d_focal_length"];

  $zf=2*$f/($data["x_max"]-$data["x_min"]);

  foreach ($vertices as $key => $coord)
  {
    $x=$coord[0];
    $y=$coord[1];
    $z=$coord[2]*$zf;
    $x_proj=($f*$x)/($f+$z);
    $y_proj=($f*$y)/($f+$z);
    $x_pix=\webdb\chart\chart_to_pixel_x($x_proj,$data);
    $y_pix=\webdb\chart\chart_to_pixel_y($y_proj,$data);
    $vertices[$key][]=$x_pix;
    $vertices[$key][]=$y_pix;
    imagerectangle($data["buffer"],$x_pix-2,$y_pix-2,$x_pix+2,$y_pix+2,$line_color);
  }

  foreach ($edges as $key => $pair)
  {
    $vertex1=$pair[0];
    $vertex2=$pair[1];
    $vertex1=$vertices[$vertex1];
    $vertex2=$vertices[$vertex2];
    $x1=$vertex1[3];
    $y1=$vertex1[4];
    $x2=$vertex2[3];
    $y2=$vertex2[4];
    imageline($data["buffer"],$x1,$y1,$x2,$y2,$line_color);
  }

}

#####################################################################################################

function perpendicular_distance($x,$y,$L1x,$L1y,$L2x,$L2y)
{
  # https://www.loughrigg.org/rdp/viewsource.php
  if ($L1x==$L2x)
  {
    return abs($x-$L2x);
  }
  else
  {
    $m=(($L2y-$L1y)/($L2x-$L1x));
    $c=(0-$L1x)*$m+$L1y;
    return (abs($m*$x-$y+$c))/(sqrt($m*$m+1));
  }
}

#####################################################################################################

function auto_grid_y($pix,&$data)
{
  $chart_h=$data["h"]-$data["top"]-$data["bottom"];
  $ppu=(($chart_h-1)/($data["y_max"]-$data["y_min"]));
  $g=$pix/$ppu;
  if ($g>0.1)
  {
    $data["grid_y"]=0.1;
  }
  if ($g>0.5)
  {
    $data["grid_y"]=0.5;
  }
  if ($g>1)
  {
    $data["grid_y"]=1;
  }
  if ($g>2)
  {
    $data["grid_y"]=2;
  }
  if ($g>5)
  {
    $data["grid_y"]=5;
  }
  if ($g>10)
  {
    $data["grid_y"]=10;
  }
  if ($g>20)
  {
    $data["grid_y"]=20;
  }
  if ($g>50)
  {
    $data["grid_y"]=50;
  }
  if ($g>100)
  {
    $data["grid_y"]=100;
  }
  if ($g>200)
  {
    $data["grid_y"]=200;
  }
  if ($g>500)
  {
    $data["grid_y"]=500;
  }
  if ($g>1000)
  {
    $data["grid_y"]=1000;
  }
}

#####################################################################################################

function zero_value($value)
{
  if (abs($value)<=1e-12)
  {
    return 0.0;
  }
  return $value;
}

#####################################################################################################

function chart_colors()
{
  $colors=array();
  $colors["teal"]=array(11,132,165);
  $colors["yellow"]=array(246,200,95);
  $colors["purple"]=array(111,78,124);
  $colors["light_green"]=array(157,216,102);
  $colors["red"]=array(202,71,47);
  $colors["light_red"]=array(254,200,216);
  $colors["orange"]=array(255,160,86);
  $colors["sky_blue"]=array(141,221,208);
  $colors["magenta"]=array(211,54,130);
  $colors["blue"]=array(38,139,210);
  $colors["grid"]=array(230,230,230);
  $colors["sub_grid"]=array(240,240,240);
  $colors["border"]=array(230,230,250);
  $colors["black"]=array(0,0,0);
  return $colors;
}

#####################################################################################################

function assign_discontinuous_plot_data($chart_data,$plot_data,$x_key,$y_key,$color_key,$marker="",$limits="update")
{
  # $segment_data[$i]["p1|2"][$x|y_key]
  $plot=array();
  $plot["color"]=$color_key;
  $plot["marker"]=$marker;
  $plot["segments"]=array();
  if ($limits=="assign")
  {
    $min_x=PHP_INT_MAX;
    $max_x=PHP_INT_MIN;
    $min_y=PHP_INT_MAX;
    $max_y=PHP_INT_MIN;
  }
  else
  {
    $min_x=$chart_data["x_min"];
    $max_x=$chart_data["x_max"];
    $min_y=$chart_data["y_min"];
    $max_y=$chart_data["y_max"];
  }
  $n=count($plot_data);
  for ($i=0;$i<$n;$i++)
  {
    $data=$plot_data[$i];
    $x1=$data["p1"][$x_key];
    $y1=$data["p1"][$y_key];
    $x2=$data["p2"][$x_key];
    $y2=$data["p2"][$y_key];
    $segment=array();
    $segment["p1"]=array($x1,$y1);
    $segment["p2"]=array($x2,$y2);
    $plot["segments"][]=$segment;
    if ($x1<$min_x)
    {
      $min_x=$x1;
    }
    if ($x1>$max_x)
    {
      $max_x=$x1;
    }
    if ($y1<$min_y)
    {
      $min_y=$y1;
    }
    if ($y1>$max_y)
    {
      $max_y=$y1;
    }
    if ($x2<$min_x)
    {
      $min_x=$x2;
    }
    if ($x2>$max_x)
    {
      $max_x=$x2;
    }
    if ($y2<$min_y)
    {
      $min_y=$y2;
    }
    if ($y2>$max_y)
    {
      $max_y=$y2;
    }
  }
  $chart_data["discontinuous_plots"][]=$plot;
  if (($limits=="update") or ($limits=="assign"))
  {
    if (($min_x<$max_x) and ($min_y<$max_y))
    {
      $chart_data["x_min"]=$min_x;
      $chart_data["x_max"]=$max_x;
      $chart_data["y_min"]=$min_y;
      $chart_data["y_max"]=$max_y;
    }
  }
  return $chart_data;
}

#####################################################################################################

function assign_3d_plot_data($chart_data,$vertices,$edges,$color_key)
{
  $series=array();
  $series["color"]=$color_key;
  $series["vertices"]=$vertices;
  $series["edges"]=$edges;
  $chart_data["3d_series"][]=$series;
  return $chart_data;
}

#####################################################################################################

function assign_plot_data($chart_data,$series_data,$x_key,$y_key,$color_key,$marker="",$assign_limits=true,$line_enabled=true,$name="",$style="solid",$series_data_color_key=false,$line_thickness=1)
{
  # $style="solid"|"dash"
  # $series_data[$i][$x|y_key] (continuous)
  $series=array();
  $series["name"]=$name;
  $series["color"]=$color_key;
  $series["type"]="plot"; # plot|step|column
  $series["marker"]=$marker;
  $series["line_enabled"]=$line_enabled;
  $series["x_values"]=array();
  $series["y_values"]=array();
  $series["colors"]=array();
  $series["style"]=$style;
  if ($line_thickness>1)
  {
    $series["thickness"]=$line_thickness;
  }
  $min_x=PHP_INT_MAX;
  $max_x=0;
  $min_y=PHP_INT_MAX;
  $max_y=0;
  $n=count($series_data);
  for ($i=0;$i<$n;$i++)
  {
    $coord=$series_data[$i];
    $x=$coord[$x_key];
    $y=$coord[$y_key];
    $series["x_values"][]=$x;
    $series["y_values"][]=$y;
    if ($series_data_color_key!==false)
    {
      $series["colors"][]=$coord[$series_data_color_key]; # contains array(R,G,B)
    }
    else
    {
      $series["colors"][]=false;
    }
    if ($x<$min_x)
    {
      $min_x=$x;
    }
    if ($x>$max_x)
    {
      $max_x=$x;
    }
    if ($y<$min_y)
    {
      $min_y=$y;
    }
    if ($y>$max_y)
    {
      $max_y=$y;
    }
  }
  $chart_data["series"][]=$series;
  if ($assign_limits==true)
  {
    if (($min_x<=$max_x) and ($min_y<=$max_y))
    {
      $chart_data["x_min"]=$min_x;
      $chart_data["x_max"]=$max_x;
      $chart_data["y_min"]=$min_y;
      $chart_data["y_max"]=$max_y;
    }
  }
  return $chart_data;
}

#####################################################################################################

function initilize_chart($copy_source=false)
{
  $data=array();
  $data["colors"]=\webdb\chart\chart_colors();
  $data["w"]=1800;
  $data["h"]=800;
  $data["left"]=60;
  $data["right"]=10;
  $data["bottom"]=60;
  $data["top"]=10;
  $data["series"]=array();
  $data["discontinuous_plots"]=array();
  $data["grid_x"]=1;
  $data["grid_y"]=1;
  $data["sub_grid_x"]=false;
  $data["sub_grid_y"]=false;
  $data["x_min"]=0;
  $data["x_max"]=10;
  $data["y_min"]=0;
  $data["y_max"]=10;
  $data["x_range_override"]=false;
  $data["y_range_override"]=false;
  $data["x_title"]="";
  $data["y_title"]="";
  $data["scale"]=1;
  $data["x_axis_scale"]="linear"; # or log10
  $data["y_axis_scale"]="linear"; # or log10
  $data["show_grid_x"]=true;
  $data["show_grid_y"]=true;
  $data["show_x_axis"]=true;
  $data["show_y_axis"]=true;
  $data["bg_color_r"]=253;
  $data["bg_color_g"]=253;
  $data["bg_color_b"]=253;
  $data["auto_grid_x_pix"]=30;
  $data["auto_grid_y_pix"]=30;
  $data["user_data"]=array(); # use to store any data (may be useful for events)
  $data["on_after_background"]="";
  $data["on_after_grid"]="";
  $data["on_after_plots"]="";
  $data["3d_focal_length"]=0;
  if ($copy_source!==false)
  {
    foreach ($copy_source as $key => $value)
    {
      $data[$key]=$value;
    }
  }
  # if (isset($data["x_axis_format"])==true)
  # if (isset($data["y_axis_format"])==true)
  # if (isset($data["x_captions"][$i])==true)
  # if (isset($data["y_captions"][$i])==true)
  return $data;
}

#####################################################################################################

function auto_range(&$data)
{
  $min_x=PHP_INT_MAX;
  $max_x=PHP_INT_MIN;
  $min_y=PHP_INT_MAX;
  $max_y=PHP_INT_MIN;
  for ($i=0;$i<count($data["discontinuous_plots"]);$i++)
  {
    $plot=$data["discontinuous_plots"][$i];
    $segments=$plot["segments"];
    $n=count($segments);
    for ($j=0;$j<$n;$j++)
    {
      $segment=$segments[$j];
      $x1=$segment["p1"][0];
      $y1=$segment["p1"][1];
      $x2=$segment["p2"][0];
      $y2=$segment["p2"][1];
      if ($x1<$min_x)
      {
        $min_x=$x1;
      }
      if ($x1>$max_x)
      {
        $max_x=$x1;
      }
      if ($x2<$min_x)
      {
        $min_x=$x2;
      }
      if ($x2>$max_x)
      {
        $max_x=$x2;
      }
      if ($y1<$min_y)
      {
        $min_y=$y1;
      }
      if ($y1>$max_y)
      {
        $max_y=$y1;
      }
      if ($y2<$min_y)
      {
        $min_y=$y2;
      }
      if ($y2>$max_y)
      {
        $max_y=$y2;
      }
    }
  }
  for ($i=0;$i<count($data["series"]);$i++)
  {
    $series=$data["series"][$i];
    $x_values=$series["x_values"];
    $y_values=$series["y_values"];
    $n=count($x_values);
    for ($j=0;$j<$n;$j++)
    {
      $x=$x_values[$j];
      $y=$y_values[$j];
      if ($x<$min_x)
      {
        $min_x=$x;
      }
      if ($x>$max_x)
      {
        $max_x=$x;
      }
      if ($y<$min_y)
      {
        $min_y=$y;
      }
      if ($y>$max_y)
      {
        $max_y=$y;
      }
    }
  }
  if ($min_x==$max_x)
  {
    if ($min_x>PHP_INT_MIN)
    {
      $min_x=$min_x-1;
    }
    if ($max_x<PHP_INT_MAX)
    {
      $max_x=$max_x+1;
    }
  }
  if ($min_y==$max_y)
  {
    if ($min_y>PHP_INT_MIN)
    {
      $min_y=$min_y-1;
    }
    if ($max_y<PHP_INT_MAX)
    {
      $max_y=$max_y+1;
    }
  }
  if ($data["x_axis_scale"]=="log10")
  {
    $min_x=floor(log10($min_x));
    $max_x=ceil(log10($max_x));
  }
  if ($data["y_axis_scale"]=="log10")
  {
    $min_y=floor(log10($min_y));
    $max_y=ceil(log10($max_y));
  }
  $data["x_min"]=$min_x;
  $data["x_max"]=$max_x;
  $data["y_min"]=$min_y;
  $data["y_max"]=$max_y;
  if ($data["x_axis_scale"]=="linear")
  {
    $dx=$max_x-$min_x;
    $data["grid_x"]=max(1,floor($data["auto_grid_x_pix"]/$data["w"]*$dx/10)*10);
  }
  if ($data["y_axis_scale"]=="linear")
  {
    $dy=$max_y-$min_y;
    $data["grid_y"]=max(1,floor($data["auto_grid_y_pix"]/$data["h"]*$dy/10)*10);
  }
}

#####################################################################################################

function adjust_min_max_months_x(&$data)
{
  $data["x_min"]=\webdb\utils\webdb_strtotime("-1 month",$data["x_min"]);
  $data["x_min"]=\webdb\utils\webdb_strtotime(date("Y-m",$data["x_min"])."-01");
  $data["x_max"]=\webdb\utils\webdb_strtotime("+2 month",$data["x_max"]);
  $data["x_max"]=\webdb\utils\webdb_strtotime(date("Y-m",$data["x_max"])."-01");
  $d1=new \DateTime("@".$data["x_min"]);
  $d2=new \DateTime("@".$data["x_max"]);
  $diff=$d1->diff($d2);
  $n=$diff->y*12+$diff->m;
  $data["grid_x"]=($data["x_max"]-$data["x_min"])/$n;
  return $n;
}

#####################################################################################################

function get_time_captions($scale,&$data,$format=false)
{
  $min_x=$data["x_min"];
  $max_x=$data["x_max"];
  $x_captions=array();
  switch ($scale)
  {
    case "day":
      if ($format===false)
      {
        $format="Y-m-d";
      }
      $min_x=\webdb\utils\webdb_strtotime("-1 day",$min_x);
      $min_x=\webdb\utils\webdb_strtotime(date("Y-m-d",$min_x));
      $data["x_min"]=$min_x;
      $max_x=\webdb\utils\webdb_strtotime("+2 day",$max_x);
      $max_x=\webdb\utils\webdb_strtotime(date("Y-m-d",$max_x));
      $data["x_max"]=$max_x;
      $diff=$max_x-$min_x;
      $data["grid_x"]=24*60*60;
      $n=$diff/$data["grid_x"];
      $x=$min_x;
      for ($i=0;$i<=$n;$i++)
      {
        $x_captions[]=date($format,$x);
        $x=\webdb\utils\webdb_strtotime("+1 day",$x);
      }
      break;
    case "month":
      if ($format===false)
      {
        $format="M-Y";
      }
      $n=\webdb\chart\adjust_min_max_months_x($data);
      $x=$data["x_min"];
      for ($i=0;$i<=($n+1);$i++)
      {
        $x_captions[]=date($format,$x);
        $x=\webdb\utils\webdb_strtotime("+1 month",$x);
      }
      break;
    case "year":
      # TODO
      break;
  }
  $data["x_captions"]=$x_captions;
}

#####################################################################################################

function output_legend_line($data,$series)
{
  $chart_colors=$data["colors"];
  $color=$series["color"];
  $color=$chart_colors[$color];
  $w=60;
  $h=20;
  return \webdb\chart\chart_legend_line($w,$h,$color);
}

#####################################################################################################

function chart_legend_line($w,$h,$color)
{
  $w=60;
  $h=20;
  $buffer=imagecreatetruecolor($w,$h);
  $bg_color=imagecolorallocate($buffer,255,0,255); # magenta
  imagecolortransparent($buffer,$bg_color);
  imagefill($buffer,0,0,$bg_color);
  $line_color=imagecolorallocate($buffer,$color[0],$color[1],$color[2]);
  imageline($buffer,0,$h/2,$w-1,$h/2,$line_color);
  return \webdb\graphics\base64_image($buffer,"png");
}

#####################################################################################################

function handle_chart_event($event_type,$chart_data)
{
  if ($chart_data[$event_type]<>"")
  {
    if (function_exists($chart_data[$event_type])==true)
    {
      return call_user_func($chart_data[$event_type],$chart_data,$event_type);
    }
  }
  return $chart_data;
}

#####################################################################################################

function draw_discontinuous_plots(&$data)
{
  global $settings;
  for ($i=0;$i<count($data["discontinuous_plots"]);$i++)
  {
    \webdb\chart\chart_draw_discontinuous_plot($data,$data["discontinuous_plots"][$i]);
  }
}

#####################################################################################################

function draw_series_plots(&$data)
{
  global $settings;
  for ($i=0;$i<count($data["series"]);$i++)
  {
    $series=$data["series"][$i];
    switch ($series["type"])
    {
      case "column":
        \webdb\chart\chart_draw_column_series($data,$series);
        break;
      case "plot":
        \webdb\chart\chart_draw_continuous_plot($data,$series);
        break;
      case "step": # refer to "mdo_risks/trends/cumulative_timestamp_series" function for example
        \webdb\chart\chart_draw_step_plot($data,$series);
        break;
    }
  }
}

#####################################################################################################

function output_chart($data,$filename=false,$no_output=false,$rhs_data=false,$draw3d=false)
{
  global $settings;
  \webdb\chart\chart_draw_create($data);
  \webdb\chart\handle_chart_event("on_after_background",$data);
  \webdb\chart\chart_draw_border($data);
  if ($draw3d==false)
  {
    \webdb\chart\chart_draw_grid($data);
    \webdb\chart\handle_chart_event("on_after_grid",$data);
    \webdb\chart\draw_discontinuous_plots($data);
    \webdb\chart\draw_series_plots($data);
    if (isset($data["today_mark"])==true)
    {
      \webdb\chart\chart_draw_today_mark($data);
    }
    $bg_color=imagecolorallocate($data["buffer"],$data["bg_color_r"],$data["bg_color_g"],$data["bg_color_b"]);
    imagefilledrectangle($data["buffer"],0,0,$data["w"],$data["top"]-1,$bg_color);
    imagefilledrectangle($data["buffer"],0,0,$data["left"]-1,$data["h"],$bg_color);
    imagefilledrectangle($data["buffer"],$data["w"]-$data["right"],0,$data["w"],$data["h"],$bg_color);
    imagefilledrectangle($data["buffer"],0,$data["h"]-$data["bottom"],$data["w"],$data["h"],$bg_color);
    if ($data["show_y_axis"]==true)
    {
      \webdb\chart\chart_draw_axis_y($data,$rhs_data);
    }
    if ($data["show_x_axis"]==true)
    {
      \webdb\chart\chart_draw_axis_x($data);
    }
    if ($data["x_title"]!=="")
    {
      \webdb\chart\chart_draw_title_x($data);
    }
    if ($data["y_title"]!=="")
    {
      \webdb\chart\chart_draw_title_y($data,$rhs_data);
    }
  }
  else
  {
    for ($i=0;$i<count($data["3d_series"]);$i++)
    {
      $series=$data["3d_series"][$i];
      \webdb\chart\chart_draw_3d_plot($data,$series);
    }
  }
  \webdb\chart\handle_chart_event("on_after_plots",$data);
  if (($data["scale"]!=="") and ($data["scale"]<>1))
  {
    \webdb\graphics\scale_img($data["buffer"],$data["scale"],$data["w"],$data["h"]);
  }
  if ($no_output==true)
  {
    return $data;
  }
  if ($filename!==false)
  {
    $result=\webdb\chart\chart_draw_save_file($data,$filename);
  }
  else
  {
    $result=\webdb\chart\chart_draw_html_out($data);
  }
  \webdb\chart\chart_draw_destroy($data);
  return $result;
}

#####################################################################################################

function pixels_per_unit($pix,$min,$max)
{
  return (($pix-1)/($max-$min));
}

#####################################################################################################

function output_chart_pix_series($data,$key)
{
  $result=array();
  $s=$data["series"][$key];
  for ($i=0;$i<count($s["x_values"]);$i++)
  {
    $x=$s["x_values"][$i];
    $x=\webdb\chart\chart_to_pixel_x($x,$data);
    $y=$s["y_values"][$i];
    $y=\webdb\chart\chart_to_pixel_y($y,$data);
    $result[]=array($x,$y);
  }
  return $result;
}

#####################################################################################################

function get_caption($data,$series_key,$axis,$val)
{
  $captions=$data[$axis."_captions"];
  $min=$data[$axis."_min"];
  $max=$data[$axis."_max"];
  $delta=$max-$min;
  $grid=$data["grid_".$axis];
  $result=false;
  $min_error=$max;
  for ($i=0;$i<count($captions);$i++)
  {
    $test=$grid*$i+$min;
    $error=abs($test-$val);
    if ($error<$min_error)
    {
      $min_error=$error;
      $result=$i;
    }
  }
  if ($result!==false)
  {
    $result=$captions[$result];
  }
  return $result;
}

#####################################################################################################

function pixel_to_chart_x($pix,$data)
{
  $w=$data["w"];
  $left=$data["left"];
  $min=$data["x_min"];
  $max=$data["x_max"];
  $ppu=\webdb\chart\pixels_per_unit($pix,$min,$max);
  $chart_w=$w-$left-$right;
  $val=($pix-$left)/$ppu+$min_x;
  if ($data["x_axis_scale"]=="log10")
  {
    return log10($val);
  }
  return $val;
}

#####################################################################################################

function pixel_to_chart_y($pix,$data)
{
  $h=$data["h"];
  $top=$data["top"];
  $bottom=$data["bottom"];
  $min=$data["y_min"];
  $max=$data["y_max"];
  $ppu=\webdb\chart\pixels_per_unit($pix,$min,$max);
  $chart_h=$h-$top-$bottom;
  $val=($chart_h-$pix+$top-1)/$ppu+$min;
  if ($data["y_axis_scale"]=="log10")
  {
    return log10($val);
  }
  return $val;
}

#####################################################################################################

function chart_to_pixel_x($val,$data)
{
  if ($data["x_axis_scale"]=="log10")
  {
    $val=log10($val);
  }
  $chart_w_pix=$data["w"]-$data["left"]-$data["right"];
  $ppu=\webdb\chart\pixels_per_unit($chart_w_pix,$data["x_min"],$data["x_max"]);
  return intval(round(($val-$data["x_min"])*$ppu+$data["left"]));
}

#####################################################################################################

function chart_to_pixel_y($val,$data)
{
  if ($data["y_axis_scale"]=="log10")
  {
    $val=log10($val);
  }
  $chart_h_pix=$data["h"]-$data["top"]-$data["bottom"];
  $ppu=\webdb\chart\pixels_per_unit($chart_h_pix,$data["y_min"],$data["y_max"]);
  return intval(round($chart_h_pix-1-($val-$data["y_min"])*$ppu+$data["top"]));
}

#####################################################################################################

function chart_draw_create(&$data)
{
  $data["buffer"]=imagecreatetruecolor($data["w"],$data["h"]);
  imageantialias($data["buffer"],false);
  imagesetthickness($data["buffer"],1);
  imageantialias($data["buffer"],true);
  $bg_color=imagecolorallocate($data["buffer"],$data["bg_color_r"],$data["bg_color_g"],$data["bg_color_b"]);
  imagefill($data["buffer"],0,0,$bg_color);
}

#####################################################################################################

function chart_draw_destroy(&$data)
{
  imagedestroy($data["buffer"]);
  unset($data["buffer"]);
}

#####################################################################################################

function chart_draw_border(&$data)
{
  $color=$data["colors"]["border"];
  $line_color=imagecolorallocate($data["buffer"],$color[0],$color[1],$color[2]);
  imagerectangle($data["buffer"],0,0,$data["w"]-1,$data["h"]-1,$line_color);
}

#####################################################################################################

function chart_draw_today_mark(&$data)
{
  $rx=time();
  if (isset($data["today_override"])==true)
  {
    $rx=$data["today_override"];
  }
  if (($rx>$data["x_min"]) and ($rx<$data["x_max"]))
  {
    $color=$data["today_mark"];
    $color=$data["colors"][$color];
    $line_color=imagecolorallocate($data["buffer"],$color[0],$color[1],$color[2]);
    $px=\webdb\chart\chart_to_pixel_x($rx,$data);
    $py1=\webdb\chart\chart_to_pixel_y($data["y_max"],$data);
    $py2=\webdb\chart\chart_to_pixel_y($data["y_min"],$data);
    imageline($data["buffer"],$px,$py1,$px,$py2,$line_color);
  }
}

#####################################################################################################

function chart_draw_column_series(&$data,$series,$y_min=0)
{
  $color=$series["color"];
  $color=$data["colors"][$color];
  $color_line=imagecolorallocate($data["buffer"],$color[0],$color[1],$color[2]);
  $color_delta=70;
  $color_fill=imagecolorallocate($data["buffer"],min(255,$color[0]+$color_delta),min(255,$color[1]+$color_delta),min(255,$color[2]+$color_delta));
  $x_values=$series["x_values"];
  $y_values=$series["y_values"];
  $chart_w_pix=$data["w"]-$data["left"]-$data["right"];
  $ppu=\webdb\chart\pixels_per_unit($chart_w_pix,$data["x_min"],$data["x_max"]);
  $half_col=$data["grid_x"]/2*$ppu-3;
  $n=count($x_values);
  $y1=\webdb\chart\chart_to_pixel_y($y_min,$data);
  for ($i=0;$i<$n;$i++)
  {
    $x1=round(\webdb\chart\chart_to_pixel_x($x_values[$i],$data)-$half_col);
    $x2=round(\webdb\chart\chart_to_pixel_x($x_values[$i],$data)+$half_col);
    $y2=\webdb\chart\chart_to_pixel_y($y_values[$i],$data);
    if ($y1==$y2)
    {
      continue;
    }
    imagerectangle($data["buffer"],$x1,$y1,$x2,$y2,$color_line);
    imagefilledrectangle($data["buffer"],$x1+1,$y1,$x2-1,$y2+1,$color_fill);
  }
}

#####################################################################################################

function val_range_x($val,$data)
{
  if (($val>=$data["x_min"]) and ($val<=$data["x_max"]))
  {
    return true;
  }
  return false;
}

#####################################################################################################

function val_range_y($val,$data)
{
  if (($val>=$data["y_min"]) and ($val<=$data["y_max"]))
  {
    return true;
  }
  return false;
}

#####################################################################################################

function force_within_limits($bx,$by,$gx,$gy,$m,$c,$data) # b=bad,g=good
{
  $x=$bx;
  $y=$by;
  if ($x<$data["x_min"])
  {
    $x=$data["x_min"];
    if ($m!==false)
    {
      $y=$m*$x+$c;
    }
  }
  if ($x>$data["x_max"])
  {
    $x=$data["x_max"];
    if ($m!==false)
    {
      $y=$m*$x+$c;
    }
  }
  if ($y<$data["y_min"])
  {
    $y=$data["y_min"];
    if ($m!==false)
    {
      $x=($y-$c)/$m;
    }
  }
  if ($y>$data["y_max"])
  {
    $y=$data["y_max"];
    if ($m!==false)
    {
      $x=($y-$c)/$m;
    }
  }
  return array($x,$y);
}

#####################################################################################################

function chart_draw_continuous_plot(&$data,$series)
{
  imagesetthickness($data["buffer"],1);
  $color=$series["color"];
  $color=$data["colors"][$color];
  $line_color=imagecolorallocate($data["buffer"],$color[0],$color[1],$color[2]);
  if (isset($series["x_values"])==false)
  {
    $series["x_values"]=array();
  }
  if (isset($series["y_values"])==false)
  {
    $series["y_values"]=array();
  }
  if (isset($series["colors"])==false)
  {
    $series["colors"]=array();
  }
  $x_values=$series["x_values"];
  $y_values=$series["y_values"];
  $colors=$series["colors"];
  $n=count($x_values);
  if (($series["style"]=="bar") or ($series["style"]=="hollow_bar"))
  {
    if ($n==2)
    {
      $x1=\webdb\chart\chart_to_pixel_x($x_values[0],$data);
      $y1=\webdb\chart\chart_to_pixel_y($y_values[0],$data);
      $x2=\webdb\chart\chart_to_pixel_x($x_values[1],$data);
      $y2=\webdb\chart\chart_to_pixel_y($y_values[1],$data);
      if ($series["line_enabled"]==true)
      {
        if ($series["style"]=="hollow_bar")
        {
          imagerectangle($data["buffer"],$x1,$y1,$x2,$y2,$line_color);
        }
        else
        {
          imagefilledrectangle($data["buffer"],$x1,$y1,$x2,$y2,$line_color);
        }
      }
    }
    else
    {
      $series["style"]="solid";
    }
  }
  $n=$n-1;
  for ($i=0;$i<$n;$i++)
  {
    $x1r=$x_values[$i];
    $y1r=$y_values[$i];
    $x2r=$x_values[$i+1];
    $y2r=$y_values[$i+1];
    $pt1ok=(\webdb\chart\val_range_x($x1r,$data) and \webdb\chart\val_range_y($y1r,$data));
    $pt2ok=(\webdb\chart\val_range_x($x2r,$data) and \webdb\chart\val_range_y($y2r,$data));
    if (($pt1ok==false) and ($pt2ok==false))
    {
      continue;
    }
    $x1=\webdb\chart\chart_to_pixel_x($x1r,$data);
    $y1=\webdb\chart\chart_to_pixel_y($y1r,$data);
    $x2=\webdb\chart\chart_to_pixel_x($x2r,$data);
    $y2=\webdb\chart\chart_to_pixel_y($y2r,$data);
    if (($pt1ok==false) or ($pt2ok==false))
    {
      $dx=$x2r-$x1r;
      $dy=$y2r-$y1r;
      if ($dx==0)
      {
        $m=false;
        $c=false;
      }
      else
      {
        $m=$dy/$dx;
        $c=$y1r-$m*$x1r;
      }
      if ($pt1ok==false)
      {
        $p1=\webdb\chart\force_within_limits($x1r,$y1r,$x2r,$y2r,$m,$c,$data);
        $x1=\webdb\chart\chart_to_pixel_x($p1[0],$data);
        $y1=\webdb\chart\chart_to_pixel_y($p1[1],$data);
      }
      if ($pt2ok==false)
      {
        $p2=\webdb\chart\force_within_limits($x2r,$y2r,$x1r,$y1r,$m,$c,$data);
        $x2=\webdb\chart\chart_to_pixel_x($p2[0],$data);
        $y2=\webdb\chart\chart_to_pixel_y($p2[1],$data);
      }
    }
    if ($colors[$i]!==false)
    {
      $line_color=imagecolorallocate($data["buffer"],$colors[$i][0],$colors[$i][1],$colors[$i][2]);
    }
    if (($series["marker"]=="box") and ($pt1ok==true))
    {
      imagerectangle($data["buffer"],$x1-2,$y1-2,$x1+2,$y1+2,$line_color);
      if ($series["line_enabled"]==false)
      {
        imagesetpixel($data["buffer"],$x1,$y1,$line_color);
      }
    }
    if (isset($series["thickness"])==true)
    {
      imageantialias($data["buffer"],false);
      imagesetthickness($data["buffer"],$series["thickness"]);
    }
    if ($series["line_enabled"]==true)
    {
      switch ($series["style"])
      {
        case "solid":
          imageline($data["buffer"],$x1,$y1,$x2,$y2,$line_color);
          break;
        case "dash":
          imagedashedline($data["buffer"],$x1,$y1,$x2,$y2,$line_color);
          break;
      }
    }
    imagesetthickness($data["buffer"],1);
    imageantialias($data["buffer"],true);
  }
  if (($series["marker"]=="box") and ($n>0) and ($pt2ok==true))
  {
    imagerectangle($data["buffer"],$x2-2,$y2-2,$x2+2,$y2+2,$line_color);
  }
}

#####################################################################################################

function chart_draw_step_plot(&$data,$series)
{
  $color=$series["color"];
  $color=$data["colors"][$color];
  $line_color=imagecolorallocate($data["buffer"],$color[0],$color[1],$color[2]);
  $x_values=$series["x_values"];
  $y_values=$series["y_values"];
  $x2=false;
  $y2=false;
  $n=count($x_values)-1;
  for ($i=0;$i<$n;$i++)
  {
    $min_x_exceeded=false;
    if ($x_values[$i+1]<$data["x_min"])
    {
      if ($x_values[$i+1]<>end($x_values))
      {
        continue;
      }
      else
      {
        $x_values[$i+1]=$data["x_min"];
        $min_x_exceeded=true;
      }
    }
    if ($x_values[$i]>$data["x_max"])
    {
      continue;
    }
    if ($x_values[$i]<$data["x_min"])
    {
      $x_values[$i]=$data["x_min"];
    }
    $max_x_exceeded=false;
    if ($x_values[$i+1]>$data["x_max"])
    {
      $x_values[$i+1]=$data["x_max"];
      $y_values[$i+1]=$y_values[$i];
      $max_x_exceeded=true;
    }
    $x1=\webdb\chart\chart_to_pixel_x($x_values[$i],$data);
    $y1=\webdb\chart\chart_to_pixel_y($y_values[$i],$data);
    $x2=\webdb\chart\chart_to_pixel_x($x_values[$i+1],$data);
    imageline($data["buffer"],$x1,$y1,$x2,$y1,$line_color);
    if ($max_x_exceeded==false)
    {
      $y2=\webdb\chart\chart_to_pixel_y($y_values[$i+1],$data);
      imageline($data["buffer"],$x2,$y1,$x2,$y2,$line_color);
      if ($min_x_exceeded==false)
      {
        imagerectangle($data["buffer"],$x2-2,$y2-2,$x2+2,$y2+2,$line_color);
      }
    }
  }
  $rx=time();
  if ((end($x_values)<$rx) and (isset($data["today_mark"])==true) and ($x2!==false) and ($y2!==false))
  {
    $x=\webdb\chart\chart_to_pixel_x($rx,$data);
    imageline($data["buffer"],$x2,$y2,$x,$y2,$line_color);
  }
}

#####################################################################################################

function chart_draw_discontinuous_plot(&$data,$plot)
{
  # $plot["p1|2"]["x|y_values"]
  $color=$plot["color"];
  $color=$data["colors"][$color];
  $line_color=imagecolorallocate($data["buffer"],$color[0],$color[1],$color[2]);
  $segments=$plot["segments"];
  $n=count($segments);
  for ($i=0;$i<$n;$i++)
  {
    $segment=$segments[$i];
    $x1=$segment["p1"][0];
    $y1=$segment["p1"][1];
    $x2=$segment["p2"][0];
    $y2=$segment["p2"][1];
    $x1=\webdb\chart\chart_to_pixel_x($x1,$data);
    $y1=\webdb\chart\chart_to_pixel_y($y1,$data);
    $x2=\webdb\chart\chart_to_pixel_x($x2,$data);
    $y2=\webdb\chart\chart_to_pixel_y($y2,$data);
    if ($plot["marker"]=="box")
    {
      imagerectangle($data["buffer"],$x1-2,$y1-2,$x1+2,$y1+2,$line_color);
    }
    imageline($data["buffer"],$x1,$y1,$x2,$y2,$line_color);
  }
  if (($plot["marker"]=="box") and ($n>0))
  {
    imagerectangle($data["buffer"],$x2-2,$y2-2,$x2+2,$y2+2,$line_color);
  }
}

#####################################################################################################

function chart_draw_grid(&$data)
{
  $color=$data["colors"]["grid"];
  $line_color=imagecolorallocate($data["buffer"],$color[0],$color[1],$color[2]);
  if ($data["show_grid_x"]==true)
  {
    switch ($data["x_axis_scale"])
    {
      case "linear":
        $dx=$data["x_max"]-$data["x_min"];
        if ($data["sub_grid_x"]!==false)
        {
          $color=$data["colors"]["sub_grid"];
          $line_color=imagecolorallocate($data["buffer"],$color[0],$color[1],$color[2]);
          $n=round($dx/$data["sub_grid_x"]);
          for ($i=0;$i<=$n;$i++)
          {
            $rx=$data["sub_grid_x"]*$i+$data["x_min"];
            $px=\webdb\chart\chart_to_pixel_x($rx,$data);
            imageline($data["buffer"],$px,$data["top"],$px,$data["h"]-$data["bottom"]-1,$line_color);
          }
          $color=$data["colors"]["grid"];
          $line_color=imagecolorallocate($data["buffer"],$color[0],$color[1],$color[2]);
        }
        $n=round($dx/$data["grid_x"]);
        for ($i=0;$i<=$n;$i++)
        {
          $rx=$data["grid_x"]*$i+$data["x_min"];
          $px=\webdb\chart\chart_to_pixel_x($rx,$data);
          imageline($data["buffer"],$px,$data["top"],$px,$data["h"]-$data["bottom"]-1,$line_color);
        }
        break;
      case "log10":
        $dx=$data["x_max"]-$data["x_min"]; # major grids (eg: -1 to 3 => 0.1 to 1000)
        for ($i=0;$i<=$dx;$i++)
        {
          for ($j=1;$j<=$data["grid_x"];$j++) # 9 spaces typ.
          {
            # log10(0.1)=-1
            # 10^-1=0.1
            $rx=pow(10,$data["x_min"]+$i)*$j;
            #var_dump($rx);
            $px=\webdb\chart\chart_to_pixel_x($rx,$data);
            imageline($data["buffer"],$px,$data["top"],$px,$data["h"]-$data["bottom"]-1,$line_color);
          }
        }
        break;
    }
  }
  if ($data["show_grid_y"]==true)
  {
    switch ($data["y_axis_scale"])
    {
      case "linear":
        $dy=$data["y_max"]-$data["y_min"];
        if ($data["sub_grid_y"]!==false)
        {
          $color=$data["colors"]["sub_grid"];
          $line_color=imagecolorallocate($data["buffer"],$color[0],$color[1],$color[2]);
          $n=round($dy/$data["sub_grid_y"]);
          for ($i=0;$i<=$n;$i++)
          {
            $ry=$data["sub_grid_y"]*$i+$data["y_min"];
            $py=\webdb\chart\chart_to_pixel_y($ry,$data);
            imageline($data["buffer"],$data["left"],$py,$data["w"]-$data["right"]-1,$py,$line_color);
          }
          $color=$data["colors"]["grid"];
          $line_color=imagecolorallocate($data["buffer"],$color[0],$color[1],$color[2]);
        }
        $n=round($dy/$data["grid_y"]);
        for ($i=0;$i<=$n;$i++)
        {
          $ry=$data["grid_y"]*$i+$data["y_min"];
          $py=\webdb\chart\chart_to_pixel_y($ry,$data);
          imageline($data["buffer"],$data["left"],$py,$data["w"]-$data["right"]-1,$py,$line_color);
        }
        break;
      case "log10":
        $dy=$data["y_max"]-$data["y_min"]; # major grids (eg: -1 to 3 => 0.1 to 1000)
        for ($i=0;$i<=$dy;$i++)
        {
          for ($j=1;$j<=$data["grid_y"];$j++) # 9 spaces typ.
          {
            # log10(0.1)=-1
            # 10^-1=0.1
            $ry=pow(10,$data["y_min"]+$i)*$j;
            #var_dump($ry);
            $py=\webdb\chart\chart_to_pixel_y($ry,$data);
            imageline($data["buffer"],$data["left"],$py,$data["w"]-$data["right"]-1,$py,$line_color);
          }
        }
        break;
    }
  }
}

#####################################################################################################

function chart_draw_axis_x(&$data)
{
  global $settings;
  $font_size=10;
  $tick_length=5;
  $label_space=4;
  $text_file=$settings["gd_ttf"];
  $line_color=imagecolorallocate($data["buffer"],50,50,50);
  $text_color=imagecolorallocate($data["buffer"],50,50,50);
  $y=$data["h"]-$data["bottom"]-1;
  imageline($data["buffer"],$data["left"],$y,$data["w"]-$data["right"]-1,$y,$line_color);
  switch ($data["x_axis_scale"])
  {
    case "linear":
      $wp=$data["w"]-$data["left"]-$data["right"];
      $ppu=\webdb\chart\pixels_per_unit($wp,$data["x_min"],$data["x_max"]);
      $grid_x_pixels=$data["grid_x"]*$ppu;
      $dx=$data["x_max"]-$data["x_min"];
      $n=round($dx/$data["grid_x"]);
      for ($i=0;$i<=$n;$i++)
      {
        $rx=$data["grid_x"]*$i+$data["x_min"];
        $rx=\webdb\chart\zero_value($rx);
        $x=\webdb\chart\chart_to_pixel_x($rx,$data);
        $caption=$rx;
        if (isset($data["x_axis_format"])==true)
        {
          $caption=sprintf($data["x_axis_format"],$rx);
        }
        if (isset($data["x_captions"][$i])==true)
        {
          $caption=$data["x_captions"][$i];
        }
        $bbox=imagettfbbox($font_size,0,$text_file,$caption);
        $text_w=$bbox[2]-$bbox[0];
        $text_h=$bbox[1]-$bbox[7];
        if ($grid_x_pixels<($text_h*2))
        {
          if (($i%2)>0)
          {
            #continue; TODO: SKIPS MONTHS IN LINUX SOMETIMES ???
          }
        }
        imageline($data["buffer"],$x,$y,$x,$y+$tick_length,$line_color);
        imageline($data["buffer"],$x,$y+$tick_length,$x-$tick_length,$y+2*$tick_length,$line_color);
        $text_x=$x-round($text_w/sqrt(2))-$tick_length;
        $text_y=$y+round($text_w/sqrt(2))+2*$tick_length+$label_space+2;
        imagettftext($data["buffer"],$font_size,45,$text_x,$text_y,$text_color,$text_file,$caption);
      }
      break;
    case "log10":
      $dx=$data["x_max"]-$data["x_min"]; # major grids (eg: -1 to 3 => 0.1 to 1000)
      for ($i=0;$i<=$dx;$i++)
      {
        for ($j=1;$j<=$data["grid_x"];$j++) # 9 spaces typ.
        {
          # log10(0.1)=-1
          # 10^-1=0.1
          $rx=pow(10,$data["x_min"]+$i)*$j;
          #var_dump($rx);
          $px=\webdb\chart\chart_to_pixel_x($rx,$data);
          imageline($data["buffer"],$px,$y,$px,$y+$tick_length,$line_color);
        }
        $rx=pow(10,$data["x_min"]+$i);
        $x=\webdb\chart\chart_to_pixel_x($rx,$data);
        $caption=$rx;
        if (isset($data["x_axis_format"])==true)
        {
          $caption=sprintf($data["x_axis_format"],$rx);
        }
        if (isset($data["x_captions"][$i])==true)
        {
          $caption=$data["x_captions"][$i];
        }
        $bbox=imagettfbbox($font_size,0,$text_file,$caption);
        $text_w=$bbox[2]-$bbox[0];
        $text_h=$bbox[1]-$bbox[7];
        imageline($data["buffer"],$x,$y,$x,$y+$tick_length,$line_color);
        imageline($data["buffer"],$x,$y+$tick_length,$x-$tick_length,$y+2*$tick_length,$line_color);
        $text_x=$x-round($text_w/sqrt(2))-$tick_length;
        $text_y=$y+round($text_w/sqrt(2))+2*$tick_length+$label_space+2;
        imagettftext($data["buffer"],$font_size,45,$text_x,$text_y,$text_color,$text_file,$caption);
      }
      break;
  }
}

#####################################################################################################

function chart_draw_axis_y(&$data,$rhs_data=false)
{
  global $settings;
  $font_size=10;
  $tick_length=5;
  $label_space=4;
  $text_file=$settings["gd_ttf"];
  $line_color=imagecolorallocate($data["buffer"],50,50,50);
  $text_color=imagecolorallocate($data["buffer"],50,50,50);
  imageline($data["buffer"],$data["left"],$data["top"],$data["left"],$data["h"]-$data["bottom"]-1,$line_color);
  switch ($data["y_axis_scale"])
  {
    case "linear":
      $dy=$data["y_max"]-$data["y_min"];
      $n=round($dy/$data["grid_y"]);
      for ($i=0;$i<=$n;$i++)
      {
        $ry=$data["grid_y"]*$i+$data["y_min"];
        $ry=\webdb\chart\zero_value($ry);
        $y=\webdb\chart\chart_to_pixel_y($ry,$data);
        $caption=$ry;
        if (isset($data["y_axis_format"])==true)
        {
          $caption=sprintf($data["y_axis_format"],$ry);
        }
        if (isset($data["y_captions"][$i])==true)
        {
          $caption=$data["y_captions"][$i];
        }
        imageline($data["buffer"],$data["left"],$y,$data["left"]-$tick_length,$y,$line_color);
        $bbox=imagettfbbox($font_size,0,$text_file,$caption);
        $text_w=$bbox[2]-$bbox[0];
        $text_h=$bbox[1]-$bbox[7];
        $text_x=$data["left"]-$text_w-$tick_length-$label_space;
        $text_y=$y+round($text_h/2);
        imagettftext($data["buffer"],$font_size,0,$text_x,$text_y,$text_color,$text_file,$caption);
      }
      if ($rhs_data===false)
      {
        return;
      }
      $x=$data["w"]-$data["right"]-1;
      imageline($data["buffer"],$x,$data["top"],$x,$data["h"]-$data["bottom"]-1,$line_color);
      $dy=$rhs_data["y_max"]-$rhs_data["y_min"];
      $rhs_data["grid_y"]=ceil($dy/$n);
      for ($i=0;$i<=$n;$i++)
      {
        $ry=$rhs_data["grid_y"]*$i+$rhs_data["y_min"];
        $y=\webdb\chart\chart_to_pixel_y($ry,$rhs_data);
        $caption=$ry;
        if (isset($rhs_data["y_axis_format"])==true)
        {
          $caption=sprintf($rhs_data["y_axis_format"],$ry);
        }
        if (isset($rhs_data["y_captions"][$i])==true)
        {
          $caption=$rhs_data["y_captions"][$i];
          if ($caption=="")
          {
            continue;
          }
        }
        imageline($data["buffer"],$x,$y,$x-$tick_length,$y,$line_color);
        $bbox=imagettfbbox($font_size,0,$text_file,$caption);
        $text_w=$bbox[2]-$bbox[0];
        $text_h=$bbox[1]-$bbox[7];
        $text_x=$x-$text_w-$tick_length-$label_space;
        $text_y=$y+round($text_h/2);
        imagettftext($data["buffer"],$font_size,0,$text_x,$text_y,$text_color,$text_file,$caption);
      }
      break;
    case "log10":
      $dy=$data["y_max"]-$data["y_min"]; # major grids (eg: -1 to 3 => 0.1 to 1000)
      for ($i=0;$i<=$dy;$i++)
      {
        for ($j=1;$j<=$data["grid_y"];$j++) # 9 spaces typ.
        {
          # log10(0.1)=-1
          # 10^-1=0.1
          $ry=pow(10,$data["y_min"]+$i)*$j;
          $y=\webdb\chart\chart_to_pixel_y($ry,$data);
          imageline($data["buffer"],$data["left"],$y,$data["left"]-$tick_length,$y,$line_color);
        }
        $ry=pow(10,$data["y_min"]+$i);
        $py=\webdb\chart\chart_to_pixel_y($ry,$data);
        #imageline($data["buffer"],$data["left"],$py,$data["w"]-$data["right"]-1,$py,$line_color);
        $caption=$ry;
        if (isset($data["y_axis_format"])==true)
        {
          $caption=sprintf($data["y_axis_format"],$ry);
        }
        if (isset($data["y_captions"][$i])==true)
        {
          $caption=$data["y_captions"][$i];
        }
        imageline($data["buffer"],$data["left"],$y,$data["left"]-$tick_length,$y,$line_color);
        $bbox=imagettfbbox($font_size,0,$text_file,$caption);
        $text_w=$bbox[2]-$bbox[0];
        $text_h=$bbox[1]-$bbox[7];
        $text_x=$data["left"]-$text_w-$tick_length-$label_space;
        $text_y=$py+round($text_h/2);
        imagettftext($data["buffer"],$font_size,0,$text_x,$text_y,$text_color,$text_file,$caption);
      }
      break;
  }
}

#####################################################################################################

function chart_draw_title_x(&$data)
{
  global $settings;
  $title_font_size=12;
  $title_margin=5;
  $text_color=imagecolorallocate($data["buffer"],50,50,50);
  $text_file=$settings["gd_ttf"];
  $cx=($data["w"]-$data["left"]-$data["right"])/2+$data["left"];
  $bbox=imagettfbbox($title_font_size,0,$text_file,$data["x_title"]);
  $text_w=$bbox[2]-$bbox[0];
  $text_h=$bbox[1]-$bbox[7];
  $text_x=$cx-round($text_w/2);
  $text_y=$data["h"]-$title_margin;
  imagettftext($data["buffer"],$title_font_size,0,round($text_x),round($text_y),$text_color,$text_file,$data["x_title"]);
}

#####################################################################################################

function chart_draw_title_y(&$data,$rhs_data=false)
{
  global $settings;
  $title_font_size=12;
  $title_margin=5;
  $text_color=imagecolorallocate($data["buffer"],50,50,50);
  $text_file=$settings["gd_ttf"];
  $cy=($data["h"]-$data["bottom"]-$data["top"])/2+$data["top"];
  $bbox=imagettfbbox($title_font_size,0,$text_file,$data["y_title"]);
  $text_w=$bbox[2]-$bbox[0];
  $text_h=$bbox[1]-$bbox[7];
  $text_x=$title_margin+$text_h;
  $text_y=$cy+round($text_w/2);
  imagettftext($data["buffer"],$title_font_size,90,$text_x,$text_y,$text_color,$text_file,$data["y_title"]);
  if ($rhs_data===false)
  {
    return;
  }
  $bbox=imagettfbbox($title_font_size,0,$text_file,$rhs_data["y_title"]);
  $text_w=$bbox[2]-$bbox[0];
  $text_h=$bbox[1]-$bbox[7];
  $title_rhs_margin=25;
  if (isset($rhs_data["title_rhs_margin"])==true)
  {
    $title_rhs_margin=$rhs_data["title_rhs_margin"];
  }
  $text_x=$data["w"]-$data["right"]-$title_rhs_margin-$text_h;
  $text_y=$cy+round($text_w/2);
  imagettftext($data["buffer"],$title_font_size,90,$text_x,$text_y,$text_color,$text_file,$rhs_data["y_title"]);
}

#####################################################################################################

function chart_draw_save_file(&$data,$filename)
{
  return imagepng($data["buffer"],$filename);
}

#####################################################################################################

function chart_draw_html_out(&$data)
{
  return \webdb\graphics\base64_image($data["buffer"],"png");
}

#####################################################################################################



#####################################################################################################
