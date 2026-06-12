<?php

global $dbx;
  if($channels > 9 && $channels != 19){
    $spammer=strstr($cinput," ");
    $spammer=ucwords(trim($spammer));
    $query="Select Id,name from Players where name='$spammer'";
    if(($result=mysqli_query($dbx, $query))==TRUE && mysqli_num_rows($result)==1){
      extract(mysqli_fetch_array($result),EXTR_PREFIX_ALL,"tgt");
      $query="Insert into Messages values($tgt_Id,'Help','Please click <a href=\'http://www.geocities.com/shimlarpguideline\' target=\'_blank\'>here</a> to learn more about Roleplay.',6,now())";
      mysqli_query($dbx, $query);
      $chatresult="Links sent to $tgt_name";
    }else {
      $chatresult="Player $spammer does not exist";
    }
    }else {
      $chatresult="You do not have this power!";
    }
?>
