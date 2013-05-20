<?php
/*
 * lledit.php
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
require_once("mm_shared.inc.php");
require_once("linklist.inc.php");

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

// output all configuration parameters for debugging
if(DEBUG === TRUE) {
	print_begin(LMS_NAME." - ".$l10n['txt_edit_title']);
	print "<pre>\n\n\n\n";
	print "proto = $proto\ntemp url = $temp_url\nTHIS = ".THIS_URL."\nMAIN = ".MAIN_URL."\nREDIR = ".REDIR_URL."\n";
	print_r($_POST);
	print "</pre>\n";
	/*
			print_msg('Debug mode. No further action.');
			print_end();
			exit;
	*/
}

$db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_DB);
if(!$db) {
	print_begin(LMS_NAME." - ".$l10n['txt_edit_title']);
	print_error($l10n['err_db_conn']);
	print_end();
	exit;
}
// set character set for DB connections (default is always "latin1")
if(!mysqli_set_charset($db, "utf8")) {
	print_begin(LMS_NAME." - ".$l10n['txt_edit_title']);
	print_error($l10n['err_db_charset']);
	print_end();
	exit;
}

// get id of authenticated user (fetched from http environment)
$userid = get_userid($_SERVER['REMOTE_USER'], $db);
// write access must be authorized (public user id "0" is not allowed here)
$edit_ok = ($userid == 0) ? FALSE : TRUE;

if(!$edit_ok) {
	print_begin(LMS_NAME." - ".$l10n['txt_edit_title']);
	print_nav($cats, $edit_ok);
	print_msg($l10n['err_no_edit']);
	print_end();
	exit;
}

$cats = array();
$cats[0]["depth"] = -1;
// this function fills the global array $cats with all categories
build_cat_tree($cats, 0, 0, $userid, $db);

$users = get_all_users($db);

if(isset($_POST['linkadd_submit']) and $_POST['linkadd_submit'] == $l10n['frm_submit_link_add']) {
	// this function directly parses and sanitizes the input from the $_POST superglobal
	$ret = add_link($userid, $cats, $db);
	if($ret === TRUE) {
		header("Location: ".MAIN_URL."?added=true");
		print_begin(LMS_NAME." - ".$l10n['txt_edit_title']);
		print_nav($cats, $edit_ok);
		print_msg($l10n['txt_added_entry']);
		print '    <div class="hr2ns"></div>'."\n";
		print '<p><a href="'.MAIN_URL.'">'.$l10n['nav_reload']."</a></p>\n";
		print_end();
		exit;
	} else {
		print_begin(LMS_NAME." - ".$l10n['txt_edit_title']);
		print_nav($cats, $edit_ok);
		print_msg($ret);
		print '    <div class="hr2ns"></div>'."\n";
	}
} elseif(isset($_POST['catadd_submit']) and $_POST['catadd_submit'] == $l10n['frm_submit_cat_add']) {
	// this function directly parses and sanitizes the input from the $_POST superglobal
	$ret = add_cat($cats, $db);
	if($ret === TRUE) {
		header("Location: ".MAIN_URL."?added=true");
		print_begin(LMS_NAME." - ".$l10n['txt_edit_title']);
		print_nav($cats, $edit_ok);
		print_msg($l10n['txt_added_cat']);
		print '    <div class="hr2ns"></div>'."\n";
		print '<p><a href="'.MAIN_URL.'">'.$l10n['nav_reload']."</a></p>\n";
		print_end();
		exit;
	} else {
		print_begin(LMS_NAME." - ".$l10n['txt_edit_title']);
		print_nav($cats, $edit_ok);
		print_error($ret);
		print '    <div class="hr2ns"></div>'."\n";
	}
} else {
	print_begin(LMS_NAME." - ".$l10n['txt_edit_title']);
	print_nav($cats, $edit_ok);
}

