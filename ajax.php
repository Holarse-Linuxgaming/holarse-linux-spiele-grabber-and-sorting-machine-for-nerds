<?
include('includes/functions.php');

# Hole Usereingabe
$ajax   = request_var('ajax', '', true);
$suche  = trim(request_var('suche', '', true));
$in     = request_var('in', '', true);
$dbu    = request_var('dbu', '', true);

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

    $db->select('steamid, steamname, holarsename')->from('spiele');
    
    # SQL WHERE Clause wenn Suchbegriff mindestens 2 Zeichen lang ist
    if (strlen($suche) > 1)
    {
        switch ($in)
        {
            case 'SteamID':     $db->where('steamid', (int)$db->escape($suche)); 
            break;
            case 'Steam':       $db->where('steamname LIKE', '%'.$db->escape($suche).'%'); 
            break;
            case 'Holarse':     $db->where('holarsename LIKE', '%'.$db->escape($suche).'%');
            break;
        }
    }

    # Hole die daten aus der Datenbank
    # und zähle die SQL Rows
    $db->order_by("steamid", "asc");
    $dbdata = $db->fetch();
    $entry  = $db->affected_rows;

    # Zähle alle Einträge
    $db->select('id')->from('spiele')->fetch();
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
        $('#stat').html('<?=$stats?>');
    });
    </script>
<?
}
if ($ajax == 'update' && $dbu != '')
{
    include('includes/config.php');
    include('includes/mysqli.class.php');

    # Öffne Datenbank verbindung
    $db = new Database(DB_SERVER, DB_USER, DB_PASS, DB_DATABASE);
    
    if ($dbu == 'steamdb')
        echo updateSteamdbNew($db,STEAM_API_KEY);

    if ($dbu == 'holarse')
        echo updateHolarse($db);
    
    
    # Zähle alle Einträge
    $db->select('id')->from('spiele')->fetch();
    $all   = $db->affected_rows;

    # Zähle Übereinstimmungen
    # von Holarse Einträgen
    $db->select('id')->from('spiele')->where('holarsename !=', '')->fetch();
    $hol   = $db->affected_rows;

    if ($hol > 0)
        $percent = round($hol / $all * 100);
    else
        $percent = 0;

    # Status Anzeige generieren, welche per JS updated wird
    $stats = "Gesamt: $all | Steam: $all | Holarse: $hol ($percent%)";
    
    # Letzte Updates Abfragen
    $upddata    = $db->select('name, xtime')->from('`update`')->order_by('id', 'asc')->fetch();
    $t_steamdb  = date("H:i",$upddata[0]['xtime']);
    $t_holarse  = date("H:i",$upddata[1]['xtime']);
    
    $upd_time = "SteamDB: $t_steamdb Uhr | Holarse: $t_holarse Uhr";
    
?>
    <script>
    $(function()
    {
        $('#stat').html('<?=$stats?>');
        $('.updatetime').html('<?=$upd_time?>');
    });
    </script>
<?
}
?>
