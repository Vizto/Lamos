<?php
/*
 * linklist.inc.php
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


// include configuration and shared code
require_once("config/config.php");
require_once("mm_shared.inc.php");


function get_userid($name, $db) {
	// set id of the "public" user as the default return value
	// (in case of error or if the username is not found)
	$ret     = 0;
	$query   = 'SELECT userid FROM '.T_USER.' WHERE name=?';
	$db_stmt = mysqli_stmt_init($db);
	if(mysqli_stmt_prepare($db_stmt, $query) and
		mysqli_stmt_bind_param($db_stmt, "s", $name) and
			mysqli_stmt_execute($db_stmt) and
				mysqli_stmt_bind_result($db_stmt, $uid) and
					mysqli_stmt_fetch($db_stmt)
	) {
		$ret = $uid;
	}
	mysqli_stmt_close($db_stmt);

	return $ret;
}


// the category tree has a dummy entry with id=0 and depth=-1 as root node
// (0 is not a valid id in the database field)
function build_cat_tree(&$cats, $depth, $parent, $uid, $db) {
	global $l10n;
//    echo '<pre>DEBUG: scanning parent id '.$parent."</pre>\n";
	$query   = "SELECT DISTINCT c.catid, c.name, c.link, s.catnext, s.catprev, s.userid FROM ".
		T_STAT_CAT." s LEFT JOIN ".T_CAT." c ON (s.catid = c.catid) ".
		"WHERE (s.userid = ? OR s.userid = '0') ".
		"AND c.parentid = ?";
	$db_stmt = mysqli_stmt_init($db);
	if(!(mysqli_stmt_prepare($db_stmt, $query) and
		mysqli_stmt_bind_param($db_stmt, "ii", $uid, $parent) and
			mysqli_stmt_execute($db_stmt) and
				mysqli_stmt_bind_result($db_stmt, $r_catid, $r_name, $r_link, $r_catnext, $r_catprev, $r_user))
	) {
		print_error($l10n['err_db_query']);
		if(DEBUG === TRUE) {
			print_error($l10n['dbg_db_query'].$query);
			print_error($l10n['dbg_db_error'].mm_db_error($db));
		}

		return;
	}
	while(mysqli_stmt_fetch($db_stmt)) {
		// The same category can be returned twice: for the $uid user and
		// the public user with id 0. If the fetched row belongs to the
		// public user and the category already exists in the $cats array,
		// the $cats entry belongs to the $uid and has priority. So the
		// fetched row for the public user must be ignored.
		// On the other hand, entries for the public user will be overwritten
		// if the $uid row is fetched later on.
		if(($r_user == 0) and array_key_exists($r_catid, $cats)) {
			continue;
		}
		// html-escape all user-given data as soon as possible
		$cats[$r_catid]["name"]  = htmlspecialchars($r_name, ENT_QUOTES, "UTF-8");
		$cats[$r_catid]["depth"] = $depth;
		if($r_link != 0) {
			$cats[$r_catid]["link"] = "t".$r_catid;
		}
		// create backward link from child to parent
		$cats[$r_catid]["parent"] = $parent;
		// create link to next entry below this parent
		$cats[$r_catid]["next"] = $r_catnext;
		// create backward link to previous entry below this parent
		$cats[$r_catid]["prev"] = $r_catprev;
		if($r_catprev == 0) {
			// create link from parent to first child
			$cats[$parent]["child"] = $r_catid;
		}
//        echo '<pre>DEBUG: ... child = '. $cats[$parent]["child"] ."\n";
//        echo 'DEBUG: ... id = '. $r_catid ."\n";
//        echo 'DEBUG: ... ... prev = '. $cats[$r_catid]["prev"] ."\n";
//        echo 'DEBUG: ... ... next = '. $cats[$r_catid]["next"] ."\n";
//        echo 'DEBUG: ... ... parent = '. $cats[$r_catid]["parent"] ."</pre>\n";
	}
	mysqli_stmt_close($db_stmt);

	// subtree must be queried after the prepared statement was closed
	if(array_key_exists("child", $cats[$parent])) {
//        echo '<pre>DEBUG: ... fetching child = '. $cats[$parent]["child"] ."</pre>\n";
		$id = $cats[$parent]["child"];
		while($id !== 0) {
			// build subtree below this category
			build_cat_tree($cats, $depth + 1, $id, $uid, $db);
			$id = $cats[$id]["next"];
		}
	}
}


// print all contents of a category subtree
function print_cat($parent, $uid, &$cats, $db) {
	$indent = str_repeat("    ", $cats[$parent]["depth"] + 1);
	print $indent.'<div class="depth'.$cats[$parent]["depth"].'">'."\n";
	print $indent.'    <p class="big">'.$cats[$parent]["name"].'</p>'."\n";
	print_cat_rec($parent, $uid, $cats, $db);
	print $indent.'</div>'."\n";
}


// output category contents by recursion
function print_cat_rec($parent, $uid, &$cats, $db) {
	$indent = str_repeat("    ", $cats[$parent]["depth"] + 2);
	// print all links for the parent category
	print_links($parent, $indent, $uid, $db);

	// print all subcategories recursively
	if(!array_key_exists("child", $cats[$parent])) {
		return;
	}
	$id = $cats[$parent]["child"];
	while($id != 0) {
		print $indent.'<div class="depth'.$cats[$id]["depth"].'">'."\n";
		print $indent.'    <p class="big">';
		if(isset($cats[$id]["link"])) {
			print '<a id="'.$cats[$id]["link"].'" name="'.
				$cats[$id]["link"].'">'.$cats[$id]["name"].
				'</a> [<a href="#top">top</a>]';
		} else {
			print $cats[$id]["name"];
		}
		print '</p>'."\n";
		print_cat_rec($id, $uid, $cats, $db);
		print $indent.'</div>'."\n";
		$id = $cats[$id]["next"];
	}
}


function print_links($cat, $indent, $uid, $db) {
	global $l10n;
	$query   = "SELECT DISTINCT l.url, l.title, l.linkid FROM ".T_STAT_LINK.
		" s LEFT JOIN ".T_LINK." l ON (s.linkid=l.linkid) ".
		"WHERE (s.userid = ? OR s.userid = '0') AND l.catid = ? ".
		"AND s.private = FALSE ORDER BY s.linkcount DESC";
	$db_stmt = mysqli_stmt_init($db);
	if(!(mysqli_stmt_prepare($db_stmt, $query) and
		mysqli_stmt_bind_param($db_stmt, "ii", $uid, $cat) and
			mysqli_stmt_execute($db_stmt) and
				mysqli_stmt_bind_result($db_stmt, $r_url, $r_title, $r_linkid))
	) {
		print_error($l10n['err_db_query']);
		if(DEBUG === TRUE) {
			print_error($l10n['dbg_db_query'].$query);
			print_error($l10n['dbg_db_error'].mm_db_error($db));
		}

		return;
	}

	while(mysqli_stmt_fetch($db_stmt)) {
		$html_name = htmlspecialchars($r_title, ENT_QUOTES, "UTF-8");
		$html_link = htmlspecialchars($r_url, ENT_QUOTES, "UTF-8");
		print $indent.'<a href="'.REDIR_URL."?link=".$r_linkid.
			'" title="'.$html_link.'">'.$html_name.'</a>'."\n";
	}
	mysqli_stmt_close($db_stmt);
}


function print_private($uid, $db) {
	global $l10n;
	$indent = str_repeat("    ", 2);
	// don't query for userid 0 here, because the public user can
	// have no private links
	$query   = "SELECT l.url, l.title, l.linkid FROM ".T_STAT_LINK.
		" s LEFT JOIN ".T_LINK." l ON (s.linkid=l.linkid) ".
		"WHERE s.userid = ? AND s.private = TRUE ".
		"ORDER BY s.linkcount DESC";
	$db_stmt = mysqli_stmt_init($db);
	if(!(mysqli_stmt_prepare($db_stmt, $query) and
		mysqli_stmt_bind_param($db_stmt, "i", $uid) and
			mysqli_stmt_execute($db_stmt) and
				mysqli_stmt_bind_result($db_stmt, $r_url, $r_title, $r_linkid))
	) {
		print_error($l10n['err_db_query']);
		if(DEBUG === TRUE) {
			print_error($l10n['dbg_db_query'].$query);
			print_error($l10n['dbg_db_error'].mm_db_error($db));
		}

		return;
	}
	print $indent.'<div class="depth1">'."\n";
	print $indent.'    <p class="big">'.$l10n['txt_private_links']."</p>\n";
	while(mysqli_stmt_fetch($db_stmt)) {
		$html_name = htmlspecialchars($r_title, ENT_QUOTES, "UTF-8");
		$html_link = htmlspecialchars($r_url, ENT_QUOTES, "UTF-8");
		print $indent.'    <a href="'.$html_link.'">'.$html_name.'</a>'."\n";
	}
	print $indent.'</div>'."\n";
	mysqli_stmt_close($db_stmt);
}


function print_top_links($top, $uid, &$cats, $db) {
	global $l10n;
	$indent  = str_repeat("    ", 2);
	$query   = "SELECT l.url, l.title, l.linkid, l.catid, s.linkcount FROM ".
		T_STAT_LINK." s LEFT JOIN ".T_LINK." l ON (s.linkid=l.linkid) ".
		"WHERE (s.userid = ? OR s.userid = '0') ".
		"AND s.private = FALSE ORDER BY s.linkcount DESC LIMIT ?";
	$db_stmt = mysqli_stmt_init($db);
	if(!(mysqli_stmt_prepare($db_stmt, $query) and
		mysqli_stmt_bind_param($db_stmt, "ii", $uid, $top) and
			mysqli_stmt_execute($db_stmt) and
				mysqli_stmt_bind_result($db_stmt, $r_url, $r_title, $r_linkid, $r_catid, $r_linkcount))
	) {
		print_error($l10n['err_db_query']);
		if(DEBUG === TRUE) {
			print_error($l10n['dbg_db_query'].$query);
			print_error($l10n['dbg_db_error'].mm_db_error($db));
		}

		return;
	}

	print '    <div class="depth0">'."\n";
	print '        <p class="big">'.$l10n['txt_top'].' '.$top.' '.$l10n['txt_links']."</p>\n";
	print '        <table class="tcenter1">'."\n";
	print '            <tr><th>'.$l10n['txt_name'].'</th><th>'.$l10n['txt_cat']."</th></tr>\n";
	while(mysqli_stmt_fetch($db_stmt)) {
		$html_name = htmlspecialchars($r_title, ENT_QUOTES, "UTF-8");
		$html_link = htmlspecialchars($r_url, ENT_QUOTES, "UTF-8");
		$catwalk   = $r_catid;
		$catstr    = $cats[$catwalk]["name"];
		while($cats[$catwalk]["parent"] != 0) {
			$catwalk = $cats[$catwalk]["parent"];
			$catstr  = $cats[$catwalk]["name"].' / '.$catstr;
		}
		print '            <tr>'."\n";
		print '                <td><div><a href="'.REDIR_URL."?link=".
			$r_linkid.'" title="'.$html_link.'">'.$html_name.
			'</a></div></td>'."\n";
		print '                <td><a href="'.MAIN_URL.'?show='.
			$r_catid.'">'.$catstr.'</a></td>'."\n";
		print '            </tr>'."\n";
	}
	print '        </table>'."\n";
	print '    </div>'."\n";
	mysqli_stmt_close($db_stmt);
}


function print_search($search, $uid, &$cats, $db) {
	global $l10n;
	$indent = str_repeat("    ", 2);
	// use the search string to create a simple search pattern as SQL parameter
	$search = '%'.$search.'%';
	// Note: The linkcount can not be fetched here. Because DISTINCT
	// works only as desired, if only columns from T_LINK are fetched.
	// Otherwise we would get duplicate results if there are rows for
	// a userid and the public userid
	$query   = "SELECT DISTINCT l.url, l.title, l.linkid, l.catid FROM ".
		T_STAT_LINK." s LEFT JOIN ".T_LINK." l ON (s.linkid=l.linkid) ".
		"WHERE (s.userid = ? OR s.userid = '0') ".
		"AND (l.title LIKE ? OR l.url LIKE ?) ".
		"ORDER BY s.linkcount DESC";
	$db_stmt = mysqli_stmt_init($db);
	if(!(mysqli_stmt_prepare($db_stmt, $query) and
		mysqli_stmt_bind_param($db_stmt, "iss", $uid, $search, $search) and
			mysqli_stmt_execute($db_stmt) and
				mysqli_stmt_bind_result($db_stmt, $r_url, $r_title, $r_linkid, $r_catid))
	) {
		print_error($l10n['err_db_query']);
		if(DEBUG === TRUE) {
			print_error($l10n['dbg_db_query'].$query);
			print_error("search =".$search);
			print_error($l10n['dbg_db_error'].mm_db_error($db));
		}

		return;
	}
	print '    <div class="depth0">'."\n";
	print '        <p class="big">'.$l10n['txt_search_res']."</p>\n";
	print '        <table class="tcenter1">'."\n";
	print '            <tr><th>'.$l10n['txt_name'].'</th><th>'.$l10n['txt_cat']."</th></tr>\n";
	while(mysqli_stmt_fetch($db_stmt)) {
		$html_name = htmlspecialchars($r_title, ENT_QUOTES, "UTF-8");
		$html_link = htmlspecialchars($r_url, ENT_QUOTES, "UTF-8");
		$catwalk   = $r_catid;
		$catstr    = $cats[$catwalk]["name"];
		while($cats[$catwalk]["parent"] != 0) {
			$catwalk = $cats[$catwalk]["parent"];
			$catstr  = $cats[$catwalk]["name"].' / '.$catstr;
		}
		print '            <tr>'."\n";
		print '                <td><div><a href="'.REDIR_URL."?link=".
			$r_linkid.'" title="'.$html_link.'">'.$html_name.
			'</a></div></td>'."\n";
		print '                <td><a href="'.MAIN_URL.'?show='.
			$r_catid.'">'.$catstr.'</a></td>'."\n";
		print '            </tr>'."\n";
	}
	print '        </table>'."\n";
	print '    </div>'."\n";
	mysqli_stmt_close($db_stmt);
}


function print_error($msg) {
	global $l10n;
	print "<div class=\"red\"><strong>".$l10n['txt_error'].":&nbsp;</strong>\n";
	print "    $msg<br />\n";
}


function print_msg($msg) {
	print("<div><strong>$msg</strong><br /></div>\n");
}


function print_begin($title) {
	// split xml expression in string to avoid parser confusion in vim
	echo '<?xml version="1.0" encoding="UTF-8" ?'.'>'."\n";
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"'."\n";
	echo '    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'."\n";
	echo '<?xml-stylesheet type="text/css" href="lms.css" ?'.'>'."\n";
	echo "\n";
	echo '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="de" lang="de">'."\n";
	echo '<head>'."\n";
	echo '        <title>'.$title.'</title>'."\n";
	echo '        <meta http-equiv="content-type" content="application/xhtml+xml; charset=UTF-8" />'."\n";
	echo '        <!-- pages expire immediately and should not be cached  -->'."\n";
	echo '        <meta http-equiv="expires" content="0" />'."\n";
	echo '        <meta name="author" content="Michel Messerschmidt" />'."\n";
	echo '        <meta name="robots" content="noindex" />'."\n";
	echo '        <meta name="language" content="'.LMS_LANG.'" />'."\n";
	echo '        <meta http-equiv="Content-Style-Type" content="text/css" />'."\n";
	echo '        <link rel="stylesheet" href="lms.css" type="text/css" />'."\n";
	echo '</head>'."\n";
	echo '<body>'."\n";
}

function print_nav(&$cats, $show_edit) {
	global $l10n;
	echo '    <!-- Navbar -->'."\n";
	echo '    <form id="catsel" name="catsel" action="'.MAIN_URL.'" method="post" accept-charset="UTF-8">'."\n";
	echo '        <div class="nav">'."\n";
	echo '            <a href="../index.html" title="'.
		$l10n['lnk_title_start_page'].'">Home</a> - '."\n";
	echo '            <a href="index.php" hreflang="'.LMS_LANG.
		'" title="'.$l10n['txt_top'].' '.TOPLIST.' '.
		$l10n['txt_links'].'">'.LMS_NAME.'</a> -&nbsp;'."\n";
	echo '            <select name="showtree" size="1" tabindex="1" '.
		'onchange="document.catsel.submit()" >'."\n";
	echo '                <option selected="selected" value="-3">'.
		$l10n['frm_sel_default']."</option>\n";
	echo '                <option value="-2">['.$l10n['txt_top'].'&nbsp;'.
		TOPLIST.'&nbsp;'.$l10n['txt_links'].']</option>'."\n";
	echo '                <option value="-1">['.$l10n['txt_private_links'].']</option>'."\n";
	echo '                <option value="0">['.$l10n['txt_all'].']</option>'."\n";
	print_cat_tree(0, MAXDEPTH, $cats);
	echo '            </select>&nbsp;&nbsp;'."\n";
	echo '            <input type="submit" value="'.
		$l10n['frm_submit_cat_sel'].'" tabindex="2" />'."\n";
	echo '            &nbsp;&nbsp;'."\n";
	echo '            <input type="text" id="searchkey" name="searchkey" size="20" tabindex="3" />'."\n";
	echo '            <input type="submit" name="search_submit" value="'.
		$l10n['frm_submit_search'].'" tabindex="4" />'."\n";
	if($show_edit == TRUE) {
		echo '            &nbsp;&nbsp;<a href="lledit.php">'.$l10n['lnk_edit'].'</a>'."\n";
	}


	echo '        </div>'."\n";
	echo '    </form>'."\n";
	echo '    <div class="navspace"><a name="top" id="top"></a></div>'."\n";
	echo '    <div class="navspace"><a name="top" id="top"></a></div>'."\n";
	echo "\n";
}


function print_cat_tree($parent, $depth, &$cats) {
	# skip all categories that are nested deeper than requested
	if($depth >= 0 and $cats[$parent]["depth"] >= $depth) {
		return;
	}
	if(!array_key_exists("child", $cats[$parent])) {
		return;
	}
	$id = $cats[$parent]["child"];
	// the loop must abort whenever id is 0 (= no next entry)
	while($id != 0) {
		$out = str_repeat(
			"&nbsp;&nbsp;&nbsp;&nbsp;",
			$cats[$id]["depth"]
		).$cats[$id]["name"];
		print '                <option value="'.$id.'">'.$out.
			'</option>'."\n";
		print_cat_tree($id, $depth, $cats);
		$id = $cats[$id]["next"];
	}
}


function print_menulist($parent, &$cats) {
	echo '    <div class="big">'."\n";
	print_menulist_rec($parent, $cats);
	echo '    </div>'."\n";
	echo '    <div class="hr2ns"></div>'."\n";
}


function print_menulist_rec($parent, &$cats) {
	if(!array_key_exists("child", $cats[$parent])) {
		return;
	}
	$id = $cats[$parent]["child"];
	while($id != 0) {
		if(isset($cats[$id]["link"])) {
			print '        <a href="#'.$cats[$id]["link"].'">';
			print $cats[$id]["name"]."</a>\n";
			print_menulist_rec($id, $cats);
		}
		$id = $cats[$id]["next"];
	}
}


function print_end() {
	global $l10n;
	date_default_timezone_set("UTC");
	echo "\n";
	echo '    <address>'."\n";
	echo '        '.$l10n['txt_created'].' <a href="mailto:www@michel-messerschmidt.de">'."\n";
	echo '        Michel Messerschmidt</a> &nbsp;&nbsp;&nbsp;'."\n";
	echo '        <a href="http://www.validome.org/referer">'."\n";
	echo '            <img class="vmid" src="../validome_set4_valid_xhtml_1_0.gif"'."\n";
	echo '                 alt="Valid XHTML 1.0" title="Valid XHTML 1.0"'."\n";
	echo '                 height="15" width="80" /></a>'."\n";
	echo '        &#160;&#160;&#160;'."\n";
	echo '        Powered by <a href="http://www.michel-messerschmidt.de/lamos/index.html">lamos</a>'."\n";
//    echo '        &nbsp;&nbsp;&nbsp;'. $l10n['txt_modified'] ."\n";
//    echo '        ' . date("Y-m-d H:i T", getlastmod()) . "\n";
	echo '        &nbsp;&nbsp;&nbsp;[<a href="#top" title="'.$l10n['lnk_title_top'].'">top</a>]'."\n";
	echo '    </address>'."\n";
	echo '</body>'."\n";
	echo '</html>'."\n";
}


?>