if(isset($_GET['added']) and $_GET['added'] == "true") {
	print_msg($l10n['txt_added_entry']);
	print '    <div class="hr2ns"></div>'."\n";
}

print_addform($cats, $users);

mysqli_close($db);
print_end();


function add_link($userid, &$cats, $db) {
	global $l10n;
	$ret = TRUE;
	// verify and sanitize input data
	// prepare boolean values for SQL insert statement
	// Because the mysqli supports no boolean variables in prepared statements
	// the booleans must be substituted with integer values for now.
	//$private = isset($_POST['lmsprivate']) ? "TRUE" : "FALSE";
	$private = isset($_POST['lmsprivate']) ? 1 : 0;

	// sanitize strings
	if(!is_string($_POST['lmsname']) or $_POST['lmsname'] === "") {
		return $l10n['err_add_no_name'];
	}
	$entry_name = $_POST['lmsname'];
	if(mb_strlen($entry_name, "UTF-8") > 255) {
		$entry_name = substr($entry_name, 0, 255);
		//currently only errors can be returned, but no info/warning messages
		//$ret = "WARNING: name truncated, because of database restrictions (only 255 characters allowed)";
	}
	$entry_name = mm_db_filter_input($db, $entry_name, DB_TYPE);

	if(!is_string($_POST['lmsurl']) or $_POST['lmsurl'] === "") {
		return $l10n['err_add_no_url'];
	}
	$entry_url = $_POST['lmsurl'];
	// restrict url to 10921 = 65535/6 chars, because MySQL TEXT fields
	// can only hold 65535 bytes and a utf8 char can be up to 6 byte long
	if(mb_strlen($_POST['lmsurl'], "UTF-8") > 10921) {
		$entry_url = mb_substr($_POST['lmsurl'], 0, 10921, "UTF-8");
		//currently ony errors can be returned, but no info/warning messages
		$ret = $l10n['err_add_no_url'];
	}
	$entry_url = mm_db_filter_input($db, $entry_url, DB_TYPE);

	// sanitize integers
//    if ($private == "FALSE" and (!isset($_POST['parent_node']) or $_POST['parent_node'] == "")) {
	if($private == 0 and (!isset($_POST['parent_node']) or $_POST['parent_node'] == "")) {
		return $l10n['err_add_no_cat'];
	}
	$entry_node = intval($_POST['parent_node']);
//    if ($private == "TRUE") { 
	if($private == 1) {
		// although this is redundant for PHP 5.2 (0 is the default value for casts),
		// better do it explicitly for future compatibility
		$entry_node = 0;
	}
	if(!array_key_exists($entry_node, $cats)) {
		// submited a new entry but with an invalid category
		return $l10n['err_add_unknown_cat'];
	}
	// sanitize user ids
	$entry_uid = array();
	if($private == 1) {
		// private entries can only be added for the own user
		$entry_uid[] = $userid;
	} elseif(!is_array($_POST['users'])) {
		$entry_uid[] = intval($_POST['users']);
	} else {
		foreach($_POST['users'] as $u) {
			$entry_uid[] = intval($u);
		}
	}

	$linkid = 0;
	$max_id = 0;
	/*
	//TODO: use this on PostgreSQL instead of LAST_INSERT_ID() on MySQL???
	// check for existing entry (name, url, cat) to avoid problems finding the correct id later on
	// check for maximum id: get MAX(id) before insert and retrieve id > max(id) after insert ??
		$q_id = "SELECT MAX(linkid) from " . T_LINK;
		if ($result_id and ($data = mm_db_fetch_row($result_id, DB_TYPE))) {
			$max_id = $data[0];
		} else {
			$ret = "database error";
			print_error("SQL query failed: <br />");
			if (DEBUG === TRUE) {
				print_error("SQL query =". $q_id);
			}
			return $ret;
		}
	*/
	// check if this entry already exists in the link table
	$query_le   = "SELECT linkid FROM ".T_LINK." WHERE catid = ? AND url = ?";
	$db_stmt_le = mysqli_stmt_init($db);
	if(!(mysqli_stmt_prepare($db_stmt_le, $query_le) and
		mysqli_stmt_bind_param($db_stmt_le, "is", $entry_node, $entry_url) and
			mysqli_stmt_execute($db_stmt_le) and
				mysqli_stmt_bind_result($db_stmt_le, $linkid))
	) {
		$ret = $l10n['err_db_query'];
		if(DEBUG === TRUE) {
			$ret .= "<br />".$l10n['dbg_db_query'].$query_le."<br />";
		}
		mysqli_stmt_close($db_stmt_le);

		return $ret;
	}
	if(mysqli_stmt_fetch($db_stmt_le)) {
		// link exists in T_LINK and the linkid is already stored in $linkid by the "fetch"
		if(DEBUG === TRUE) {
			echo "<pre>".$l10n['dbg_db_linkid'].$linkid."</pre>";
		}
		mysqli_stmt_close($db_stmt_le);
	} else {
		// link does not exist in T_LINK. So we need to insert it first
		// but first the now unneeded previous statement needs to be closed
		mysqli_stmt_close($db_stmt_le);
		$query1   = "INSERT INTO ".T_LINK." (".
			"linkid, catid, title, url, adddate".
			") VALUES (".
			"NULL, ?, ?, ?, NULL)";
		$db_stmt1 = mysqli_stmt_init($db);
		if(!(mysqli_stmt_prepare($db_stmt1, $query1) and
			mysqli_stmt_bind_param($db_stmt1, "iss", $entry_node, $entry_name, $entry_url) and
				mysqli_stmt_execute($db_stmt1) and
					mysqli_stmt_affected_rows($db_stmt1) == 1)
		) {
			$ret = $l10n['err_db_insert'];
			if(DEBUG === TRUE) {
				$ret .= "<br />".$l10n['dbg_db_query'].$query1."<br />catid=$entry_node, title=$entry_name, url=$entry_url<br/>".$l10n['dbg_db_error'].mysqli_stmt_error($db_stmt1)."<br />";
			}
			mysqli_stmt_close($db_stmt1);

			return $ret;
		}
		mysqli_stmt_close($db_stmt1);

		$query2  = "SELECT LAST_INSERT_ID()";
		$result2 = mysqli_query($db, $query2, MYSQLI_STORE_RESULT);
		if($result2 and ($data = mysqli_fetch_row($result2))) {
			$linkid = $data[0];
		} else {
			$ret = $l10n['err_db_query'];
			if(DEBUG === TRUE) {
				$ret .= "<br />".$l10n['dbg_db_query'].$query2."<br />";
			}

			return $ret;
		}
	}

	foreach($entry_uid as $uid) {
		$query3  = "INSERT INTO ".T_STAT_LINK." (".
			"userid, linkid, private, linkcount".
			") VALUES (?, ?, ?, 0)";
		$db_stmt = mysqli_stmt_init($db);
		if(!(mysqli_stmt_prepare($db_stmt, $query3) and
			mysqli_stmt_bind_param($db_stmt, "iii", $uid, $linkid, $private) and
				mysqli_stmt_execute($db_stmt) and
					mysqli_stmt_affected_rows($db_stmt) == 1)
		) {
			//TODO: give better feedback if the error is caused by duplicate keys
			$ret = $l10n['err_db_insert'];
			if(DEBUG === TRUE) {
				$ret .= "<br />".$l10n['dbg_db_query'].$query3."<br />userid=$uid, linkid=$linkid, private=$private, linkcount=0, <br />".$l10n['dbg_db_error'].mysqli_stmt_error($db_stmt)."<br />";
			}
			mysqli_stmt_close($db_stmt);
			continue;
		}
		mysqli_stmt_close($db_stmt);
	}

	return $ret;
}


