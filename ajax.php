<?
include('includes/functions.php');

# Hole Usereingabe
$ajax	= request_var('ajax', '', true);
$suche	= request_var('suche', '', true);
$in	= request_var('in', '', true);

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
	    case 'SteamID':	$sql = "SELECT steamid, steamname, holarsename
				FROM spiele WHERE steamid = '".$db->escape($suche)."' ORDER BY steamid ASC";
		break;
	    case 'Steam':	$sql = "SELECT steamid, steamname, holarsename
				FROM spiele WHERE steamname LIKE '%".$db->escape($suche)."%' ORDER BY steamid ASC";
		break;
	    case 'Holarse':	$sql = "SELECT steamid, steamname, holarsename
				FROM spiele WHERE holarsename LIKE '%".$db->escape($suche)."%' ORDER BY steamid ASC";
		break;
	}
    }
    # SQL Abfrage wenn Suchbegriff weniger als 2 Zeichen lang ist
    else
    {
	$sql	= "SELECT steamid, steamname, holarsename
		FROM spiele ORDER BY steamid ASC";
    }
    
    # Hole die daten aus der Datenbank
    $dbdata = $db->query($sql)->fetch();
    # Zähle die SQL Rows
    $entry	= $db->affected_rows;
    
    # Wenn keine Einträge gefunden wurden
    if ($entry < 1)
    {
	echo '<tr><td colspan="3">Keine Einträge gefunden</td></tr>';
    }
    # Einträge ausgeben
    else
    {
	foreach ($dbdata AS $key)
	{
	    $match = '';
	
	    if ($key['holarsename'] != '')
	    {
		$match = ' class="match"';
	    }
	    echo '<tr'.$match.'><td>';
	    echo '<a href="http://store.steampowered.com/app/'.$key['steamid'].'/" target="_blank">'.$key['steamid'].'</a>';
	    echo '</td><td>'.$key['steamname'].'</td><td>'.$key['holarsename'].'</td></tr>';
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
	        var rtitle = $(this).attr('data-hint');
	        $(this).attr('title',rtitle);
            });
	    $('#ecc').html('<?=$entries?>');
	    $("table").trigger("updateAll");
	});
    </script>
<?
}
?>