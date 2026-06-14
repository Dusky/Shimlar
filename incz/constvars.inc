<?php

/*$filename = "/home/shimlar/critical.inc";
include($filename);*/

$shim_host = getenv('DB_HOST') ?: 'localhost';
$shim_user = getenv('DB_USER') ?: 'shimlar';
$shim_pass = getenv('DB_PASS') ?: 'shimlar';
$shim_db   = getenv('DB_NAME') ?: 'shimlar_game';

function init_dbx(){
  global $dbx,$shim_host,$shim_user,$shim_pass,$shim_db;
  $dbx=mysqli_connect($shim_host,$shim_user,$shim_pass,$shim_db);
  if (!$dbx) {
  	die('Could not connect: ' . mysqli_connect_error());
  }
}
function close_dbx(){
  global $dbx;
  mysqli_close($dbx);
}
function ep2($errnum){
  print "eX($errnum);\n";
}
function ityper($xcode){
  $x_type=($xcode-($xcode%1000000))/1000000;
  return $x_type;
}
function inumer($xcode){
  $x_num=(($xcode%1000000)-($xcode%10000))/10000;
  return $x_num;
}
function inumdrop($xcode) {
	$xcode=$xcode-($xcode%5);
	return $xcode;
}
function firstGem($xcode) {
	$xcode=$xcode%100;
	return $xcode;
}
function secondGem($xcode) {
	$xcode=(($xcode%10000)-firstGem($xcode))/100;
	return $xcode;
}
function inumdropRandom($xcode) {
	$xcode=($xcode-($xcode%5))/5;
	if($xcode==0) {
		return 0;
	} else {
		return (rand(0,$xcode+1)*5);
	}
}
function tarkista($jono){
  if(strlen($jono)==0)
    return 1;
  if(strlen($jono)>100)
    return 0;
  if(strstr($jono,"\""))
    return 0;
  if(strstr($jono,"'"))
    return 0;
  if(strstr($jono,"\\"))
    return 0;
  if(strstr($jono,"<"))
    return 0;
  if(strstr($jono,">"))
    return 0;
  if(strstr($jono,"&"))
    return 0;
  for($i=0;$i<strlen($jono);$i++){
    $xc=ord($jono[$i]);
    if($jono[$i]!=" " && ($xc<39||$xc>122)){
      return 0;
    }
  }
return 1;
}
function inschat($xchat,$xname,$xtxt,$xtype){
  global $dbx;
  $query="insert into $xchat (msgname,msgtxt,msgtype) values(\"$xname\",\"$xtxt\",\"$xtype\")";
  mysqli_query($dbx, $query);
}
function sqlP($sqlvar,$sqlvalue,$sqlid){
  global $dbx;
  $query="update Players set $sqlvar='$sqlvalue' where Id = $sqlid";
  mysqli_query($dbx, $query);
}
function sqlS($sqlvar,$sqlvalue,$sqlid){
  global $dbx;
  $query="update Stats set $sqlvar='$sqlvalue' where Id = $sqlid";
  mysqli_query($dbx, $query);
}
function sqlI($sqlvar,$sqlvalue,$sqlid){
  global $dbx;
  $query="update Inventory set $sqlvar='$sqlvalue' where Id = $sqlid";
  mysqli_query($dbx, $query);
}
function sqlH($sqlvar,$sqlvalue,$sqlloc){
  global $dbx;
  $query="update Housing set $sqlvar='$sqlvalue' where h_loc=$sqlloc";
  mysqli_query($dbx, $query);
}
function sqlC($sqlvar,$sqlvalue,$sqlclan){
  global $dbx;
  $query="update Clans set $sqlvar='$sqlvalue' where cid=$sqlclan";
  mysqli_query($dbx, $query);
}
function sqlK($sqlvar,$sqlvalue,$sqlloc){
  global $dbx;
  $query="update Kingdoms set $sqlvar='$sqlvalue' where kd_loc=$sqlloc";
  mysqli_query($dbx, $query);
}
function clanp($clvl){
  $cp = 0; 
  switch (true) {
  case ($clvl<100):
    $cp = 0;
    break;
  case ($clvl<300):
    $cp = 1;
    break;
  case ($clvl<500):
    $cp = 2;
    break;
  case ($clvl<700):
    $cp = 4;
    break;
  case ($clvl<900):
    $cp = 8;
    break;
  case ($clvl<1100):
    $cp = 15;
    break;
  case ($clvl<1300):
    $cp = 25;
    break;
  case ($clvl<1500):
    $cp = 35;
    break;
  case ($clvl<1750):
    $cp = 45;
    break;
  case ($clvl<2000):
    $cp = 55;
    break;
  case ($clvl<2250):
    $cp = 65;
    break;
  case ($clvl<2500):
    $cp = 75;
    break;
  default :
    $cp = 100;
    break;
  }
  return $cp;
}

