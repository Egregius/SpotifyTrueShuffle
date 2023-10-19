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
lg('Start cli-dedup');

require '/var/www/functions.php';
require 'vendor/autoload.php';
require 'config.php';
$sleep=15;
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

$playlists = $api->getUserPlaylists($spotifyusername, ['limit' => 50]);
$q=1;
$mylists=array(
	"Afrojack"=>"DJs",
	"Alok"=>"DJs",
	"Armin Van Buuren"=>"DJs",
	"Boris Brejcha"=>"DJs",
	"Charlotte De Witte"=>"DJs",
	"David Guetta"=>"DJs",
	"Dimitri Vegas & Like Mike"=>"DJs",
	"Don Diablo"=>"DJs",
	"Fritz Kalkbrenner"=>"DJs",
	"Paul Kalkbrenner"=>"DJs",
	"KSHMR"=>"DJs",
	"Martin Garrix"=>"DJs",
	"Oliver Heldens"=>"DJs",
	"R3HAB"=>"DJs",
	"Steve Aoki"=>"DJs",
	"Tiësto"=>"DJs",
	"Timmy Trumpet"=>"DJs",
	"Vintage Culture"=>"DJs",
	"DJs"=>"EDM + DJs",
	"EDM"=>"EDM + DJs",
	"EDM + DJs"=>"EDM + DJs + Pop",
	"Pop"=>"EDM + DJs + Pop",
	"EDM + DJs + Pop"=>"Mix",
	"Pop"=>"Ballads + Pop",
	"Ballads"=>"Ballads + Pop",
	"Ballads + Pop"=>"Mix",
);

foreach ($playlists->items as $playlist) {
	$playlisttitle=explode(' -', $playlist->name);
	$playlisttitle=$playlisttitle[0];
	if (in_array($playlisttitle,$mylists)||array_key_exists($playlisttitle,$mylists)) {
		$sortedplaylists[$playlisttitle][$playlist->name]['id']=$playlist->id;
		$sortedplaylists[$playlisttitle][$playlist->name]['trackstotal']=$playlist->tracks->total;
		$playlistnames[$playlist->id]=$playlist->name;
		$playlistsnapshots[$playlist->id]=$playlist->snapshot_id;
	}
}