function add_cat(&$cats, $db) {
	global $l10n;
	$ret = TRUE;
	// verify and sanitize input data
	// prepare boolean values for SQL insert statement
	//TODO: replace with the correct boolean values, once the mysqli prepared statements can handle them
	//$shortcut = isset($_POST['lmslink']) ? TRUE : FALSE;
	$shortcut = isset($_POST['lmslink']) ? 1 : 0;

	// sanitize strings
	if(!is_string($_POST['lmsname']) or $_POST['lmsname'] === "") {
		return $l10n['err_add_no_name'];
	}
	$entry_name = $_POST['lmsname'];
	if(mb_strlen($entry_name, "UTF-8") > 255) {
		$entry_name = substr($entry_name, 0, 255);
		//currently only errors can be returned, but no info/warning messages
		//$ret = "WARNING: name truncated, because of database restrictions (only 255 characters allowed)";
	}
	$entry_name = mm_db_filter_input($db, $entry_name, DB_TYPE);


	// sanitize integers
	if(!isset($_POST['parent_node']) or $_POST['parent_node'] == "") {
		return $l10n['err_add_no_cat'];
	}
	$entry_node = intval($_POST['parent_node']);
	if(!array_key_exists($entry_node, $cats)) {
		// submited a new entry but with an invalid category
		return $l10n['err_add_unknown_parent_cat'];
	}
	// sanitize user ids
	$entry_uid = array();
	if(!is_array($_POST['users'])) {
		$entry_uid[] = intval($_POST['users']);
	} else {
		foreach($_POST['users'] as $u) {
			$entry_uid[] = intval($u);
		}
	}

	$catid = 0;
	// check if this entry already exists in the link table
	$query_ce   = "SELECT catid FROM ".T_CAT." WHERE parentid = ? AND name = ?";
	$db_stmt_ce = mysqli_stmt_init($db);
	if(!(mysqli_stmt_prepare($db_stmt_ce, $query_ce) and
		mysqli_stmt_bind_param($db_stmt_ce, "is", $entry_node, $entry_name) and
			mysqli_stmt_execute($db_stmt_ce) and
				mysqli_stmt_bind_result($db_stmt_ce, $catid))
	) {
		$ret = $l10n['err_db_query'];
		if(DEBUG === TRUE) {
			$ret .= "<br />".$l10n['dbg_db_query'].$query_ce."<br />";
		}
		mysqli_stmt_close($db_stmt_ce);

		return $ret;
	}
	if(mysqli_stmt_fetch($db_stmt_ce)) {
		// category exists in T_CAT and the catid is already stored in $catid by the "fetch"
		if(DEBUG === TRUE) {
			echo "<pre>".$l10n['dbg_db_catid'].$catid."</pre>";
		}
		mysqli_stmt_close($db_stmt_ce);
	} else {
		// category does not exist in T_CAT and needs to be inserted
		// but first the now unneeded previous statement needs to be closed
		mysqli_stmt_close($db_stmt_ce);
		$query1   = "INSERT INTO ".T_CAT." (".
			"catid, parentid, link, name".
			") VALUES (".
			"NULL, ?, ?, ?)";
		$db_stmt1 = mysqli_stmt_init($db);
		if(!(mysqli_stmt_prepare($db_stmt1, $query1) and
			mysqli_stmt_bind_param($db_stmt1, "iis", $entry_node, $shortcut, $entry_name) and
				mysqli_stmt_execute($db_stmt1) and
					mysqli_stmt_affected_rows($db_stmt1) == 1)
		) {
			$ret .= $l10n['err_db_insert'];
			if(DEBUG === TRUE) {
				$ret .= "<br />".$l10n['dbg_db_query'].$query1."<br />parentid=$entry_node, link=$shortcut, title=$entry_name<br />".$l10n['dbg_db_error'].mysqli_stmt_error($db_stmt1)."<br />";
			}
			mysqli_stmt_close($db_stmt1);

			return $ret;
		}
		mysqli_stmt_close($db_stmt1);

		$query2  = "SELECT LAST_INSERT_ID()";
		$result2 = mysqli_query($db, $query2, MYSQLI_STORE_RESULT);
		if($result2 and ($data = mysqli_fetch_row($result2))) {
			$catid = $data[0];
		} else {
			$ret = $l10n['err_db_query'];
			if(DEBUG === TRUE) {
				$ret .= "<br />".$l10n['dbg_db_query'].$query2."<br />";
			}

			return $ret;
		}
	}

	foreach($entry_uid as $uid) {
		$query_prev   = "SELECT s.catid from ".T_STAT_CAT." s LEFT JOIN ".
			T_CAT." c ON (s.catid = c.catid) ".
			"WHERE c.parentid = ? and s.userid = ? and s.catnext = 0";
		$db_stmt_prev = mysqli_stmt_init($db);
		if(!(mysqli_stmt_prepare($db_stmt_prev, $query_prev) and
			mysqli_stmt_bind_param($db_stmt_prev, "ii", $entry_node, $uid) and
				mysqli_stmt_execute($db_stmt_prev) and
					mysqli_stmt_bind_result($db_stmt_prev, $prev))
		) {
			//TODO: give better feedback if the error is caused by duplicate keys
			$ret .= $l10n['err_db_query'];
			if(DEBUG === TRUE) {
				$ret .= "<br />".$l10n['dbg_db_query'].$query_prev."<br />userid=$uid, parentid=$entry_node, <br />".$l10n['dbg_db_error'].mysqli_stmt_error($db_stmt_prev)."<br />";
			}
			mysqli_stmt_close($db_stmt_prev);
			// don't abort on this error but try to insert the new entry anyway
//            continue;
		}
		if(mysqli_stmt_fetch($db_stmt_prev)) {
			// there should always be a catnext with value 0, already fetched into $prev
			// if at least one entry exists.
		} else {
			// use a default value with the assumption that no category exists below this parent
			$prev = 0;
		}
		mysqli_stmt_close($db_stmt_prev);

		if($prev != 0) {
			// there was another "last" category below the parent that needs
			// to be updated first
			$query_extend   = "UPDATE ".T_STAT_CAT." SET catnext = ? ".
				"WHERE catid = ? and userid = ?";
			$db_stmt_extend = mysqli_stmt_init($db);
			if(!(mysqli_stmt_prepare($db_stmt_extend, $query_extend) and
				mysqli_stmt_bind_param($db_stmt_extend, "iii", $catid, $prev, $uid) and
					mysqli_stmt_execute($db_stmt_extend) and
						mysqli_stmt_affected_rows($db_stmt_extend) == 1)
			) {
				//TODO: give better feedback if the error is caused by duplicate keys
				$ret .= $l10n['err_db_update'];
				if(DEBUG === TRUE) {
					$ret .= "<br />".$l10n['dbg_db_query'].$query_extend."<br />userid=$uid, catid=$catid, catnext=$prev<br />".$l10n['dbg_db_error'].mysqli_stmt_error($db_stmt_extend)."<br />";
				}
				mysqli_stmt_close($db_stmt_extend);
				// don't abort on this error but try to insert the new entry anyway
//                continue;
			}
			mysqli_stmt_close($db_stmt_extend);
		}

		$query3   = "INSERT INTO ".T_STAT_CAT." (".
			"userid, catid, catcount, catnext, catprev".
			") VALUES (?, ?, 0, 0, ?)";
		$db_stmt3 = mysqli_stmt_init($db);
		if(!(mysqli_stmt_prepare($db_stmt3, $query3) and
			mysqli_stmt_bind_param($db_stmt3, "iii", $uid, $catid, $prev) and
				mysqli_stmt_execute($db_stmt3) and
					mysqli_stmt_affected_rows($db_stmt3) == 1)
		) {
			//TODO: give better feedback if the error is caused by duplicate keys
			$ret .= $l10n['err_db_insert'];
			if(DEBUG === TRUE) {
				$ret .= "<br />".$l10n['dbg_db_query'].$query3."<br />userid=$uid, catid=$catid, catcount=0, catnext=0, catprev=$prev<br />".$l10n['dbg_db_error'].mysqli_stmt_error($db_stmt3)."<br />";
			}
			mysqli_stmt_close($db_stmt3);
			continue;
		}
		mysqli_stmt_close($db_stmt3);
	}

	return $ret;
}


