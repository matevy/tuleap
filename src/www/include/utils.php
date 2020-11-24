<?php
/**
 * Copyright (c) Enalean, 2016-Present. All Rights Reserved.
 * Copyright 1999-2000 (c) The SourceForge Crew
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

require_once('session.php');

function util_microtime_float($offset = null)
{
    list($usec, $sec) = explode(" ", microtime());
    $now = ((float)$usec + (float)$sec);
    return ($offset !== null) ? ($now - $offset) : $now;
}

// This function returns a string of the date $value with the format $format and
// if this date is not set, return the default value $default_value
function format_date($format, $value, $default_value = '-')
{
    if ($value == 0) {
        return $default_value;
    } else {
        return date($format, $value);
    }
}

/**
* Convert a date in sys_datefmt (Y-M-d H:i ex: 2004-Feb-03 16:13)
* into the user defined format.
* This format is depending on the choosen language, and is defined
* in the site-content file <language>.tab
*
* @global $sys_datefmt the user preference date format defined in the language file, and set by pre.php
*
* @param string $date the date in the sys_datefmt format (Y-M-d H:i ex: 2004-Feb-03 16:13)
* @return string the date in the user format, or null if the conversion was not possible or wrong
*/
function util_sysdatefmt_to_userdateformat($date)
{

    $user_date = null;
    $unix_timestamp = util_sysdatefmt_to_unixtime($date);
    if ($unix_timestamp[1]) {
        $user_date = format_date($GLOBALS['Language']->getText('system', 'datefmt'), $unix_timestamp[0], null);
    } else {
        $user_date = null;
    }
    return $user_date;
}

function util_get_user_preferences_export_datefmt()
{
    $fmt = '';
    $u_pref = user_get_preference("user_csv_dateformat");
    switch ($u_pref) {
        case "month_day_year":
            $fmt = 'm/d/Y H:i:s';
            break;
        case "day_month_year":
            $fmt = 'd/m/Y H:i:s';
            break;
        default:
            $fmt = 'm/d/Y H:i:s';
            break;
    }
    return $fmt;
}

// Convert a date in sys_datefmt (Y-M-d H:i ex: 2004-Feb-03 16:13)
// into a Unix time. if string is empty return 0 (Epoch time)
// Returns a list with two values: the unix time and a boolean saying whether the conversion
// went well (true) or bad (false)
function util_importdatefmt_to_unixtime($date)
{
    $time = 0;
    if (!$date||$date=="") {
        return array($time,false);
    }

    if (strstr($date, "/") !== false) {
        list($year,$month,$day,$hour,$minute) = util_xlsdatefmt_explode($date);
        $time = mktime($hour, $minute, 0, $month, $day, $year);

        return array($time,true);
    }

    if (strstr($date, "-") !== false) {
        list($year,$month,$day,$hour,$minute) = util_sysdatefmt_explode($date);
        $time = mktime($hour, $minute, 0, $month, $day, $year);
        return array($time,true);
    }

    return array($time,false);
}

// Explode a date in the form of (m/d/Y H:i or d/m/Y H:i) into its a list of 5 parts (YYYY,MM,DD,H,i)
// if DD and MM are not defined then default them to 1
function util_xlsdatefmt_explode($date)
{

    if ($u_pref = user_get_preference("user_csv_dateformat")) {
    } else {
        $u_pref = PFUser::DEFAULT_CSV_DATEFORMAT;
    }

    $res = preg_match("/\s*(\d+)\/(\d+)\/(\d+) (\d+):(\d+)/", $date, $match);
    if ($res == 0) {
      //if it doesn't work try (n/j/Y) only
        $res = preg_match("/\s*(\d+)\/(\d+)\/(\d+)/", $date, $match);
        if ($res == 0) {
          // nothing is valid return Epoch time
            $year = '1970';
            $month='1';
            $day='1';
            $hour='0';
            $minute='0';
        } else {
            if ($u_pref == "day_month_year") {
                list(,$day,$month,$year) = $match;
                $hour='0';
                $minute='0';
            } else {
                list(,$month,$day,$year) = $match;
                $hour='0';
                $minute='0';
            }
        }
    } else {
        if ($u_pref == "day_month_year") {
            list(,$day,$month,$year,$hour,$minute) = $match;
        } else {
            list(,$month,$day,$year,$hour,$minute) = $match;
        }
    }

    return array($year,$month,$day,$hour,$minute);
}


