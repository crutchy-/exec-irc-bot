<?php

#####################################################################################################
################################### ROCK / PAPER / SCISSORS GAME ####################################
#####################################################################################################

/*
exec:~rps|10|0|0|1|||||php scripts/rps.php %%trailing%% %%dest%% %%nick%% %%alias%% %%params%% %%server%%
*/

#####################################################################################################

require_once("lib.php");

$trailing=strtolower(trim($argv[1]));
$dest=$argv[2];
$nick=$argv[3];
$alias=$argv[4];
$params=$argv[5];
$server=$argv[6];

$data=array();
$fn=DATA_PATH."rps_data_".$server;
if (file_exists($fn)==True)
{
  $data=json_decode(file_get_contents($fn),True);
}

if ((valid_rps_sequence($trailing)==True) and ($trailing<>""))
{
  $account=users_get_account($nick);
  if ($account=="")
  {
    privmsg("you need to identify with nickserv to play");
    return;
  }
  $ts=microtime(True);
  if (isset($data["users"][$account]["timestamp"])==True)
  {
    if (($ts-$data["users"][$account]["timestamp"])<mt_rand(3,8))
    {
      privmsg("please wait a few seconds before trying again");
      return;
    }
  }
  $data["users"][$account]["timestamp"]=$ts;
  if (isset($data["rounds"])==False)
  {
    $data["rounds"]=1;
  }
  if (isset($data["users"][$account]["sequence"])==False)
  {
    $data["users"][$account]["sequence"]="";
  }
  if (strlen($data["users"][$account]["sequence"].$trailing)>($data["rounds"]+1))
  {
    $trailing=substr($trailing,0,$data["rounds"]-strlen($data["users"][$account]["sequence"])+1);
    privmsg("sequence trimmed");
  }
  if (isset($data["users"])==False)
  {
    $data["users"]=array();
  }
  if (isset($data["users"][$account])==False)
  {
    $data["users"][$account]=array();
    $data["users"][$account]["rank"]="ERROR";
  }
  $data["users"][$account]["sequence"]=$data["users"][$account]["sequence"].$trailing;
  $data["rounds"]=max($data["rounds"],strlen($data["users"][$account]["sequence"]));
  if (file_put_contents($fn,json_encode($data,JSON_PRETTY_PRINT))===False)
  {
    privmsg("error writing data file");
    return;
  }
  $ranks=update_ranking($data);
  privmsg("rank for $account: ".$data["users"][$account]["rank"]." - ".output_ixio_paste($ranks,False));
  return;
}

if ($trailing=="ranks")
{
  if (isset($data["users"])==True)
  {
    output_ixio_paste(update_ranking($data));
    return;
  }
  else
  {
    privmsg("no players registered yet");
  }
}

privmsg("syntax: ~rps [ranks|r|p|s]");

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
        continue;
      default:
        return False;
    }
  }
  return True;
}

#####################################################################################################

