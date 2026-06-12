<?php
include 'constvars.inc'; 
init_dbx();

$query="Optimize Table Messages,chat1,chat2,Transfers"; 
mysqli_query($dbx, $query);

close_dbx();
  
?>