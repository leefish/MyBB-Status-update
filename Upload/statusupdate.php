<?php

/********************************************************************************************************************************
*
*  Status update (/statusupfates.php)
*  Author: Krzysztof "Supryk" Supryczyński
*  Copyright: © 2013 - 2015 @ Krzysztof "Supryk" Supryczyński @ All rights reserved
*  
*  Website: 
*  Description: User status update like IP.Board.
*
********************************************************************************************************************************/
/********************************************************************************************************************************
*
* This file is part of "Status update" plugin for MyBB.
* Copyright © 2013 - 2015 @ Krzysztof "Supryk" Supryczyński @ All rights reserved
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Lesser General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Lesser General Public License for more details.
*
* You should have received a copy of the GNU Lesser General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
********************************************************************************************************************************/
 
define('IN_MYBB', '1'); 
define('THIS_SCRIPT', 'statusupdate.php');
require "./global.php"; 

$templatelist = "statusupdate";
$templatelist .= ",multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";

require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

$lang->load("statusupdate");

if(!$db->table_exists("statusupdate"))
{
	error($lang->statusupdate_not_installed);
}

if($mybb->usergroup['canviewstatusupdate'] != '1')
{
	error_no_permission();
}

$plugins->run_hooks("statusupdate_start");

if($mybb->input['action'] == "do_add_comment_statusupdate"  && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key'), true))
	{
		error($lang->statusupdate_ivalid_post_key);
	}

	if($mybb->get_input('commenttext') == '')
	{
		error($lang->statusupdate_comment_empty);
	}
	
	if(strlen($mybb->get_input('commenttext')) > $mybb->settings['statusupdate_max_comment_symbols'])
	{
		error($lang->sprintf($lang->statusupdate_comment_too_long, $mybb->settings['statusupdate_max_comment_symbols']));
	}
		
	if($mybb->usergroup['canaddstatusupdatecomments'] != '1')
	{
		error($lang->statusupdate_no_permissions_to_add_comment);
	}
		
	if($mybb->get_input('sid', 1) == "0")
	{
		error($lang->statusupdate_ivalid_status_id);
	}
		
	$comment = array(
		"sid"			=> $mybb->get_input('sid', 1),
		"uid"				=> $mybb->get_input('uid', 1),
		"text"				=> $db->escape_string($mybb->get_input('commenttext')),
		"dateline"	=> TIME_NOW,
		"ipaddress" => $session->packedip,
	);
	$db->insert_query("statusupdatecomments", $comment);
	
	$status = array(
		"totalcomments" =>  $mybb->get_input('totalcomments', 1) + 1,
	);
	
	$db->update_query("statusupdate", $status, "sid='".$mybb->get_input('sid', 1)."'");
		
	redirect("index.php", $lang->statusupdate_comment_added);
}

if(!$mybb->input['action'])
{	
	add_breadcrumb($lang->statusupdate_statusupdate);
	eval('$statusupdate_script = "'.$templates->get("statusupda_script").'";');
	eval('$statusupdate = "'.$templates->get("statusupdate").'";');
	output_page($statusupdate);
}