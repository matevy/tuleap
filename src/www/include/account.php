<?php
//
// SourceForge: Breaking Down the Barriers to Open Source Development
// Copyright 1999-2000 (c) The SourceForge Crew
// http://sourceforge.net
//
// 
//
// adduser.php - All the forms and functions to manage unix users
//

require_once('common/mail/Mail.class.php');
require_once('common/password/PasswordStrategy.class.php');
require_once('common/password/PasswordRegexpValidator.class.php');
require_once('common/widget/WidgetLayoutManager.class.php');
require_once('common/event/EventManager.class.php');
require_once('common/valid/Rule.class.php');




// ***** function account_pwvalid()
// ***** check for valid password
function account_pwvalid($pw, &$errors) {
    $password_strategy =& new PasswordStrategy();
    include($GLOBALS['Language']->getContent('account/password_strategy'));
    $valid = $password_strategy->validate($pw);
    $errors = $password_strategy->errors;
    return $valid;
}

// Set user password (Unix, Web)
function account_set_password($user_id,$password) {
    $um   = UserManager::instance();
    $user = $um->getUserById($user_id);
    $user->setPassword($password);
    return $um->updateDb($user);
}

// Add user to an existing project
function account_add_user_to_group ($group_id,&$user_unix_name) {
    $um = UserManager::instance();
    $user = $um->findUser($user_unix_name);
    if ($user) {
        return account_add_user_obj_to_group($group_id, $user);
    } else {
        //user doesn't exist
        $GLOBALS['Response']->addFeedback('error', $Language->getText('include_account','user_not_exist'));
        return false;
    }
}

/**
 * Add a new user into a given project
 * 
 * @param Integer $group_id Project id
 * @param User    $user     User to add
 * 
 * @return Boolean
 */
function account_add_user_obj_to_group ($group_id, User $user) {
    //user was found but if it's a pending account adding
    //is not allowed
    if (!$user->isActive() && !$user->isRestricted()) {
        $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('include_account', 'account_notactive', $user->getUserName()));
        return false;
    }

        //if not already a member, add it
    $res_member = db_query("SELECT user_id FROM user_group WHERE user_id=".$user->getId()." AND group_id='".db_ei($group_id)."'");
    if (db_numrows($res_member) < 1) {
        //not already a member
        db_query("INSERT INTO user_group (user_id,group_id) VALUES (".db_ei($user->getId()).",".db_ei($group_id).")");


        //if no unix account, give them a unix_uid
        if ($user->getUnixStatus() == 'N' || !$user->getUnixUid()) {
            $user->setUnixStatus('A');
            $um = UserManager::instance();
            $um->assignNextUnixUid($user);
            $um->updateDb($user);
        }

        // Raise an event
        $em = EventManager::instance();
        $em->processEvent('project_admin_add_user', array(
                'group_id'       => $group_id,
                'user_id'        => $user->getId(),
                'user_unix_name' => $user->getUserName(),
        ));

        $GLOBALS['Response']->addFeedback('info', $GLOBALS['Language']->getText('include_account','user_added'));
        account_send_add_user_to_group_email($group_id, $user->getId());
        group_add_history('added_user', $user->getUserName(), $group_id, array($user->getUserName()));

        return true;
    } else {
        $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('include_account','user_already_member'));
    }
    return false;
}

// Warn user she has been added to a project
function account_send_add_user_to_group_email($group_id,$user_id) {
  global $Language;
    $base_url = get_server_url();

    // Get email address
    $res = db_query("SELECT email FROM user WHERE user_id=".db_ei($user_id));
    if (db_numrows($res) > 0) {
        $email_address = db_result($res,0,'email');
        $res2 = db_query("SELECT group_name,unix_group_name FROM groups WHERE group_id=".db_ei($group_id));
        if (db_numrows($res2) > 0) {
            $group_name = db_result($res2,0,'group_name');
            $unix_group_name = db_result($res2,0,'unix_group_name');
            // $message is defined in the content file
            include($Language->getContent('include/add_user_to_group_email'));
            
            list($host,$port) = explode(':',$GLOBALS['sys_default_domain']);		
            $mail =& new Mail();
            $mail->setTo($email_address);
            $mail->setFrom($GLOBALS['sys_noreply']);
            $mail->setSubject($Language->getText('include_account','welcome',array($GLOBALS['sys_name'],$group_name)));
            $mail->setBody($message);
            if (!$mail->send()) {
                $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('global', 'mail_failed', array($GLOBALS['sys_email_admin'])));
            }
        }
    }
}

