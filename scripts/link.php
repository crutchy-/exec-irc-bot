<?php

#####################################################################################################

/*
exec:~link|10|0|0|1|*||||php scripts/link.php %%trailing%% %%dest%% %%nick%% %%alias%%
exec:~links|10|0|0|1|*||||php scripts/link.php %%trailing%% %%dest%% %%nick%% %%alias%%
exec:~!|10|0|0|1|*||||php scripts/link.php %%trailing%% %%dest%% %%nick%% %%alias%%
*/

#####################################################################################################

require_once("lib.php");

# TODO: do something similar to the github feed; output 6 and then make the last item something like "25 more not shown"

$trailing=trim($argv[1]);
$dest=$argv[2];
$nick=$argv[3];
$alias=$argv[4];

if ($trailing=="")
{
  privmsg("syntax to search: $alias %search%, set: $alias %id% %content%, delete: $alias %id% -");
  privmsg("keys can't contain pipe (|) character and %id% can't contain spaces, but %content% can, %search% is a regexp pattern");
  privmsg("will return a list of one or more %id% => %content% if %search% matches either %id% or %content%");
  return;
}

$list=load_settings(DATA_PATH."links","|");

if ($trailing=="count")
{
  privmsg(count($list));
  return;
}

ksort($list);
$parts=explode(" ",$trailing);
if (count($parts)>=2)
{
  if ((count($parts)==2) and ($parts[1]=="-"))
  {
    if (isset($list[$parts[0]])==True)
    {
      unset($list[$parts[0]]);
      privmsg("  └─ deleted ".$parts[0]);
    }
    else
    {
      privmsg("  └─ ".$parts[0]." not found");
    }
  }
  else
  {
    $id=$parts[0];
    array_shift($parts);
    $content=implode(" ",$parts);
    if (strpos($id,"|")===False)
    {
      $list[$id]=base64_encode($content);
      privmsg("  └─ $id => ".$content);
    }
    else
    {
      privmsg("  └─ error: id can't contain pipe (|) character");
    }
  }
  save_settings($list,DATA_PATH."links","|");
}
else
{
  foreach ($list as $key=>$value)
  {
    $list[$key]=base64_decode($value);
  }
  $results=array_merge(match_keys($trailing,$list),match_values($trailing,$list));
  $n=count($results);
  if ($n>0)
  {
    $w=max_key_len($results);
    $i=0;
    foreach ($results as $key => $value)
    {
      if ($i==($n-1))
      {
        privmsg("  └─ ".str_pad($key,$w)." => $value");
      }
      else
      {
        privmsg("  ├─ ".str_pad($key,$w)." => $value");
      }
      $i++;
    }
  }
  else
  {
    privmsg("  └─ \"".trim($argv[1])."\" not found");
  }
}

#####################################################################################################

function max_key_len($array)
{
  $result=0;
  foreach ($array as $key => $value)
  {
    $result=max($result,strlen($key));
  }
  return $result;
}

#####################################################################################################

function match_keys($query,$subject)
{
  $result=array();
  foreach ($subject as $key => $value)
  {
    if (strpos(strtolower($key),strtolower($query))!==False)
    {
      $result[$key]=$value;
      continue;
    }
    $pattern="~".$query."~";
    if (preg_match($pattern,$key)==1)
    {
      $result[$key]=$value;
    }
  }
  return $result;
}

#####################################################################################################

function match_values($query,$subject)
{
  $result=array();
  foreach ($subject as $key => $value)
  {
    if (strpos(strtolower($value),strtolower($query))!==False)
    {
      $result[$key]=$value;
      continue;
    }
    $pattern="~".$query."~";
    if (preg_match($pattern,$value)==1)
    {
      $result[$key]=$value;
    }
  }
  return $result;
}

#####################################################################################################

?>
