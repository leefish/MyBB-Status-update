<?php

/********************************************************************************************************************************
*
*  Status update (/inc/plugins/statusupfates.php)
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

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("admin_user_groups_edit_graph_tabs", "statusupdate_groups_graph_tabs");
$plugins->add_hook("admin_user_groups_edit_graph", "statusupdate_groups_graph");
$plugins->add_hook("admin_user_groups_edit_commit", "statusupdate_groups_commit");
$plugins->add_hook("usercp_menu_built", "statusupdate_usercp_menu_built");
$plugins->add_hook("usercp_start", "statusupdate_usercp");
$plugins->add_hook("index_start", "statusupdate_index");
$plugins->add_hook("global_start", "statusupdate_templatelist");

function statusupdate_info()
{
    global $lang;
    $lang->load("config_statusupdate");
	
	return array(
		"name"				=> $lang->statusupdate_name,
		"description"		=> $lang->statusupdate_desc,
		"website"			=> "",
		"author"			=> "Krzysztof \"Supryk\" Supryczyński",
		"authorsite"		=> "",
		"version"			=> "1.0",
		"compatibility"		=> "1801,1802,1803,1804,1805,1806",
		"codename"			=> "status_update",
	);
}

function statusupdate_is_installed()
{
	global $db;

	return $db->num_rows($db->simple_select("settinggroups", "*", "name=\"statusupdate\""));
}

function statusupdate_install()
{
	global $db, $lang, $mybb, $cache, $session;
	$lang->load("config_statusupdate");
	
	if(!in_array($mybb->version_code, explode("," ,"1801,1802,1803,1804,1805,1806")))
	{
		flash_message($lang->statusupdate_to_old_mybb, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	if(!file_exists(MYBB_ROOT."statusupdate.php")) 
	{
		flash_message($lang->statusupdate_upload_all_files, 'error');
		admin_redirect("index.php?module=config-plugins");
	}
	
	statusupdate_uninstall();
	
	if(!$db->table_exists("statusupdate"))
	{
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."statusupdate (
			`sid` INT NOT NULL auto_increment,
			`uid` TEXT NOT NULL,
			`text` TEXT NOT NULL,
			`dateline` TEXT NOT NULL,
			`lastedituid` TEXT NOT NULL,		
			`lasteditdate` TEXT NOT NULL,
			`totalcomments` INT(10) NOT NULL DEFAULT '0',
			`ipaddress` VARBINARY(16) NOT NULL DEFAULT '',
			PRIMARY KEY  (`sid`)
			) ENGINE=MyISAM ".$db->build_create_table_collation().";
		");
	}
	
	if(!$db->table_exists("statusupdatecomments"))
	{
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."statusupdatecomments (
			`cid` INT NOT NULL auto_increment,
			`sid` TEXT NOT NULL,
			`uid` TEXT NOT NULL,
			`text` TEXT NOT NULL,
			`dateline` TEXT NOT NULL,
			`ipaddress` VARBINARY(16) NOT NULL DEFAULT '',
			PRIMARY KEY  (`cid`)
			) ENGINE=MyISAM ".$db->build_create_table_collation().";
		");
	}
	
	$status = array(
		"sid"			=> "NULL",
		"uid"			=> $db->escape_string($mybb->user['uid']),
		"text"				=> $db->escape_string("Shine' bright like a diamond"),
		"dateline"	=> TIME_NOW,
		"lastedituid"	=> $db->escape_string($mybb->user['uid']),
		"lasteditdate"	=> TIME_NOW,
		"ipaddress" => my_inet_pton(get_ip()),
		"totalcomments" =>  $db->escape_string("0"),
	);
	$db->insert_query("statusupdate", $status);

	if(!$db->field_exists("canviewstatusupdate", "usergroups"))
	{
		$db->add_column("usergroups", "canviewstatusupdate", "TINYINT(1) NOT NULL DEFAULT '1'");
	}
	
	if(!$db->field_exists("canaddstatusupdate", "usergroups"))
	{
		$db->add_column("usergroups", "canaddstatusupdate", "TINYINT(1) NOT NULL DEFAULT '1'");
	}
	
	if(!$db->field_exists("caneditownstatusupdate", "usergroups"))
	{
		$db->add_column("usergroups", "caneditownstatusupdate", "TINYINT(1) NOT NULL DEFAULT '1'");
	}
	
	if(!$db->field_exists("candeleteownstatusupdate", "usergroups"))
	{
		$db->add_column("usergroups", "candeleteownstatusupdate", "TINYINT(1) NOT NULL DEFAULT '1'");
	}
	
	if(!$db->field_exists("canviewstatusupdatecomments", "usergroups"))
	{
		$db->add_column("usergroups", "canviewstatusupdatecomments", "TINYINT(1) NOT NULL DEFAULT '1'");
	}
	
	if(!$db->field_exists("canaddstatusupdatecomments", "usergroups"))
	{
		$db->add_column("usergroups", "canaddstatusupdatecomments", "TINYINT(1) NOT NULL DEFAULT '1'");
	}

	if(!$db->field_exists("caneditownstatusupdatecomments", "usergroups"))
	{
		$db->add_column("usergroups", "caneditownstatusupdatecomments", "TINYINT(1) NOT NULL DEFAULT '1'");
	}
	
	if(!$db->field_exists("candeleteownstatusupdatecomments", "usergroups"))
	{
		$db->add_column("usergroups", "candeleteownstatusupdatecomments", "TINYINT(1) NOT NULL DEFAULT '1'");
	}
	
	if(!$db->field_exists("canmoderatestatusupdate", "usergroups"))
	{
		$db->add_column("usergroups", "canmoderatestatusupdate", "TINYINT(1) NOT NULL DEFAULT '0'");
	}
	
	$usergroups = array(
		"canviewstatusupdate" => $db->escape_string("1"),
		"canaddstatusupdate" => $db->escape_string("1"),
		"caneditownstatusupdate" => $db->escape_string("1"),
		"candeleteownstatusupdate" => $db->escape_string("1"),
		"canviewstatusupdatecomments" => $db->escape_string("1"),
		"canaddstatusupdatecomments" => $db->escape_string("1"),
		"caneditownstatusupdatecomments" => $db->escape_string("1"),
		"candeleteownstatusupdatecomments" => $db->escape_string("1"),
		"canmoderatestatusupdate" => $db->escape_string("1"),
	);
	$db->update_query("usergroups", $usergroups, "gid='3' OR gid='4' OR gid='6'");
	
	$usergroups = array(
		"canviewstatusupdate" => $db->escape_string("1"),
		"canaddstatusupdate" => $db->escape_string("0"),
		"caneditownstatusupdate" => $db->escape_string("0"),
		"candeleteownstatusupdate" => $db->escape_string("0"),
		"canviewstatusupdatecomments" => $db->escape_string("1"),
		"canaddstatusupdatecomments" => $db->escape_string("0"),
		"caneditownstatusupdatecomments" => $db->escape_string("0"),
		"candeleteownstatusupdatecomments" => $db->escape_string("0"),
		"canmoderatestatusupdate" => $db->escape_string("0"),
	);
	$db->update_query("usergroups", $usergroups, "gid='1' OR gid='5' OR gid='7'");
	
	$cache->update_usergroups();
	
	$max_disporder = $db->fetch_field($db->simple_select("settinggroups", "MAX(disporder) AS max_disporder"), "max_disporder");
	
	$settinggroup = array(
		"gid"				=> "NULL",
		"name" 				=> "statusupdate", 
		"title"					=> $db->escape_string($lang->statusupdate_setting),
		"description" 		=> $db->escape_string($lang->statusupdate_setting_desc),
		"disporder" 		=> $max_disporder + 1,
		"isdefault" 		=> "0",
	);
	$gid = $db->insert_query("settinggroups", $settinggroup);
	
	$settings = array();
	
	$settings[] = array(
		"sid"					=> "NULL",
		"name"				=> "statusupdate_index_limit",
		"title"					=> $db->escape_string($lang->statusupdate_setting_index_limit),
		"description"		=> $db->escape_string($lang->statusupdate_setting_index_limit_desc),
		"optionscode"	=> "numeric",
		"value"				=> "5",
		"disporder"		=> "1",
		"gid"					=> $gid,
		"isdefault"			=> "0",
	);
	
	$settings[] = array(
		"sid"					=> "NULL",
		"name"				=> "statusupdate_usercp_limit",
		"title"					=> $db->escape_string($lang->statusupdate_setting_usercp_limit),
		"description"		=> $db->escape_string($lang->statusupdate_setting_usercp_limit_desc),
		"optionscode"	=> "numeric",
		"value"				=> "5",
		"disporder"		=> "2",
		"gid"					=> $gid,
		"isdefault"			=> "0",
	);
	
	$settings[] = array(
		"sid"					=> "NULL",
		"name"				=> "statusupdate_max_status_symbols",
		"title"					=> $db->escape_string($lang->statusupdate_setting_max_status_symbols),
		"description"		=> $db->escape_string($lang->statusupdate_setting_max_status_symbols_desc),
		"optionscode"	=> "numeric",
		"value"				=> "50",
		"disporder"		=> "3",
		"gid"					=> $gid,
		"isdefault"			=> "0",
	);
	
	$settings[] = array(
		"sid"					=> "NULL",
		"name"				=> "statusupdate_max_comment_symbols",
		"title"					=> $db->escape_string($lang->statusupdate_setting_max_comment_symbols),
		"description"		=> $db->escape_string($lang->statusupdate_setting_max_comment_symbols_desc),
		"optionscode"	=> "numeric",
		"value"				=> "50",
		"disporder"		=> "4",
		"gid"					=> $gid,
		"isdefault"			=> "0",
	);
	
	$settings[] = array(
		"sid"					=> "NULL",
		"name"				=> "statusupdate_drop_permissions",
		"title"					=> $db->escape_string($lang->statusupdate_setting_drop_permissions),
		"description"		=> $db->escape_string($lang->statusupdate_setting_drop_permissions_desc),
		"optionscode"	=> "onoff",
		"value"				=> "1",
		"disporder"		=> "5",
		"gid"					=> $gid,
		"isdefault"			=> "0",
	);
	
	$settings[] = array(
		"sid"					=> "NULL",
		"name"				=> "statusupdate_drop_tables",
		"title"					=> $db->escape_string($lang->statusupdate_setting_drop_tables),
		"description"		=> $db->escape_string($lang->statusupdate_setting_drop_tables_desc),
		"optionscode"	=> "onoff",
		"value"				=> "1",
		"disporder"		=> "6",
		"gid"					=> $gid,
		"isdefault"			=> "0",
	);
	
	$db->insert_query_multiple("settings", $settings);
	
	rebuild_settings();
	
	$templates = array();
		
	$templates[] = array(
		"tid" 			=> "NULL",
		"title" 		=> "statusupdate_index",
		"template" 		=> $db->escape_string('<table border="0" cellspacing="0" cellpadding="0" class="tborder">
	<tr>
		<td class="thead" colspan="2">
			<strong>{$lang->statusupdate_statusupdate}{$set}</strong>
		</td>
	</tr>
	{$tpl[\'row\']}
</table>
<br />'),
		"sid" 			=> "-1",
	);
	
	/*
	<tr><td class="{$bgcolor}">
<table width="100%" border="0" cellpadding="4" cellspacing="1">
<tr>
<td width="10%" valign="top">
{$tpl['avatar']}
</td>
<td width="90%" valign="top">
{$tpl['profilelink']}<span style="float: right;widthmargin-right: 5px;">{$tpl['date']}</span><br />
{$tpl['statustext']}
  <br /><span class="showcomment smalltext">Pokaż komentarze</span><span class="smalltext"> (1) | </span><span class="addcomment smalltext">Add comment</span>
  <div class="showcomment_row" style="display: none">
    Test
  </div>
      <div class="addcomment_row" style="display: none">
    Test
  </div>
</td></tr></table></td></tr>
	
	*/
	
	$templates[] = array(
		"tid" 			=> "NULL",
		"title" 		=> "statusupdate_index_row",
		"template" 		=> $db->escape_string('<tr>
	<td class="{$altbg}">
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td style="width: 10%; vertical-align: top; text-align: center;">
					<img src="{$statususeravatar[\'image\']}" alt="" style="float: left;margin-right: 3px;" width="32" height="32"/>
				</td>
				<td style="width: 90%;">
					{$tpl[\'statusprofilelink\']}
					<span style="float: right; padding: 0 2px 0 0; font-size: 10px;">
						{$tpl[\'statusdate\']}
					</span>
				</td>
			</tr>
			<tr>
				<td style="width: 10%;">&nbsp;</td>
				<td style="width: 90%; padding: 2px;">
					{$tpl[\'statustext\']}
				</td>
			</tr>
			<tr>
				<td colspan="2" style="">
					{$showcomments}{$statusupdatecommentcount}{$sep}{$addcomments}
					{$statusupdatecomment}
					{$statusupdatecommentform}
				</td>
			</tr>
		</table>
	</td>
</tr>'),
		"sid" 			=> "-1",
	);

	
	$templates[] = array(
		"tid" 			=> "NULL",
		"title" 		=> "statusupda_script",
		"template" 		=> $db->escape_string('<script type="text/javascript">
jQuery(document).ready(function($) {
    var secondButton, button, buttonShow, buttonHide;
    buttonHide = "{$lang->statusupdate_hidecomments}";
    buttonShow = "{$lang->statusupdate_showcomments}";
    button = $(".showcomment");
    secondButton = $(".addcomment");
    button.html(buttonShow);
    button.click(function(e) {
        e.preventDefault();
        if (!$(this).nextAll(".showcomment_row").is(":animated")) {
            $(this).nextAll(".showcomment_row").slideToggle("slow", function() {
                if($(this).is(":visible")) {
                    $(this).prevAll(".showcomment:first").html(buttonHide);
                } else {
                    $(this).prevAll(".showcomment:first").html(buttonShow);
                }
            });
        }
    });
    secondButton.click(function(e) {
        e.preventDefault();
        if (!$(this).nextAll(".addcomment_row").is(":animated")) {
            $(this).nextAll(".addcomment_row").slideToggle("slow");
        }
    });
});
</script>'),
		"sid" 			=> "-1",
	);
	
	$templates[] = array(
		"tid" 			=> "NULL",
		"title" 		=> "statusupdate_index_row_show_comment",
		"template" 		=> $db->escape_string('<div class="showcomment_row" style="display:none">
		{$tpl[\'rowcomments\']}
</div>'),
		"sid" 			=> "-1",
	);
	
	$templates[] = array(
		"tid" 					=> "NULL",
		"title" 				=> "statusupdate_index_row_show_comment_row",
		"template" 		=> $db->escape_string('<div class="statusupdate_comment">
	<div class="statusupdate_comment_avatar">
		<img src="{$commentuseravatar[\'image\']}" alt="" style="float: left;margin-right: 3px;"  width="28" height="28"/>
	</div>
	<div class="statusupdate_comment_head">
		{$tpl[\'commentprofilelink\']}
		<span style="float: right;">
			{$tpl[\'commentdate\']}
		</span>
	</div>
</div>
<div class="statusupdate_comment_text">
	<div class="statusupdate_comment_null">&nbsp;</div>
	<div class="statusupdate_comment_text_row">
		{$tpl[\'commenttext\']}
	</div>
</div>'),
		"sid" 				=> "-1",
	);
	
	$templates[] = array(
		"tid" 					=> "NULL",
		"title" 				=> "statusupdate_index_row_add_comment_form",
		"template" 		=> $db->escape_string('<div class="addcomment_row" style="display:none">
        <form action="statusupdate.php" method="post" name="input">
           <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
           <input type="hidden" name="uid" value="{$mybb->user[\'uid\']}" />
		   <input type="hidden" name="sid" value="{$tpl[\'statussid\']}" />
		   <input type="hidden" name="totalcomments" value="{$tpl[\'statustotalcomments\']}" />
           <textarea name="commenttext" cols="29" rows="1" class="usercp_notepad" style="font-size:11px;float: right;"></textarea>
          <input type="submit" class="button" style="font-size:11px;float: right;" name="submit" value="{$lang->statusupdate_addcomment}" />
          <input type="hidden" name="action" value="do_add_comment_statusupdate" />
           <input type="hidden" name="confirm" value="1" />
        </form>
</div>'),
		"sid" 				=> "-1",
	);
	
	$templates[] = array(
		"tid" 					=> "NULL",
		"title" 				=> "statusupdate_usercp_menu",
		"template" 		=> $db->escape_string('<tr><td class="trow1 smalltext"><a href="usercp.php?action=statusupdate" class="usercp_nav_item usercp_nav_statusupdate">{$lang->statusupdate_statusupdate}</a></td></tr>'),
		"sid" 				=> "-1",
	);
	
	$templates[] = array(
		"tid" 					=> "NULL",
		"title" 				=> "statusupdate_usercp",
		"template" 		=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->statusupdate_statusupdate}</title>
{$headerinclude}
</head>
<body>
{$header}
{$statusupda_script}
<table width="100%" border="0" align="center">
<tr>
{$usercpnav}
<td valign="top">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tr>
		<td class="thead" colspan="4"><strong>{$lang->statusupdate_statusupdate}</strong></td>
	</tr>
	<tr>
		<td class="tcat" align="left" width="50%"><span class="smalltext"><strong>{$lang->statusupdate_text}</strong></span></td>
		<td class="tcat" align="center" width="25%"><span class="smalltext"><strong>{$lang->statusupdate_date}</strong></span></td>
		<td class="tcat" align="center" width="25%" colspan="2"><span class="smalltext"><strong>{$lang->statusupdate_options}</strong></span></td>
	</tr>
	{$statusupdate_statuses}
</table>
<br />
{$multipage}
<br />
{$statusupdate_add}
</td>
</tr>
</table>
{$footer}
</body>
</html>'),
		"sid" 				=> "-1",
	);
	
	$templates[] = array(
		"tid" 					=> "NULL",
		"title" 				=> "statusupdate_usercp_statuses",
		"template" 		=> $db->escape_string('<tr>
	<td align="left" class="{$bgcolor}" width="50%">{$tpl[\'statustext\']}</td>
	<td align="center" class="{$bgcolor}" width="25%">{$tpl[\'date\']}</td>
		<td align="center" class="{$bgcolor}" width="12,5%">{$statusupdate_action_edit}</td>
	<td align="center" class="{$bgcolor}" width="12,5%">{$statusupdate_action_delete}</td>
</tr>'),
		"sid" 				=> "-1",
	);
	
	$templates[] = array(
		"tid" 					=> "NULL",
		"title" 				=> "statusupdate_usercp_statuses_none",
		"template" 		=> $db->escape_string('<tr><td class="trow2" align="center" colspan="3">{$lang->statusupdate_none}</td></tr>'),
		"sid" 				=> "-1",
	);
	
	$templates[] = array(
		"tid" 					=> "NULL",
		"title" 				=> "statusupdate_usercp_add",
		"template" 		=> $db->escape_string('<form action="usercp.php" method="post" name="input">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="uid" value="{$mybb->user[\'uid\']}" />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tr>
		<td class="thead" colspan="3"><strong>{$lang->statusupdate_add}</strong></td>
	</tr>
	<tr>
		<td class="trow2" align="center" colspan="1" width="50%"><textarea name="statustext" cols="1" rows="3" class="usercp_notepad"></textarea></td>
      <td class="trow2" align="left" colspan="1" width="50%" valign="top">{$lang->statusupdate_rules}<br />{$lang->statusupdate_rules_1}<br />{$lang->statusupdate_rules_2}<br />{$lang->statusupdate_rules_3}</td>
	</tr>
</table>
<br />
<div align="center"><input type="submit" class="button" name="submit" value="{$lang->statusupdate_add_submit}" /></div>
<input type="hidden" name="action" value="do_add_statusupdate" />
<input type="hidden" name="confirm" value="1" />
</form>'),
		"sid" 				=> "-1",
	);
	
	$templates[] = array(
		"tid" 					=> "NULL",
		"title" 				=> "statusupdate_usercp_edit",
		"template" 		=> $db->escape_string('<form action="usercp.php" method="post" name="input">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="uid" value="{$mybb->user[\'uid\']}" />
<input type="hidden" name="sid" value="{$tpl[\'sid\']}" />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tr>
		<td class="thead" colspan="3"><strong>{$lang->statusupdate_edit}</strong></td>
	</tr>
	<tr>
		<td class="trow2" align="center" colspan="1" width="50%"><textarea name="statustext" cols="1" rows="3" class="usercp_notepad">{$tpl[\'text\']}</textarea></td>
      <td class="trow2" align="left" colspan="1" width="50%" valign="top">{$lang->statusupdate_rules}<br />{$lang->statusupdate_rules_1}<br />{$lang->statusupdate_rules_2}<br />{$lang->statusupdate_rules_3}</td>
	</tr>
</table>
<br />
<div align="center"><input type="submit" class="button" name="submit" value="{$lang->statusupdate_edit_submit}" /></div>
<input type="hidden" name="action" value="do_edit_statusupdate" />
<input type="hidden" name="confirm" value="1" />
</form>'),
		"sid" 				=> "-1",
	);
	
	$templates[] = array(
		"tid" 					=> "NULL",
		"title" 				=> "statusupdate_usercp_delete",
		"template" 		=> $db->escape_string('<form action="usercp.php" method="post" name="input">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="uid" value="{$mybb->user[\'uid\']}" />
<input type="hidden" name="sid" value="{$tpl[\'sid\']}" />
<input type="hidden" name="page" value="{$tpl[\'page\']}" />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tr>
		<td class="thead" colspan="3"><strong>{$lang->statusupdate_delete}</strong></td>
	</tr>
	<tr>
		<td class="trow2" align="left" colspan="1" width="50%">{$tpl[\'text\']}</td>
      <td class="trow2" align="left" colspan="1" width="50%" valign="top">{$lang->statusupdate_rules}<br />{$lang->statusupdate_rules_1}<br />{$lang->statusupdate_rules_2}<br />{$lang->statusupdate_rules_3}</td>
	</tr>
</table>
<br />
<div align="center"><input type="submit" class="button" name="submit" value="{$lang->statusupdate_delete_submit}" /></div>
<input type="hidden" name="action" value="do_delete_statusupdate" />
<input type="hidden" name="confirm" value="1" />
</form>'),
		"sid" 				=> "-1",
	);
	
	$templates[] = array(
		"tid" 					=> "NULL",
		"title" 				=> "statusupdate_usercp_action_edit",
		"template" 		=> $db->escape_string('<a href="{$edit_url}&goedit=1" class="button">{$lang->statusupdate_action_edit}</a>'),
		"sid" 				=> "-1",
	);
	
	$templates[] = array(
		"tid" 					=> "NULL",
		"title" 				=> "statusupdate_usercp_action_delete",
		"template" 		=> $db->escape_string('<a href="{$delete_url}&godelete=1" class="button">{$lang->statusupdate_action_delete}</a>'),
		"sid" 				=> "-1",
	);
	
	$templates[] = array(
		"tid" 					=> "NULL",
		"title" 				=> "statusupdate_usercp_add_denied",
		"template" 		=> $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tr>
		<td class="thead" colspan="3"><strong>{$lang->statusupdate_add}</strong></td>
	</tr>
	<tr>
		<td class="trow2" align="center" colspan="1">{$lang->statusupdate_add_denied}</td>
	</tr>
</table>
<br />'),
		"sid" 				=> "-1",
	);
	
	$templates[] = array(
		"tid" 					=> "NULL",
		"title" 				=> "statusupdate_usercp_edit_denied",
		"template" 		=> $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tr>
		<td class="thead" colspan="3"><strong>{$lang->statusupdate_edit}</strong></td>
	</tr>
	<tr>
		<td class="trow2" align="center" colspan="1">{$lang->statusupdate_edit_denied}</td>
	</tr>
</table>
<br />'),
		"sid" 				=> "-1",
	);
	
	$templates[] = array(
		"tid" 					=> "NULL",
		"title" 				=> "statusupdate_usercp_delete_denied",
		"template" 		=> $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tr>
		<td class="thead" colspan="3"><strong>{$lang->statusupdate_delete}</strong></td>
	</tr>
	<tr>
		<td class="trow2" align="center" colspan="1">{$lang->statusupdate_delete_denied}</td>
	</tr>
</table>
<br />'),
		"sid" 				=> "-1",
	);
	
	$db->insert_query_multiple("templates", $templates);
	
	$style = ".usercp_nav_statusupdate {
	background-position: 0 -340px;
}

.showcomment {
	cursor: pointer;
}

.addcomment {
	cursor: pointer;
}

.showcomment_row li {
	list-style-type: none;
}

.showcomment_row {
    display: table;
	margin-left: 13%;
}

.statusupdate_comment_text_row {
    padding: 2px;
}
	
.statusupdate_comment {
    display: table-row;    
    width: 100%;
}


.statusupdate_comment_avatar {
    display: table-cell;
    width: 10%;
    vertical-align: middle;    
}

.statusupdate_comment_head {
    display: table-cell;
    width: 90%;
    vertical-align: middle;    
}

.statusupdate_comment_head > span {
	float: right !important;
	font-size: 10px;
	padding: 0 3px 0 0;
}

.statusupdate_comment_text {
    display: table-row;
    width: 100%;
}

.statusupdate_comment_null  {
    display: table-cell;
    vertical-align: middle;    
}";
	
	$stylesheet = array(
		"sid"         		=> "NNULL",
		"name"         		=> "statusupdate.css",
		"cachefile"		=> "statusupdate.css",
		"tid"         			=> "1",
		"attachedto"   	=> "",
		"stylesheet"   	=> $db->escape_string($style),
		'lastmodified' 	=> TIME_NOW
	);

	$db->insert_query("themestylesheets", $stylesheet);
	
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

	cache_stylesheet(1, "statusupdate.css", $style);
	update_theme_stylesheet_list(1, false, true);
}

function statusupdate_uninstall()
{
    global $db, $lang, $mybb, $cache;
    $lang->load("config_statusupdate");
	
	if($mybb->settings['statusupdate_drop_tables'] == "1")
	{
		if($db->table_exists("statusupdate"))
		{
			$db->drop_table("statusupdate");
		}
		
		if($db->table_exists("statusupdatecomments"))
		{
			$db->drop_table("statusupdatecomments");
		}
	}
	
	if($mybb->settings['statusupdate_drop_permissions'] == "1")
	{
		if($db->field_exists("canviewstatusupdate", "usergroups"))
		{
			$db->drop_column("usergroups", "canviewstatusupdate");
		}
		
		if($db->field_exists("canaddstatusupdate", "usergroups"))
		{
			$db->drop_column("usergroups", "canaddstatusupdate");
		}
		
		if($db->field_exists("caneditownstatusupdate", "usergroups"))
		{
			$db->drop_column("usergroups", "caneditownstatusupdate");
		}
		
		if($db->field_exists("candeleteownstatusupdate", "usergroups"))
		{
			$db->drop_column("usergroups", "candeleteownstatusupdate");
		}
		
		if($db->field_exists("canviewstatusupdatecomments", "usergroups"))
		{
			$db->drop_column("usergroups", "canviewstatusupdatecomments");
		}
		
		if($db->field_exists("canaddstatusupdatecomments", "usergroups"))
		{
			$db->drop_column("usergroups", "canaddstatusupdatecomments");
		}

		if($db->field_exists("caneditownstatusupdatecomments", "usergroups"))
		{
			$db->drop_column("usergroups", "caneditownstatusupdatecomments");
		}
		
		if($db->field_exists("candeleteownstatusupdatecomments", "usergroups"))
		{
			$db->drop_column("usergroups", "candeleteownstatusupdatecomments");
		}
		
		if($db->field_exists("canmoderatestatusupdate", "usergroups"))
		{
			$db->drop_column("usergroups", "canmoderatestatusupdate");
		}
		
		$cache->update_usergroups();
	}
	
	$db->delete_query("settinggroups", "name = \"statusupdate\"");
	$db->delete_query("settings", "name LIKE \"statusupdate%\"");
	rebuild_settings();
	$db->delete_query("templates", "title LIKE \"statusupdate%\"");	
	$db->delete_query("themestylesheets", "name= \"statusupdate.css\"");
		
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    
    $query = $db->simple_select("themes", "tid");
    while($theme = $db->fetch_array($query))
    {
        @unlink(MYBB_ROOT."cache/themes/theme{$theme['tid']}/statusupdate.css");
        @unlink(MYBB_ROOT."cache/themes/theme{$theme['tid']}/statusupdate.min.css");
        update_theme_stylesheet_list($theme['tid'], false, true); 
    }
}

function statusupdate_activate()
{		
	statusupdate_deactivate();
	
	find_replace_templatesets('index', '#'.preg_quote('{$headerinclude}').'#', '{$headerinclude}'."\n".'{$statusupdate_script}');
	find_replace_templatesets('usercp_nav_misc', '#'.preg_quote('<tbody style="{$collapsed[\'usercpmisc_e\']}" id="usercpmisc_e">').'#', '<tbody style="{$collapsed[\'usercpmisc_e\']}" id="usercpmisc_e">'."\n\t".'{statusupdate}');
}

function statusupdate_deactivate()
{
	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	
	find_replace_templatesets('index', '#' . preg_quote("\n".'{$statusupdate_script}') . '#', '', 0);
	find_replace_templatesets('usercp_nav_misc', '#' . preg_quote("\n\t".'{statusupdate}') . '#', '', 0);
}

function statusupdate_groups_graph_tabs($tabs)
{
	global $lang;
	$lang->load("config_statusupdate");
	
	$tabs['statusupdate'] = $lang->statusupdate_statusupdate;
	return $tabs;
}

function statusupdate_groups_graph()
{
	global $lang, $form, $mybb;
	$lang->load("config_statusupdate");
	
	echo "<div id=\"tab_statusupdate\">";	
	$form_container = new FormContainer($lang->statusupdate_statusupdate);
	
	$statuses_options = array(
		$form->generate_check_box("canviewstatusupdate", 1, $lang->statusupdate_can_view_statusupdate, array("checked" => $mybb->input['canviewstatusupdate'])),
		$form->generate_check_box("canaddstatusupdate", 1, $lang->statusupdate_can_add_statusupdate, array("checked" => $mybb->input['canaddstatusupdate'])),
		$form->generate_check_box("caneditownstatusupdate", 1, $lang->statusupdate_can_edit_own_statusupdate, array("checked" => $mybb->input['caneditownstatusupdate'])),
		$form->generate_check_box("candeleteownstatusupdate", 1, $lang->statusupdate_can_delete_own_statusupdate, array("checked" => $mybb->input['candeleteownstatusupdate'])),
	);
	$form_container->output_row($lang->statusupdate_statusupdate_permissions, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $statuses_options)."</div>");
	
	$comments_options = array(
		$form->generate_check_box("canviewstatusupdatecomments", 1, $lang->statusupdate_can_view_statusupdate_comments, array("checked" => $mybb->input['canviewstatusupdatecomments'])),
		$form->generate_check_box("canaddstatusupdatecomments", 1, $lang->statusupdate_can_add_statusupdate_comments, array("checked" => $mybb->input['canaddstatusupdatecomments'])),
		$form->generate_check_box("caneditownstatusupdatecomments", 1, $lang->statusupdate_can_edit_statusupdate_comments, array("checked" => $mybb->input['caneditownstatusupdatecomments'])),
		$form->generate_check_box("candeleteownstatusupdatecomments", 1, $lang->statusupdate_can_delete_statusupdate_comments, array("checked" => $mybb->input['candeleteownstatusupdatecomments'])),
	);
	$form_container->output_row($lang->statusupdate_comments_permissions, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $comments_options)."</div>");
	
	$moderators = array(
		$form->generate_check_box("canmoderatestatusupdate", 1, $lang->statusupdate_can_moderate_statusupdate, array("checked" => $mybb->input['canmoderatestatusupdate'])),
	);
	$form_container->output_row($lang->statusupdate_moderators, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $moderators)."</div>");

	$form_container->end();
	echo "</div>";
}

function statusupdate_groups_commit()
{
	global $updated_group, $mybb;
	
	$updated_group['canviewstatusupdate'] = $mybb->get_input("canviewstatusupdate", 1);
	$updated_group['canaddstatusupdate'] = $mybb->get_input("canaddstatusupdate", 1);
	$updated_group['caneditownstatusupdate'] = $mybb->get_input("caneditownstatusupdate", 1);
	$updated_group['candeleteownstatusupdate'] = $mybb->get_input("candeleteownstatusupdate", 1);
	
	$updated_group['canviewstatusupdatecomments'] = $mybb->get_input("canviewstatusupdatecomments", 1);
	$updated_group['canaddstatusupdatecomments'] = $mybb->get_input("canaddstatusupdatecomments", 1);
	$updated_group['caneditownstatusupdatecomments'] = $mybb->get_input("caneditownstatusupdatecomments", 1);
	$updated_group['candeleteownstatusupdatecomments'] = $mybb->get_input("candeleteownstatusupdatecomments", 1);
	
	$updated_group['canmoderatestatusupdate'] = $mybb->get_input("canmoderatestatusupdate", 1);
}

function statusupdate_usercp_menu_built()
{
	global $lang, $mybb, $templates, $usercpnav, $statusupdate;
	$lang->load("statusupdate");
	
	if($mybb->usergroup['canviewstatusupdate'] != '1')
	{
		$statusupdate = "";
	}
	else
	{
		eval("\$statusupdate = \"".$templates->get("statusupdate_usercp_menu")."\";");
	}

	$usercpnav = str_replace("{statusupdate}", $statusupdate, $usercpnav);
}

function statusupdate_index()
{
	global $db, $lang, $mybb, $templates, $theme, $statusupdate, $statusupdate_script;
	$lang->load("statusupdate");
	
	if($mybb->usergroup['canviewstatusupdate'] != "1")
	{
		return;
	}
	
	require_once MYBB_ROOT."inc/class_parser.php";
	$parser = new postParser;
	
	$altbg = alt_trow();
	$tpl['row'] = "";
	
	if($mybb->settings['statusupdate_index_limit'] <= "0")
	{
		$mybb->settings['statusupdate_index_limit'] = "5";
	}
	
	$query = $db->query("
		SELECT 
		s.sid as statussid, s.uid as statusuid, s.text as statustext, s.dateline as statusdateline, s.lastedituid as statuslastedituid, s.lasteditdate as statuslasteditdate, s.totalcomments as statustotalcomments, s.ipaddress as statusipaddress,
		su.uid as statusuid, su.username as statususername, su.usergroup as statususergroup, su.displaygroup as statusdisplaygroup, su.avatar as statusavatar, su.avatardimensions as statusavatardimensions
		FROM ".TABLE_PREFIX."statusupdate s
		LEFT JOIN ".TABLE_PREFIX."users su ON (s.uid=su.uid)
		ORDER BY s.lasteditdate DESC
		LIMIT 0, ".$mybb->settings['statusupdate_index_limit']."");
	while($row = $db->fetch_array($query))
	{
		$tpl['statussid'] = $row['statussid'];
		$tpl['statususername'] = format_name($row['statususername'], $row['statususergroup'], $row['statusdisplaygroup']);
		$tpl['statusprofilelink'] = build_profile_link($tpl['statususername'], $row['statusuid']);
		$tpl['statusdate'] = my_date('relative', $row['statuslasteditdate']);
		
		$statususeravatar = format_avatar(htmlspecialchars_uni($row['statusavatar']));
		
		$parser_options = array(
			"allow_html" => 0,
			"allow_mycode" => 1,
			"allow_smilies" => 1,
			"allow_imgcode" => 0, 
			"allow_videocode" => 0, 
			"filter_badwords" => 1,
		);
		$tpl['statustext'] = $parser->parse_message($row['statustext'], $parser_options); 
		
		$tpl['statustotalcomments'] = my_number_format($row['statustotalcomments']);
			
		$showcomments = "";
		$statusupdatecommentcount = "";
		
		if($row['statustotalcomments'] > 0 && $mybb->usergroup['canviewstatusupdatecomments'] == '1')
		{
			$showcomments = "<span class=\"showcomment smalltext\">".$lang->statusupdate_showcomments."</span>";
			$statusupdatecommentcount = " <span class=\"smalltext\">(".$tpl['statustotalcomments'].")</span>";
			$tpl['rowcomments'] = "";
			
			static $comments;
			static $comments_check = false;
			
			if(!$comments_check)
			{	
				$querycomment = $db->query("
					SELECT 
					sc.cid as commentcid, sc.sid as commentsid, sc.uid as commentuid, sc.text as commenttext, sc.dateline as commentdateline, sc.ipaddress as commentipaddress,
					cu.uid as commentuid, cu.username as commentusername, cu.usergroup as commentusergroup, cu.displaygroup as commentdisplaygroup, cu.avatar as commentavatar, cu.avatardimensions as commentavatardimensions
					FROM ".TABLE_PREFIX."statusupdatecomments sc 
					LEFT JOIN ".TABLE_PREFIX."users cu ON (sc.uid=cu.uid)
					ORDER BY sc.dateline DESC");
				while($row = $db->fetch_array($querycomment))
				{
					$comments[$row['commentsid']][] = array(
						"commentcid" => $row['commentcid'],
						"commentsid" => $row['commentsid'],
						"commentuid" => $row['commentuid'],
						"commenttext" => $row['commenttext'],
						"commentdateline" => $row['commentdateline'],
						"commentipaddress" => $row['commentipaddress'],
						"commentuid" => $row['commentuid'],
						"commentusername" => $row['commentusername'],
						"commentusergroup" => $row['commentusergroup'],
						"commentdisplaygroup" => $row['commentdisplaygroup'],
						"commentavatar" => $row['commentavatar'],
						"commentavatardimensions" => $row['commentavatardimensions'],
					);
				}
					$comments_check = true;
			} 

			if(isset($comments[$tpl['statussid']]))
			{	
				foreach($comments[$tpl['statussid']] as $comment)
				{
					$tpl['commentusername'] = format_name($comment['commentusername'], $comment['commentusergroup'], $comment['commentdisplaygroup']);
					$tpl['commentprofilelink'] = build_profile_link($tpl['commentusername'], $comment['commentuid']);
					$tpl['commentdate'] = my_date('relative', $comment['commentdateline']);
						
					$commentuseravatar = format_avatar(htmlspecialchars_uni($comment['commentavatar']), $comment['commentavatardimensions']);

					$parser_options = array(
						"allow_html" => 0,
						"allow_mycode" => 1,
						"allow_smilies" => 1,
						"allow_imgcode" => 0, 
						"allow_videocode" => 0, 
						"filter_badwords" => 1,
					);
					
					$tpl['commenttext'] = $parser->parse_message($comment['commenttext'], $parser_options); 
					eval("\$tpl['rowcomments'] .= \"" . $templates->get("statusupdate_index_row_show_comment_row") . "\";");
				}
				
				eval("\$statusupdatecomment = \"".$templates->get("statusupdate_index_row_show_comment")."\";");
			}
		}
			
		$addcomments = "";
		
		if($mybb->usergroup['canaddstatusupdatecomments'] == '1')
		{
			$addcomments = "<span class=\"addcomment smalltext\">".$lang->statusupdate_addcomment."</span>";
			eval('$statusupdatecommentform = "'.$templates->get("statusupdate_index_row_add_comment_form").'";');
		}
			
		$br = "";
		
		if($showcomments || $addcomments)
		{
			$br = "<br />";
		}
			
		$sep = "";
			
		if($showcomments && $addcomments)
		{
			$sep = "<span class=\"smalltext\"> | </span>";
		}
			
		eval("\$tpl['row'] .= \"" . $templates->get("statusupdate_index_row") . "\";");
		$altbg = alt_trow();
	}
	
	if($mybb->usergroup['canaddstatusupdate'] == '1') 
	{
		$set = "<span><a href=\"".$mybb->settings['bburl']."/usercp.php?action=statusupdate\">".$lang->statusupdate_set."</a></span>";
	}
	
	eval('$statusupdate_script = "'.$templates->get("statusupda_script").'";');
	eval("\$statusupdate = \"".$templates->get("statusupdate_index")."\";");
}
	
function statusupdate_usercp()
{
	global $db, $lang, $mybb, $theme, $templates, $header, $footer, $headerinclude, $usercpnav, $session;
	$lang->load("statusupdate");
	
	if($mybb->input['action'] == "statusupdate")
	{
		if($mybb->usergroup['canviewstatusupdate'] != '1')
		{
			error_no_permission();
		}
		
		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;
		
		if($mybb->settings['statusupdate_usercp_limit'] <= "0")
		{
			$mybb->settings['statusupdate_usercp_limit'] = "5";
		}
				
		$query = $db->query("
			SELECT *
			FROM ".TABLE_PREFIX."statusupdate
			WHERE uid = '".$mybb->user['uid']."'
		");
		
		$statuscount = $db->num_rows($query);
		$perpage = $mybb->settings['statusupdate_usercp_limit'];
		$page = $mybb->get_input('page', 1);
		if($page > 0)
		{
			$start = ($page-1) * $perpage;
			$pages = $statuscount / $perpage;
			$pages = ceil($pages);
			
			if($page > $pages || $page <= 0)
			{
				$start = 0;
				$page = 1;
			}
		}
		else
		{
			$start = 0;
			$page = 1;
		}
		$end = $start + $perpage;
		$lower = $start+1;
		$upper = $end;
		
		if($upper > $statuscount)
		{
			$upper = $statuscount;
		}
		
		$multipage = multipage($statuscount, $perpage, $page, "usercp.php?action=statusupdate");
		
		$bgcolor = alt_trow();
		$statusupdate_statuses = "";
		
		$query = $db->query("
			SELECT *
			FROM ".TABLE_PREFIX."statusupdate
			WHERE uid='".$mybb->user['uid']."'
			ORDER BY lasteditdate DESC
			LIMIT $start, $perpage
		");
		
		if($db->num_rows($query))
		{
			while($row = $db->fetch_array($query))
			{
				$parser_options = array(
					"allow_html" => 0,
					"allow_mycode" => 1,
					"allow_smilies" => 1,
					"allow_imgcode" => 0, 
					"allow_videocode" => 0, 
					"filter_badwords" => 1,
				);
				
				$tpl['statustext'] = $parser->parse_message($row['text'], $parser_options); 
				$tpl['date'] = my_date('relative', $row['lasteditdate']);
				$tpl['sid'] = $row['sid'];
				$tpl['statuscomments'] = my_number_format($row['totalcomments']);
				$tpl['ipaddress'] = my_inet_ntop($db->unescape_binary($row['ipaddress']));
				
				$showcomments = "";
				$statusupdatecommentcount = "";
		
				if($row['statustotalcomments'] > 0 && $mybb->usergroup['canviewstatusupdatecomments'] == '1')
				{
					$showcomments = "<span class=\"showcomment smalltext\">".$lang->statusupdate_showcomments."</span>";
					$statusupdatecommentcount = " <span class=\"smalltext\">(".$tpl['statustotalcomments'].")</span>";
				}
				
				if($mybb->usergroup['candeleteownstatusupdate'] == '1')
				{
					if($mybb->get_input('page', 1) != "" && $mybb->get_input('page', 1) != "0")
					{
						$delete_url = "usercp.php?action=statusupdate&page=".$mybb->get_input('page', 1)."&delete=".$row['sid'];
					}
					else
					{
						$delete_url = "usercp.php?action=statusupdate&delete=".$row['sid'];
					}
					/*
					if($mybb->get_input('godelete', 1) != "" && $mybb->get_input('godelete', 1) != "0")
					{
						redirect($delete_url, $lang->statusupdate_redirect_delete);
					}
					*/
					eval('$statusupdate_action_delete = "'.$templates->get("statusupdate_usercp_action_delete").'";');
				}
				else
				{
					$statusupdate_delete = $lang->statusupdate_not_available;
				}
				
				if($mybb->usergroup['caneditownstatusupdate'] == '1')
				{
					if($mybb->get_input('page', 1) != "" && $mybb->get_input('page', 1) != "0")
					{
						$edit_url = "usercp.php?action=statusupdate&page=".$mybb->get_input('page', 1)."&edit=".$row['sid'];
					}
					else
					{
						$edit_url = "usercp.php?action=statusupdate&edit=".$row['sid'];
					}
					/*
					if($mybb->get_input('goedit', 1) != "" && $mybb->get_input('goedit', 1) != "0")
					{
						redirect($edit_url, $lang->statusupdate_redirect_edit);
					}
					*/
					eval('$statusupdate_action_edit = "'.$templates->get("statusupdate_usercp_action_edit").'";');
				}
				else
				{ 
					$statusupdate_delete = $lang->statusupdate_not_available;
				}
				
				eval('$statusupdate_statuses .= "'.$templates->get("statusupdate_usercp_statuses").'";');
				$bgcolor = alt_trow();
			}
		}
		else
		{
			eval('$statusupdate_statuses = "'.$templates->get("statusupdate_usercp_statuses_none").'";');
		}
		
		if($mybb->usergroup['canaddstatusupdate'] == '1')
		{
			$lang->statusupdate_rules_2 = $lang->sprintf($lang->statusupdate_rules_2, $mybb->settings['statusupdate_max_status_symbols']);
			
			if($mybb->get_input('edit', 1) != "" && $mybb->get_input('edit', 1) != "0")
			{
				if($mybb->usergroup['caneditownstatusupdate'] == '1')
				{
					$query = $db->query("
						SELECT *
						FROM ".TABLE_PREFIX."statusupdate
						WHERE uid='".$mybb->user['uid']."' AND sid=".$mybb->get_input('edit', 1)."
					");
					if($db->num_rows($query))
					{
						while($row = $db->fetch_array($query))
						{
							$tpl['text'] = $row['text'];
							$tpl['sid'] = $row['sid'];
							eval('$statusupdate_add = "'.$templates->get("statusupdate_usercp_edit").'";');
						}
					}
					else
					{
						error($lang->statusupdate_ivalid_status_id);
					}
				}
				else
				{
					eval('$statusupdate_add = "'.$templates->get("statusupdate_usercp_edit_denied").'";');
				}
			}
			elseif($mybb->get_input('delete', 1) != "" && $mybb->get_input('delete', 1) != "0")
			{
				if($mybb->usergroup['candeleteownstatusupdate'] == '1')
				{
					$query = $db->query("
						SELECT *
						FROM ".TABLE_PREFIX."statusupdate
						WHERE uid='".$mybb->user['uid']."' AND sid=".$mybb->get_input('delete', 1)."
					");
					if($db->num_rows($query))
					{
						while($row= $db->fetch_array($query))
						{
							$parser_options = array(
								"allow_html" => 0,
								"allow_mycode" => 1,
								"allow_smilies" => 1,
								"allow_imgcode" => 0, 
								"allow_videocode" => 0, 
								"filter_badwords" => 1,
							);
							
							$tpl['text'] = $parser->parse_message($row['text'], $parser_options); 
							$tpl['sid'] = $row['sid'];
							
							if($perpage + 1 >= $statuscount)
							{
								$tpl['page'] = "";
							}
							else
							{
								$tpl['page'] = $mybb->get_input('page', 1);
							}
							eval('$statusupdate_add = "'.$templates->get("statusupdate_usercp_delete").'";');
						}
					}
					else
					{
						error($lang->statusupdate_ivalid_status_id);
					}
				}
				else
				{
					eval('$statusupdate_add = "'.$templates->get("statusupdate_usercp_edit_denied").'";');
				}
			}
			else
			{
				eval('$statusupdate_add = "'.$templates->get("statusupdate_usercp_add").'";');
			}
		}
		else
		{
			eval('$statusupdate_add = "'.$templates->get("statusupdate_usercp_add_denied").'";');
		}
		
		add_breadcrumb($lang->statusupdate_statusupdate);
		eval('$statusupdate_script = "'.$templates->get("statusupda_script").'";');
		eval('$statusupdate = "'.$templates->get("statusupdate_usercp").'";');
		output_page($statusupdate);
	}
	elseif($mybb->input['action'] == "do_add_statusupdate"  && $mybb->request_method == "post")
	{
		if(!verify_post_check($mybb->get_input('my_post_key'), true))
		{
			error($lang->statusupdate_ivalid_post_key);
		}

		if($mybb->get_input('statustext') == '')
		{
			error($lang->statusupdate_status_empty);
		}
		
		if(strlen($mybb->get_input('statustext')) > $mybb->settings['statusupdate_max_status_symbols'])
		{
			error($lang->sprintf($lang->statusupdate_status_too_long, $mybb->settings['statusupdate_max_status_symbols']));
		}
		
		if($mybb->get_input('uid', 1) != $mybb->user['uid'])
		{
			error_no_permission();
		}
		
		if($mybb->usergroup['canaddstatusupdate'] != '1')
		{
			error($lang->statusupdate_no_permissions_to_add);
		}
		
		$status = array(
			"sid"			=> "NULL",
			"uid"				=> $mybb->get_input('uid', 1),
			"text"				=> $db->escape_string($mybb->get_input('statustext')),
			"dateline"	=> TIME_NOW,
			"lastedituid"	=> $mybb->get_input('uid', 1),
			"lasteditdate"	=> TIME_NOW,
			"ipaddress" => $session->packedip,
			"totalcomments" =>  $db->escape_string("0"),
		);
		$db->insert_query("statusupdate", $status);
		
		redirect("usercp.php?action=statusupdate", $lang->statusupdate_added);
	}
	elseif($mybb->input['action'] == "do_delete_statusupdate"  && $mybb->request_method == "post")
	{
		if(!verify_post_check($mybb->get_input('my_post_key'), true))
		{
			error($lang->statusupdate_ivalid_post_key);
		}
		
		if($mybb->get_input('sid', 1) == "0")
		{
			error($lang->statusupdate_ivalid_status_id);
		}

		if(!$db->num_rows($db->simple_select("statusupdate", "*", "sid = \"{$mybb->get_input('sid', 1)}\" AND uid = \"{$mybb->user['uid']}\"")))
		{
			error($lang->statusupdate_ivalid_status_id);
		}
		
		if($mybb->get_input('uid') != $mybb->user['uid'])
		{
			error_no_permission();
		}
		
		if($mybb->usergroup['candeleteownstatusupdate'] != '1')
		{
			error($lang->statusupdate_no_permissions_to_delete);
		}
		
		$db->delete_query("statusupdate", "sid=".$mybb->get_input('sid', 1));
		$db->delete_query("statusupdatecomments", "sid=".$mybb->get_input('sid', 1));
		
		if($mybb->get_input('page', 1) != "" && $mybb->get_input('page', 1) != "0")
		{
			$delete_url = "usercp.php?action=statusupdate&page=".$mybb->get_input('page', 1);
		}
		else
		{
			$delete_url = "usercp.php?action=statusupdate";
		}
		
		redirect($delete_url, $lang->statusupdate_deleted);
	}
	elseif($mybb->input['action'] == "do_edit_statusupdate"  && $mybb->request_method == "post")
	{
		if(!verify_post_check($mybb->get_input('my_post_key'), true))
		{
			error($lang->statusupdate_ivalid_post_key);
		}
		
		if($mybb->get_input('statustext') == '')
		{
			error($lang->statusupdate_status_empty);
		}
		
		if(strlen($mybb->get_input('statustext')) > $mybb->settings['statusupdate_max_status_symbols'])
		{
			error($lang->sprintf($lang->statusupdate_status_too_long, $mybb->settings['statusupdate_max_status_symbols']));
		}
		
		if($mybb->get_input('sid', 1) == "0")
		{
			error($lang->statusupdate_ivalid_status_id);
		}
		
		if($mybb->get_input('uid') != $mybb->user['uid'])
		{
			error_no_permission();
		}
		
		if($mybb->usergroup['caneditownstatusupdate'] != '1')
		{
			error($lang->statusupdate_no_permissions_to_edit);
		}
		
		$status = array(
			"text"				=> $db->escape_string($mybb->get_input('statustext')),
			"lastedituid"	=> $mybb->get_input('uid', 1),
			"lasteditdate"	=> TIME_NOW,
		);
		$db->update_query("statusupdate", $status, "sid='".$mybb->get_input('sid', 1)."'");
		
		redirect("usercp.php?action=statusupdate", $lang->statusupdate_edited);
	}
}

function statusupdate_templatelist()
{	
	if(in_array(THIS_SCRIPT, explode("," ,"index.php")))
	{
		global $templatelist;
		
		if(isset($templatelist))
		{
			$templatelist .= ",";
		}
		
		$templatelist .= "statusupdate_index,statusupdate_index_row,statusupdate_index_row_add_comment_form,statusupdate_index_row_show_comment,statusupdate_index_row_show_comment_row,statusupdate_index_row_show_comment_row_avatar,statusupda_script";
	}
	
	if(in_array(THIS_SCRIPT, explode("," ,"usercp.php")))
	{
		global $templatelist;
	
		if(isset($templatelist))
		{
			$templatelist .= ",";
		}
	
		$templatelist .= "statusupdate_usercp,statusupdate_usercp_statuses,statusupdate_usercp_statuses_none,statusupdate_usercp_add,statusupdate_usercp_edit,statusupdate_usercp_delete,statusupdate_usercp_action_delete,statusupdate_usercp_action_edit,statusupdate_usercp_add_denied,statusupdate_usercp_edit_denied,statusupdate_usercp_delete_denied,statusupdate_usercp_menu,statusupda_script";
	}
}