/**
 * Remove a user from a project
 *
 * @param Integer $groupId Project id
 * @param Integer $userId  User id
 */
function account_remove_user_from_group($groupId, $userId) {
    $pm = ProjectManager::instance();
    $res=db_query("DELETE FROM user_group WHERE group_id='$groupId' AND user_id='$userId' AND admin_flags <> 'A'");
    if (!$res || db_affected_rows($res) < 1) {
        $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('project_admin_index','user_not_removed'));
    } else {
        // Raise an event
        $em = EventManager::instance();
        $em->processEvent('project_admin_remove_user', array(
                'group_id' => $groupId,
                'user_id' => $userId
        ));

        //
        //  get the Group object
        //
        $group = $pm->getProject($groupId);
        if (!$group || !is_object($group) || $group->isError()) {
            exit_no_group();
        }
        $atf = new ArtifactTypeFactory($group);
        if (!$group || !is_object($group) || $group->isError()) {
            $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('project_admin_index','not_get_atf'));
        }

        // Get the artfact type list
        $at_arr = $atf->getArtifactTypes();

        if ($at_arr && count($at_arr) > 0) {
            for ($j = 0; $j < count($at_arr); $j++) {
                if ( !$at_arr[$j]->deleteUser($userId) ) {
                    $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('project_admin_index','del_tracker_perm_fail',$at_arr[$j]->getName()));
                }
            }
        }

        // Remove user from ugroups attached to this project
        if (!ugroup_delete_user_from_project_ugroups($groupId,$userId)) {
            $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('project_admin_index','del_user_from_ug_fail'));
        }
        $name = user_getname($userId);
        $GLOBALS['Response']->addFeedback('info', $GLOBALS['Language']->getText('project_admin_index','user_removed').' ('.$name.')');
        group_add_history ('removed_user',user_getname($userId)." ($userId)",$groupId);
        return true;
    }
    return false;
}

// Generate a valid Unix login name from the email address.
function account_make_login_from_email($email) {
    $pattern = "/^(.*)@.*$/";
    $replacement = "$1";
    $name=preg_replace($pattern, $replacement, $email);
    $name = substr($name, 0, 32);
    $name = strtr($name, ".:;,?%^*(){}[]<>+=$", "___________________");
    $name = strtr($name, "�a��e�u�", "aaeeeuuc");
    return strtolower($name);
}


function account_namevalid($name, $key = '') {
  global $Language;
    // no spaces
    if (strrpos($name,' ') > 0) {
        if ($key == '') {
            $k = 'login_err';
        } else {
            $k = $key . '_spaces';
        }
        $GLOBALS['register_error'] = $Language->getText('include_account', $k);	
        return 0;
    }

    $rule = new Rule_UserNameFormat();

    // must have at least one character
    // MV: not useful because we already have both 'min length' and
    // 'valid chars' rules
    // NT: still useful since it checks if the name does not start with a digit
    if (strspn($name,"abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ") == 0) {
        $GLOBALS['register_error'] = $Language->getText('include_account','char_err');
        return 0;
    }

    // must contain all legal characters
    if ($rule->containsIllegalChars($name)) {
        $GLOBALS['register_error'] = $Language->getText('include_account','illegal_char');
        return 0;
    }

    // min and max length
    if ($rule->lessThanMin($name)) {
        $GLOBALS['register_error'] = $Language->getText('include_account','name_too_short');
        return 0;
    }
    if ($rule->greaterThanMax($name)) {
        $GLOBALS['register_error'] = $Language->getText('include_account','name_too_long');
        return 0;
    }

    // illegal names
    if ($rule->isNotLegalName($name)) {
        $GLOBALS['register_error'] = $Language->getText('include_account','reserved');
        return 0;
    }
    if ($rule->isCvsAccount($name)) {
        $GLOBALS['register_error'] = $Language->getText('include_account','reserved_cvs');
        return 0;
    }
        
    return 1;
}

