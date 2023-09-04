<?php
$lock_file = fopen('/run/lock/spotify.pid', 'c');
$got_lock = flock($lock_file, LOCK_EX | LOCK_NB, $wouldblock);
if ($lock_file === false || (!$got_lock && !$wouldblock)) {
    throw new Exception(
        "Unexpected error opening or locking lock file. Perhaps you " .
        "don't  have permission to write to the lock file or its " .
        "containing directory?"
    );
} else if (!$got_lock && $wouldblock) {
    exit("Another instance is already running; terminating.\n");
}
ftruncate($lock_file, 0);
fwrite($lock_file, getmypid() . "\n");

lg('Start cli');
$q=0;
require 'vendor/autoload.php';
require 'config.php';
$sleep=5;

$session = new SpotifyWebAPI\Session($clientid, $clientsecrect, $returnurl);
$options = [
	'auto_retry'=>true,
	'scope' => [
		'playlist-modify-public',
		'user-read-email',
		'user-library-modify',
		'user-read-recently-played',
	],
];

$api = new SpotifyWebAPI\SpotifyWebAPI(array('auto_retry'=>true));

$db = new PDO("mysql:host=localhost;dbname=spotify;",'spotify','spotify');
$stmt = $db->query("SELECT refreshToken FROM token;");
while ($row=$stmt->fetch(PDO::FETCH_ASSOC)) $token=$row;

$session->refreshAccessToken($token['refreshToken']);
$accessToken = $session->getAccessToken();
$refreshToken = $session->getRefreshToken();
$api->setAccessToken($accessToken);

if ($refreshToken!=$token['refreshToken']) {
	$stmt = $db->prepare("UPDATE token SET refreshToken = :refreshToken;");
	$opts = array(':refreshToken'=>$refreshToken);
	$stmt->execute($opts);
}
lg('getMyRecentTracks');
$recents = $api->getMyRecentTracks(array('limit'=>50));
$q++;
$played = array();
$liststocheck = array();
foreach ($recents->items as $track) {
	if (!in_array($track->track->id, $played)) $played[$track->track->id] = $track->played_at;
	$liststocheck[$track->context->uri]=1;
}
sleep($sleep);
lg('getUserPlaylists');
$playlists = $api->getUserPlaylists($spotifyusername, ['limit' => 50]);
$q++;
foreach ($playlists->items as $playlist) {
	if ($playlist->tracks->total>50&&$playlist->tracks->total<50000&&isset($liststocheck[$playlist->uri])) {
		lg($playlist->name);
		$tracks = array();
		$move = array();
		$offset=0;
		$playlistTracks = $api->getPlaylistTracks($playlist->id, $options=['offset'=>0,'limit'=>50]);
		$q++;
		if (isset($playlistTracks->items)) {
			foreach ($playlistTracks->items as $track) {
				if (isset($track->track->id)) {
					if (array_key_exists($track->track->id, $played)) {
						$move[]=$offset;
						if (isset($track->track->artists)) {
							$artist='';
							foreach ($track->track->artists as $i) @$artist.=$i->name.' ';
						}
					}
				}
				$offset++;
			}
		}
		if (count($move)>0) {
			$min=min($move);
			if ($min>0) $move[]=0;
			rsort($move);
			foreach ($move as $i) {
				sleep($sleep);
				$place=rand(floor(($playlist->tracks->total)*0.8), $playlist->tracks->total);
				lg('	'.$i.'	>>> '.$place.'	| '.$playlist->tracks->total-$removed);
				$api->reorderPlaylistTracks($playlist->id, [
					'range_start' => $i,
					'insert_before' => $place,
				]);
				$q++;
			}
		}
	}
}

lg('End cli. '.$q.' requests send');

function lg($msg) {
	echo $msg.PHP_EOL;
	$fp=fopen('/var/log/Spotify-dedup-shuffle-'.date("Y-m-d").'.log', "a+");
	$time=microtime(true);
	$dFormat="Y-m-d H:i:s";
	$mSecs=$time-floor($time);
	$mSecs=substr(number_format($mSecs, 3), 1);
	fwrite($fp, sprintf("%s%s %s\n", date($dFormat), $mSecs, $msg));
	fclose($fp);
}

ftruncate($lock_file, 0);
flock($lock_file, LOCK_UN);