function print_addform(&$cats, &$users) {
	global $l10n;
	print '    <form id="linkadd" action="'.THIS_URL.'" method="post" accept-charset="UTF-8">'."\n";
	print '        <fieldset>'."\n";
	print '        <legend><strong>'.$l10n['frm_legend_link_add'].'</strong></legend>'."\n";
	print '            <label for="lmsname">'.$l10n['frm_lbl_name'].':</label>&nbsp;<input type="text" id="lmsname" name="lmsname" size="30" maxlength="255"/>'."\n";
	print '            &nbsp;<label for="lmsurl">'.$l10n['frm_lbl_url'].':</label>&nbsp;<input type="text" id="lmsurl" name="lmsurl" size="50" maxlength="999"/>'."\n";
	print '            &nbsp;&nbsp;'."\n";
	print '            <br />'."\n";
	print '            '.$l10n['frm_lbl_parent'].': <select name="parent_node" size="1" >'."\n";
	print '            <option selected="selected" value=""> '.$l10n['frm_sel_default'].' </option>'."\n";
	print_cat_tree(0, -1, $cats);
	print '            </select><br />'."\n";
	print '            '.$l10n['frm_lbl_link_users'].':&nbsp;&nbsp;&nbsp;';
	print '            <br />'."\n";
	print '            <input class="depth2" type="checkbox" id="user0" '.
		'name="users[]" value="0" /><label for="user0">'.
		$l10n['frm_lbl_user_public'].'</label>&nbsp;&nbsp;'."\n";
	print '            <br />'."\n";
	foreach($users as $uid => $uname) {
		print '            <input class="depth2" type="checkbox" id="user'.$uid.'" name="users[]" value="'.$uid.'" /><label for="user'.$uid.'">'.$uname.'</label>&nbsp;&nbsp;'."\n";
	}
	print '            <br />'."\n";
	print '            <input class="depth2" type="checkbox" id="lmsprivate"'.
		' name="lmsprivate" value="true" /><label for="lmsprivate">'.
		$l10n['frm_lbl_user_private'].'</label>&nbsp;&nbsp;'."\n";
	print '            <br />'."\n";

	print '            <input type="submit" name="linkadd_submit" value="'.
		$l10n['frm_submit_link_add'].'" />'."\n";
	print '            <input type="reset" name="linkadd_reset" value="'.
		$l10n['frm_reset_link_add'].'" />'."\n";
	print '        </fieldset>'."\n";
	print '    </form>'."\n";
	print '    <div class="vspace"></div>'."\n";


	print '    <form id="catadd" action="'.THIS_URL.'" method="post" accept-charset="UTF-8">'."\n";
	print '        <fieldset>'."\n";
	print '        <legend><strong>'.$l10n['frm_legend_cat_add'].'</strong></legend>'."\n";
	print '            <label for="lmsname">'.$l10n['frm_lbl_name'].':</label>&nbsp;<input type="text" id="lmsname" name="lmsname" size="30" maxlength="255"/>'."\n";
	print '            &nbsp;<label for="lmslink">'.$l10n['frm_lbl_link'].':</label>&nbsp;<input type="checkbox" id="lmslink" name="lmslink" value="true" />'."\n";
	print '            &nbsp;&nbsp;'."\n";
	print '            <br />'."\n";
	print '            '.$l10n['frm_lbl_parent'].': <select name="parent_node" size="1" >'."\n";
	print '            <option selected="selected" value=""> '.$l10n['frm_sel_default'].' </option>'."\n";
	print '            <option value="0">['.$l10n['frm_sel_add_top_cat'].']</option>'."\n";
	print_cat_tree(0, -1, $cats);
	print '            </select><br />'."\n";
	print '            '.$l10n['frm_lbl_cat_users'].':&nbsp;&nbsp;&nbsp;';
	print '            <br />'."\n";
	print '            <input class="depth2" type="checkbox" id="user0"'.
		' name="users[]" value="0" /><label for="user0">'.
		$l10n['frm_lbl_user_public'].'</label>&nbsp;&nbsp;'."\n";
	print '            <br />'."\n";
	foreach($users as $uid => $uname) {
		print '            <input class="depth2" type="checkbox" id="user'.$uid.'" name="users[]" value="'.$uid.'" /><label for="user'.$uid.'">'.$uname.'</label>&nbsp;&nbsp;'."\n";
	}
	print '            <br />'."\n";

	print '            <input type="submit" name="catadd_submit" value="'.
		$l10n['frm_submit_cat_add'].'" />'."\n";
	print '            <input type="reset" name="catadd_reset" value="'.
		$l10n['frm_reset_cat_add'].'" />'."\n";
	print '        </fieldset>'."\n";
	print '    </form>'."\n";
	print '    <div class="vspace"></div>'."\n";

}


