<?php

namespace webdb\graphics;

#####################################################################################################

function base64_image($image,$type)
{
  ob_start();
  switch ($type)
  {
    case "gif":
      {
        imagegif($image);
        break;
      }
    case "jpeg":
      {
        imagejpg($image);
        break;
      }
    case "png":
      {
        imagepng($image);
        break;
      }
  }
  $data=ob_get_contents();
  ob_end_clean();
  $params=array();
  $params["type"]=$type;
  $params["data"]=base64_encode($data);
  $data=\webdb\utils\template_fill("base64_image",$params);
  return $data;
}

#####################################################################################################

function scale_img(&$buffer,$scale,$w,$h)
{
  $final_w=round($w*$scale);
  $final_h=round($h*$scale);
  $buffer_resized=imagecreatetruecolor($final_w,$final_h);
  if (imagecopyresampled($buffer_resized,$buffer,0,0,0,0,$final_w,$final_h,$w,$h)==false)
  {
    return false;
  }
  imagedestroy($buffer);
  $buffer=imagecreate($final_w,$final_h);
  if (imagecopy($buffer,$buffer_resized,0,0,0,0,$final_w,$final_h)==false)
  {
    return false;
  }
  imagedestroy($buffer_resized);
}

#####################################################################################################
