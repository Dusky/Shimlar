<?php

global $dbx;
          $query="Select cbonus,cint,cint2 from Clans where cid=$clan";
          $result=mysqli_query($dbx, $query);
          if($result==TRUE and mysqli_num_rows($result)>0){
            extract(mysqli_fetch_array($result));
            if($cint==$Id || $cint2==$Id){
              $clx=1.25;
            }else{
              $clx=1;
            }
            $query="Select kd_mine,kd_misc,kd_tax from Kingdoms where kd_clan=$clan";
            $result=mysqli_query($dbx, $query);
            if($result==TRUE and mysqli_num_rows($result)>0){
              extract(mysqli_fetch_array($result));
            }
          }else {
            $cbonus=0;$clx=1;
          }

?>