// Convert a date as used in the bug tracking system and other services (YYYY-MM-DD)
// into a Unix time. if string is empty return 0 (Epoch time)
// Returns a list with two values: the unix time and a boolean saying whether the conversion
// went well (true) or bad (false)
function util_date_to_unixtime($date)
{
    $time = 0;
    if (!$date||$date=="") {
        return array($time,false);
    }

    list($year,$month,$day) = util_date_explode($date);
    $time = mktime(0, 0, 0, $month, $day, $year);
    return array($time,true);
}

// Explode a date in the form of (YYYY-MM-DD) into its a list of 3 parts (YYYY,MM,DD)
// if DD and MM are not defined then default them to 1
function util_date_explode($date)
{
    $res = preg_match("/\s*(\d+)-(\d+)-(\d+)/", $date, $match);
    if ($res == 0) {
    // if it doesn't work try YYYY-MM only
        $res = preg_match("/\s*(\d+)-(\d+)/", $date, $match);
        if ($res == 0) {
            // if it doesn't work try YYYY only
            $res = preg_match("/\s*(\d+)/", $date, $match);
            return array('1970','1','1');
            if ($res == 0) {
         // nothing is valid return Epoch time
                $year = '1970';
                $month='1';
                $day='1';
            } else {
                list(,$year) = $match ;
                $month='1';
                $day='1';
            }
        } else {
            list(,$year,$month) = $match ;
            $day='1';
        }
    } else {
        list(,$year,$month,$day) = $match;
    }
    return array($year,$month,$day);
}

// Convert a date in sys_datefmt (Y-M-d H:i ex: 2004-Feb-03 16:13)
// into a Unix time. if string is empty return 0 (Epoch time)
// Returns a list with two values: the unix time and a boolean saying whether the conversion
// went well (true) or bad (false)
function util_sysdatefmt_to_unixtime($date)
{
    $time = 0;
    if (!$date||$date=="") {
        return array($time,false);
    }

    list($year,$month,$day,$hour,$minute) = util_sysdatefmt_explode($date);
    $time = mktime($hour, $minute, 0, $month, $day, $year);
    return array($time,true);
}

// Explode a date in the form of (Y-M-d H:i) into its a list of 5 parts (YYYY,MM,DD,H,i)
// if DD and MM are not defined then default them to 1
function util_sysdatefmt_explode($date)
{
    $months = array("Jan"=>1, "Feb"=>2, "Mar"=>3, "Apr"=>4, "May"=>5, "Jun"=>6, "Jul"=>7, "Aug"=>8, "Sep"=>9, "Oct"=>10, "Nov"=>11, "Dec"=>12);

    $res = preg_match("/\s*(\d+)-(.+)-(\d+) (\d+):(\d+)/", $date, $match);
    if ($res == 0) {
      //if it doesn't work try (Y-M-d) only
        $res = preg_match("/\s*(\d+)-(.+)-(\d+)/", $date, $match);
        if ($res == 0) {
          // if it doesn't work try Y-M only
            $res = preg_match("/\s*(\d+)-(.+)/", $date, $match);
            if ($res == 0) {
         // if it doesn't work try YYYY only
                $res = preg_match("/\s*(\d+)/", $date, $match);
                if ($res == 0) {
                       // nothing is valid return Epoch time
                       $year = '1970';
                    $month='1';
                    $day='1';
                    $hour='0';
                    $minute='0';
                } else {
                          list(,$year) = $match ;
                    $month='1';
                    $day='1';
                    $hour='0';
                    $minute='0';
                }
            } else {
                list(,$year,$month) = $match ;
                $day='1';
                $hour='0';
                $minute='0';
            }
        } else {
            list(,$year,$month,$day) = $match;
            $hour='0';
            $minute='0';
        }
    } else {
        list(,$year,$month,$day,$hour,$minute) = $match;
    }

    return array($year,getMonth($month, $ok),$day,$hour,$minute);
}

