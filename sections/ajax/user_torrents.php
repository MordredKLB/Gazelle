<?
$UserID = (int)$_GET['userid'];
$Type = (string)$_GET['type'];
$Limit = (int)$_GET['limit'];
$Offset = (int)$_GET['offset'];

if (empty($UserID) || empty($Type)) {
	json_die("failure", "bad parameters");
}

if ($UserID != $LoggedUser['ID']) {
	// We can always view our own torrents
	$DB->query("
		SELECT m.Paranoia
		FROM users_main AS m
		WHERE m.ID = $UserID");

	if (!$DB->has_results()) { // If user doesn't exist
		json_die("failure", "no such user");
	}

	list($Paranoia) = $DB->next_record(MYSQLI_NUM, false);

	$Paranoia = unserialize($Paranoia);
	if (!is_array($Paranoia)) {
		$Paranoia = array();
	}
}

if (empty($Limit)) {
	// retrive all values. Somehow this is the accepted way to do this: http://stackoverflow.com/questions/2294842/mysql-retrieve-all-rows-with-limit
	$Limit = 2147483647;
}
if (empty($Offset)) {
	$Offset = 0;
}

switch ($Type) {
	case 'snatched':
		$From = "FROM xbt_snatched AS s
					INNER JOIN torrents AS t ON t.ID = s.fid
					INNER JOIN torrents_group AS g ON t.GroupID = g.ID";
		$Where = "WHERE s.uid = '$UserID'
					AND g.CategoryID = '1'";
		$Order = "ORDER BY s.tstamp DESC";
		break;
	case 'seeding':
		$From = "FROM xbt_files_users AS xfu
					JOIN torrents AS t ON t.ID = xfu.fid
					INNER JOIN torrents_group AS g ON t.GroupID = g.ID";
		$Where = "WHERE xfu.uid = '$UserID'
					AND xfu.active = 1
					AND xfu.Remaining = 0";
		$Order = "ORDER BY xfu.mtime DESC";
		break;
	case 'leeching':
		$From = "FROM xbt_files_users AS xfu
					JOIN torrents AS t ON t.ID = xfu.fid
					INNER JOIN torrents_group AS g ON t.GroupID = g.ID";
		$Where = "WHERE xfu.uid = '$UserID'
					AND xfu.active = 1
					AND xfu.Remaining > 0";
		$Order = "ORDER BY xfu.mtime DESC";
		break;
	case 'uploaded':
		$Type = 'uploads';	// paranoia settings use 'uploads' but the main site typically uses 'uploaded'
		// fall through
	case 'uploads':
		$From = "FROM torrents_group AS g
					INNER JOIN torrents AS t ON t.GroupID = g.ID";
		$Where = "WHERE t.UserID = '$UserID'
					AND g.CategoryID = '1'";
		$Order = "ORDER BY t.Time DESC";
		break;
	default:
		json_die("failure", $Type." is not a valid type");
}

$Results = array();
if (check_paranoia_here($Type)) {
	$DB->query("SELECT
					g.ID AS groupId,
					g.Name AS name,
					t.ID AS torrentId ".
				$From." ".$Where." ".$Order." ".
				"LIMIT $Offset, $Limit");
	$TorrentGroups = $DB->to_array(false, MYSQLI_ASSOC);

	$Artists = Artists::get_artists($DB->collect('groupId'));

	foreach ($TorrentGroups as $Key => $SnatchInfo) {
		$TorrentGroups[$Key]['artistName'] = Artists::display_artists($Artists[$SnatchInfo['groupId']], false, false, true);
		if (Count($Artists[$SnatchInfo['groupId']][1]) === 1)
			$TorrentGroups[$Key]['artistId'] = $Artists[$SnatchInfo['groupId']][1][0]['id'];
	}
	$Results[$Type] = $TorrentGroups;
} else {
	$Results[$Type] = "hidden";
}

json_print("success", $Results);

function check_paranoia_here($Setting) {
	global $Paranoia, $Class, $UserID, $Preview;
	if ($Preview == 1) {
		return check_paranoia($Setting, $Paranoia, $Class);
	} else {
		return check_paranoia($Setting, $Paranoia, $Class, $UserID);
	}
}