function account_groupnamevalid($name) {
  global $Language;
	if (!account_namevalid($name, 'project')) return 0;
	
	// illegal names
	if (eregi("^((www[0-9]?)|(cvs[0-9]?)|(shell[0-9]?)|(ftp[0-9]?)|(irc[0-9]?)|(news[0-9]?)"
        . "|(mail[0-9]?)|(ns[0-9]?)|(download[0-9]?)|(pub)|(users)|(compile)|(lists)"
        . "|(slayer)|(orbital)|(tokyojoe)|(webdev)|(projects)|(cvs)|(slayer)|(monitor)|(mirrors?))$",$name)) {
        $GLOBALS['register_error'] = $Language->getText('include_account','reserved');
        return 0;
	}

    //Group name cannot contain underscore for DNS reasons.
	if (eregi("_",$name)) {
        $GLOBALS['register_error'] = $Language->getText('include_account','dns_error');
        return 0;
	}

	return 1;
}


// print out shell selects
function account_shellselects($current) {
	$shells = file("/etc/shells");

	for ($i = 0; $i < count($shells); $i++) {
        $this_shell = chop($shells[$i]);

        if ($current == $this_shell) {
        	echo "<option selected value=$this_shell>$this_shell</option>\n";
        } else {
        	echo "<option value=$this_shell>$this_shell</option>\n";
        }
	}
}
// Set user password (Unix, Web)
function account_create($loginname=''
                        ,$pw=''
                        ,$ldap_id=''
                        ,$realname=''
                        ,$register_purpose=''
                        ,$email=''
                        ,$status='P'
                        ,$confirm_hash=''
                        ,$mail_site=0
                        ,$mail_va=0
                        ,$timezone='GMT'
                        ,$lang_id='en_US'
                        ,$unix_status='N'
                        ,$expiry_date=0
                        ) {
    $um   = UserManager::instance();
    $user = new User();
    $user->setUserName($loginname);
    $user->setPassword($pw);
    $user->setLdapId($ldap_id);
    $user->setRegisterPurpose($register_purpose);
    $user->setEmail($email);
    $user->setStatus($status);
    $user->setConfirmHash($confirm_hash);
    $user->setMailSiteUpdates($mail_site);
    $user->setMailVA($mail_va);
    $user->setTimezone($timezone);
    $user->setLanguageID($lang_id);
    $user->setUnixStatus($unix_status);
    $user->setExpiryDate($expiry_date);
    
    $u = $um->createAccount($user);
    if ($u) {
        return $u->getId();
    } else {
        return $u;
    }
}
function account_create_mypage($user_id) {
    $um   = UserManager::instance();
    return $um->accountCreateMyPage($user_id);
}

function account_redirect_after_login() {
    global $pv;  

    $em =& EventManager::instance();
    $em->processEvent('account_redirect_after_login', null);

    if(array_key_exists('return_to', $_REQUEST) && $_REQUEST['return_to'] != '') {
        $returnToToken = parse_url($_REQUEST['return_to']);
        if(preg_match('{/my(/|/index.php|)}i', $returnToToken['path'])) {
            util_return_to('/my/index.php');
        }
        else {
            util_return_to('/my/redirect.php');
        }
    }
    else {
        if (isset($pv) && $pv == 2) {
            util_return_to('/my/index.php?pv=2');
	} else {
	    util_return_to('/my/index.php');
        }
    }
}

?>
