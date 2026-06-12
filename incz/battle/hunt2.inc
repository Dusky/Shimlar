<?php

global $dbx;
      $gold=$gold+100000;
      $query="Select Players.Id from Players,Stats where Players.Id=Stats.Id and Players.loc_zone=$loc_zone and Stats.status=999";
      $result=mysqli_query($dbx, $query);
      $c2=mysqli_num_rows($result);
      for($i=0;$i<$c2;++$i){
  			$row=mysqli_fetch_row($result);
  			$bks=$row[0];
  			$query="Insert into Messages values($bks,'$tgt_bname',100000,21,now())";
  			mysqli_query($dbx, $query);
      }
      $query="Update Stats set banked=banked+100000,status=0,e_health=0 where status=999";
      mysqli_query($dbx, $query);
      $query="Update Zones set zhunt=0 where znum=$loc_zone";
      mysqli_query($dbx, $query);$x=0;
      for($i=0;$i<30;++$i){
  			if($x==0 && $invlist[$i]==0){
    			$x=$i+1;
  			}
      }
      if($x==0){
  			ep2(24);
      }else{
  			$invlist[$x-1]=$tgt_eq;
  			$sqlix="i$x";
  			sqlI($sqlix,$tgt_eq,$Id);
  			$special1=3;
  			$query="Insert into Messages values($Id,'$tgt_bname',$tgt_eq,22,now())";
  			mysqli_query($dbx, $query);
      }
      inschat("chat1",$pname,"$tgt_bname",28);
?>