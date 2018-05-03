<?php
$token = [YOUR SLACK TOKEN];
$feed = [YOUR RSS FEED];
$siteloc = [YOUR DOMAIN];
$room = [YOUR SLACK CHANNEL];
$listfile = [YOUR WHITELIST];

#==============================================================
$feed = file_get_contents($feed);
$feed = explode("</item>", $feed);
foreach ($feed as &$row) {
    $row = pregRssCleanup($row);
}
$feed = implode("</item>", $feed);
$xml = json_decode(json_encode((array)simplexml_load_string($feed, 'SimpleXMLElement', LIBXML_NOCDATA)), TRUE);
$data = $xml['channel']['item'];
$oldstories = file_get_contents($listfile);
$string = <<<EOF
*<%s|POSTED TO %s: %s>*
%s: %s
`<%s|Chartbeat>`  `<%s|Twitter>`  `<%s|Facebook>`  `<%s|Reddit>`  `<%s|LinkedIn>`
EOF;
$string = trim(str_replace("\r\n", "\n", $string));
foreach ($data as &$row) {
    $row['article'] = preg_replace("/^.*?article([0-9]+).html$/", "$1", $row['guid']);
    $row['chartbeat'] = "https://chartbeat.com/publishing/dashboard/sacbee.com/#path=" . urlencode(str_replace("http://www.", "", $row['guid']));
    $row['facebook'] = "https://www.facebook.com/sharer/sharer.php?u=" . $row['guid'];
    $row['twitter'] = "https://twitter.com/share?text=" . urlencode($row['title']) . "&url=" . $row['guid'];
    $row['reddit'] = "https://www.reddit.com/submit?url=" . $row['guid'];
    $row['linkedin'] = "https://www.linkedin.com/shareArticle?mini=true&url=" . $row['guid'] . "&title=" . urlencode($row['title']);
    if (!isset($row['email'])) {
        $row['email'] = "noone@google.com";
    }
    if (is_array($row['description'])) {
        $row['description'] = multi_implode($row['description'], ". ");
    }
    if (strstr($row['description'], "\. ")) {
        $row['description'] = preg_replace("/^(.*?\.) .*?$/", "$1", $row['description']);
    }
    $row['title'] = html_entity_decode($row['title'], ENT_QUOTES | ENT_XML1, 'UTF-8');
    $row['description'] = html_entity_decode($row['description'], ENT_QUOTES | ENT_XML1, 'UTF-8');
    if (!strstr($oldstories, $row['article']) && strstr($row['email'], $siteloc)) {
        $message = sprintf($string, $row['guid'], strtoupper($siteloc), $row['title'], $row['author'], $row['description'], $row['chartbeat'], $row['twitter'], $row['facebook'], $row['reddit'], $row['linkedin']);
        $response = slackAPI($token, $message, $room);
        $response = json_decode($response, 1);
        $callback = $response['ts'] . " ::: " . $row['article'] . "\r\n";
        file_put_contents($listfile, $callback, FILE_APPEND | LOCK_EX);
    }
}

#==============================================================
function pregRssCleanup($row) {
    $row = preg_replace("/<\!\[CDATA\[(.*?)\]\]>/ism", "$1", $row);
    $row = preg_replace("/<dc:creator>.*?<span class=\"ng_byline_name\">(.*?)<\/span>(<\/p>)*/ism", "<author>$1</author>", $row);
    $row = preg_replace("/<p><span class=\"ng_byline_email\"><a href.*?>(.*?)<\/a><\/span>(<\/p>)*/ism", "<email>$1</email>", $row);
    $row = preg_replace("/<span class=\"ng_byline_credit\">(.*?)<\/span>/ism", "\r\n<credit>$1</credit>", $row);
    $row = preg_replace("/<\/dc:creator>/ism", "", $row);
    $row = preg_replace("/<dc:creator>/ism", "", $row);
    $row = preg_replace("/ &#8230;.*?<\/a><\/description>/ism", "</description>", $row);
    $row = preg_replace("/<link>.*?<\/link>/", "", $row);
    $row = preg_replace("/&/", "&#038;", $row);
    return $row;
}
function slackAPI($token, $message, $room = "sandbox", $icon = ":robot_face:") {
    $room = ($room) ? $room : "sandbox";
    $data = http_build_query(["channel" => "#{$room}", "token" => $token, "as_user" => "false", "username" => "[BOT NAME]", "text" => $message, "icon_emoji" => $icon]);
    $ch = curl_init("https://slack.com/api/chat.postMessage");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
function multi_implode($a, $g, $gb = NULL) {
    if ($gb == NULL) {
        $gb = $g;
    }
    $r = '';
    foreach ($a as $i) {
        if (is_array(is_array($i))) {
            $r.= multi_implode($i, $g) . $g;
        } elseif (is_array($i)) {
            $r.= multi_implode($i, $g) . $gb;
        } else {
            $r.= $i . $g;
        }
    }
    $r = substr($r, 0, 0 - strlen($g));
    return $r;
}
