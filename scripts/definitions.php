<?php

# gpl2
# by crutchy
# 22-july-2014

# maybe eventually change to ~query

# http://api.urbandictionary.com/v0/define?term=shitton
# thanks weirdpercent

# https://encyclopediadramatica.es

#####################################################################################################

require_once("lib.php");
$trailing=$argv[1];
$alias=$argv[2];
define("DEFINITIONS_FILE","../data/definitions");
define("DEFINE_SOURCES_FILE","../data/define_sources");
define("MAX_DEF_LENGTH",200);
if (file_exists(DEFINE_SOURCES_FILE)==False)
{
  # if you add/remove elements from this array you need to delete (or amend) the define_sources file in the data path of whatever machine exec is running on
  $sources=array(
    "www.wolframalpha.com"=>array(
      "name"=>"wolframalpha",
      "port"=>80,
      "uri"=>"/input/?i=define%3A%%term%%",
      "template"=>"%%term%%",
      "get_param"=>"",
      "order"=>2,
      "delim_start"=>"context.jsonArray.popups.pod_0200.push( {\"stringified\": \"",
      "delim_end"=>"\",\"mInput\": \"\",\"mOutput\": \"\", \"popLinks\": {} });"),
    "www.urbandictionary.com"=>array(
      "name"=>"urbandictionary",
      "port"=>80,
      "uri"=>"/define.php?term=%%term%%",
      "template"=>"%%term%%",
      "get_param"=>"term",
      "order"=>1,
      "delim_start"=>"<div class='meaning'>",
      "delim_end"=>"</div>"),
    "www.stoacademy.com"=>array(
      "name"=>"stoacademy",
      "port"=>80,
      "uri"=>"/datacore/dictionary.php?searchTerm=%%term%%",
      "template"=>"%%term%%",
      "get_param"=>"",
      "order"=>3,
      "delim_start"=>"<b><u>",
      "delim_end"=>"<p>"));
}
else
{
  $sources=unserialize(file_get_contents(DEFINE_SOURCES_FILE));
}
reorder($sources);
$terms=unserialize(file_get_contents(DEFINITIONS_FILE));
switch($alias)
{
  case "~define-count":
    privmsg("custom definition count: ".count($terms));
    break;
  case "~define-sources":
    /*$out="";
    foreach ($sources as $host => $params)
    {
      if ($out<>"")
      {
        $out=$out.", ";
      }
      $out=$out.$host;
    }
    privmsg("definition sources: $out");*/
    foreach ($sources as $host => $params)
    {
      privmsg("$host => ".$params["name"]."|".$params["port"]."|".$params["uri"]."|".$params["template"]."|".$params["get_param"]."|".$params["order"]."|".$params["delim_start"]."|".$params["delim_end"]);
      usleep(0.5*1e6);
    }
    break;
  case "~define-source-edit":
    $params=explode("|",$trailing);
    if (count($params)==9)
    {
      $host=$params[0];
      $action="inserted";
      if (isset($sources[$host])==True)
      {
        $action="updated";
      }
      $sources[$host]["name"]=$params[1];
      $sources[$host]["port"]=$params[2];
      $sources[$host]["uri"]=$params[3];
      $sources[$host]["template"]=$params[4];
      $sources[$host]["get_param"]=$params[5];
      $sources[$host]["order"]=$params[6];
      $sources[$host]["delim_start"]=$params[7];
      $sources[$host]["delim_end"]=$params[8];
      reorder($sources);
      privmsg("source \"".$params[1]."\" $action");
    }
    else
    {
      privmsg("syntax: ~define-source-edit host|name|port|uri|template|get_param|order|delim_start|delim_end");
      privmsg("example: ~define-source-edit www.urbandictionary.com|urbandictionary|80|/define.php?term=%%term%%|%%term%%|term|1|<div class='meaning'>|</div>");
    }
    break;
  case "~define-source-param":
    $params=explode(" ",$trailing);
    if (count($params)==3)
    {
      $host=$params[0];
      $param=$params[1];
      $value=$params[2];
      if (isset($sources[$host][$param])==True)
      {
        $sources[$host][$param]=$value;
        $suffix="";
        if ($param=="order")
        {
          reorder($sources);
          $suffix=" (after reoder)";
        }
        privmsg("param \"$param\" for source with host \"$host\" changed to \"".$sources[$host][$param]."\"$suffix");
      }
      else
      {
        privmsg("param \"$param\" for source with host \"$host\" not found");
      }
    }
    else
    {
      privmsg("syntax: ~define-source-param host param value");
    }
    break;
  case "~define-source-delete":
    if (isset($sources[$trailing])==True)
    {
      unset($sources[$trailing]);
      reorder($sources);
      privmsg("source \"$trailing\" deleted");
    }
    else
    {
      privmsg("source \"$trailing\" not found");
    }
    break;
  case "~define-add":
    $parts=explode(",",$trailing);
    if (count($parts)>1)
    {
      $term=trim($parts[0]);
      array_shift($parts);
      $def=trim(implode(",",$parts));
      $terms[$term]=$def;
      if (file_put_contents(DEFINITIONS_FILE,serialize($terms))===False)
      {
        privmsg("error writing definitions file");
      }
      else
      {
        privmsg("definition for term \"$term\" set to \"$def\"");
      }
    }
    else
    {
      privmsg("syntax: ~define-add <term>, <definition>");
    }
    break;
  case "~define":
    foreach ($terms as $term => $def)
    {
      $lterms[strtolower($term)]=$term;
    }
    if (isset($lterms[strtolower($trailing)])==True)
    {
      $def=$terms[$lterms[strtolower($trailing)]];
      privmsg("[soylent] $trailing: $def");
    }
    else
    {
      foreach ($sources as $host => $params)
      {
        if (source_define($host,$trailing,$params)==True)
        {
          return;
        }
        else
        {
          term_echo("$trailing: unable to find definition @ $host");
        }
      }
      privmsg("$trailing: unable to find definition");
    }
    break;
}
file_put_contents(DEFINE_SOURCES_FILE,serialize($sources));

