<?php

global $dbx;
   $query="select PKill from Stats where Id = $Id";
   $result=mysqli_query($dbx, $query);
   extract(mysqli_fetch_array($result),EXTR_PREFIX_ALL,"this");
   if ($this_PKill == 0) {
    $chatresult="You did not kill anyone yet.";
   } else {
    $chatresult="You have killed $this_PKill player(s).";
   }
?>