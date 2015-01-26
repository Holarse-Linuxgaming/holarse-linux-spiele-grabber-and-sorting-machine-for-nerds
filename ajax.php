<?
include('includes/functions.php');

# Hole Usereingabe
$ajax   = request_var('ajax', '', true);
$suche  = trim(request_var('suche', '', true));
$in     = request_var('in', '', true);

# AJAX suche
if ($ajax == 'suche' && $in != '')
{
    include('includes/config.php');
    include('includes/mysqli.class.php');

    # Öffne Datenbank verbindung
    $db = new Database(DB_SERVER, DB_USER, DB_PASS, DB_DATABASE);

    # Wenn nach einer ID gesucht wird, aber keine nummer angegeben ist
    # leere die variable
    if ($in == 'SteamID' && !is_numeric($suche))
        $suche  = '';

    # SQL Abfrage wenn Suchbegriff mindestens 2 Zeichen lang ist
    if (strlen($suche) > 1)
    {
        switch ($in)
        {
            case 'SteamID':     $sql = "SELECT steamid, steamname, holarsename FROM spiele
                                WHERE steamid = '".$db->escape($suche)."' ORDER BY steamid ASC";
            break;
            case 'Steam':       $sql = "SELECT steamid, steamname, holarsename FROM spiele
                                WHERE steamname LIKE '%".$db->escape($suche)."%' ORDER BY steamid ASC";
            break;
            case 'Holarse':     $sql = "SELECT steamid, steamname, holarsename FROM spiele
                                WHERE holarsename LIKE '%".$db->escape($suche)."%' ORDER BY steamid ASC";
            break;
        }
    }
    # SQL Abfrage wenn Suchbegriff weniger als 2 Zeichen lang ist
    else
    {
        $sql = "SELECT steamid, steamname, holarsename FROM spiele ORDER BY steamid ASC";
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

    # Status Anzeige generieren, welche per JS updated wird
    $stats = "Gesamt: $all | Steam: $entry | Holarse: $holamatch ($percent%)";

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

            echo '<tr'.$match.'>';
                echo '<td>';
                    echo '<a href="http://store.steampowered.com/app/'.$key['steamid'].'/" target="_blank">';
                        echo $key['steamid'];
                    echo '</a>';
                echo '</td>';
                echo '<td>'.$key['steamname'].'</td>';
                echo '<td>'.$key['holarsename'].'</td>';
            echo '</tr>';
        }
    }

    # Erstelle den Anzahls counter
    if ($entry == 1)
        $entries = "$entry Eintrag";
    else
        $entries = "$entry Einträge";
?>
    <script>
    $(function()
    {
        $('.ribbon').each(function()
        {
            $(this).attr('title', $(this).attr('data-hint') );
        });
        $("table").trigger("updateAll");
        $('#statistic').html('<?=$stats?>');
    });
    </script>
<?
}
?>