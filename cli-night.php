<?php
$lock_file = fopen('/run/lock/spotify.pid', 'c');
$got_lock = flock($lock_file, LOCK_EX | LOCK_NB, $wouldblock);
if ($lock_file === false || (!$got_lock && !$wouldblock)) {
    throw new Exception(
        "Unexpected error opening or locking lock file. Perhaps you " .
        "don't  have permission to write to the lock file or its " .
        "containing directory?"
    );
}
else if (!$got_lock && $wouldblock) {
    exit("Another instance is already running; terminating.\n");
}
ftruncate($lock_file, 0);
fwrite($lock_file, getmypid() . "\n");
lg('Start cli-night');
$q=0;

require 'vendor/autoload.php';
require 'config.php';
$sleep=10;
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
		$removed=0;
		while ($offset < $playlist->tracks->total*$randomposition) {
			$playlistTracks = $api->getPlaylistTracks($playlist->id, $options=['offset'=>$offset,'limit'=>50]);
			$q++;
			if (isset($playlistTracks->items)) {
				foreach ($playlistTracks->items as $track) {
					if (isset($track->track->artists,$track->track->name,$track->track->duration_ms)) {
						$title='';
						if (isset($track->track->artists)) {
							$artist='';
							foreach ($track->track->artists as $i) {
								$title.=$i->name.' ';
								$artist.=$i->name.' ';
                            }
						}
						$title.=' | '.$track->track->name.' | '.round($track->track->duration_ms/10000);
						$title=strtolower($title);
						$title=str_replace(array(' ','.','-','_','(',')',"'","’",'!'),'',$title);
						$title=str_replace(array('radioedit','radioversion','originalmix','remastered','albummix'),'',$title);
						$title=strtr($title,array('š'=>'s','ž'=>'z','à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'a','ç'=>'c','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ð'=>'o','ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o','ù'=>'u','ú'=>'u','û'=>'u','ý'=>'y','þ'=>'b','ÿ'=>'y'));
						$len=strlen($title);
						if ($len>12) {
							if (in_array($title, $tracks)) {
								lg('---	'.$title);
								$positions[]=$offset;
								$removed++;
							} else {
								$tracks[]=$title;
							}
						}
						if (array_key_exists($track->track->id, $played)) {
							$move[]=$offset;
							lg('>>> '.$offset.'	'.$title);
						}
					}
					$offset++;
					unset($title);
				}
			} else {
				lg(print_r($playlistTracks, true));
			}
			usleep(500000);
		}
		if (isset($positions)) {
			lg('Deleting '.print_r($positions, true));
			$api->deletePlaylistTracks($playlist->id, array('positions'=>$positions), $playlist->snapshot_id);
			$q++;
			unset($positions);
			exit;
		}
		
		if (count($move)>0) {
			$min=min($move);
			if ($min>0) $move[]=0;
			rsort($move);
			lg('Start moving tracks...');
			foreach ($move as $i) {
				sleep($sleep);
				$place=rand(floor(($playlist->tracks->total-$removed)*$randomposition), $playlist->tracks->total-$removed);
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

$db=new PDO("mysql:host=localhost;dbname=spotify;",'spotify','spotify');
$stmt=$db->query("TRUNCATE `played`;");

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
