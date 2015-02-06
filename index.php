<?
include('includes/config.php');
include('includes/mysqli.class.php');
include('includes/functions.php');

# Öffne Datenbank Verbindung
$db = new Database(DB_SERVER, DB_USER, DB_PASS, DB_DATABASE);

# Hole Usereingabe
$filter     = request_var('filter', '', true);
$suche      = trim(request_var('suche', '', true));
$in         = request_var('in', '', true);

# Weise variablen zu
$f0a = $f1a = $fa = '';

# Switch für den Menü highlight
switch ($filter)
{
    case 'f0':  $f0a = ' class="active"';
    break;
    case 'f1':  $f1a = ' class="active"';
    break;
    default:    $fa  = ' class="active"';
}

# Wenn nach einer ID gesucht wird, aber keine nummer angegeben ist
# leere die variable
if ($in == 'SteamID' && !is_numeric($suche))
    $suche  = '';

# SQL Abfrage für die Suche (No AJAX mode)
if ($suche != '' && $in != '')
{
    switch ($in)
    {
        case 'SteamID': $sql = "SELECT steamid, steamname, holarsename FROM spiele
                        WHERE steamid = '".$db->escape($suche)."' ORDER BY steamid ASC";
        break;
        case 'Steam':   $sql = "SELECT steamid, steamname, holarsename FROM spiele
                        WHERE steamname LIKE '%".$db->escape($suche)."%' ORDER BY steamid ASC";
        break;
        case 'Holarse': $sql = "SELECT steamid, steamname, holarsename FROM spiele
                        WHERE holarsename LIKE '%".$db->escape($suche)."%' ORDER BY steamid ASC";
        break;
    }
}
# SQL Abfrage
else
{
    switch ($filter)
    {
        case 'f0':  $sql = "SELECT steamid, steamname, holarsename FROM spiele
                    WHERE holarsename = '' ORDER BY steamid ASC";
        break;
        case 'f1':  $sql = "SELECT steamid, steamname, holarsename FROM spiele
                    WHERE holarsename != '' ORDER BY steamid ASC";
        break;
        default:    $sql = "SELECT steamid, steamname, holarsename FROM spiele
                    ORDER BY steamid ASC";
    }
}

# Hole die daten aus der Datenbank
# und zähle die SQL Rows
$dbdata = $db->query($sql)->fetch();
$entry  = $db->affected_rows;

# Zähle alle Einträge
$sqla  = "SELECT id FROM spiele";
$db->query($sqla)->fetch();
$all   = $db->affected_rows;

# Zähle Übereinstimmungen
# von Holarse Einträgen
$holamatch = 0;
foreach ($dbdata AS $key)
{
    if ($key['holarsename'] != '')
        $holamatch++;
}

if ($holamatch > 0)
    $percent = round($holamatch / $entry * 100);
else
    $percent = 0;

# Letzte Updates Abfragen
$updsql     = "SELECT name, xtime FROM `update` ORDER BY id ASC";
$upddata    = $db->query($updsql)->fetch();
$t_steamdb  = date("H:i",$upddata[0]['xtime']);
$t_holarse  = date("H:i",$upddata[1]['xtime']);

# HTML
?>
<!doctype html>
<html>
<head>
    <base href="<?echo PROT.$_SERVER['SERVER_NAME'];?>/">
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
    <title>Linuxspiele Liste</title>
    <link rel="icon" href="favicon.png" type="image/x-icon">
    <script src="js/jquery.min.js"></script>
    <script src="js/jquery.tablesorter.min.js"></script>
</head>
<body>
    <div class="box">
        <div id="menu">
            <ul>
                <li<?=$fa?>><a href="./">Alle Anzeigen</a>
                <li<?=$f0a?>><a href="nicht-vorhanden/">Nicht vorhanden</a>
                <li<?=$f1a?>><a href="vorhanden/">Vorhanden</a>
            </ul>
            <br>
            <div id="entrycounter">
                <div class="search">
                    <form method="get"  action="/">
                        <select id="search_select" name="in">
                            <option>SteamID</option>
                            <option selected>Steam</option>
                            <option>Holarse</option>
                        </select>
                        <input id="search_input" type="text" name="suche" placeholder="Suchen ...">
                    </form>
                </div>
                <div class="statistic" id="stat">
                    Gesamt: <?=$all?> | Steam: <?=$entry?> | Holarse: <?=$holamatch?> (<?=$percent?>%)
                </div>
                <div class="statistic date">
                    <span class="title">Letzte Updates</span>
                    <div class="upd_button" title="Update Database">
                        <div class="upd_menu" title="">
                            <div class="upd_holarse">Holarse</div>
                            <div class="upd_steamdb">SteamDB</div>
                        </div>
                    </div>
                    <br><span class="updatetime">SteamDB: <?=$t_steamdb?> Uhr | Holarse: <?=$t_holarse?> Uhr</span>
                </div>
            </div>
        </div>
        <div class="update"></div>
        <table id="lstable">
            <thead>
                <tr>
                    <th>SteamID</th>
                    <th>Spieltitel Steam</th>
                    <th>Spieltitel Holarse</th>
                </tr>
            </thead>
            <tfoot>
                <tr><th colspan="3"></th></tr>
            </tfoot>
<?
            # Wenn keine Einträge in der Datenbank gefunden wurden
            # den Benutzer darüber informieren
            if ($entry < 1)
            {
                echo '<tr><td colspan="3">Keine Einträge gefunden</td></tr>';
            }
            # Datenbank Einträge ausgeben
            else
            {
                foreach ($dbdata AS $key)
                {
                    $match = '';

                    if ($key['holarsename'] != '')
                        $match = ' class="match"';
?>
                <tr<?=$match?>>
                    <td>
                        <a href="http://store.steampowered.com/app/<?=$key['steamid']?>/" target="_blank">
                            <?=$key['steamid']?>
                        </a>
                    </td>
                    <td><?=$key['steamname']?></td>
                    <td><?=$key['holarsename']?></td>
                </tr>
<?
                }
            }
?>
        </table>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
