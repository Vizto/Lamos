<?php
/*
 * mm_shared.inc.php
 * This file is part of lamos
 * 
 * Copyright 2008 Michel Messerschmidt
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


// sanitize untrusted database input data (from GET, POST, ...)
function mm_db_filter_input($db, $data, $type) {
	//TODO: filter unwanted content here (NUL, CTRL-Z, ...)
	if(get_magic_quotes_gpc()) {
		// revert effect of magic_quotes_gpc because it is not
		// sufficient and depends on server configuration
		$data = stripslashes($data);
	}
	if($type === "mysql") {
		return mysqli_real_escape_string($db, $data);
	} elseif($type === "pgsql") {
		return pg_escape_string($db, $data);
	}

	return "";
}


//connect to a database and return db handle
function mm_db_connect($server, $user, $password, $db_name, $type) {
	if($type === "mysql") {
		$db = mysqli_connect($server, $user, $password, $db_name);
		if($db) {
			if(mysqli_set_charset($db, "utf8")) {
				return $db;
			}
			//close connection if previous call failed
			mysqli_close($db);
		}
	} elseif($type === "pgsql") {
		$connect_str = "host='".$server.
			"' user='".$user.
			"' password='".$password.
			"' dbname='".$db_name."'";
		$db          = pg_connect($connect_str);
		if($db) {
			//FIXME: how to specify UTF-8 on all PostgreSQL versions ?
			if(pg_set_client_encoding($db, "UNICODE") === 0) {
				return $db;
			}
			//close connection if previous call failed
			pg_close($db);
		}
	}

	return FALSE;
}


//close a database connection
function mm_db_close($db_handle, $type) {
	if($type === "mysql") {
		return mysqli_close($db_handle);
	} elseif($type === "pgsql") {
		return pg_close($db_handle);
	}

	return FALSE;
}


//execute a database query
function mm_db_query($query, $db_handle, $type) {
	if($type === "mysql") {
		return mysqli_query($db_handle, $query, MYSQLI_STORE_RESULT);
	} elseif($type === "pgsql") {
		return pg_query($db_handle, $query);
	}

	return FALSE;
}


//get SQL error description
function mm_db_error($db_handle) {
	if($type === "mysql") {
		return mysqli_error($db_handle);
	} elseif($type === "pgsql") {
		return pg_last_error($db_handle);
	}

	return FALSE;
}


//process db query - get number of rows from result
function mm_db_num_rows($result, $type) {
	if($type === "mysql") {
		return mysqli_num_rows($result);
	} elseif($type === "pgsql") {
		return pg_num_rows($result);
	}

	return FALSE;
}


//process db query - get next row from result as array
function mm_db_fetch_array($result, $type) {
	if($type === "mysql") {
		return mysqli_fetch_array($result, MYSQLI_BOTH);
	} elseif($type === "pgsql") {
		return pg_fetch_array($result);
	}

	return FALSE;
}


//process db query - get next row from result as associative array
function mm_db_fetch_assoc($result, $type) {
	if($type === "mysql") {
		return mysqli_fetch_assoc($result);
	} elseif($type === "pgsql") {
		return pg_fetch_assoc($result);
	}

	return FALSE;
}


//process database query - get next row from result as numerical array
function mm_db_fetch_row($result, $type) {
	if($type === "mysql") {
		return mysqli_fetch_row($result);
	} elseif($type === "pgsql") {
		return pg_fetch_row($result);
	}

	return FALSE;
}


//free results of a database query
function mm_db_free_result($res_handle, $type) {
	if($type === "mysql") {
		return mysqli_free_result($res_handle);
	} elseif($type === "pgsql") {
		return pg_free_result($res_handle);
	}

	return FALSE;
}

/*
//prepare a prepared statement
function mm_db_prepare($query, $db_handle, $type) {
//TODO: handle different placeholders transparently
    if ($type === "mysql") {
        // query must contain a question mark as placeholder for each 
        // parameter to be bound to a variable
        $stmt = mysqli_stmt_init($db_handle);
        if (mysqli_stmt_prepare($stmt, $query)) {
            return $stmt;
        }
    }
    elseif ($type === "pgsql") {
        // query must contain numbered placeholders $1, $2, ... for 
        // each parameter to be bound to a variable
        if (pg_prepare($db_handle, "", $query)) {
            return "";
        }
    }
    return FALSE;
}


//execute a prepared statement
function mm_db_execute($db_stmt, $db_handle, $type, \$param) {
    if ($type === "mysql") {
        return mysqli_stmt_bind_param($db_handle, $stmt);
    }
    elseif ($type === "pgsql") {
        return pg_execute($db_handle, $db_stmt, $param);
    }
    return FALSE;
}
*/