//accept now month either in format Jan-Dec or 1-12
function getMonth($month, &$ok)
{
    $months = array("Jan"=>1, "Feb"=>2, "Mar"=>3, "Apr"=>4, "May"=>5, "Jun"=>6, "Jul"=>7, "Aug"=>8, "Sep"=>9, "Oct"=>10, "Nov"=>11, "Dec"=>12);
    if (array_key_exists($month, $months)) {
        $ok = true;
        return $months[$month];
    } elseif (in_array($month, $months)) {
        $ok = true;
        return $month;
    }
    $ok = false;
    return 1;
}

/**
 * ISO8601 dates are used by subversion.
 * It looks like YYYY-MM-DDTHH-mm-ss.������Z
 * where T separates date and time
 * and Z ends the time.
 * ������ are milliseconds.
 */
function util_ISO8601_to_date($ISO8601_date)
{
    $date = str_replace("T", " ", $ISO8601_date);
    $date = substr($date, 0, 16);
    return $date;
}

function util_prep_string_for_sendmail($body)
{
    $body=str_replace("\\", "\\\\", $body);
    $body=str_replace("\"", "\\\"", $body);
    $body=str_replace("\$", "\\\$", $body);
        $body=str_replace("`", "\\`", $body);
    return $body;
}

function util_unconvert_htmlspecialchars($string)
{
    if (strlen($string) < 1) {
        return '';
    } else {
        $string=str_replace('&nbsp;', ' ', $string);
        $string=str_replace('&quot;', '"', $string);
        $string=str_replace('&gt;', '>', $string);
        $string=str_replace('&lt;', '<', $string);
        $string=str_replace('&amp;', '&', $string);
        return $string;
    }
}

function util_result_column_to_array($result, $col = 0)
{
    /*
        Takes a result set and turns the optional column into
        an array
    */
    $rows=db_numrows($result);

    if ($rows > 0) {
        $arr=array();
        for ($i=0; $i<$rows; $i++) {
            $arr[$i]=db_result($result, $i, $col);
        }
    } else {
        $arr=array();
    }
    return $arr;
}

function result_column_to_array($result, $col = 0)
{
    /*
        backwards compatibility
    */
    return util_result_column_to_array($result, $col);
}

function util_wrap_find_space($string, $wrap)
{
    //echo"\n";
    $start=$wrap-5;
    $try=1;
    $found=false;

    while (!$found) {
     //find the first space starting at $start
        $pos=@strpos($string, ' ', $start);

     //if that space is too far over, go back and start more to the left
        if (($pos > ($wrap+5)) || !$pos) {
            $try++;
            $start=($wrap-($try*5));
         //if we've gotten so far left , just truncate the line
            if ($start<=10) {
                return $wrap;
            }
            $found=false;
        } else {
            $found=true;
        }
    }

    return $pos;
}

function util_line_wrap($text, $wrap = 80, $break = "\n")
{
    $paras = explode("\n", $text);

    $result = array();
    $i = 0;
    while ($i < count($paras)) {
        if (strlen($paras[$i]) <= $wrap) {
            $result[] = $paras[$i];
            $i++;
        } else {
            $pos=util_wrap_find_space($paras[$i], $wrap);

            $result[] = substr($paras[$i], 0, $pos);

            $new = trim(substr($paras[$i], $pos, strlen($paras[$i]) - $pos));
            if ($new != '') {
                $paras[$i] = $new;
                $pos=util_wrap_find_space($paras[$i], $wrap);
            } else {
                $i++;
            }
        }
    }
    return implode($break, $result);
}

function util_make_reference_links($data, $group_id)
{
    if (empty($data)) {
        return $data;
    }
    $reference_manager = ReferenceManager::instance();
    if ($group_id) {
        $reference_manager->insertReferences($data, $group_id);
    }

    return $data;
}

function util_user_link($username)
{

    global $Language;
    $hp = Codendi_HTMLPurifier::instance();
    if ($username == $Language->getText('global', 'none') || empty($username)) {
        return  $hp->purify($username, CODENDI_PURIFIER_CONVERT_HTML) ;
    }
    return '<a href="/users/'.urlencode($username).'">'. $hp->purify(UserHelper::instance()->getDisplayNameFromUserName($username), CODENDI_PURIFIER_CONVERT_HTML) .'</a>';
}

