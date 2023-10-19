# SpotifyTrueShuffle
Shuffles your Spotify playlists to create a true shuffle.

Uses https://github.com/jwilsson/spotify-web-api-php

This script needs to be executed by cron. For example:<br>
10,20,30,40,50 5-23 * * * /usr/bin/php /var/www/secure.egregius.be/spotify/cli-shuffle.php > /dev/null 2>&1<br>
0 5,7,9,11,13,15,17,19,21,23 * * * /usr/bin/php /var/www/secure.egregius.be/spotify/cli-shuffle.php > /dev/null 2>&1<br>
0 0,6,8,10,12,14,16,18,20,22 * * * /usr/bin/php /var/www/secure.egregius.be/spotify/cli-shuffle-all.php > /dev/null 2>&1<br>
0 1 * * * /usr/bin/php /var/www/secure.egregius.be/spotify/cli-dedup.php > /dev/null 2>&1<br>
<br>
cli-shuffle.php fetches your recently played songs and searches in the first 100 tracks of the playlist where it is. Finally the tracks are put randomly at the back of the playlist.<br>
cli-shuffle-all.php fetches your recently played songs and searches in the first 100 tracks of all the playlists. Finally the tracks are put randomly at the back of the playlist.<br>
cli-dedup fetches all playlists and tracks and removes duplicates. Finally a cascade system can be used to add tracks to parent playlists.<br>
<br>
