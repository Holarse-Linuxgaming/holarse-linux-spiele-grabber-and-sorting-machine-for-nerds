<?
# Datenbank Konfiguration
define('DB_SERVER',     'localhost');   # Datenbank Server, normalerweise localhost
define('DB_USER',       '');            # Datenbank Benutzername
define('DB_PASS',       '');            # Datenbank Passwort
define('DB_DATABASE',   '');            # Datenbank
define('STEAM_API_KEY', '');
# Protokoll des Aufrufs
if (empty($_SERVER['HTTPS']))
    define('PROT',  'http://');
else
    define('PROT',  'https://');
?>