function util_user_nolink($username)
{
    global $Language;
    $hp = Codendi_HTMLPurifier::instance();
    if ($username == $Language->getText('global', 'none') || empty($username)) {
        return  $hp->purify($username, CODENDI_PURIFIER_CONVERT_HTML) ;
    }
    return $hp->purify(UserHelper::instance()->getDisplayNameFromUserName($username), CODENDI_PURIFIER_CONVERT_HTML) ;
}

function util_multi_user_link($usernames)
{

    $users = explode(", ", $usernames);
    if (count($users) > 1) {
     // Multiple users

        $str = "";
        for ($i=0; $i<count($users)-1; $i++) {
            $str .= util_user_link($users[$i]).", ";
        }
        $str .= util_user_link($users[$i]);
        return $str;
    } else {
     // Single user name
        return util_user_link($usernames);
    }
}

function util_multi_user_nolink($usernames)
{

    $users = explode(", ", $usernames);
    if (count($users) > 1) {
     // Multiple users

        $str = "";
        for ($i=0; $i<count($users)-1; $i++) {
            $str .= util_user_nolink($users[$i]).", ";
        }
        $str .= util_user_nolink($users[$i]);
        return $str;
    } else {
     // Single user name
        return util_user_nolink($usernames);
    }
}

function util_double_diff_array($arr1, $arr2)
{

    // first transform both arrays in hashes
    $h1 = [];
    $h2 = [];
    foreach ($arr1 as $v) {
        $h1[$v] = $v;
    }
    foreach ($arr2 as $v) {
        $h2[$v] = $v;
    }

    $deleted = array();
    foreach ($h1 as $k => $v) {
        if (! isset($h2[$k])) {
            $deleted[] = $k;
        }
    }
    $added = [];
    foreach ($h2 as $k => $v) {
        if (!isset($h1[$k])) {
            $added[] = $k;
        }
    }

    return array($deleted, $added);
}

// Deprecated
function get_priority_color($index)
{
    return $GLOBALS['HTML']->getPriorityColor($index);
}

