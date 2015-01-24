<?
# Steamdb Update Funktion
function updateSteamdb($db)
{
    $sdbdata    = getUrl('https://steamdb.info/linux/');

    $regex1     = "/(?<=href\=\"\/app\/).*(?=\/\")/msSU";
    preg_match_all ($regex1, $sdbdata, $gsteamid, PREG_PATTERN_ORDER);

    $regex2     = "/(?<=\<\/a\>\<\/td\>\<td\>).*(?=\<\/td\>)/msSU";
    preg_match_all ($regex2, $sdbdata, $gsteamname, PREG_PATTERN_ORDER);
    $array      = array();

    $upcount    = 0;

    foreach ($gsteamid[0] as $key => $steamid)
    {
        $sql    = "SELECT steamid FROM spiele WHERE steamid = '".$db->escape($steamid)."'";
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

    $updresponse = '<div class="update">SteamDB Update: Es wurden '.$upcount.' Einträge hinzugefügt</div>';
    return $updresponse;
}

# Curl Website download Funktion
function getUrl($url)
{
    $curl = curl_init();
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