// escape each quotation mark (") in the input string
// so that it is safe to be used as quoted string in output functions
function mm_esc_quot($in) {
	$out = preg_replace('/"/', '\"', $in);

	return $out;
}


// escape chars in the input string so that it is safe to be 
// inserted into javascript code
function mm_esc_js($in) {
	$out = preg_replace('-/-', '\/', $in);
	$out = preg_replace('-\x0A-', '\\n', $out);
	$out = preg_replace('-\x0D-', '\\r', $out);

	return $out;
}


// escape chars in the input string so that it is safe to be 
// inserted into html code
function mm_esc_html($in) {
	// this rule must be executed first because it would corrupt
	// the other replacements
	$out = preg_replace('-&-', '&amp;', $in);
	$out = preg_replace('-<-', '&lt;', $out);
	$out = preg_replace('->-', '&gt;', $out);
	$out = preg_replace('-"-', '&quot;', $out);
	$out = preg_replace("-'-", '&#039;', $out);

	return $out;
}


// remove html tags from a text string
function mm_strip_html($in) {
	$out = preg_replace("-<[^ <][^>]*>-", '', $in);

	return $out;
}


//TODO:
function mm_allow_only_basic_html($text) {
	// allow line break
	$text = preg_replace("-&lt;br ?/?&gt;-", "<br />", $text);

	// allow simple logical text formatting
	$text = preg_replace("-&lt;(/)?(pre|em|strong|code|samp|kbd|var|cite|dfn|abbr|acronym|q) *&gt;-", "<$1$2>", $text);

	// optional attributes should not be allowed here (potential security hole)
	//ERROR: not working for more than one attribute:
	//$text = preg_replace("-&lt;(pre|em|strong|code|samp|kbd|var|cite|dfn|abbr|acronym|q) (class|id|style|title|dir|lang)=&quot;(.*)&quot; *&gt;-", "<$1 $2=\"$3\">", $text);
	//$text = preg_replace("-&lt;(pre|em|strong|code|samp|kbd|var|cite|dfn|abbr|acronym|q) (class|id|style|title|dir|lang)=&#039;(.*)&#039; *&gt;-", "<$1 $2='$3'>", $text);
	// 'q' has the additional attribute 'cite'
	$text = preg_replace('-&lt;q +cite=&quot;([^"]*)&quot; *&gt;-', "<q cite=\"$1\">", $text);
	$text = preg_replace("-&lt;q +cite=&#039;([^']*)&#039; *&gt;-", "<q cite='$1'>", $text);

	// allow hyperlinks
	$text = preg_replace('-&lt;a +href=&quot;([^"]*)&quot;( +(accesskey|charset|hreflang|name|tabindex|type)=&quot;[^"]*&quot;)* *&gt;-', '<a href="$1"$2>', $text);
	$text = preg_replace("-&lt;a +href=&#039;([^']*)&#039;( +(accesskey|charset|hreflang|name|tabindex|type)=&#039;[^']*&#039;)* *&gt;-", "<a href='$1$2'>", $text);
	$text = preg_replace("-&lt;/a&gt;-", "</a>", $text);

	// allow image references
//ERROR: not working for more than one attribute:
	$text = preg_replace('-&lt;img ((src|alt|height|longdesc|width)=&quot;([^"]*)&quot;)+ */?&gt;-', "<img $1 />", $text);

//TODO: object tag
//    $text = preg_replace("", "", $text);

	return $text;
}


//compute next month of given date in format 'YYYYMM'
function mm_next_month($in) {
	$y = intval(substr($in, 0, 4));
	$m = intval(substr($in, 4, 2));
	$m++;
	if($m > 12) {
		$m = 1;
		$y++;
	}
	$out = sprintf("%04u%02u", $y, $m);

	return $out;
}


//compute previous month of given date in format 'YYYYMM'
function mm_prev_month($in) {
	$y = intval(substr($in, 0, 4));
	$m = intval(substr($in, 4, 2));
	$m--;
	if($m < 1) {
		$m = 12;
		$y--;
	}
	$out = sprintf("%04u%02u", $y, $m);

	return $out;
}


//test if 32bit integers are supported by the platform
function mm_test_int() {
	$i = 2147483647;
	if(!is_int($i) or $i != intval($i)) {
		return FALSE;
	}

	return TRUE;
}


?>
