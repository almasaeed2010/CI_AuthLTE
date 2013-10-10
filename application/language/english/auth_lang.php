<?php
if (!defined('BASEPATH'))
    exit('No direct script access allowed');
/*
 * Author: Abdullah A Almsaeed
 * Date: Sep 14, 2013
 * Description: Arabic
 */

//Registration Errors
$lang['auth.email_exists'] = 'The email address is already registered.';

//Login errors
$lang['auth.invalid_credentials'] = 'Invalid email or password';
$lang['auth.user_id_unavailable'] = 'The user id is not available';

//Groups and privileges errors
$lang['auth.priv_group_connection_exists'] = 'The connection between the privilege and group already exists';
$lang['auth.privilege_connection_exists'] = 'The connection between the privilege and the account already exists';
$lang['auth.group_name_exists'] = 'The group name already exists';
$lang['auth.privilege_name_exists'] = 'The privilege name already exists';

//Other errors
$lang['auth.invalid_args'] = 'The arguments given the function %s are invalid';
$lang['auth.user_banned'] = 'Login attempts exceeded. Please wait few seconds and try again';

//Email title
$lang['auth.password_reset_email_subject'] = 'Password Reset';
$lang['auth.email_verification_email_subject'] = 'Email Verification';