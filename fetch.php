<?php
    
    error_reporting(E_ERROR);

    $query = htmlspecialchars($_GET["q"]);
    $newsobj = file_get_contents("https://news.google.com/rss/search?q=".$query."&hl=en-US&gl=US&ceid=US:en");

    $xml = simplexml_load_string($newsobj) or die("Error: Cannot create object");
    $json = json_encode($xml);
    $json = json_decode($json, true);
    $channel = $json["channel"];
        
    $i = 0;
    $newsArray = array();
    
    foreach($channel["item"] as $article){
      $newsArray[$i]["id_u"] = $i;
      $newsArray[$i]["title"] = $article["title"];
      $newsArray[$i]["link"] = $article["link"];
      $i++;
    }

    $data = array(
      "news" => $newsArray
    );

    echo json_encode($data);
?>
