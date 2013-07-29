<?php

define("TIME_OUT_SECONDS", 600);
define("DABA_NAME", "universo");
define("NUMBER_OF_ARTICLES_TO_DISPLAY", 3);
echo ("timeout " . TIME_OUT_SECONDS);
echo ("dabaname " . DABA_NAME);

function updateDaba($url) {
    $m = new MongoClient();
    $db = $m->DABA_NAME;
    $meta = $db->meta;
    $resort = $db->$url;
    //look when was last access for resort
    //if longer ago then timeout reset and fetch news
    $query = array( 'url' => $url );
    $fields = array( 'last_update' => 1, '_id' => 0);
    $lastAccess = $meta->findOne($query, $fields)["last_update"];
    var_dump($lastAccess);
    echo("last Access" . $lastAccess);
    echo("time       " . time());
    if (( time() - $lastAccess ) > TIME_OUT_SECONDS ) {
        echo("feching news!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!1111onceonce!uno!!111");
        $xmlDoc = new DOMDocument();
        $xmlDoc->load($url);
        //process metaData
        $channel = $xmlDoc->getElementsByTagName('channel')->item(0);
        $channel_title = $channel->getElementsByTagName('link')->item(0)->childNodes->item(0)->nodeValue;
        $channel_link = $channel->getElementsByTagName('link')->item(0)->childNodes->item(0)->nodeValue;
        $channel_desc = $channel->getElementsByTagName('description')->item(0)->childNodes->item(0)->nodeValue;
        // Upsert
        $meta->update(
            $query,
            array(
                '$set' => array(
                    'last_update' => time(),
                    'title' => $channel_title,
                    'link'  => $channel_link,
                    'description'  => $channel_desc
                )
            ),
            array('upsert' => true)
        );


        //process article
        $articles = $xmlDoc->getElementsByTagName('item');
        $resort->remove();
        foreach ($articles as $a) {
            $title = $a->getElementsByTagName('title')->item(0)->childNodes->item(0)->nodeValue;
            $link = $a->getElementsByTagName('link')->item(0)->childNodes->item(0)->nodeValue;
            $description = $a->getElementsByTagName('description')->item(0)->childNodes->item(0)->nodeValue;
            $resort->insert(array( 'title' => $title, 'link' => $link, 'description' => $description));
        }
    }
    return $resort->count();
}

function getArticles($url, $from, $to) {
    $m = new MongoClient();
    $db = $m->DABA_NAME;
    $resort = $db->$url;
    $cursor = $resort->find();
    $cursor->sort(array( '_id' => 1))->limit( $to - $from)->skip($from);
    return  $cursor;
}

function getMeta($url) {
    $m = new MongoClient();
    $db = $m->DABA_NAME;
    $meta = $db->meta;
    return $meta->find(array('url' => $url));
}

function printArticle($document) {
    echo ("<p><a href='" . $document["link"] . "'>" .
        $document["title"] . "</a>");
    echo("<br>");
    echo($document["description"] . "</p>");
}

//get the q parameter from URL
$q=$_GET["q"];
$lower=$_GET["lower"];

$urls = array( 1 => "http://www.eluniverso.com/rss/politica.xml", 
               2 => "http://www.eluniverso.com/rss/economia.xml",
               3 => "http://www.eluniverso.com/rss/internacional.xml");

$url = $urls[$q];
$noOfArticles = updateDaBa($urls[$q]);
echo("noofarticles: " . $noOfArticles);
$cM = getMeta($urls[$q]);
foreach ($cM as $document) {
    printArticle($document);
}
echo("lower: ". $lower);
$cA = getArticles($urls[$q], $lower, $lower + NUMBER_OF_ARTICLES_TO_DISPLAY);

foreach ($cA as $document) {
    printArticle($document);
}

$forwardLower = ($noOfArticles - $lower) > NUMBER_OF_ARTICLES_TO_DISPLAY ? $lower + NUMBER_OF_ARTICLES_TO_DISPLAY 
                                                                     : $lower;
$backLower = max($lower - NUMBER_OF_ARTICLES_TO_DISPLAY, 0);
//create paging
echo("<input type=\"button\" value=\"back\" onclick=\"showRSS($q, $backLower);\" />");
echo("<input type=\"button\" value=\"forward\" onclick=\"showRSS($q, $forwardLower);\" />");

?>
