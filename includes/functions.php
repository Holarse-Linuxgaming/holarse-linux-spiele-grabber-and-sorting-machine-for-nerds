<?
# Steamdb Update Funktion
function updateSteamdb($db)
{
    $sdbdata    = getUrl('https://steamdb.info/linux/');
    
    # Falls Url nicht erreichbar ist, hier abbrechen!
    if ( !isset($sdbdata) )
    {
        $updresponse = 'SteamDB Update: Es ist ein Fehler aufgetreten!';
        return $updresponse;
    }
    
    # Hole alle als funktionierend gemeldete Spiele
    $regex0     = "/(?<=id\=\"table-apps-confirmed\"\>).*(?=\<\/table\>\<\/div\>)/msSU";
    preg_match_all ($regex0, $sdbdata, $availlinux, PREG_PATTERN_ORDER);

    $regex1     = "/(?<=href\=\"\/app\/).*(?=\/\")/msSU";
    preg_match_all ($regex1, $availlinux[0][0], $gsteamid, PREG_PATTERN_ORDER);

    $regex2     = "/(?<=\<\/a\>\<\/td\>\<td\>).*(?=\<\/td\>)/msSU";
    preg_match_all ($regex2, $availlinux[0][0], $gsteamname, PREG_PATTERN_ORDER);
    $array      = array();

    $upcount    = 0;

    foreach ($gsteamid[0] as $key => $steamid)
    {
        $sql        = "SELECT steamid FROM spiele WHERE steamid = '".$db->escape($steamid)."'";
        $db->query($sql)->fetch();

        if ($db->affected_rows == 0)
        {
            $steamname  = $gsteamname[0][$key];
            $eintrag    = "INSERT INTO spiele (steamid, steamname)
                            VALUES ('$steamid' , '$steamname')";

            $db->query($eintrag)->execute();

            $upcount++;
        }
    }
    
    # Hole nun die restlichen Spiele mit Linux Symbol
    $regex00     = "/(?<=id\=\"table-apps\"\>).*(?=\<\/table\>\<\/div\>)/msSU";
    preg_match_all ($regex00, $sdbdata, $availlinux1, PREG_PATTERN_ORDER);

    $regex01     = "/(?<=\<tr class\=\"app\").*(?=\<\/tr\>)/msSU";
    preg_match_all ($regex01, $availlinux1[0][0], $gsteamtr, PREG_PATTERN_ORDER);
    
    foreach ($gsteamtr[0] AS $key)
    {
        if (strpos($key,'Game Possibly Works') !== false)
        {
            $regex03    = "/(?<=href\=\"\/app\/).*(?=\/\")/msSU";
            preg_match_all ($regex03, $key, $gsteamid, PREG_PATTERN_ORDER);

            $regex04    = "/(?<=\<\/a\>\<\/td\>\<td\>).*(?=\<\/td\>)/U";
            preg_match_all ($regex04, $key, $gsteamname, PREG_PATTERN_ORDER);
            
            $steamidn   = $gsteamid[0][0];
            $steamnamen = $gsteamname[0][0];
            
            $sql        = "SELECT steamid FROM spiele WHERE steamid = '".$db->escape($steamidn)."'";
            $db->query($sql)->fetch();

            if ($db->affected_rows == 0)
            {
                $eintrag    = "INSERT INTO spiele (steamid, steamname)
                                VALUES ('$steamidn' , '$steamnamen')";

                $db->query($eintrag)->execute();

                $upcount++;
            }
        }
    }
    
    $store_time = "UPDATE `update` SET xtime = '".time()."' WHERE name = 'steamdb';";

    $db->query($store_time)->execute();

    $updresponse = 'SteamDB Update: Es wurden '.$upcount.' Einträge hinzugefügt';
    return $updresponse;
}

# Neue Steamdb Update Funktion
function updateSteamdbNew($db, $STEAM_API_KEY)
{
    $sdbdata    = json_decode(getUrl('http://api.steampowered.com/ISteamApps/GetAppList/v2?key='.$STEAM_API_KEY), true);
    $games      = json_decode(getUrl('https://raw.githubusercontent.com/SteamDatabase/SteamLinux/master/GAMES.json'), true);
    #$games      = json_decode(file_get_contents('./GAMES.json', true), true);
    $upcount    = 0;
    $sgames     = array();

    # Falls eine Url nicht erreichbar ist, hier abbrechen!
    if ( !isset($sdbdata) OR !isset($games) )
    {
        $updresponse = 'SteamDB Update: Es ist ein Fehler aufgetreten!';
        return $updresponse;
    }

    # Erstelle ein benutzbares array der Steam Spiele
    foreach ($sdbdata['applist']['apps'] AS $key => $value)
    {
        $sgames[$value['appid']] = $value['name'];
    }

    foreach ($games AS $steamid => $value)
    {
        if ( !isset($value['Hidden']) )
        {
            $sql    = "SELECT steamid FROM spiele WHERE steamid = '".$db->escape($steamid)."'";
            $db->query($sql)->fetch();

            if ($db->affected_rows == 0)
            {
                $eintrag    = "INSERT INTO spiele (steamid, steamname)
                                VALUES ('".$db->escape($steamid)."' , '".$db->escape($sgames[$steamid])."')";

                $db->query($eintrag)->execute();

                $upcount++;
            }
        }
    }

    $store_time = "UPDATE `update` SET xtime = '".time()."' WHERE name = 'steamdb';";

    $db->query($store_time)->execute();

    $updresponse = 'SteamDB Update: Es wurden '.$upcount.' Einträge hinzugefügt';
    return $updresponse;
}

