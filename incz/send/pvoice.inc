<?php

global $dbx;

  $spammer=strstr($cinput," ");
  $spammer=strtolower(trim($spammer));
  $query="Select name from Players where name='$spammer'";
  if(($result=mysqli_query($dbx, $query))==TRUE && mysqli_num_rows($result)==1){
    $cookieValue = $_COOKIE["pIgnore"];
    if(!(strstr($cookieValue,$spammer))){
      $spammer=ucwords(trim($spammer));
      $chatresult="You are not ignoring $spammer";
    } else {
      //$query="Insert into Messages values('$spammer','$pname','is not ignoring you anymore',26,now())";
      //Mysql_query($query);
      $index = strpos ($cookieValue, $spammer);
      $newValue = substr($cookieValue,0,$index);
      $addIndex = $index + strlen ($spammer);
      $addValue = substr($cookieValue, $addIndex);
      $newCookieValue = $newValue.$addValue;
      print("setIgn('$newCookieValue');");
      print("\r\n");
      $spammer=ucwords(trim($spammer));
      $chatresult="You are no longer ignoring $spammer.";
    }
  } else {
    $spammer=ucwords(trim($spammer));
    $chatresult="Player $spammer does not exist";
  }
  
?>
