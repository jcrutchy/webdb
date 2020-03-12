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