sleep($sleep);
foreach ($sortedplaylists as $playlisttitle=>$playlists) {
	ksort($playlists, SORT_NATURAL);
//	lg('		'.$playlisttitle.'	'.print_r($playlists,true));
		foreach ($playlists as $k=>$playlist) {
			$first=true;
			
		//	print_r($playlist);
			if (!isset(${$playlisttitle})) ${$playlisttitle} = array();
			$offset=0;
			$r=0;
			while ($offset < $playlist['trackstotal']) {
				$playlistTracks = $api->getPlaylistTracks($playlist['id'], $options=['offset'=>$offset,'limit'=>100]);
				$q++;
				$r++;
//				lg('		'.$offset.' / '.$playlist['trackstotal'].' tracks');
				if (isset($playlistTracks->items)) {
					foreach ($playlistTracks->items as $track) {
						if (isset($track->track->artists,$track->track->name,$track->track->duration_ms)) {
							$title='';
							if (isset($track->track->artists)) {
								$artist='';
								sort($track->track->artists);
								foreach ($track->track->artists as $i) {
									$title.=$i->name.' ';
									$artist.=$i->name.' ';
								}
							}
							$title.=' | '.$track->track->name;
							$title=strtolower($title);
							$title=str_replace(array(' ','.','-','_','(',')','[',']',"'","’",'!','&','/'),'',$title);
							$title=str_replace(array('radioedit','radiomix','radioversion','originalmix','remastered','albummix','clubmix','clubedit','extended','mixedit','edit','featuring','feat','remix','festivalmix'),'',$title);
							$title=strtr($title,array('š'=>'s','ž'=>'z','à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'a','ç'=>'c','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ð'=>'o','ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o','ù'=>'u','ú'=>'u','û'=>'u','ý'=>'y','þ'=>'b','ÿ'=>'y'));
							$len=strlen($title);
							if ($len>8) {
								if (array_key_exists($title, ${$playlisttitle})) {
									if ($track->track->duration_ms>=${$playlisttitle}[$title]-20000&&$track->track->duration_ms<=${$playlisttitle}[$title]+20000) {
										if($first==true) {
											$first=false;
											lg('Working on "'.$k.'" '.$playlist['trackstotal'].' tracks');
										}
										lg('  -  '.$title);
										$positions[$playlist['id']][]=$offset;
									} //else lg('		++ '.$title);
								} else {
									${$playlisttitle}[$title]=$track->track->duration_ms;
									${$playlisttitle.'ids'}[$track->track->id]=$title;
								}
							}
						} 
						unset($title);
						$offset++;
					} 
					sleep($sleep);
					if ($q>=200) {
						$session->refreshAccessToken($token['refreshToken']);
						$accessToken = $session->getAccessToken();
						$refreshToken = $session->getRefreshToken();
						$api->setAccessToken($accessToken);
						if ($refreshToken!=$token['refreshToken']) {
							$stmt = $db->prepare("UPDATE token SET refreshToken = :refreshToken;");
							$opts = array(':refreshToken'=>$refreshToken);
							$stmt->execute($opts);
						}
						$q=0;
					}
				} else {
					lg(print_r($playlistTracks, true));
				}
				if ($r>101) break;
			}
		}
		unset($playlists,$playlist,$playlistTracks,$track);
}

if (isset($positions)) {
	foreach ($positions as $playlist => $items) {
			$aantal=count($items);
			if ($aantal>0) $items=array_slice($items,0,100);
			lg ('	Deleting in playlist '.$playlistnames[$playlist].' = '.count($items).' tracks');
			$api->deletePlaylistTracks($playlist, array('positions'=>$items), $playlistsnapshots[$playlist]);
			$q++;
			sleep($sleep);
			if ($q>=200) {
				$session->refreshAccessToken($token['refreshToken']);
				$accessToken = $session->getAccessToken();
				$refreshToken = $session->getRefreshToken();
				$api->setAccessToken($accessToken);
				if ($refreshToken!=$token['refreshToken']) {
					$stmt = $db->prepare("UPDATE token SET refreshToken = :refreshToken;");
					$opts = array(':refreshToken'=>$refreshToken);
					$stmt->execute($opts);
				}
				sleep($sleep);
				$q=0;
			}
	}
}

$maxtracks=10000;

foreach ($mylists as $child=>$parent) {
	$temp=array_diff_key(${$child.'ids'},${$parent.'ids'});
	if (is_array($temp)&&count($temp)>0) {
		$ids=array();
		foreach ($temp as $k=>$i) if (!array_key_exists($k, ${$parent.'ids'})&&!in_array(${$child.'ids'}[$k],${$parent.'ids'})) $ids[]=$k;
		$aantal=count($ids);
		if (count($ids)>0) {
			if (isset($sortedplaylists[$parent][$parent.' - Part 3'])) {
				lg( $aantal.' Tracks to add from '.$child.' to '.$parent.' - Part 3');
				if ($aantal>200) {$api->addPlaylistTracks($sortedplaylists[$parent][$parent.' - Part 3']['id'], array_slice($ids,200,100));$q++;sleep($sleep);}
				if ($aantal>100) {$api->addPlaylistTracks($sortedplaylists[$parent][$parent.' - Part 3']['id'], array_slice($ids,100,100));$q++;sleep($sleep);}
				$api->addPlaylistTracks($sortedplaylists[$parent][$parent.' - Part 3']['id'], array_slice($ids,0,100));$q++;sleep($sleep);
				if ($aantal>250) {$api->addMyTracks(array_slice($ids,250,50));$q++;sleep($sleep);}
				if ($aantal>200) {$api->addMyTracks(array_slice($ids,200,50));$q++;sleep($sleep);}
				if ($aantal>150) {$api->addMyTracks(array_slice($ids,150,50));$q++;sleep($sleep);}
				if ($aantal>100) {$api->addMyTracks(array_slice($ids,100,50));$q++;sleep($sleep);}
				if ($aantal>50) {$api->addMyTracks(array_slice($ids,50,50));$q++;sleep($sleep);}
				$api->addMyTracks(array_slice($ids,0,50));
				$q++;
				sleep($sleep);
			} elseif (isset($sortedplaylists[$parent][$parent.' - Part 2'])) {
				lg( $aantal.' Tracks to add from '.$child.' to '.$parent.' - Part 2');
				if ($aantal>200) {$api->addPlaylistTracks($sortedplaylists[$parent][$parent.' - Part 2']['id'], array_slice($ids,200,100));$q++;sleep($sleep);}
				if ($aantal>100) {$api->addPlaylistTracks($sortedplaylists[$parent][$parent.' - Part 2']['id'], array_slice($ids,100,100));$q++;sleep($sleep);}
				$api->addPlaylistTracks($sortedplaylists[$parent][$parent.' - Part 2']['id'], array_slice($ids,0,100));$q++;sleep($sleep);
				if ($aantal>250) {$api->addMyTracks(array_slice($ids,250,50));$q++;sleep($sleep);}
				if ($aantal>200) {$api->addMyTracks(array_slice($ids,200,50));$q++;sleep($sleep);}
				if ($aantal>150) {$api->addMyTracks(array_slice($ids,150,50));$q++;sleep($sleep);}
				if ($aantal>100) {$api->addMyTracks(array_slice($ids,100,50));$q++;sleep($sleep);}
				if ($aantal>50) {$api->addMyTracks(array_slice($ids,50,50));$q++;sleep($sleep);}
				$api->addMyTracks(array_slice($ids,0,50));
				$q++;
				sleep($sleep);
			} else {
				if ($aantal>$maxtracks-$sortedplaylists[$parent][$parent]['trackstotal']) {
					telegram('SPOTIFY'.PHP_EOL.'Playlist '.$parent.' vol');
				} else {
					lg( $aantal.' Tracks to add from '.$child.' to '.$parent);
					if ($aantal>200) {$api->addPlaylistTracks($sortedplaylists[$parent][$parent]['id'], array_slice($ids,200,100));$q++;sleep($sleep);}
					if ($aantal>100) {$api->addPlaylistTracks($sortedplaylists[$parent][$parent]['id'], array_slice($ids,100,100));$q++;sleep($sleep);}
					$api->addPlaylistTracks($sortedplaylists[$parent][$parent]['id'], array_slice($ids,0,100));$q++;sleep($sleep);
	
					if ($aantal>250) {$api->addMyTracks(array_slice($ids,250,50));$q++;sleep($sleep);}
					if ($aantal>200) {$api->addMyTracks(array_slice($ids,200,50));$q++;sleep($sleep);}
					if ($aantal>150) {$api->addMyTracks(array_slice($ids,150,50));$q++;sleep($sleep);}
					if ($aantal>100) {$api->addMyTracks(array_slice($ids,100,50));$q++;sleep($sleep);}
					if ($aantal>50) {$api->addMyTracks(array_slice($ids,50,50));$q++;sleep($sleep);}
					$api->addMyTracks(array_slice($ids,0,50));
					$q++;
					sleep($sleep);
				}
			}
			${$parent.'ids'}=array_merge(${$child.'ids'},${$parent.'ids'});
		}
	}
	if ($q>=200) {
		$session->refreshAccessToken($token['refreshToken']);
		$accessToken = $session->getAccessToken();
		$refreshToken = $session->getRefreshToken();
		$api->setAccessToken($accessToken);
		if ($refreshToken!=$token['refreshToken']) {
			$stmt = $db->prepare("UPDATE token SET refreshToken = :refreshToken;");
			$opts = array(':refreshToken'=>$refreshToken);
			$stmt->execute($opts);
		}
		sleep($sleep);
		$q=0;
	}
}
lg('End cli-dedup');

function lg($msg) {
	echo $msg.PHP_EOL;
	$fp=fopen('/var/log/Spotify-dedup-shuffle-'.date("Y-m-d").'.log', "a+");
	$time=microtime(true);
	$dFormat="Y-m-d H:i:s";
	$mSecs=$time-floor($time);
	$mSecs=substr(number_format($mSecs, 3), 1);
	fwrite($fp, sprintf("%s%s	%s\n", date($dFormat), $mSecs, $msg));
	fclose($fp);
}

ftruncate($lock_file, 0);
flock($lock_file, LOCK_UN);
