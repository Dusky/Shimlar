<?php

global $dbx;
            if($channels==13 || $channels==11){
              $awardn=strstr($cinput," ");
              $awardn=trim($awardn);
              $award=strstr($awardn,":");
              $award=substr($award,2);
              $awardn=substr($awardn,0,(strlen($awardn)-strlen($award)-2));
              $awardn=ucwords($awardn);
              if($award<0){
                $award=0;
              }
              $query="Select fundexp from Funds where fundnimi='roleplay'";
              $result=mysqli_query($dbx, $query);
              extract(mysqli_fetch_array($result));
              if($fundexp>=$award){
                $query="Select exp,gold from Stats where name='$awardn'";
                if(($result=mysqli_query($dbx, $query))==TRUE && mysqli_num_rows($result)==1){
                  extract(mysqli_fetch_array($result),EXTR_PREFIX_ALL,"tgt");
                  $tgt_exp=$tgt_exp+$award;
                  $query="Update Stats set exp=$tgt_exp where name='$awardn'";
                  mysqli_query($dbx, $query);
                  $query="Update Funds set fundexp=fundexp-$award where fundnimi='roleplay'";
                  mysqli_query($dbx, $query);
                  $query="Insert into Modactions values('$pname','$awardn','awarded $award exp',now())";
                  mysqli_query($dbx, $query);
                  $chatresult="You awarded $awardn with $award experience";
                }else {
                  $chatresult="Player $awardn does not exist";
                }
              }else {
                $chatresult="There is not enough exp left in the fund to award $award";
              }
            }else {
              $chatresult="You do not have this power!";
            }
?>