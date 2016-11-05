<?php

#####################################################################################################

define("ICEWEASEL_UA","Mozilla/5.0 (Windows NT 6.3; rv:36.0) Gecko/20100101 Firefox/36.0");

$url_blacklist=explode(PHP_EOL,file_get_contents("../data/url_blacklist"));
delete_empty_elements($url_blacklist,True);

#####################################################################################################

function wget_proper($url,$titleonly=False)
{
  $redirect_data=get_redirected_url($url,"","",array());
  if ($redirect_data===False)
  {
    pm("crutchy","wget_proper: get_redirected_url=false: $url");
    return False;
  }
  $rd_url=$redirect_data["url"];
  $rd_cookies=$redirect_data["cookies"];
  $rd_extra_headers=$redirect_data["extra_headers"];
  $host="";
  $uri="";
  $port=80;
  if (get_host_and_uri($rd_url,$host,$uri,$port)==False)
  {
    pm("crutchy","wget_proper: get_host_and_uri=false: $url");
    return False;
  }
  $breakcode="";
  if ($titleonly==True)
  {
    $breakcode="return ((strpos(strtolower(\$response),\"</title>\")!==False) or (strlen(\$response)>=10000));";
  }
  $response=wget($host,$uri,$port,ICEWEASEL_UA,$rd_extra_headers,20,$breakcode,256);
  if ($titleonly==True)
  {
    $html=strip_headers($response);
    $title=extract_raw_tag($html,"title");
    $title=html_decode($title);
    $title=trim(html_decode($title));
    if ($title=="")
    {
      pm("crutchy","wget_proper: title is empty: $url");
      return False;
    }
    return $title;
  }
  else
  {
    return $response;
  }
}

#####################################################################################################

function upload_to_imgur($data,$type="png")
{
  $pwd=file_get_contents("../pwd/imgur");
  if ($pwd===False)
  {
    return False;
  }
  $pwd=trim($pwd);
  $pwd=explode(PHP_EOL,$pwd);
  if (count($pwd)<>2)
  if ($pwd===False)
  {
    return False;
  }
  $client_id=trim($pwd[0]);
  $headers=array();
  $headers["Authorization"]="Client-ID ".$client_id;
  $headers["Content-Type"]="image/$type";
  $headers["TE"]="";
  $response=wpost("api.imgur.com","/3/image.json",443,ICEWEASEL_UA,$data,$headers,20,True);
  $data=json_decode(strip_headers($response),True);
  if (isset($data["data"]["link"])==True)
  {
    return $data["data"]["link"];
  }
  else
  {
    return "error uploading image to imgur";
  }
}

#####################################################################################################

function get_user_localhost_ports($return_pids=False)
{
  $stdout=shell_exec("netstat -tlpn4 2>&1");
  $result=array();
  $lines=explode(PHP_EOL,$stdout);
  for ($i=0;$i<count($lines);$i++)
  {
    $line=trim($lines[$i]);
    if ($line=="")
    {
      continue;
    }
    if (substr($line,0,3)<>"tcp")
    {
      continue;
    }
    $parts=explode(" ",$line);
    delete_empty_elements($parts);
    if (count($parts)<>7)
    {
      continue;
    }
    $address=$parts[3];
    $address_parts=explode(":",$address);
    if (count($address_parts)<>2)
    {
      continue;
    }
    if ($address_parts[0]<>"127.0.0.1")
    {
      continue;
    }
    if ($return_pids==True)
    {
      $pid=$parts[6];
      $pid_parts=explode("/",$pid);
      if (count($pid_parts)<>2)
      {
        continue;
      }
      $result[]=array("pid"=>$pid_parts[0],"port"=>$address_parts[1]);
    }
    else
    {
      $result[]=$address_parts[1];
    }
  }
  return $result;
}

#####################################################################################################

function output_ixio_paste($data,$msg=True,$id="nAz")
{
  $fn=tempnam("/tmp","exec_");
  $h=fopen($fn,"w");
  fwrite($h,$data);
  fclose($h);
  /*
  to get new id for exec:exec
  echo hello | curl -F 'f:1=<-' exec:exec@ix.io
  */
  $out=shell_exec("cat $fn | curl -F 'f:1=<-' -F 'id:1=$id' exec:exec@ix.io 2>&1");
  $out=clean_text($out);
  $out=explode("curl: (",trim($out));
  array_shift($out);
  if ($msg==True)
  {
    if (count($out)==1)
    {
      privmsg("curl: (".$out[0]);
    }
    else
    {
      privmsg("http://ix.io/".$id);
    }
  }
  unlink($fn);
}