# Holarse Update Funktion
function updateHolarse($db)
{
    $hdata    = json_decode(getUrl('https://www.holarse-linuxgaming.de/api/steamgames.json'),true);
    $regex    = "/(?<=\/app\/).[0-9]+/";
    $upcount  = 0;

    # Falls Url nicht erreichbar ist, hier abbrechen!
    if ( !isset($hdata) )
    {
        $updresponse = 'Holarse Update: Es ist ein Fehler aufgetreten!';
        return $updresponse;
    }

    foreach ($hdata AS $key)
    {
        preg_match_all ($regex, $key['field_steam_value']['raw'], $gsteamid, PREG_PATTERN_ORDER);

        $steamid = isset($gsteamid[0][0]) ? $gsteamid[0][0] : '';

        $sql     = "SELECT steamid FROM spiele WHERE steamid = '".$db->escape($steamid)."' AND holarsename = ''";

        $db->query($sql)->fetch();

        if ($db->affected_rows == 1)
        {
            $holname    = $key['title']['raw'];

            $eintrag    = "UPDATE spiele SET holarsename = '".$db->escape($holname)."'
                            WHERE steamid = '".$db->escape($steamid)."';";

            $db->query($eintrag)->execute();

            $upcount++;
        }
    }
    
    $store_time = "UPDATE `update` SET xtime = '".time()."' WHERE name = 'holarse';";

    $db->query($store_time)->execute();

    $updresponse = 'Holarse Update: Es wurden '.$upcount.' Einträge hinzugefügt';
    return $updresponse;
}

# Curl Website download Funktion
function getUrl($url)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_POST, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:35.0) Gecko/20100101 Firefox/35.0");
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,0);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30); //timeout in seconds
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

    $data = curl_exec($curl);
    curl_close($curl);
    return $data;
}

define('STRIP', false);

function set_var(&$result, $var, $type, $multibyte = false)
{
    settype($var, $type);
    $result = $var;

    if ($type == 'string')
    {
        $result = trim(htmlspecialchars(str_replace(array("\r\n", "\r", "\0"), array("\n", "\n", ''), $result), ENT_COMPAT, 'UTF-8'));

        if (!empty($result))
        {
            // Make sure multibyte characters are wellformed
            if ($multibyte)
            {
                if (!preg_match('/^./u', $result))
                {
                    $result = '';
                }
            }
            else
            {
                // no multibyte, allow only ASCII (0-127)
                $result = preg_replace('/[\x80-\xFF]/', '?', $result);
            }
        }

        $result = (STRIP) ? stripslashes($result) : $result;
    }
}

/**
* request_var
*
* Used to get passed variable
*/
function request_var($var_name, $default, $multibyte = false, $cookie = false)
{
    if (!$cookie && isset($_COOKIE[$var_name]))
    {
        if (!isset($_GET[$var_name]) && !isset($_POST[$var_name]))
        {
            return (is_array($default)) ? array() : $default;
        }
        $_REQUEST[$var_name] = isset($_POST[$var_name]) ? $_POST[$var_name] : $_GET[$var_name];
    }

    $super_global = ($cookie) ? '_COOKIE' : '_REQUEST';
    if (!isset($GLOBALS[$super_global][$var_name]) || is_array($GLOBALS[$super_global][$var_name]) != is_array($default))
    {
        return (is_array($default)) ? array() : $default;
    }

    $var = $GLOBALS[$super_global][$var_name];
    if (!is_array($default))
    {
        $type = gettype($default);
    }
    else
    {
        list($key_type, $type) = each($default);
        $type = gettype($type);
        $key_type = gettype($key_type);
        if ($type == 'array')
        {
            reset($default);
            $default = current($default);
            list($sub_key_type, $sub_type) = each($default);
            $sub_type = gettype($sub_type);
            $sub_type = ($sub_type == 'array') ? 'NULL' : $sub_type;
            $sub_key_type = gettype($sub_key_type);
        }
    }

    if (is_array($var))
    {
        $_var = $var;
        $var = array();

        foreach ($_var as $k => $v)
        {
            set_var($k, $k, $key_type);
            if ($type == 'array' && is_array($v))
            {
                foreach ($v as $_k => $_v)
                {
                    if (is_array($_v))
                    {
                        $_v = null;
                    }
                    set_var($_k, $_k, $sub_key_type, $multibyte);
                    set_var($var[$k][$_k], $_v, $sub_type, $multibyte);
                }
            }
            else
            {
                if ($type == 'array' || is_array($v))
                {
                    $v = null;
                }
                set_var($var[$k], $v, $type, $multibyte);
            }
        }
    }
    else
    {
        set_var($var, $var, $type, $multibyte);
    }

    return $var;
}
?>