function accn($sqlid){
  global $dbx;
  $query="Select accx from Inventory where Id = $sqlid";
  $result=mysqli_query($dbx, $query); $row=mysqli_fetch_row($result); $accx=$row[0];
  if($accx>0){
    $query="Select i$accx from Inventory where Id = $sqlid";
    $result=mysqli_query($dbx, $query); $row=mysqli_fetch_row($result); $accs=$row[0];
    return $accs;
  }else{
    return 0;
  }
}

function getPartialIp($ip) 
{
	return substr($ip,0,strrpos($ip,".")+1)."%";
}

function getName($byid){
  global $dbx;
  if ($byid == 0){
  	$playername = "";
  } else {
  	$query="select name from Players where Id = $byid";
 	$result=mysqli_query($dbx, $query);
 	if (mysqli_num_rows($result)==1) {
 		$row=mysqli_fetch_row($result);
 		$playername=$row[0];
 	} else {
 		$playername="";
 	} 	
 }
 return $playername;
}

function getPKTag($byid){
  global $dbx;
	$pktag="";
	$query="select pktag from PKTag where Id = $byid";
  $result=mysqli_query($dbx, $query);
  if(mysqli_num_rows($result)==1){
  	$row=mysqli_fetch_row($result);
  	$pktag = $row[0];
  }
  return $pktag;
}

function getIdByLogin($bylogin){
  global $dbx;
  $bylogin = mysqli_real_escape_string($dbx, trim($bylogin));
  if ($bylogin == ""){
  	$playerid = -1;
  } else {
  	$query="select Id from Players where login = '$bylogin'";
 	$result=mysqli_query($dbx, $query);
 	if (mysqli_num_rows($result)==1) {
 		$row=mysqli_fetch_row($result);
 		$playerid=$row[0];
 	} else {
 		$playerid=-1;
 	} 	
 }
 return $playerid;
}

function logText($logtext) {
  writeLogFile($logtext,"logdata.txt");
}

function debugText($logtext) {
  writeLogFile($logtext,"debugdata.txt");
}

function kingdomGifts($logtext) {
	writeLogFile($logtext,"kingdomgifts.txt");
}

function writeLogFile($logtext, $filename) {
   $logDir = getenv('LOG_DIR') ?: '/var/log/shimlar';
   if (!is_dir($logDir)) {
       @mkdir($logDir, 0777, true);
   }
   $fp = fopen($logDir."/".$filename,"a");
   if ($fp)
   {
      flock($fp,2);
      $nl = "\r\n";
      $tab = "    ";
      fputs ($fp, date("d.m.y H:i:s"));
      fputs ($fp, $tab);
      fputs ($fp, $logtext);
      fputs ($fp, $nl);
      flock($fp,3);
      fclose($fp);
   }
}

function checkCookie($pname)
{
    $cookieName = $_COOKIE["loginName"];
    if (trim(strtolower($cookieName)) != trim(strtolower($pname))){
       writeLogFile("cookieLogin =".$cookieName,"cookiedata.txt");
       writeLogFile("Pname       =".$pname,"cookiedata.txt");    	
    }
}
function is_url($url) { 
    if (!preg_match('#^http\\:\\/\\/[a-z0-9\-]+\.([a-z0-9\-]+\.)?[a-z]+#i', $url)) { 
        return false; 
    } else { 
        return true; 
    } 
}

?>