#####################################################################################################

function authorization_header_value($uname,$passwd,$prefix)
{
  return "$prefix ".base64_encode("$uname:$passwd");
}

#####################################################################################################

function shorten_url($url,$mode="title")
{
  if ($url=="")
  {
    return False;
  }
  $params=array();
  $params["url"]=$url;
  if ($mode<>"")
  {
    $params["mode"]=$mode; # optional
  }
  $response=wpost("o.my.to","/","80",ICEWEASEL_UA,$params,"",30);
  $short_url=trim(strip_headers($response));
  if ($short_url<>"")
  {
    return $short_url;
  }
  else
  {
    return False;
  }
}

#####################################################################################################

function lowercase_tags($html)
{
  $tags=explode("<",$html);
  for ($i=0;$i<count($tags);$i++)
  {
    $parts=explode(">",$tags[$i]);
    if (count($parts)==2)
    {
      $tags[$i]=strtolower($parts[0]).">".$parts[1];
    }
  }
  return implode("<",$tags);
}

#####################################################################################################

function check_url($url)
{
  global $url_blacklist;
  $lower_url=strtolower($url);
  for ($i=0;$i<count($url_blacklist);$i++)
  {
    if (strpos($lower_url,$url_blacklist[$i])!==False)
    {
      term_echo("*** blacklisted URL detected ***");
      return False;
    }
  }
  return True;
}

#####################################################################################################

function wtouch($host,$uri,$port,$timeout=5)
{
  if (check_url($host.$uri)==False) # check url against blacklist
  {
    return False;
  }
  $errno=0;
  $errstr="";
  if ($port==80)
  {
    $fp=@fsockopen($host,80,$errno,$errstr,$timeout);
  }
  elseif ($port==443)
  {
    $fp=@fsockopen("ssl://$host",443,$errno,$errstr,$timeout);
  }
  else
  {
    $fp=@fsockopen($host,$port,$errno,$errstr,$timeout);
  }
  if ($fp===False)
  {
    return False;
  }
  fwrite($fp,"GET $uri HTTP/1.0\r\nHost: $host\r\nConnection: Close\r\n\r\n");
  $response=fgets($fp,256);
  fclose($fp);
  return trim($response);
}

#####################################################################################################

function get_host_and_uri($url,&$host,&$uri,&$port)
{
  $url=trim($url);
  $comp=parse_url($url);
  $host="";
  if (isset($comp["host"])==True)
  {
    if ($comp["host"]<>"")
    {
      $host=$comp["host"];
    }
  }
  if ($host=="")
  {
    return False;
  }
  $port=80;
  if (isset($comp["scheme"])==True)
  {
    if ($comp["scheme"]=="https")
    {
      $port=443;
    }
  }
  $uri="/";
  if (isset($comp["path"])==True)
  {
    $uri=$comp["path"];
  }
  if (isset($comp["query"])==True)
  {
    if ($comp["query"]<>"")
    {
      $uri=$uri."?".$comp["query"];
    }
  }
  if (isset($comp["fragment"])==True)
  {
    if ($comp["fragment"]<>"")
    {
      $uri=$uri."#".$comp["fragment"];
    }
  }
  return True;
}

#####################################################################################################

    # http://news.google.com.au/news/url?sr=1&ct2=au%2F1_0_s_2_1_a&sa=t&usg=AFQjCNHp84kZxA_17wYT4j7KeBQK5tFokw&cid=52778885459115&url=http%3A%2F%2Fwww.goodgearguide.com.au%2Farticle%2F577990%2Fhow-encryption-keys-could-stolen-by-your-lunch%2F&ei=UYqIVe_ZOMjs8AW16oHYDQ&rt=SECTION&vm=STANDARD&bvm=section&did=-764385622478417134&sid=en_au%3Atc&ssid=tc&at=dt0
    #$response=wget("news.google.com.au","/news/url?sr=1&ct2=au%2F1_0_s_2_1_a&sa=t&usg=AFQjCNHp84kZxA_17wYT4j7KeBQK5tFokw&cid=52778885459115&url=http%3A%2F%2Fwww.goodgearguide.com.au%2Farticle%2F577990%2Fhow-encryption-keys-could-stolen-by-your-lunch%2F&ei=UYqIVe_ZOMjs8AW16oHYDQ&rt=SECTION&vm=STANDARD&bvm=section&did=-764385622478417134&sid=en_au%3Atc&ssid=tc&at=dt0",80);
    #var_dump($response);
    #return;