function get_all_users($db) {
	global $l10n;
	// set invalid user id as default return value
	$all_users = array();
	$query     = "SELECT userid, name FROM ".T_USER;
	$result    = mm_db_query($query, $db, DB_TYPE);
	if($result) {
		while($data = mm_db_fetch_row($result, DB_TYPE)) {
			$all_users[$data[0]] = $data[1];
		}
	} else {
//TODO: avoid direct output even for debugging, because this sent before the page headers
		print_error($l10n['err_db_query']."<br />".$l10n['dbg_db_query'].$query1);
	}

	return $all_users;
}


function cat_resort($parent, $user) {
	global $l10n;
	$ret      = FALSE;
	$cats_no  = 0;
	$cats     = array();
	$old_sort = array();
	$new_sort = array();

	$query  = "SELECT s.catid, s.catsort FROM ".T_STAT_CAT." s LEFT JOIN ".
		T_CAT." c ON (s.catid = c.catid) ".
		"WHERE c.parentid = '".$parent."' AND s.userid = '".
		$user."' SORT BY s.catsort ASC";
	$result = mm_db_query($query, $db, DB_TYPE);
	if($result) {
		while($data = mm_db_fetch_row($result, DB_TYPE)) {
			$nodes[$cats_no]    = $data[0];
			$old_sort[$cats_no] = $data[1];
			$cats_no++;
		}
	} else {
//TODO: avoid direct output even for debugging, because this sent before the page headers
		print_error($l10n['err_db_query']);
		if(DEBUG === TRUE) {
			print_error("<br />".$l10n['dbg_db_query'].$query."<br />");
		}

		return $ret;
	}

	/*
	 MAX=1000
	  NO=  50
	STEP=  50
	RANGE=450-950

	newstep=1000/50/2 = 10
	newrange=10*50 = 500
	new_val=(1000-50*10)/2 =250

	*/
	if(($cats_no + 1) < CAT_SORT_MAX) {
		//Note: integer casts in PHP always round towards 0
		$new_step = (int)(CAT_SORT_MAX / ($cats_no + 1) / 2);
		if($new_step > CAT_SORT_STEP) {
			$new_step = CAT_SORT_STEP;
		}
		$new_value = (int)((CAT_SORT_MAX - ($new_step * ($cats_no + 1))) / 2);
		foreach($nodes as $i => $cat) {
			$new_sort[$i] = $new_value;
			$new_value += $new_step;
		}
	}

//TODO: write new sort values back into db

	return $ret;
}


?>
