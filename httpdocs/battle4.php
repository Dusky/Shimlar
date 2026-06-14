<?php
include '../incz/constvars.inc';
include '../incz/batx4.inc'; 
global $HTTP_REFERER;
$_game_url = getenv('GAME_URL') ?: 'http://localhost:9090/';
$position = strpos($_SERVER['HTTP_REFERER'] ?? '', $_game_url);
if ( $position !== false || ($_SERVER['HTTP_REFERER'] ?? '') === '' ) {
  init_dbx();
  sleep(1);
  $randomz = rand(1,50);
  if ($randomz != 25) {
  	print "<script language=\"javascript\" src=\"upd8.js\"></script>";
  } else {
  	print "<script language=\"javascript\">";
  	include 'upd8.js';
  	print "</script>";
  }
  print "<script language=\"javascript\">";
  batproc($_POST["l"],$_POST["p"],$_POST["a"],$_POST["k"],$_POST["m"]);
  print "</script>";
  close_dbx();
} else {
  logText("battle4.php");
  logText("Player = ".getName($_POST["l"]));
  logText("password = ".$_POST["p"]);  
  logText("REMOTE_ADDR = ".$_SERVER['REMOTE_ADDR']);
  logText("HTTP_REFERER = ".$_SERVER['HTTP_REFERER']);
  ep2 (59);
  if (is_numeric($_POST["l"]) != "") {
  init_dbx();
    $spammer=ucwords(trim(getName($_POST["l"])));
    $query="update Players set banned=100 where Id = ".$_POST["l"];
    mysqli_query($dbx, $query);
    $query="Insert into Modactions values('HTTP Autoban','$spammer','accessing from : ".$_SERVER['HTTP_REFERER']."',now())";
    mysqli_query($dbx, $query);
    close_dbx();
  }
}
?>
