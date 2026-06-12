<?php
include 'constvars.inc'; 
init_dbx();
$query	="Select Id,name from Players where mhd = 0 and channels = 66";
$result	=mysqli_query($dbx, $query);
$c1	=mysqli_num_rows($result);
for($i=0;$i<$c1;$i++) {
	$row	=mysqli_fetch_row($result);
	$spId	=$row[0];
	$spName	=$row[1];
	$query	="update Players set banned=100, channels=0 where Id = $spId";
	mysqli_query($dbx, $query);
	$query	="insert into Modactions values('Lord A', '$spName', 'Perma2ban', now())";
	mysqli_query($dbx, $query);
};
close_dbx();
?>