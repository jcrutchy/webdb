<?php

# INFINITE ASYNCHRONOUS ROCK / PAPER / SCISSORS GAME

namespace webdb\chat\rps;

#####################################################################################################

function page_rps($form_config)
{
  global $settings;
  $user_record=\webdb\chat\get_user_record_by_id($settings["user_record"]["user_id"]);
  $result_params=array();
  $result_params["response"]=implode(PHP_EOL,\webdb\chat\rps\play_rps($user_record,"",false));
  $page_params=array();
  $page_params["results"]=\webdb\utils\template_fill("chat/rps_results",$result_params);
  $content=\webdb\utils\template_fill("chat/rps",$page_params);
  $title="INFINITE ASYNCHRONOUS ROCK/PAPER/SCISSORS";
  \webdb\utils\output_page($content,$title);
}

#####################################################################################################

function ajax_rps($form_config,$field_name,$event_type,$event_data)
{
  global $settings;
  $user_record=\webdb\chat\get_user_record_by_id($settings["user_record"]["user_id"]);
  $result_params=array();
  $result_params["response"]=implode(PHP_EOL,\webdb\chat\rps\play_rps($user_record,$_GET["id"],false));
  $data=array();
  $data["results"]=\webdb\utils\template_fill("chat/rps_results",$result_params);
  $data=json_encode($data);
  die($data);
}

#####################################################################################################
#####################################################################################################

