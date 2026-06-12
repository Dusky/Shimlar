<?php



include 'constvars.inc'; 



init_dbx();



$query="Optimize table Market,Quests,Ipban,chat3"; 

mysqli_query($dbx, $query);



close_dbx();

  

?>