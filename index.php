<?php
/*
 * index.php
 * This file is part of lamos
 * 
 * Copyright 2008-2009 Michel Messerschmidt
 * 
 * This program is free software: you can redistribute it and/or modify 
 * it under the terms of the GNU General Public License as published by 
 * the Free Software Foundation, either version 3 of the License, or (at 
 * your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful, but 
 * WITHOUT ANY WARRANTY; without even the implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
 * See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License 
 * along with this program in the file LICENSE. 
 * If not, see http://www.gnu.org/licenses/
 */

error_reporting(0);
ini_set("log_errors", "1");
ini_set("display_errors", "0");

// include configuration
require_once("config/lms_config.inc.php");

if(DEBUG === TRUE) {
	// be verbose
	error_reporting(E_ALL | E_STRICT);
	ini_set("display_errors", "1");
}

// include localization strings
// as a side effect the array $l10n is defined inside the included file
require_once("locale/".LMS_LANG.".inc.php");

// set some global constants
define("THIS_FILE", $_SERVER['SCRIPT_FILENAME']);
// determine used protocol from request headers
$proto = "http";
if(isset($_SERVER["HTTPS"]) and $_SERVER["HTTPS"] == "on") {
	$proto .= "s";
}
// create correct base URL
$temp_url = preg_replace('-^https?-', '', LMS_URL);
$temp_url = $proto.$temp_url;
if(substr($temp_url, -1, 1) != '/') {
	$temp_url .= '/';
}
define("MAIN_URL", $temp_url.'index.php');
define("REDIR_URL", $temp_url.'count.php');
define("THIS_URL", $proto.'://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);

// include shared code
require_once("linklist.inc.php");
require_once("mm_shared.inc.php");

// get database handle
$db = mm_db_connect(DB_HOST, DB_USER, DB_PASS, DB_DB, DB_TYPE);
if(!$db) {
	print_begin(LMS_NAME);
	print_error($l10n['err_db_conn']);
	print_end();
	exit;
}

// get id of authenticated user (fetched from http environment)
$userid = get_userid($_SERVER['REMOTE_USER'], $db);
// write access must be authorized (public user id "0" is not allowed here)
$edit_ok = ($userid == 0) ? FALSE : TRUE;

$cats = array();
$cats[0]["depth"] = -1;
// this function fills the global array $cats with all categories
build_cat_tree($cats, 0, 0, $userid, $db);

// output all configuration parameters for debugging
if(DEBUG === TRUE) {
	print "<pre>\n\n\n\n";
	print "proto = $proto\ntemp url = $temp_url\nTHIS = ".THIS_URL."\nMAIN = ".MAIN_URL."\nREDIR = ".REDIR_URL."\n";
	print "userid = $userid\nwrite access = ".($edit_ok ? "TRUE" : "FALSE")."\n";
	print_r($_POST);
	//print_r($cats);
	print "</pre>\n";
}

print_begin(LMS_NAME);
print_nav($cats, $edit_ok);
if(isset($_GET['added']) and $_GET['added'] == "true") {
	print_msg($l10n['txt_added_entry']);
	print '    <div class="hr2ns"></div>'."\n";
}

// print all categories recursively
$root = 0;
if(isset($_POST['searchkey']) and $_POST['searchkey'] != "") {
	// sanitize string for use in sql queries
	$search_term = mm_db_filter_input($db, $_POST['searchkey'], DB_TYPE);
	print_search($search_term, $userid, $cats, $db);
} elseif(isset($_POST['showtree'])) {
	$root = intval($_POST['showtree']);
	if($root == -2) {
		print_top_links(TOPLIST, $userid, $cats, $db);
	} elseif($root == -1) {
		print_private($userid, $db);
	} elseif($root >= 0) {
		print_menulist($root, $cats);
		print_cat($root, $userid, $cats, $db);
	}
} elseif(isset($_GET['show']) and $_GET['show'] == "private") {
	print_private($userid, $db);
} elseif(isset($_GET['show']) and intval($_GET['show']) >= 0) {
	$root = intval($_GET['show']);
	print_menulist($root, $cats);
	print_cat($root, $userid, $cats, $db);
} else {
	print_top_links(TOPLIST, $userid, $cats, $db);
}

print_end();

mm_db_close($db, DB_TYPE);

?>