/*
2015-06-22 22:26:44 > string(1121) "HTTP/1.0 200 OK
2015-06-22 22:26:44 > Content-Type: text/html; charset=UTF-8
2015-06-22 22:26:44 > Set-Cookie: NID=68=lK22dEGidgP8irSn4JlsbdmBQFExNq5S9ykH6s2vUa0wSdznWz3of3YabfPNgmMmgarfl6rcV8cM_Ssb2gt3FOBWtNqDX55irLVHx_tKL6j2JbZLytmUhy5z7L2XvF7f;Domain=.google.com.au;Path=/;Expires=Tue, 22-Dec-2015 22:26:42 GMT;HttpOnly
2015-06-22 22:26:44 > P3P: CP="This is not a P3P policy! See http://www.google.com/support/accounts/bin/answer.py?hl=en&answer=151657 for more info."
2015-06-22 22:26:44 > Date: Mon, 22 Jun 2015 22:26:42 GMT
2015-06-22 22:26:44 > Expires: Mon, 22 Jun 2015 22:26:42 GMT
2015-06-22 22:26:44 > Cache-Control: private, max-age=0
2015-06-22 22:26:44 > X-Content-Type-Options: nosniff
2015-06-22 22:26:44 > X-Frame-Options: SAMEORIGIN
2015-06-22 22:26:44 > X-XSS-Protection: 1; mode=block
2015-06-22 22:26:44 > Server: GSE
2015-06-22 22:26:45 > Alternate-Protocol: 80:quic,p=0
2015-06-22 22:26:45 > Accept-Ranges: none
2015-06-22 22:26:45 > Vary: Accept-Encoding
2015-06-22 22:26:45 >
2015-06-22 22:26:45 > <!DOCTYPE html><html><head><meta name="referrer" content="origin"><script type="text/javascript">document.location.replace('http:\/\/www.goodgearguide.com.au\/article\/577990\/how-encryption-keys-could-stolen-by-your-lunch\/');</script><noscript><META http-equiv="refresh" content="0;URL='http://www.goodgearguide.com.au/article/577990/how-encryption-keys-could-stolen-by-your-lunch/'"></noscript></head></html>"
*/

# http://www.heraldsun.com.au/news/national/support-for-libs-actions-over-damien-mantach/story-fnjj6013-1227513636816