function update_ranking(&$data)
{
  global $server;
  $max_sequence=0;
  foreach ($data["users"] as $account => $user_data)
  {
    $data["users"][$account]["wins"]=0;
    $data["users"][$account]["losses"]=0;
    $data["users"][$account]["ties"]=0;
    $max_sequence=max($max_sequence,strlen($data["users"][$account]["sequence"]));
    for ($i=0;$i<strlen($data["users"][$account]["sequence"]);$i++)
    {
      foreach ($data["users"] as $sub_account => $sub_user_data)
      {
        if ($sub_account==$account)
        {
          continue;
        }
        if (isset($data["users"][$sub_account]["sequence"][$i])==True)
        {
          switch ($data["users"][$account]["sequence"][$i])
          {
            case "r":
              switch ($data["users"][$sub_account]["sequence"][$i])
              {
                case "r":
                  $data["users"][$account]["ties"]=$data["users"][$account]["ties"]+1;
                  break;
                case "p":
                  $data["users"][$account]["losses"]=$data["users"][$account]["losses"]+1;
                  break;
                case "s":
                  $data["users"][$account]["wins"]=$data["users"][$account]["wins"]+1;
                  break;
              }
              break;
            case "p":
              switch ($data["users"][$sub_account]["sequence"][$i])
              {
                case "r":
                  $data["users"][$account]["wins"]=$data["users"][$account]["wins"]+1;
                  break;
                case "p":
                  $data["users"][$account]["ties"]=$data["users"][$account]["ties"]+1;
                  break;
                case "s":
                  $data["users"][$account]["losses"]=$data["users"][$account]["losses"]+1;
                  break;
              }
              break;
            case "s":
              switch ($data["users"][$sub_account]["sequence"][$i])
              {
                case "r":
                  $data["users"][$account]["losses"]=$data["users"][$account]["losses"]+1;
                  break;
                case "p":
                  $data["users"][$account]["wins"]=$data["users"][$account]["wins"]+1;
                  break;
                case "s":
                  $data["users"][$account]["ties"]=$data["users"][$account]["ties"]+1;
                  break;
              }
              break;
          }
        }
      }
    }
  }
  $rankings=array();
  foreach ($data["users"] as $account => $user_data)
  {
    $data["users"][$account]["rounds"]=$data["users"][$account]["wins"]+$data["users"][$account]["losses"]+$data["users"][$account]["ties"];
    $data["users"][$account]["rank"]=0;
    if ($data["users"][$account]["rounds"]>0)
    {
      $delta=$data["users"][$account]["wins"]-$data["users"][$account]["losses"];
      if ($delta>=0)
      {
        $rankings[$account]=$delta*$data["users"][$account]["rounds"];
      }
      else
      {
        $rankings[$account]=$delta/$data["users"][$account]["rounds"]*100000;
      }
    }
    else
    {
      $rankings[$account]=0;
    }
  }
  ksort($rankings);
  uasort($rankings,"ranking_sort_callback");
  $ranking_keys=array_keys($rankings);
  foreach ($data["users"] as $account => $user_data)
  {
    $data["users"][$account]["rank"]=array_search($account,$ranking_keys)+1;
  }
  $out="infinite asynchronous play-by-irc rock/paper/scissors rankings for $server:\n\n";
  $actlen=0;
  foreach ($data["users"] as $account => $user_data)
  {
    if (strlen($account)>$actlen)
    {
      $actlen=strlen($account);
    }
  }
  $head_account="account";
  $actlen=max($actlen,strlen($head_account));
  $out=$out.$head_account.str_repeat(" ",$actlen-strlen($head_account))."\trounds\twins\tlosses\tties\twins\trank\thandicap\n";
  $out=$out."=======".str_repeat(" ",$actlen-strlen($head_account))."\t======\t====\t======\t====\t====\t====\t========\n";
  foreach ($rankings as $account => $rank)
  {
    if ($data["users"][$account]["rounds"]>0)
    {
      $win_frac=$data["users"][$account]["wins"]/$data["users"][$account]["rounds"]*100;
    }
    else
    {
      $win_frac=0;
    }
    $out=$out.$account.str_repeat(" ",$actlen-strlen($account))."\t".$data["users"][$account]["rounds"]."\t".$data["users"][$account]["wins"]."\t".$data["users"][$account]["losses"]."\t".$data["users"][$account]["ties"]."\t".sprintf("%.0f",$win_frac)."%\t".$data["users"][$account]["rank"]."\t".str_pad(sprintf("%.0f",$rankings[$account]),strlen("handicap")," ",STR_PAD_LEFT)."\n";
  }
  $out=$out."\n\nmaximum sequence length: ".$max_sequence;
  $out=$out."\n\nhandicap = (wins-losses)*rounds for more wins than losses\nhandicap = (wins-losses)/rounds*100000 for more losses than wins\n\n\n";
  $out=$out.file_get_contents(__DIR__."/rps.help");
  return $out;
}

#####################################################################################################

function ranking_sort_callback($a,$b)
{
  return ($b-$a);
}

#####################################################################################################

?>
