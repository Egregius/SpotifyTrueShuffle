<html>
	<heead>
		<title><?php echo strftime("%T");?>SpotifyTrueShuffle</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	</head>
	<body>
		<a href="#/"> Reload </a><br><br>
<?php
require 'vendor/autoload.php';
require 'config.php';

$session = new SpotifyWebAPI\Session($clientid, $clientsecrect, $returnurl);
$api = new SpotifyWebAPI\SpotifyWebAPI();
if (isset($_GET['code'])) {
    $session->requestAccessToken($_GET['code']);
    $api->setAccessToken($session->getAccessToken());

    $accessToken = $session->getAccessToken();
	$refreshToken = $session->getRefreshToken();
    
    echo 'Refresh token = '.$refreshToken;exit;
} else {
	$options = [
		'scope' => [
			'playlist-modify-public',
			'user-read-email',
			'user-library-modify',
			'user-read-recently-played',
		],
	];
    header('Location: ' . $session->getAuthorizeUrl($options));
    die();
}
?>
	</body>
</html>