function get_redirected_url($from_url,$url_list="",$last_loc="",$cookies="")
{
  $url=trim($from_url);
  if ($url=="")
  {
    term_echo("get_redirected_url: empty url");
    return False;
  }
  #term_echo("  get_redirected_url: $url");
  $comp=parse_url($url);
  $host="";
  if (isset($comp["host"])==False)
  {
    if (is_array($url_list)==True)
    {
      if (count($url_list)>0)
      {
        $host=parse_url($url_list[count($url_list)-1],PHP_URL_HOST);
        $scheme=parse_url($url_list[count($url_list)-1],PHP_URL_SCHEME);
        $url=$scheme."://".$host.$url;
      }
    }
  }
  else
  {
    $host=$comp["host"];
  }
  if ($host=="")
  {
    term_echo("get_redirected_url: redirect without host: ".$url);
    return False;
  }
  $uri="/";
  if (isset($comp["path"])==True)
  {
    $uri=$comp["path"];
  }
  if (isset($comp["query"])==True)
  {
    if ($comp["query"]<>"")
    {
      $uri=$uri."?".$comp["query"];
    }
  }
  if (isset($comp["fragment"])==True)
  {
    if ($comp["fragment"]<>"")
    {
      $uri=$uri."#".$comp["fragment"];
    }
  }
  $port=80;
  if (isset($comp["scheme"])==True)
  {
    if ($comp["scheme"]=="https")
    {
      $port=443;
    }
  }
  if (($host=="") or ($uri==""))
  {
    term_echo("get_redirected_url: empty host or uri");
    return False;
  }
  $extra_headers="";
  if (isset($cookies[$host])==True)
  {
    $cookie_strings=array();
    foreach ($cookies[$host] as $key => $value)
    {
      $cookie_strings[]=$key."=".$value;
    }
    $extra_headers=array();
    $extra_headers["Cookie"]=implode("; ",$cookie_strings);
  }
  #$breakcode="return (substr(\$response,strlen(\$response)-4)==\"\r\n\r\n\");";
  $breakcode="return ((strlen(\$response)>10000) or (substr(\$response,strlen(\$response)-7)==\"</head>\"));";
  $response=wget($host,$uri,$port,ICEWEASEL_UA,$extra_headers,10,$breakcode);
  if (is_array($cookies)==True)
  {
    $new_cookies=exec_get_cookies($response);
    if (count($new_cookies)>0)
    {
      for ($i=0;$i<count($new_cookies);$i++)
      {
        $parts=explode("; ",$new_cookies[$i]);
        $keyval=explode("=",$parts[0]);
        if (count($keyval)>=2)
        {
          $key=$keyval[0];
          array_shift($keyval);
          $value=implode("=",$keyval);
          $cookies[$host][$key]=$value;
        }
      }
    }
  }
  #var_dump($response);
  $loc_header=trim(exec_get_header($response,"location",False));
  $location=$loc_header;

# <META http-equiv="refresh" content="0;URL='http://www.goodgearguide.com.au/article/577990/how-encryption-keys-could-stolen-by-your-lunch/'">

  if (($location=="") or ($location==$last_loc))
  {
    if (is_array($cookies)==False)
    {
      return $url;
    }
    else
    {
      return array("url"=>$url,"cookies"=>$cookies,"extra_headers"=>$extra_headers);
    }
  }
  else
  {
    if ($location[0]=="/")
    {
      $location=$url.$location;
    }
    if (is_array($url_list)==True)
    {
      $n=0;
      for ($i=0;$i<count($url_list);$i++)
      {
        if ($url_list[$i]==$url_list)
        {
          $n++;
        }
      }
      if ($n>1)
      {
        term_echo("get_redirected_url: redirected url already been visited twice");
        return False;
      }
      else
      {
        $list=$url_list;
        $list[]=$url;
        if (count($list)<10)
        {
          return get_redirected_url($location,$list,$loc_header,$cookies);
        }
        else
        {
          if (is_array($cookies)==False)
          {
            return $url;
          }
          else
          {
            return array("url"=>$url,"cookies"=>$cookies,"extra_headers"=>$extra_headers);
          }
        }
      }
    }
    else
    {
      $list=array($url);
      return get_redirected_url($location,$list,$loc_header,$cookies);
    }
  }
}

#####################################################################################################

function whead($host,$uri,$port=80,$agent=ICEWEASEL_UA,$extra_headers="",$timeout=20)
{
  if (check_url($host.$uri)==False) # check url against blacklist
  {
    return "";
  }
  $errno=0;
  $errstr="";
  if ($port==443)
  {
    $fp=@fsockopen("ssl://$host",443,$errno,$errstr,$timeout);
  }
  else
  {
    $fp=@fsockopen($host,$port,$errno,$errstr,$timeout);
  }
  if ($fp===False)
  {
    $msg="Error connecting to \"$host\".";
    term_echo($msg);
    return $msg;
  }
  $headers="HEAD $uri HTTP/1.0\r\n";
  $headers=$headers."Host: $host\r\n";
  if ($agent<>"")
  {
    $headers=$headers."User-Agent: $agent\r\n";
  }
  if ($extra_headers<>"")
  {
    foreach ($extra_headers as $key => $value)
    {
      $headers=$headers.$key.": ".$value."\r\n";
    }
  }
  $headers=$headers."Connection: Close\r\n\r\n";
  fwrite($fp,$headers);
  $response="";
  while (!feof($fp))
  {
    $response=$response.fgets($fp,1024);
  }
  fclose($fp);
  return $response;
}

#####################################################################################################

function wget_ssl($host,$uri,$agent=ICEWEASEL_UA,$extra_headers="")
{
  return wget($host,$uri,443,$agent,$extra_headers);
}

#####################################################################################################

function exec_get_ssl_stream_context($peer_name)
{
  $context_options=array(
    "ssl"=>array(
      "peer_name"=>$peer_name,
      "verify_peer"=>True,
      "verify_peer_name"=>True,
      "allow_self_signed"=>False,
      "verify_depth"=>5,
      "disable_compression"=>True,
      "SNI_enabled"=>True,
      "ciphers"=>"ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:AES128:AES256:HIGH:!SSLv2:!aNULL:!eNULL:!EXPORT:!DES:!MD5:!RC4:!ADH"));
  return stream_context_create($context_options);
}

#####################################################################################################

