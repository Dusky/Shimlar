global $dbx;
          }else if(tsub($cinput,5)=="duck "){
            if($pname=="Toshax"|| $pname=="Kiymetli"){
              $spammer=strstr($cinput," ");
              $spammer=ucwords(trim($spammer));
              $query="Select Id from Players where name='$spammer'";
              if(($result=mysqli_query($dbx, $query))==TRUE && mysqli_num_rows($result)==1){
                extract(mysqli_fetch_array($result),EXTR_PREFIX_ALL,"tgt");
                $query="Insert into Messages values($tgt_Id,'Shimlar','duck shake',26,now())";
                mysqli_query($dbx, $query);
                $chatresult="$spammer is now shaking :)";
              }else {
                $chatresult="Player $spammer does not exist";
              }
            }else {
              $chatresult="You do not have this power!";
            }
          }else if(tsub($cinput,7)=="duckall"){
            if($pname=="Toshax"|| $pname=="Kiymetli"){
              inschat("chat1",'Shimlar','all shake',26);
              $chatresult="Everyone is now shaking :P ph33r!";
            }else {
              $chatresult="You do not have this power!";
            }