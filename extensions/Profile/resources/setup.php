<?php
	$dbr = wfgetDB( DB_SLAVE );
    $var = $dbr->selectFieldValues(
       'user',                  
       'user_name',             
       '',                      
       __METHOD__,              
       array(),                 
       array()                  
     );