function ShowResultSet($result, $title = "Untitled", $linkify = false)
{
    global $group_id,$HTML;
    /*
        Very simple, plain way to show a generic result set
        Accepts a result set and title
        Makes certain items into HTML links
    */

    if ($result) {
        $rows  =  db_numrows($result);
        $cols  =  db_numfields($result);

        echo '
			<TABLE BORDER="0" WIDTH="100%">';

     /*  Create the title  */

        echo '
		<TR class="boxtitle">
		<TD COLSPAN="'.$cols.'" class="boxitem"><B>'.$title.'</B></TD></TR>';

     /*  Create the rows  */
        for ($j = 0; $j < $rows; $j++) {
            echo '<TR class="'. html_get_alt_row_color($j+1) .'">';
            for ($i = 0; $i < $cols; $i++) {
                if ($linkify && $i == 0) {
                    $link = '<A HREF="?';
                    $linkend = '</A>';
                    if ($linkify == "bug_cat") {
                        $link .= 'group_id='.$group_id.'&bug_cat_mod=y&bug_cat_id='.db_result($result, $j, 'bug_category_id').'">';
                    } elseif ($linkify == "bug_group") {
                        $link .= 'group_id='.$group_id.'&bug_group_mod=y&bug_group_id='.db_result($result, $j, 'bug_group_id').'">';
                    } elseif ($linkify == "patch_cat") {
                        $link .= 'group_id='.$group_id.'&patch_cat_mod=y&patch_cat_id='.db_result($result, $j, 'patch_category_id').'">';
                    } elseif ($linkify == "support_cat") {
                        $link .= 'group_id='.$group_id.'&support_cat_mod=y&support_cat_id='.db_result($result, $j, 'support_category_id').'">';
                    } elseif ($linkify == "pm_project") {
                        $link .= 'group_id='.$group_id.'&project_cat_mod=y&project_cat_id='.db_result($result, $j, 'group_project_id').'">';
                    } else {
                        $link = $linkend = '';
                    }
                } else {
                    $link = $linkend = '';
                }
                echo '<td>'.$link . db_result($result, $j, $i) . $linkend.'</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo db_error();
    }
}

// Clean up email address (remove starting and ending spaces),replace semicolon by comma and put to lower
// case
function util_cleanup_emails($addresses)
{
    $addresses=preg_replace("/\s+[,;]/", ",", $addresses);
    $addresses=preg_replace("/[,;]\s+/", ",", $addresses);
    $addresses=str_replace(";", ",", $addresses);
    return strtolower(rtrim(trim($addresses)));
}

// Clean up email address (remove spaces...) and add @... if it is a simple
// login name
function util_normalize_email($address)
{
    if (strpos(':', $GLOBALS['sys_default_domain']) === false) {
        $host = $GLOBALS['sys_default_domain'];
    } else {
        list($host, $port) = explode(':', $GLOBALS['sys_default_domain']);
    }
    $address = util_cleanup_emails($address);
    if (validate_email($address)) {
        return $address;
    } else {
        return $address."@$host";
    }
}

// Clean up email address (remove spaces...) and split comma or semi-colon separated emails
function util_split_emails($addresses)
{
    $addresses = util_cleanup_emails($addresses);
    return preg_split('/,/D', $addresses);
}

/**
 * Sparate invalid email addresses
 *
 * @param String $addresses List of mail addresses to be cleaned
 *
 * @return Array containing two arrays one containing good addresses the other contain bad ones
 */
function util_cleanup_email_list($addresses)
{
    $list             = util_split_emails($addresses);
    $cleanedAddresses = array();
    $badAddresses     = array();
    foreach ($list as $address) {
        if (validate_email($address)) {
            $cleanedAddresses[] = $address;
        } else {
            $badAddresses[] = $address;
        }
    }
    return array('clean' => $cleanedAddresses, 'bad' => $badAddresses);
}

// One Email Verification
function validate_email($address)
{
    $rule = new Rule_Email();
    return $rule->isValid($address);
}

// Verification of comma separated list of email addresses
function validate_emails($addresses)
{
    $arr = util_split_emails($addresses);
    foreach ($arr as $addr) {
        if (! validate_email($addr)) {
            return false;
        }
    }
    return true;
}
/**
     * Return if the email addresses are valid
     *
     * @param arr_email: list of email addresses
     * @param message (OUT): error message if an error is found
     * @param strict (IN): Parametrer for user_finder function
     *
     * @return bool
     */
function util_validateCCList(&$arr_email, &$message, $strict = false)
{
    global $Language;
    $valid = true;
    $message = "";
    $purifier = Codendi_HTMLPurifier::instance();
    foreach ($arr_email as $key => $cc) {
        // Make sure that the address is valid
        $ref = util_user_finder($cc, $strict);
        if (empty($ref)) {
            $valid = false;
            $message .= "'".$purifier->purify($cc)."'<br>";
            continue;
        } else {
            $arr_email[$key] = $ref;
        }
    }

    if (! $valid) {
        $message = $Language->getText('include_utils', 'address_problem').":"
            . "<blockquote>$message</blockquote>"
            . $Language->getText('include_utils', 'email_explain');
    }

    return $valid;
}


/**
 * Try to find the best user identifier for a given identifier.
 *
 * The best (from Codendi point of view) user identifier is the Codendi
 * user_name. But people don't remember Codendi user_name. A given user can
 * reference another user with his email, codendi user_name, ldap uid, ldap
 * common name.
 * This function returns the best identifier:
 * - First ask to plugins (mainly LDAP) if they know a codendi user with this
 *   identifier
 * - If no user found by plugin, test if identifier is a valid codendi username
 * - Otherwise, if not in strict mode (ie. doesn't mandate a valid codendi user)
 *   test if its a valid email address.
 * - Else, return an empty string (ie. not a valid identifier)
 *
 * @param String  $ident (IN)      A user identifier
 * @param bool $strict (IN)     If strict mode is enabled only Codendi user and ldap valid
 * entries are allowed. Otherwise, return an empty string
 *
 * @return String
 */
function util_user_finder($ident, $strict = true)
{
    $ident = trim($ident);
    $user = UserManager::instance()->findUser($ident);
    if ($user) {
        return $user->getUserName();
    } else {
        // Neither Plugins nor Codendi found a valid user with this
        // identifier. If allowed, return the identifier as email address
        // if the identifier is a valid email address.
        if (!$strict) {
            if (validate_email($ident)) {
                return $ident;
            }
        }
    }
    return '';
}

// this function get the css file for the theme
// Requirement: $sys_user_theme is already
// set (done by theme.php in pre.php)
function util_get_css_theme()
{
    return '/themes/'.$GLOBALS['sys_user_theme'].'/css/style.css';
}

// This function get the image file for the theme.
// The theme may be specified as an optional second parameter.
// If no theme parameter is given, the current global theme is used.
// If $absolute is true, then the generated path will be absolute,
// otherwise it is relative to $sys_urlroot.
function util_get_image_theme($fn, $the_theme = false, $absolute = false)
{
    $path = util_get_dir_image_theme($the_theme);
    if ($absolute) {
        $path = $GLOBALS['sys_urlroot'] . $path;
    }
    return $path.$fn;
}

// this function get the image directory for the theme
// (either given or current theme)
function util_get_dir_image_theme($the_theme = false)
{
    if (! $the_theme) {
        $the_theme = $GLOBALS['sys_user_theme'];
    }

    return '/themes/'.$the_theme.'/images/';
}

// Format a size in byte into a size in Mb
function formatByteToMb($size_byte)
{
    return intval($size_byte/(1024*1024));
}

/**
 * Return human readable sizes
 *
 * @link        http://aidanlister.com/repos/v/function.size_readable.php
 * @param       int     $size        size in bytes
 * @param       string  $max         maximum unit
 * @param       string  $system      'si' for SI, 'bi' for binary prefixes
 * @param       string  $retstring   return string format
 */
function size_readable($size, $max = null, $system = 'bi', $retstring = 'auto')
{
    // Pick units
    $systems['si']['prefix'] = array('B', 'K', 'MB', 'GB', 'TB', 'PB');
    $systems['si']['size']   = 1000;
    $systems['bi']['prefix'] = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');
    $systems['bi']['size']   = 1024;
    $sys = isset($systems[$system]) ? $systems[$system] : $systems['si'];

    // Max unit to display
    $depth = count($sys['prefix']) - 1;
    if ($max && false !== $d = array_search($max, $sys['prefix'])) {
        $depth = $d;
    }

    // Loop
    $i = 0;
    while (abs($size) >= $sys['size'] && $i < $depth) {
        $size /= $sys['size'];
        $i++;
    }

    // Adapt the decimal places to the number of digit:
    // 1.24 / 12.3 / 123
    if ($retstring == 'auto') {
        $nbDigit = (int)(log(abs($size))/log(10)) + 1;
        switch ($nbDigit) {
            case 1:
                $retstring = '%.2f %s';
                break;
            case 2:
                $retstring = '%.1f %s';
                break;
            default:
                $retstring = '%d %s';
                break;
        }
    }

    return sprintf($retstring, $size, $sys['prefix'][$i]);
}

// Return mailing list server URL
// Used e.g. when inserting links in emails
function get_list_server_url()
{
    $request = HTTPRequest::instance();
    if ($request->isSecure()) {
        return "https://".$GLOBALS['sys_lists_host'];
    } else {
        return "http://".$GLOBALS['sys_lists_host'];
    }
}


/**
 * util_check_fileupload() - determines if a filename is appropriate for upload
 *
 * @param       string  The name of the file being uploaded
 */
function util_check_fileupload($filename)
{

    /* Empty file is a valid file.
    This is because this function should be called
    unconditionally at the top of submit action processing
    and many forms have optional file upload. */
    if ($filename == 'none' || $filename == '') {
        return true;
    }

    /* This should be enough... */
    if (!is_uploaded_file($filename)) {
      //echo "$filename is not uploaded file";
        return false;
    }
    /* ... but we'd rather be paranoic */
    if (strstr($filename, '..')) {
        return false;
    }
    if (!is_file($filename)) {
      //echo "$filename is not file";
        return false;
    }
    if (!file_exists($filename)) {
      //echo "$filename does not exist";
        return false;
    }
    return true;
}



/**
 * Return the group name (i.e. project name) from the group_id
 */
function util_get_group_name_from_id($group_id)
{
    $sql = "SELECT group_name FROM groups WHERE group_id = ".db_ei($group_id);
    $result = db_query($sql);
    return db_result($result, 0, 0);
}


/**
 * Retrieve the artifact group_id, artifact_type_id and item name using the artifact id
 *
 * @param aid: the artifact id
 * @param group_id: the group id (OUT)
 * @param group_artifact_id: the tracker id (OUT)
 * @param art_name: the item name corresponding to this tracker (OUT) e.g. 'bug', 'defect', etc.
 *
 * @return bool
 */
function util_get_ids_from_aid($aid, &$art_group_id, &$atid, &$art_name)
{
    $sql = "SELECT group_artifact_id FROM artifact WHERE artifact_id = ".db_ei($aid);

    $result = db_query($sql);
    if ($result && db_numrows($result) > 0) {
        $atid = db_result($result, 0, 0);

        $sql = "SELECT group_id,item_name FROM artifact_group_list WHERE group_artifact_id = ".db_ei($atid);

        $result = db_query($sql);
        $rows=db_numrows($result);
        if (!$result || $rows < 1) {
            return false;
        }
        $art_group_id = db_result($result, 0, 'group_id');
        $art_name = db_result($result, 0, 'item_name');
        return true;
    } else {
        return false;
    }
}

/**
 * Return the group id (i.e. project) the commit belongs to
 *
 * @param cid: the commit id
 *
 * @return group_id, or 0 if group does not exist
 */
function util_get_group_from_commit_id($cid)
{
    $sql = "SELECT repositoryid FROM cvs_checkins WHERE commitid=".db_ei($cid);
    $res = db_query($sql);
    $repository_id = db_result($res, 0, 'repositoryid');
    if (!$repository_id) {
        return 0;
    }

    $sql = "SELECT repository FROM cvs_repositories WHERE id=".db_ei($repository_id);
    $res = db_query($sql);
    $repository = db_result($res, 0, 'repository');
    if (!$repository) {
        return 0;
    }

  // Remove ".*/cvsroot/" to get the project unix name
    $projname = preg_replace("/.*\/cvsroot\//i", "", $repository);
    if (!$projname) {
        return 0;
    }

    $sql = "SELECT group_id FROM groups WHERE unix_group_name='".db_es($projname)."'";
    $res = db_query($sql);
    return db_result($res, 0, 'group_id');
}

/**
 * getStringFromServer - get a string from Server environment
 *
 * @param string $key key of the wanted value
 * @return string the value
 */
function getStringFromServer($key)
{
    if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
    } else {
            return '';
    }
}

/**
 * If $text begins with $prefixe ends with $suffixe then returns the
 * translated name found in page $pagename. Else returns $name.
**/
function util_translate($text, $prefixe, $suffixe, $pagename)
{
    $new_text = $text;
    if (strpos($new_text, $prefixe) === 0 && strpos($new_text, $suffixe)+strlen($suffixe) === strlen($new_text)) {
        $new_text = $GLOBALS['Language']->getText($pagename, $new_text);
    }
    return $new_text;
}

/**
 * Translate the name of an ugroup
**/
function util_translate_name_ugroup($name)
{
    return util_translate($name, "ugroup_", "_name_key", "project_ugroup");
}
/**
 * Translate the description of an ugroup
**/
function util_translate_desc_ugroup($desc)
{
    return util_translate($desc, "ugroup_", "_desc_key", "project_ugroup");
}

function util_return_to($url)
{
    $request       = HTTPRequest::instance();
    $event_manager = EventManager::instance();
    $url_redirect  = new URLRedirect($event_manager);
    $return_to     = $request->get('return_to');
    $GLOBALS['Response']->redirect($url_redirect->makeReturnToUrl($url, $return_to));
    exit;
}

/**
 * @deprecated Use DateHelper::distanceOfTimeInWords() instead
 */
function util_distance_of_time_in_words($from_time, $to_time, $include_seconds = false)
{
    $distance_in_minutes = round((abs($to_time - $from_time))/60);
    $distance_in_seconds = round(abs($to_time - $from_time));

    if ($distance_in_minutes <= 1) {
        if (!$include_seconds) {
            return $GLOBALS['Language']->getText('include_utils', ($distance_in_minutes == 0) ? 'less_1_minute' : '1_minute');
        } else {
            if ($distance_in_seconds < 4) {
                return $GLOBALS['Language']->getText('include_utils', 'less_than_X_seconds', 5);
            } elseif ($distance_in_seconds < 9) {
                return $GLOBALS['Language']->getText('include_utils', 'less_than_X_seconds', 10);
            } elseif ($distance_in_seconds < 19) {
                return $GLOBALS['Language']->getText('include_utils', 'less_than_X_seconds', 20);
            } elseif ($distance_in_seconds < 39) {
                return $GLOBALS['Language']->getText('include_utils', 'half_a_minute');
            } elseif ($distance_in_seconds < 59) {
                return $GLOBALS['Language']->getText('include_utils', 'less_1_minute');
            } else {
                return $GLOBALS['Language']->getText('include_utils', '1_minute');
            }
        }
    } elseif ($distance_in_minutes <= 44) {
        return $GLOBALS['Language']->getText('include_utils', 'X_minutes', $distance_in_minutes);
    } elseif ($distance_in_minutes <= 89) {
        return $GLOBALS['Language']->getText('include_utils', 'about_1_hour');
    } elseif ($distance_in_minutes <= 1439) {
        return $GLOBALS['Language']->getText('include_utils', 'about_X_hours', round($distance_in_minutes / 60));
    } elseif ($distance_in_minutes <= 2879) {
        return $GLOBALS['Language']->getText('include_utils', 'about_1_day');
    } elseif ($distance_in_minutes <= 43199) {
        return $GLOBALS['Language']->getText('include_utils', 'X_days', round($distance_in_minutes / 1440));
    } elseif ($distance_in_minutes <= 86399) {
        return $GLOBALS['Language']->getText('include_utils', 'about_1_month');
    } elseif ($distance_in_minutes <= 525959) {
        return $GLOBALS['Language']->getText('include_utils', 'X_months', round($distance_in_minutes / 43200));
    } elseif ($distance_in_minutes <= 1051919) {
        return $GLOBALS['Language']->getText('include_utils', 'about_1_year');
    } else {
        return $GLOBALS['Language']->getText('include_utils', 'over_X_years', round($distance_in_minutes / 525960));
    }
}

/* expected result :
0            less than a minute      less than 5 seconds
2            less than a minute      less than 5 seconds
7            less than a minute      less than 10 seconds
12           less than a minute      less than 20 seconds
21           less than a minute      half a minute
30           1 minute                half a minute
50           1 minute                less than a minute
60           1 minute                1 minute
90           2 minutes               2 minutes
130          2 minutes               2 minutes
3000         about 1 hour            about 1 hour
6000         about 2 hours           about 2 hours
87000        1 day                   1 day
172860       2 days                  2 days
2592060      about 1 month           about 1 month
5184060      2 months                2 months
31557660     about 1 year            about 1 year
63115200     over 2 years            over 2 years

$now = time();
$times = array(
    '0' => $now - 0,
    '2' => $now - 2,
    '7' => $now - 7,
    '12' => $now - 12,
    '21' => $now - 21,
    '30' => $now - 30,
    '50' => $now - 50,
    '60' => $now - 60,
    '90' => $now - 90,
    '130' => $now - 130,
    50*60 => $now - 50*60,
    100*60 => $now - 100*60,
    1450*60 => $now - 1450*60,
    2881*60 => $now - 2881*60,
    43201*60 => $now - 43201*60,
    86401*60 => $now - 86401*60,
    525961*60 => $now - 525961*60,
    1051920*60 => $now - 1051920*60,
);
foreach($times as $key => $time) {
    echo $key ."\t\t\t". util_time_ago_in_words($time, false) ."\t\t\t". util_time_ago_in_words($time, true) ."\n";
}
*/

/**
 * Split an HTTP request in 2 parts: headers and body
 *
 * Header/body separator is CRLF
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html
 *
 * @param string $content
 * @return array
 */
function http_split_header_body($content)
{
    $body_header_separator = "\r\n\r\n";
    $end_of_headers        = strpos($content, $body_header_separator);
    $beginning_of_body     = $end_of_headers + strlen($body_header_separator);
    $headers               = substr($content, 0, $end_of_headers);
    $body                  = substr($content, $beginning_of_body);
    return array($headers, $body);
}
