<?php
include_once('../include/recordbox.php');
$search=p('search',false);
$html='';
$records=[];

if ($search) {
    $records['year']=searchRecords(['year'=>$search]);    
    $records['genre']=searchRecords(['genre'=>$search]);
    $records['label']=searchRecords(['label'=>$search]);
    $records['artist']=searchRecords(['artist'=>$search]);
    $records['record']=searchRecords(['record'=>$search]);
    $records['letter']=searchRecords(['letter'=>$search]);
} else {
    $params=array_merge($_GET,$_POST);
    $records['all']=searchRecords($params);
}

$html='';

$columns=0;
foreach ($records as $record) {
    if (is_array($record)) {$columns++;}
}
if (!$columns) {
    $html="<h2>Sorry! No records found! Why not try these ones instead?</h2>";
    $columns=1;
    $records['random']=getRandomRecords(5);
}

foreach ($records as $name=>$category) {
    if (is_array($category)) {
        $html.="<div style='width:".(95/$columns)."%;' class='searchResults'>";
        $html.="<h2>".$name."</h2>";
        foreach ($category as $result) {
            $html.=$result['icon'];
        }    
        $html.="</div>";
    }
    
}    


?>

<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js"> <!--<![endif]-->
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <title>Joe Boyd's Record Box</title>
        <meta name="description" content="The first of 6 Record Boxes commissioned by The Space has been compiled by Joe Boyd. Legendary producer and founder of the UFO club in the 1960s.">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- record box css -->
        <?php echo getCSS(); ?> 

        <!-- externally supplied js -->
        <?php echo getLibraries(); ?>

        <!-- old scripts for light box -->
        <?php echo getMainSiteScripts(); ?>
        
        <!-- record box JS -->
        <?php echo getRecordBoxJS(); ?>

        <meta property="og:title" content="John Peel Record Boxes - Joe Boyd" />
        <meta property="og:url" content="http://johnpeelarchive.com/joe-boyd//" />
        <meta property="og:image" content="http://johnpeelarchive.com/joe-boyd/img/joe-boyd-share-image.jpg" />
        <meta property="og:site_name" content="John Peel Archive" />

        <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700' rel='stylesheet' type='text/css'>
    </head>
    <body>
        
        <?php
            // things like the header have been moved into their own files
            // so they can be changed across all pages in one go
            include_once('../include/elements/header.html');
        ?>        
        
        <script src="/js/boxes/recordBox.js"></script>

        <div id='ajaxContent'>
            <?php echo $html; ?>
        </div> <!-- this is where we're going to dump all the ajax'd stuff and see what happens -->        
        
        <?php
            //echo getPageBottom(); // includes clear div, social buttons, white space at bottom of page
        ?>
        
        <script>
            //getBoxes(); // fetch page data - needs to be put into Jquery ready to ensure
            <?php echo getGoogleAnalytics(); ?>
        </script>
        
        <!-- SOCIAL MEDIA APIs -->
        <?php echo getSocialAPIs(); ?>

        <!-- the following styles must be at the bottom so they load after video-js.css -->
        <style>
            .video-js {padding-top: 56.25%}
            .vjs-fullscreen {padding-top: 0px}
        </style>
        <div id='playerPile'>
        
        </div>
        
        <?php
            // instead of having the same player code on every page, it has been moved to it's own file.
            // including this html file will just insert it right here in the page.
            include_once('../include/elements/popup.html');
            include_once('../include/elements/player.html');
        ?>

    </body>
</html>