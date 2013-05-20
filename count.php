<?php
/*
 * count.php
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

// include shared code
require_once("linklist.inc.php");


if(!(isset($_GET['link']) and intval($_GET['link']) > 0)) {
	echo "<html><head></head><body><p>".
		$l10n['err_no_link_id']."</p></body></html>";
	exit;
}
$linkid = intval($_GET['link']);
$db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_DB);
if(!$db or !mysqli_set_charset($db, "utf8")) {
	echo "<html><head></head><body><p>".
		$l10n['err_db_conn']."</p></body></html>";
	exit;
}
// get id of authenticated user (fetched from http environment)
$userid = get_userid($_SERVER['REMOTE_USER'], $db);

$q = "SELECT linkcount, private from ".T_STAT_LINK.
	" WHERE linkid = ? AND userid = ?";
$db_stmt = mysqli_stmt_init($db);
if(!(mysqli_stmt_prepare($db_stmt, $q) and
	mysqli_stmt_bind_param($db_stmt, "ii", $linkid, $userid) and
		mysqli_stmt_execute($db_stmt) and
			mysqli_stmt_bind_result($db_stmt, $r_linkcount, $r_private))
) {
	print_page($l10n['err_db_query'], $q);
}
if(!mysqli_stmt_fetch($db_stmt)) {
	print_page($l10n['err_no_data'], $q."<br/>linkid=$linkid, userid=$userid");
}
mysqli_stmt_close($db_stmt);

$q_url = "SELECT url, catid from ".T_LINK." where linkid = ?";
$db_stmt_url = mysqli_stmt_init($db);
if(!(mysqli_stmt_prepare($db_stmt_url, $q_url) and
	mysqli_stmt_bind_param($db_stmt_url, "i", $linkid) and
		mysqli_stmt_execute($db_stmt_url) and
			mysqli_stmt_bind_result($db_stmt_url, $r_url, $r_catid))
) {
	print_page($l10n['err_db_query'], $q_url);
}
if(!mysqli_stmt_fetch($db_stmt_url)) {
	print_pagr($l10n['err_no_data'], $q_url."<br/>linkid=$linkid");
}
mysqli_stmt_close($db_stmt_url);

// update link count
$r_linkcount++;
$r_private = (boolean)$r_private;
$ret = TRUE;
if(!$r_private) {
	$q_up       = "UPDATE ".T_STAT_LINK." SET linkcount = ? ".
		"WHERE linkid = ? AND userid = ?";
	$db_stmt_up = mysqli_stmt_init($db);
	if(!(mysqli_stmt_prepare($db_stmt_up, $q_up) and
		mysqli_stmt_bind_param($db_stmt_up, "iii", $r_linkcount, $linkid, $userid) and
			mysqli_stmt_execute($db_stmt_up) and
				mysqli_stmt_affected_rows($db_stmt_up) == 1)
	) {
		echo "<html><head></head><body><p>".$l10n['err_db_update']."</p>";
		if(DEBUG === TRUE) {
			echo "<p>".$l10n['dbg_db_query'].$q_up."<br/>linkid=$linkid, userid=$useridi,linkcount=$r_linkcount</p>";
		}
		$ret = FALSE;
		// don't abort on error here - the impact is not important enough
	}
	mysqli_stmt_close($db_stmt_up);

	$q_cat       = "SELECT catcount from ".T_STAT_CAT.
		" WHERE catid = ? AND userid = ?";
	$db_stmt_cat = mysqli_stmt_init($db);
	if(!(mysqli_stmt_prepare($db_stmt_cat, $q_cat) and
		mysqli_stmt_bind_param($db_stmt_cat, "ii", $r_catid, $userid) and
			mysqli_stmt_execute($db_stmt_cat) and
				mysqli_stmt_bind_result($db_stmt_cat, $r_catcount))
	) {
		print_page($l10n['err_db_query'], $q_cat);
	}
	if(!mysqli_stmt_fetch($db_stmt_cat)) {
		print_page($l10n['err_no_cat'], $q_cat."<br/>catid=$r_catid, userid=$userid");
	}
	mysqli_stmt_close($db_stmt_cat);
	// update category count
	$r_catcount++;
	$q_cat_up       = "UPDATE ".T_STAT_CAT." SET catcount = ? ".
		"WHERE catid = ? AND userid = ?";
	$db_stmt_cat_up = mysqli_stmt_init($db);
	if(!(mysqli_stmt_prepare($db_stmt_cat_up, $q_cat_up) and
		mysqli_stmt_bind_param($db_stmt_cat_up, "iii", $r_catcount, $r_catid, $userid) and
			mysqli_stmt_execute($db_stmt_cat_up) and
				mysqli_stmt_affected_rows($db_stmt_cat_up) == 1)
	) {
		echo "<html><head></head><body><p>".$l10n['err_db_update']."</p>";
		if(DEBUG === TRUE) {
			echo "<p>".$l10n['dbg_db_query'].$q_cat_up."<br/>catid=$r_catid, userid=$userid,catcount=$r_catcount</p>";
		}
		$ret = FALSE;
		// don't abort on error here - the impact is not important enough
	}
	mysqli_stmt_close($db_stmt_cat_up);

}
if(DEBUG === TRUE) {
	echo($ret ? '' : '<html><head></head><body>');
	echo '<p>'.$l10n['dbg_cnt_redir'].' <a href="'.$r_url.'">'.$r_url.'</a></p><p>';
	echo $l10n['txt_linkid']." = ".$linkid."<br />";
	echo $l10n['txt_userid']." = ".$userid."<br />";
	echo $l10n['txt_url']."= ".$r_url."<br />";
	echo $l10n['dbg_cnt_new'].$r_linkcount."<br />";
	echo $l10n['dbg_db_query'].$q_up."<br />";
	echo $l10n['dbg_db_update_success'].($ret ? "yes" : "no")."<br />";
	echo "</p></body></html>";
} else {
	if($ret) {
		header("Location: ".$r_url);
	} else {
		echo '<html><head></head><body><p>'.
			$l10n['dbg_cnt_redir'].' <a href="'.
			$r_url.'">'.$r_url.'</a></p></body></html>';
	}
}


function print_page($msg, $query) {
	global $l10n;
	echo '<html><head></head><body><p>'.$msg.'</p>';
	if(DEBUG === TRUE) {
		echo '<p>'.$l10n['dbg_db_query'].$query.'</p>';
	}
	echo "</body></html>";
	exit;
}


?>