function play_rps($user_record,$trailing,$help_out=true)
{
  global $settings;
  $response=array();
  $ts=microtime(true);
  if ($trailing=="")
  {
    if ($help_out==true)
    {
      $response[]="infinite asynchronous rock/paper/scissors help";
      $response[]="/rps [r|p|s]";
      $response[]="r =&gt; rock";
      $response[]="p =&gt; paper";
      $response[]="s =&gt; scissors";
      $response[]="/rps reset";
      $response[]="can submit multiple turns in one command, which is useful if you're a new player";
      $response[]="/rps rrrrpsrpsrpssspssr";
      $response[]="sequences will be trimmed to the current maximum sequence length of all players, plus one (to gradually advance the number of rounds)";
      $response[]="ranking is based on a handicap that balances the number of wins and losses with the number of rounds played";
      $response[]="so that a new player who gets a win doesn't secure top spot just because they have a 100% win rate";
      \webdb\chat\insert_notice_breaks($response);
    }
  }
  if ($trailing=="reset")
  {
    $data=array();
    if ($user_record["json_data"]<>"")
    {
      $data=json_decode($user_record["json_data"],true);
    }
    $data["rps"]="";
    $user_record["json_data"]=json_encode($data);
    \webdb\chat\update_user($user_record);
    $response[]="sequence reset";
    return $response;
  }
  if (\webdb\chat\rps\valid_rps_sequence($trailing)==false)
  {
    $response[]="invalid sequence";
    \webdb\chat\insert_notice_breaks($response);
    return $response;
  }
  $delta=$ts-strtotime($user_record["last_online"]);
  if ($delta<mt_rand(3,8))
  {
    # delay to prevent spamming
    #$response[]="please wait a few seconds before trying again";
    #return $response;
  }
  $max_rounds=0;
  $max_nick_len=0;
  $player_data=array();
  $user_records=\webdb\sql\file_fetch_prepare("chat/chat_user_get_all_enabled");
  for ($i=0;$i<count($user_records);$i++)
  {
    $user=$user_records[$i];
    $nick=$user["nick"];
    $max_nick_len=max($max_nick_len,strlen($nick));
    if ($user["json_data"]<>"")
    {
      $data=json_decode($user["json_data"],true);
      if (isset($data["rps"])==true)
      {
        $player=array();
        $player["nick"]=$nick;
        $player["sequence"]=$data["rps"];
        $player["wins"]=0;
        $player["ties"]=0;
        $player["losses"]=0;
        $player["rounds"]=0;
        $player["rank"]=0;
        $player["handicap"]=0;
        $player_data[$nick]=$player;
        $max_rounds=max($max_rounds,strlen($data["rps"]));
      }
    }
  }
  $nick=$user_record["nick"];
  if (isset($player_data[$nick]["sequence"])==false)
  {
    $player=array();
    $player["nick"]=$nick;
    $player["sequence"]="";
    $player["wins"]=0;
    $player["ties"]=0;
    $player["losses"]=0;
    $player["rounds"]=0;
    $player["rank"]=0;
    $player["handicap"]=0;
    $player_data[$nick]=$player;
  }
  $sequence=$player_data[$nick]["sequence"].$trailing;
  if (strlen($sequence)>($max_rounds+1))
  {
    $sequence=substr($sequence,0,($max_rounds+1));
    $response[]="sequence trimmed";
    \webdb\chat\insert_notice_breaks($response);
  }
  $max_rounds=max($max_rounds,strlen($sequence));
  $player_data[$nick]["sequence"]=$sequence;
  $data=array();
  if ($user_record["json_data"]<>"")
  {
    $data=json_decode($user_record["json_data"],true);
  }
  $data["rps"]=$sequence;
  $user_record["json_data"]=json_encode($data);
  \webdb\chat\update_user($user_record);
  foreach ($player_data as $outer_nick => $outer_player)
  {
    $outer_sequence=$outer_player["sequence"];
    for ($i=0;$i<strlen($outer_sequence);$i++)
    {
      foreach ($player_data as $inner_nick => $inner_player)
      {
        if ($outer_nick==$inner_nick)
        {
          continue;
        }
        $inner_sequence=$inner_player["sequence"];
        if (isset($inner_sequence[$i])==false)
        {
          continue;
        }
        switch ($outer_sequence[$i])
        {
          case "r":
            switch ($inner_sequence[$i])
            {
              case "r":
                $player_data[$outer_nick]["ties"]++;
                break;
              case "p":
                $player_data[$outer_nick]["losses"]++;
                break;
              case "s":
                $player_data[$outer_nick]["wins"]++;
                break;
            }
            break;
          case "p":
            switch ($inner_sequence[$i])
            {
              case "r":
                $player_data[$outer_nick]["wins"]++;
                break;
              case "p":
                $player_data[$outer_nick]["ties"]++;
                break;
              case "s":
                $player_data[$outer_nick]["losses"]++;
                break;
            }
            break;
          case "s":
            switch ($inner_sequence[$i])
            {
              case "r":
                $player_data[$outer_nick]["losses"]++;
                break;
              case "p":
                $player_data[$outer_nick]["wins"]++;
                break;
              case "s":
                $player_data[$outer_nick]["ties"]++;
                break;
            }
            break;
        }
      }
    }
  }
  $max_rounds=0;
  foreach ($player_data as $nick => $player)
  {
    $player_data[$nick]["rounds"]=$player["wins"]+$player["ties"]+$player["losses"];
    if ($player_data[$nick]["rounds"]>0)
    {
      $delta=$player["wins"]-$player["losses"];
      if ($delta>=0)
      {
        $player_data[$nick]["handicap"]=$delta*$player_data[$nick]["rounds"];
      }
      else
      {
        $player_data[$nick]["handicap"]=$delta/$player_data[$nick]["rounds"]*100000;
      }
    }
    $max_rounds=max($max_rounds,$player_data[$nick]["rounds"]);
  }
  ksort($player_data);
  uasort($player_data,"\\webdb\\chat\\rps\\ranking_sort_callback");
  $rank=1;
  foreach ($player_data as $nick => $player)
  {
    $player_data[$nick]["rank"]=$rank;
    $rank++;
  }
  $max_nick_len=max($max_nick_len,8);
  $response[]="infinite asynchronous rock/paper/scissors rankings";
  $tab=10;
  $out=str_pad("user",$max_nick_len," ",STR_PAD_RIGHT);
  $out.=str_pad("rounds",$tab," ",STR_PAD_LEFT);
  $out.=str_pad("wins",$tab," ",STR_PAD_LEFT);
  $out.=str_pad("losses",$tab," ",STR_PAD_LEFT);
  $out.=str_pad("ties",$tab," ",STR_PAD_LEFT);
  $out.=str_pad("%wins",$tab," ",STR_PAD_LEFT);
  $out.=str_pad("rank",$tab," ",STR_PAD_LEFT);
  $out.=str_pad("handicap",13," ",STR_PAD_LEFT);
  $params=array();
  $params["response"]=$out;
  $response[]=\webdb\utils\template_fill("chat/pre_notice",$params);
  foreach ($player_data as $nick => $player)
  {
    if ($player["rounds"]>0)
    {
      $win_frac=$player["wins"]/$player["rounds"]*100;
    }
    else
    {
      $win_frac=0;
    }
    $out=str_pad($nick,$max_nick_len," ",STR_PAD_RIGHT);
    $out.=str_pad($player["rounds"],$tab," ",STR_PAD_LEFT);
    $out.=str_pad($player["wins"],$tab," ",STR_PAD_LEFT);
    $out.=str_pad($player["losses"],$tab," ",STR_PAD_LEFT);
    $out.=str_pad($player["ties"],$tab," ",STR_PAD_LEFT);
    $out.=str_pad(sprintf("%.0f",$win_frac)."%",$tab," ",STR_PAD_LEFT);
    $out.=str_pad($player["rank"],$tab," ",STR_PAD_LEFT);
    $out.=str_pad(sprintf("%.0f",$player["handicap"]),13," ",STR_PAD_LEFT);
    $params=array();
    $params["response"]=$out;
    $response[]=\webdb\utils\template_fill("chat/pre_notice",$params);
  }
  $response[]="maximum sequence length: ".$max_rounds;
  $response[]="handicap = (wins-losses)*rounds for more wins than losses";
  $response[]="handicap = (wins-losses)/rounds*100000 for more losses than wins";
  $response[]="ranking is based on a handicap that balances the number of wins and losses with the number of rounds played";
  $response[]="so that a new player who gets a win doesn't secure top spot just because they have a 100% win rate";
  \webdb\chat\insert_notice_breaks($response);
  return $response;
}

#####################################################################################################

function valid_rps_sequence($trailing)
{
  for ($i=0;$i<strlen($trailing);$i++)
  {
    switch ($trailing[$i])
    {
      case "r":
      case "p":
      case "s":
        break;
      default:
        return false;
    }
  }
  return true;
}

#####################################################################################################

function ranking_sort_callback($a,$b)
{
  return ($b["rank"]-$a["rank"]);
}

#####################################################################################################
