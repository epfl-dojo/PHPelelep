<?php
search($_POST["s"]);
function search($s, $debug=false) {
   $ds=ldap_connect("ldap.epfl.ch");
   if ($ds) {
      $r=ldap_bind($ds);
      $dn = "o=epfl, c=ch";
      // Tricks to avoid services `M000000`
      $filter="(&(displayname=*" . $s . "*)(|(sn=" . $s . "*)(givenname=" . $s . "*)))";
      $justthese = array("ou", "sn", "givenname", "displayname", "mail", "memberOf","uniqueIdentifier"); //'';//
      $sr=ldap_search($ds,$dn, $filter, $justthese);
      $ldapentries = ldap_get_entries($ds, $sr);
      if ($debug) { echo "<pre>"; print_r($ldapentries); echo "</pre>"; }
      // Create a user-friendly array
      $people = array();
      foreach($ldapentries as $lek => $lev) {
         $sciper = $lev['uniqueidentifier'][0];
         if ($sciper) {
            if (!array_key_exists($sciper, $people)) {
               $people[$sciper] = array(
                  "sciper" => $sciper,
                  "displayname" => $lev['displayname'][0],
                  "sn" => $lev['sn'][0],
                  "givenname" => $lev['givenname'][0],
                  "groups" => isset($lev['memberof']) ? $lev['memberof'] : '',
                  "units" => array()
               );
            }
            $people[$sciper]['units'][] = $lev['ou'][0];
         }
      }

      if ($debug) { echo "<pre>"; print_r($people); echo "</pre>"; }
      echo json_encode($people);
      // Close
      ldap_close($ds);
   } else {
      echo '<h4>Impossible de se connecter au serveur LDAP.</h4>';
   }
}








/**
 * When querying the EPFL ldap on posixAccount it returns 25754 entries
 * To save some time, this function generate a json file with all the entries
 *
 *              /!\ PEOPLE CACHE GENERATE A 15MB TXT FILE
*/
function generatePeopleCache($force=true) {
   $people_cache_json_file = "/var/www/html/test/PHPelelep/cache/json_people_cache.txt" ;
   $people_cache_file = "/var/www/html/test/PHPelelep/cache/people_cache.txt" ;

   if (!$force){
     $decent_time_to_renew_cache = 3600*24; // one day
     // Only test one file...
     if (file_exists($people_cache_json_file)) {
      if ($decent_time_to_renew_cache >= (time()-filemtime($people_cache_json_file))) {
        echo 'Unit cache is too recent: '  . (time()-filemtime($people_cache_json_file)) .' secondes... Be kind with EPFL ldap please :-p';
      //die();
      }
     }
   }

   echo "ldapsearch -v -x -LLL -h ldap.epfl.ch -b 'c=ch' \"(&(objectClass=posixAccount))\"'<br />";
   $ds=ldap_connect("ldap.epfl.ch");
   if ($ds) {
     $r=ldap_bind($ds);
     $dn = "o=epfl, c=ch";
     $filter="(&(objectclass=posixAccount))";
     //$filter="(&(objectclass=organizationalunit)(ou=igm-ge))";
     $justthese = '';//array("ou", "sn", "givenname", "mail");
     $sr=ldap_search($ds,$dn, $filter);
     echo 'Get  ' . ldap_count_entries($ds,$sr) . " entries";
     //Fatal error: Allowed memory size of 268435456 bytes exhausted (tried to allocate 72 bytes) in /var/www/STIupload/application/controllers/api.php on line 451
     ini_set('memory_limit', '1024M');
     $info = ldap_get_entries($ds, $sr);
     $people = array();
     for ($i=0; $i<$info["count"]; $i++) {
       $dn_ou = explode(",", $info[$i]['dn']);
       // remove c=ch
       array_pop($dn_ou);
       // remove o=epfl
       array_pop($dn_ou);
       // remove itself
       array_shift($dn_ou);
       $parents = array();
       foreach ($dn_ou as $key => $val) {
         $parents[] = substr($val, 3, strlen($val));
       }
       if (isset($info[$i]['labeleduri'][0])) {
         $url = strstr($info[$i]['labeleduri'][0], ' ', true);
         $url_desc = trim(strstr($info[$i]['labeleduri'][0], ' '));
       } else {
         $url = $url_desc = '';
       }
       if (isset($info[$i]['uidnumber'][0])) {
         $people[$info[$i]['uidnumber'][0]] = array (
           'firstname' => isset($info[$i]['givenname'][0]) ? $info[$i]['givenname'][0] : '',
           'lastname' => isset($info[$i]['sn'][0]) ? $info[$i]['sn'][0] : '',
           'displayname' => isset($info[$i]['displayname'][0]) ? $info[$i]['displayname'][0] : '',
           'uidnumber' => $info[$i]['uidnumber'][0],
           'uid' => $info[$i]['uid'],
           'cn' => $info[$i]['cn'][0],
           'unit' => $info[$i]['ou'][0],
           'uniqueidentifier' => $info[$i]['uniqueidentifier'][0],
           'gidnumber' => isset($info[$i]['gidnumber'][0]) ? $info[$i]['gidnumber'][0] : '',
           'accountingnumber' => isset($info[$i]['accountingnumber'][0]) ? $info[$i]['accountingnumber'][0] : '',
           'description' => isset($info[$i]['description'][0]) ? $info[$i]['description'][0] : '',
           'description_en' => isset($info[$i]['ou;lang-en'][0]) ? $info[$i]['ou;lang-en'][0] : '',
           //'uri' => $info[$i]['labeleduri'][0],
           'url' => $url,
           'url_desc' => $url_desc,
           'dn' => $info[$i]['dn'],
           'first_parent' => isset($parents[0]) ? $parents[0] : '',
           'parents' => $parents,
         );
       }
     }
     // create json cache
     $jpeople = array();
     foreach($people as $uk => $uv) {
       $jpeople[$uk] = $uv['lastname'].' '. $uv['firstname'];
     }
     // sort by last name
     asort($jpeople);

     // reorganise array for correct json format
     $jau = array();
     foreach ($jpeople as $key => $value) {
       $jau[] = array('id' => $key, 'text' => $value);
     }

     $json_people = json_encode($jau);
     print "JSON output:<br /><pre>";
     print_r($json_people);

     // create cache file
     $cache = fopen($people_cache_json_file, 'r+');
     fputs($cache, $json_people);
     fclose($cache);

     // Create serialized cache
     print "PHP output:<pre>";
     print "<pre>";
     print_r($people);
     print "</pre>";

     // create cache file
     $cache = fopen($people_cache_file, 'r+');
     fputs($cache, serialize($people));
     fclose($cache);

     // Close
     ldap_close($ds);
   } else {
       echo '<h4>Impossible de se connecter au serveur LDAP.</h4>';
   }
}
