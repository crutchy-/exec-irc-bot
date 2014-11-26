<?php

# gpl2
# by crutchy
# blah

#####################################################################################################

/*
exec:~bitbucket|60|0|0|1||||0|php scripts/github_feed.php %%trailing%% %%dest%% %%nick%% %%alias%%
*/

#####################################################################################################

# https://confluence.atlassian.com/display/BITBUCKET/Use+the+Bitbucket+REST+APIs
# https://bitbucket.org/api/1.0/repositories/bcsd/uselessd/events

ini_set("display_errors","on");
date_default_timezone_set("UTC");
require_once("lib.php");

$trailing=$argv[1];
$dest=$argv[2];
$nick=$argv[3];
$alias=strtolower(trim($argv[4]));

define("FEED_CHAN","#github");

$list_bitbucket=array(
  "bcsd/uselessd");

define("TIME_LIMIT_SEC",300); # 5 mins

if ($alias=="~bitbucket")
{
  for ($i=0;$i<count($list_bitbucket);$i++)
  {
    check_push_events_bitbucket($list_bitbucket[$i]);
  }
  return;
}

/*for ($i=0;$i<count($list_bitbucket);$i++)
{
  check_push_events_bitbucket($list_bitbucket[$i]);
}*/

#####################################################################################################

function check_push_events_bitbucket($repo)
{
  $data=get_api_data("/api/1.0/repositories/$repo/events","bitbucket");
  file_put_contents("/nas/server/git/pretty_json",json_encode($data,JSON_PRETTY_PRINT));
  $changesets=get_api_data("/api/1.0/repositories/$repo/changesets?limit=50","bitbucket");
  for ($i=0;$i<count($data["events"]);$i++)
  {
    if (isset($data["events"][$i]["utc_created_on"])==False)
    {
      continue;
    }
    $timestamp=$data["events"][$i]["utc_created_on"];
    $t=convert_timestamp($timestamp,"Y-m-d H:i:s      ");
    $dt=microtime(True)-$t;
    #if ($dt<=TIME_LIMIT_SEC)
    #{
      if ($data["events"][$i]["event"]=="pushed")
      {
        pm(FEED_CHAN,chr(3)."13"."push to https://bitbucket.org/$repo @ ".date("H:i:s",$t)." by ".$data["events"][$i]["user"]["username"]);
        $commits=$data["events"][$i]["description"]["commits"];
        for ($j=0;$j<count($commits);$j++)
        {
          $changeset=bitbucket_get_changeset($changesets,$commits[$j]["hash"]);
          if ($changeset===False)
          {
            pm(FEED_CHAN,"changeset not found");
            continue;
          }
          $desc=$commits[$j]["description"];
          if ($desc<>$changeset["message"])
          {
            continue;
          }
          pm(FEED_CHAN,chr(3)."11"."  ".$changeset["author"].": ".$changeset["message"]);
          $url="https://bitbucket.org/$repo/commits/".$commits[$j]["hash"];
          pm(FEED_CHAN,chr(3)."11"."  ".$url);
        }
      }
    #}
    return;
  }
}

#####################################################################################################

function bitbucket_get_changeset(&$changesets,$hash)
{
  for ($i=0;$i<count($changesets["changesets"]);$i++)
  {
    $raw_node=$changesets["changesets"][$i]["raw_node"];
    if ($hash==$raw_node)
    {
      return $changesets["changesets"][$i];
    }
  }
  return False;
}

#####################################################################################################

function get_api_data($uri)
{
  $host="bitbucket.org";
  $port=443;
  $headers="";
  $response=wget($host,$uri,$port,ICEWEASEL_UA,$headers,60);
  $content=strip_headers($response);
  return json_decode($content,True);
}

#####################################################################################################

?>