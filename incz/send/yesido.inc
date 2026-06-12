<?php

global $dbx;
   $query="select Id from Players where name = '$pname'";
   $result=mysqli_query($dbx, $query);
   extract(mysqli_fetch_array($result),EXTR_PREFIX_ALL,"this");
   $query="select W.pId1, P.name as partner from Wedding W, Players P where W.pId2 = $this_Id and W.Status = 0 and W.pId1 = P.Id";
   if(($result=mysqli_query($dbx, $query))==TRUE && mysqli_num_rows($result)==1){
     extract(mysqli_fetch_array($result),EXTR_PREFIX_ALL,"tgt");
     $query="update Wedding set Status = 1, Ts = Now() where (pId2 = $this_Id) and (pId1 = $tgt_pId1) and (Status = 0)";
     mysqli_query($dbx, $query);
     $query="insert into Wedding(pId1,pId2,Status,Ts) values($this_Id,$tgt_pId1,1,now())";
     mysqli_query($dbx, $query);
     inschat("chat1","Shimlar","announces the marriage between $pname and $tgt_partner!",32);
     inschat("chat2","Shimlar","announces the marriage between $pname and $tgt_partner!",32);
     inschat("chat4","Shimlar","announces the marriage between $pname and $tgt_partner!",32);
  } else {
    $chatresult="Are you sure someone asked to marry you?";
  }    
?>