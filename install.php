<?php
if (! function_exists("out")) {
	function out($text) {
		echo $text."<br />";
	}
}

if (! function_exists("outn")) {
	function outn($text) {
		echo $text;
	}
}

global $db;
global $amp_conf;

$sql = "
CREATE TABLE IF NOT EXISTS `meetme` 
( 
	`exten` VARCHAR( 50 ) NOT NULL , 
	`options` VARCHAR( 15 ) , 
	`userpin` VARCHAR( 50 ) , 
	`adminpin` VARCHAR( 50 ) , 
	`description` VARCHAR( 50 ) , 
	`joinmsg_id` INTEGER 
)
";
$check = $db->query($sql);
if(DB::IsError($check)) {
	die_freepbx("Can not create meetme table");
}

// Version 2.5 migrate to recording ids
//
outn(_("Checking if recordings need migration.."));
$sql = "SELECT joinmsg_id FROM meetme";
$check = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if(DB::IsError($check)) {
	//  Add recording_id field
	//
	out("migrating");
	outn(_("adding joinmsg_id field.."));
  $sql = "ALTER TABLE meetme ADD joinmsg_id INTEGER";
  $result = $db->query($sql);
  if(DB::IsError($result)) {
		out(_("fatal error"));
		die_freepbx($result->getDebugInfo()); 
	} else {
		out(_("ok"));
	}

	// Get all the valudes and replace them with joinmsg_id
	//
	outn(_("migrate to recording ids.."));
  $sql = "SELECT `exten`, `joinmsg` FROM `meetme`";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if(DB::IsError($results)) {
		out(_("fatal error"));
		die_freepbx($results->getDebugInfo());	
	}
	$migrate_arr = array();
	$count = 0;
	foreach ($results as $row) {
		if (trim($row['joinmsg']) != '') {
			$rec_id = recordings_get_or_create_id($row['joinmsg'], 'conference');
			$migrate_arr[] = array($rec_id, $row['exten']);
			$count++;
		}
	}
	if ($count) {
		$compiled = $db->prepare('UPDATE `meetme` SET `joinmsg_id` = ? WHERE `exten` = ?');
		$result = $db->executeMultiple($compiled,$migrate_arr);
		if(DB::IsError($result)) {
			out(_("fatal error"));
			die_freepbx($result->getDebugInfo());	
		}
	}
	out(sprintf(_("migrated %s entries"),$count));

	// Now remove the old recording field replaced by new id field
	//
	outn(_("dropping joinmsg field.."));
  $sql = "ALTER TABLE `meetme` DROP `joinmsg`";
  $result = $db->query($sql);
  if(DB::IsError($result)) { 
		out(_("no joinmsg field???"));
	} else {
		out(_("ok"));
	}

} else {
	out("already migrated");
}
?>
