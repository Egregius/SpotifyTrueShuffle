# SpotifyTrueShuffle
Shuffles your Spotify playlists to create a true shuffle.

Uses https://github.com/jwilsson/spotify-web-api-php

This script needs to be executed by cron. For example:<br>
0,10,20,30,40,50 6-22 * * * /usr/bin/nice -n20 /usr/bin/php /var/www/html/spotify/cli.php > /dev/null 2>&1<br>
0 2,12,16 * * * /usr/bin/nice -n20 /usr/bin/php /var/www/html/spotify/cli-night.php > /dev/null 2>&1<br>

cli.php fetches your recently played songs and searches in the first 50 tracks of the playlist where it is. Finally the tracks are put randomly at the back of the playlist.<br>
cli-night.php does the same thing but searches these tracks in the whole playlist instead of only the first 50 tracks.<br>
Tracks are also checked for doubles by removing certain stuff from the artist/title and combining that data into a single string. If duplicates are found these are removed and the script stops.<br>