#####################################################################################################

function source_define($host,$term,$params)
{
  $uri=str_replace($params["template"],urlencode($term),$params["uri"]);
  $response=wget($host,$uri,$params["port"]);
  $html=strip_headers($response);
  $i=strpos($html,$params["delim_start"]);
  if ($host=="en.wikipedia.org")
  {
    var_dump($html);
  }
  $def="";
  if ($i!==False)
  {
    $html=substr($html,$i+strlen($params["delim_start"]));
    $i=strpos($html,$params["delim_end"]);
    if ($i!==False)
    {
      $def=trim(strip_tags(substr($html,0,$i)));
      $def=str_replace(array("\n","\r")," ",$def);
      $def=str_replace("  "," ",$def);
      if (strlen($def)>MAX_DEF_LENGTH)
      {
        $def=substr($def,0,MAX_DEF_LENGTH)."...";
      }
    }
  }
  if ($def=="")
  {
    $location=exec_get_header($response,"location");
    if ($location=="")
    {
      return False;
    }
    else
    {
      $new_term=extract_get($location,$params["get_param"]);
      if ($new_term<>$term)
      {
        return source_define($host,$new_term,$params);
      }
      else
      {
        return False;
      }
    }
  }
  else
  {
    privmsg("[".$params["name"]."] ".chr(3)."3$term".chr(3).": ".html_entity_decode($def,ENT_QUOTES,"UTF-8"));
    return True;
  }
}

#####################################################################################################

function reorder(&$sources)
{
  uasort($sources,"sort_source_order_compare");
  $i=1;
  foreach ($sources as $host => $params)
  {
    $sources[$host]["order"]=$i;
    $i++;
  }
}

#####################################################################################################

function sort_source_order_compare($a,$b)
{
  if ($a["order"]==$b["order"])
  {
    return 0;
  }
  elseif ($a["order"]<$b["order"])
  {
    return -1;
  }
  else
  {
    return 1;
  }
}

#####################################################################################################

?>