function wget($host,$uri,$port=80,$agent=ICEWEASEL_UA,$extra_headers="",$timeout=20,$breakcode="",$chunksize=1024,$check_url=True,$peer_name="")
{
  if ($check_url==True)
  {
    if (check_url($host.$uri)==False) # check url against blacklist
    {
      return "";
    }
  }
  $errno=0;
  $errstr="";
  if ($port==443)
  {
    if ($peer_name=="")
    {
      $fp=stream_socket_client("tls://".$host.":".$port,$errno,$errstr,$timeout);
    }
    else
    {
      $fp=stream_socket_client("tls://".$host.":".$port,$errno,$errstr,$timeout,STREAM_CLIENT_CONNECT,exec_get_ssl_stream_context($peer_name));
    }
  }
  else
  {
    $fp=stream_socket_client("tcp://".$host.":".$port,$errno,$errstr,$timeout);
  }
  if ($fp===False)
  {
    $msg="Error connecting to \"$host\".";
    term_echo($msg);
    return $msg;
  }
  $headers="GET $uri HTTP/1.0\r\n";
  $headers=$headers."Host: $host\r\n";
  if ($agent<>"")
  {
    $headers=$headers."User-Agent: $agent\r\n";
  }
  if ($extra_headers<>"")
  {
    foreach ($extra_headers as $key => $value)
    {
      $headers=$headers.$key.": ".$value."\r\n";
    }
  }
  $headers=$headers."Connection: Close\r\n\r\n";
  #$headers=$headers."Connection: keep-alive\r\n\r\n";
  #var_dump($headers);
  fwrite($fp,$headers);
  $response="";
  while (!feof($fp))
  {
    $response=$response.fgets($fp,$chunksize);
    if ($breakcode<>"")
    {
      if (eval($breakcode)===True)
      {
        break;
      }
    }
  }
  fclose($fp);
  return $response;
}

#####################################################################################################

function wpost($host,$uri,$port,$agent=ICEWEASEL_UA,$params,$extra_headers="",$timeout=20,$params_str=False,$dump_request=False,$peer_name="")
{
  if (check_url($host.$uri)==False) # check url against blacklist
  {
    return "";
  }
  $errno=0;
  $errstr="";
  if ($port==443)
  {
    if ($peer_name=="")
    {
      $fp=stream_socket_client("tls://".$host.":".$port,$errno,$errstr,$timeout);
    }
    else
    {
      $fp=stream_socket_client("tls://".$host.":".$port,$errno,$errstr,$timeout,STREAM_CLIENT_CONNECT,exec_get_ssl_stream_context($peer_name));
    }
  }
  else
  {
    $fp=stream_socket_client("tcp://".$host.":".$port,$errno,$errstr,$timeout);
  }
  if ($fp===False)
  {
    term_echo("Error connecting to \"$host\".");
    return;
  }
  if ($params_str==False)
  {
    $content="";
    foreach ($params as $key => $value)
    {
      if ($content<>"")
      {
        $content=$content."&";
      }
      $content=$content.$key."=".rawurlencode($value);
    }
  }
  else
  {
    $content=$params;
  }
  $headers="POST $uri HTTP/1.0\r\n";
  $headers=$headers."Host: $host\r\n";
  $headers=$headers."User-Agent: $agent\r\n";
  if (isset($extra_headers["Content-Type"])==False)
  {
    $headers=$headers."Content-Type: application/x-www-form-urlencoded\r\n";
  }
  if (isset($extra_headers["Content-Length"])==False)
  {
    $headers=$headers."Content-Length: ".strlen($content)."\r\n";
  }
  if ($extra_headers<>"")
  {
    foreach ($extra_headers as $key => $value)
    {
      $headers=$headers.$key.": ".$value."\r\n";
    }
  }
  $headers=$headers."Connection: Close\r\n\r\n";
  $request=$headers.$content;
  #var_dump($request);
  if ($dump_request==True)
  {
    var_dump($request);
  }
  fwrite($fp,$request);
  $response="";
  while (!feof($fp))
  {
    $response=$response.fgets($fp,1024);
  }
  fclose($fp);
  return $response;
}

#####################################################################################################

function strip_headers($response)
{
  $delim="\r\n\r\n";
  $i=strpos($response,$delim);
  if ($i===False)
  {
    return False;
  }
  return substr($response,$i+strlen($delim));
}

#####################################################################################################

