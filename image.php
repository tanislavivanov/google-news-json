<?php

    error_reporting(E_ERROR);

    $url = htmlspecialchars($_GET["url"])."&_rdr=p";
    $html = file_get_contents($url);
    
    $dom = new DOMDocument();
    $dom->loadHTML($html);

    $xpath = new DOMXPath($dom);
    $tagName = 'a';
    $attributeName = 'jsname';

    $elements = $xpath->query("//{$tagName}[@{$attributeName}]");

    if ($elements->length > 0) {
        $element = $elements->item(0);
        $tagContent = $element->nodeValue;
        $metas = get_meta_tags($tagContent);
        if ($metas["twitter:image:src"] !== null){
            echo '{"image_url": "'. $metas["twitter:image:src"] .'"}';
        } else if ($metas["twitter:image"] !== null){
            echo '{"image_url": "'. $metas["twitter:image"] .'"}';
        } else if ($metas["og:image"] !== null){
            echo '{"image_url": "'. $metas["og:image"] .'"}';
        } else if ($metas["sailthru_image_full"] !== null){
            echo '{"image_url": "'. $metas["sailthru_image_full"] .'"}';
        } else if ($metas["sailthru.image.full"] !== null){
            echo '{"image_url": "'. $metas["sailthru.image.full"] .'"}';
        } else if ($metas["msapplication-tileimage"] !== null){
            echo '{"image_url": "'. $metas["msapplication-tileimage"] .'"}';
        } else if ($metas["parsely-image-url"] !== null){
            echo '{"image_url": "'. $metas["parsely-image-url"] .'"}';
        } else {
            echo '{"image_url": "https://i0.wp.com/theperfectroundgolf.com/wp-content/uploads/2022/04/placeholder.png?fit=1200%2C800&ssl=1"}';
        }
    } else {
        echo '{"image_url": "https://i0.wp.com/theperfectroundgolf.com/wp-content/uploads/2022/04/placeholder.png?fit=1200%2C800&ssl=1"}';
    }


?>
