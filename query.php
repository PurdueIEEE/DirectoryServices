<?php
function find_directory($input) {
	$ds = ldap_connect("ped.purdue.edu");
	if (!$ds) {
		echo "<h4>Unable to connect to LDAP server</h4>";
	}
	$r = ldap_bind($ds);
	$sr = ldap_search($ds, "dc=purdue,dc=edu", ("uid=" . $input));
	$info = ldap_get_entries($ds, $sr);
	ldap_close($ds);
	return $info;
}
if ($_GET["pretty"]) { echo "<pre>"; }
echo json_encode(find_directory(htmlspecialchars($_GET["id"])));
if ($_GET["pretty"]) { echo "</pre>"; }
?>