function extract_raw_tag($html,$tag)
{
  $delim1="<$tag";
  $delim2=">";
  $delim3="</$tag>";
  $i=strpos(strtolower($html),strtolower($delim1));
  if ($i===False)
  {
    return False;
  }
  $html=substr($html,$i+strlen($delim1));
  $i=strpos($html,$delim2);
  if ($i===False)
  {
    return False;
  }
  $html=substr($html,$i+strlen($delim2));
  $i=strpos(strtolower($html),strtolower($delim3));
  if ($i===False)
  {
    return False;
  }
  return substr($html,0,$i);
}

#####################################################################################################

function extract_void_tag($html,$tag)
{
  $delim1="<$tag";
  $delim2=">";
  $html=extract_text($html,$delim1,$delim2);
  if ($html===False)
  {
    return False;
  }
  if (substr($html,strlen($html)-1,1)=="/")
  {
    $html=substr($html,0,strlen($html)-1);
  }
  return trim($html);
}

#####################################################################################################

function strip_first_tag(&$html,$tag)
{
  $lhtml=strtolower($html);
  $i=strpos($lhtml,"<$tag");
  $end="</$tag>";
  $j=strpos($lhtml,$end);
  if (($i===False) or ($j===False))
  {
    return False;
  }
  $html=substr($html,0,$i).substr($html,$j+strlen($end));
  return True;
}

#####################################################################################################

function extract_meta_content($html,$name,$key="name")
{
  # <meta name="description" content="Researchers have made a breakthrough in blah blah blah." id="metasummary" />
  $lhtml=strtolower($html);
  $lname=strtolower($name);
  $parts=explode("<meta ",$lhtml);
  array_shift($parts);
  if (count($parts)==0)
  {
    return False;
  }
  $result="";
  for ($i=0;$i<count($parts);$i++)
  {
    $n=extract_text($parts[$i],"$key=\"","\"");
    if ($n===False)
    {
      continue;
    }
    if ($n<>$lname)
    {
      continue;
    }
    $result=extract_text($parts[$i],"content=\"","\"");
    break;
  }
  if ($result=="")
  {
    return False;
  }
  $i=strpos($lhtml,$result);
  if ($i===False)
  {
    return False;
  }
  $result=substr($html,$i,strlen($result));
  return $result;
}

#####################################################################################################

function strip_comments(&$html)
{
  $i=strpos($html,"<!--");
  $end="-->";
  $j=strpos($html,$end);
  if (($i===False) or ($j===False))
  {
    return False;
  }
  $html=substr($html,0,$i).substr($html,$j+strlen($end));
  strip_comments($html);
  return True;
}

#####################################################################################################

function strip_all_tag(&$html,$tag)
{
  while (strip_first_tag($html,$tag)==True)
  {
  }
}

#####################################################################################################

function exec_get_headers($response)
{
  $delim="\r\n\r\n";
  $i=strpos($response,$delim);
  if ($i===False)
  {
    return False;
  }
  return substr($response,0,$i);
}

#####################################################################################################

function exec_get_header($response,$header,$extract_headers=True)
{
  if ($extract_headers==True)
  {
    $headers=exec_get_headers($response);
  }
  else
  {
    $headers=$response;
  }
  $lines=explode("\n",$headers);
  for ($i=0;$i<count($lines);$i++)
  {
    $line=trim($lines[$i]);
    $parts=explode(":",$line);
    if (count($parts)>=2)
    {
      $key=trim($parts[0]);
      array_shift($parts);
      $value=trim(implode(":",$parts));
      if (strtolower($key)==strtolower($header))
      {
        return $value;
      }
    }
  }
  return "";
}

#####################################################################################################

function exec_get_cookies($response)
{
  $header="Set-Cookie";
  $values=array();
  $lines=explode("\n",exec_get_headers($response));
  for ($i=0;$i<count($lines);$i++)
  {
    $line=trim($lines[$i]);
    $parts=explode(":",$line);
    if (count($parts)>=2)
    {
      $key=trim($parts[0]);
      array_shift($parts);
      $value=trim(implode(":",$parts));
      if (strtolower($key)==strtolower($header))
      {
        $values[]=$value;
      }
    }
  }
  return $values;
}

#####################################################################################################

function extract_get($url,$name)
{
  $params=array();
  parse_str(parse_url($url,PHP_URL_QUERY),$params);
  if (isset($params[$name])==True)
  {
    return $params[$name];
  }
  else
  {
    return False;
  }
}

#####################################################################################################

function html_decode($text)
{
  return html_entity_decode($text,ENT_QUOTES,"UTF-8");
}

#####################################################################################################

?>
