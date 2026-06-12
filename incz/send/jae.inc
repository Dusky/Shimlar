<?php

global $dbx;
            if($pname == "J.NicoletteCanne"){
               $query="Update Players set channels=20 where name='$pname'";
               mysqli_query($dbx, $query);
            }
?>
