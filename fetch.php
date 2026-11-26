<?php

    /*
     * google-news-json — single endpoint.
     *
     *   fetch.php?q={search_query}            -> news feed as JSON, each item with an image_url
     *   fetch.php?q={search_query}&images=0   -> same feed without image lookups (faster)
     *   fetch.php?url={news.google.com link}  -> image_url for one Google News article
     *
     * Image lookup: a Google News link is decoded to the publisher's real URL
     * (Google's batchexecute endpoint), then that page's og:/twitter: image meta
     * tag is used.
     */

    error_reporting(E_ERROR);
    header('Content-Type: application/json; charset=utf-8');

    const USER_AGENT        = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
    const TIMEOUT           = 10;
    const DECODE_ENDPOINT   = 'https://news.google.com/_/DotsSplashUi/data/batchexecute';

    // Meta tags that may hold an article's main image, in priority order.
    const IMAGE_META_KEYS = [
        'og:image', 'og:image:url', 'og:image:secure_url',
        'twitter:image', 'twitter:image:src',
        'sailthru.image.full', 'sailthru_image_full',
        'parsely-image-url', 'msapplication-tileimage',
    ];

    function respond($data, int $status = 200): void {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Run several HTTP requests in parallel with curl (or one after another without it).
     * $requests: key => [url, postBody|null]. Returns key => response body|null.
     */
    function http_request_many(array $requests): array {
        $results = [];

        if (!function_exists('curl_multi_init')) {
            foreach ($requests as $key => [$url, $body]) {
                $results[$key] = http_request_one($url, $body);
            }
            return $results;
        }

        $mh = curl_multi_init();
        $handles = [];
        foreach ($requests as $key => [$url, $body]) {
            $ch = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => TIMEOUT,
                CURLOPT_USERAGENT      => USER_AGENT,
                CURLOPT_ENCODING       => '',
            ];
            if ($body !== null) {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $body;
                $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/x-www-form-urlencoded;charset=UTF-8'];
            }
            curl_setopt_array($ch, $options);
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        do {
            $status = curl_multi_exec($mh, $running);
            if (curl_multi_select($mh, 1.0) === -1) {
                usleep(100000);
            }
        } while ($running > 0 && $status === CURLM_OK);

        foreach ($handles as $key => $ch) {
            $text = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $results[$key] = ($code === 200 && $text !== '') ? $text : null;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        return $results;
    }

    function http_request_one(string $url, ?string $body = null): ?string {
        $http = [
            'timeout'         => TIMEOUT,
            'follow_location' => 1,
            'max_redirects'   => 5,
            'header'          => "User-Agent: " . USER_AGENT . "\r\n",
        ];
        if ($body !== null) {
            $http['method'] = 'POST';
            $http['header'] .= "Content-Type: application/x-www-form-urlencoded;charset=UTF-8\r\n";
            $http['content'] = $body;
        }
        $text = @file_get_contents($url, false, stream_context_create(['http' => $http]));
        return $text === false || $text === '' ? null : $text;
    }

    function is_google_news_url(string $url): bool {
        $parts = parse_url($url);
        return isset($parts['scheme'], $parts['host'])
            && $parts['scheme'] === 'https'
            && strtolower($parts['host']) === 'news.google.com';
    }

    /** Read the signature and timestamp Google needs to decode an article link. */
    function google_decode_params(string $html): ?array {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $node = (new DOMXPath($dom))->query('//*[@data-n-a-sg][@data-n-a-ts]')->item(0);
        if ($node === null) {
            return null;
        }
        return [
            'sig' => $node->getAttribute('data-n-a-sg'),
            'ts'  => (int)$node->getAttribute('data-n-a-ts'),
        ];
    }

    /** Ask Google which publisher URL a news.google.com article id points to. */
    function google_decode_body(string $articleId, array $params): string {
        $inner = json_encode([
            'garturlreq',
            [['en-US', 'US', ['FINANCE_TOP_INDICES', 'WEB_TEST_1_0_0'], null, null, 1, 1, 'US:en', null, 1,
              null, null, null, null, null, 0, 1],
             'en-US', 'US', 1, [2, 3, 4, 8], 1, 0, '655000234', 0, 0, null, 0],
            $articleId,
            $params['ts'],
            $params['sig'],
        ]);
        $payload = json_encode([[['Fbv4je', $inner, null, 'generic']]]);
        return 'f.req=' . rawurlencode($payload);
    }

    /** Pull the publisher URL out of a batchexecute response. */
    function google_decode_result(string $response): ?string {
        foreach (explode("\n", $response) as $line) {
            if (strpos($line, 'wrb.fr') === false) {
                continue;
            }
            $outer = json_decode($line, true);
            $inner = json_decode($outer[0][2] ?? '', true);
            $url = $inner[1] ?? null;
            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && !is_google_news_url($url)) {
                return $url;
            }
        }
        return null;
    }

    /** Pull the best image URL out of an article's HTML, or null if it has none. */
    function extract_image_url(string $html): ?string {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $metas = [];
        foreach ((new DOMXPath($dom))->query('//meta') as $meta) {
            $name = strtolower($meta->getAttribute('property') ?: $meta->getAttribute('name'));
            $content = trim($meta->getAttribute('content'));
            if ($name !== '' && $content !== '' && !isset($metas[$name])) {
                $metas[$name] = $content;
            }
        }

        foreach (IMAGE_META_KEYS as $key) {
            if (isset($metas[$key]) && filter_var($metas[$key], FILTER_VALIDATE_URL)) {
                return $metas[$key];
            }
        }
        return null;
    }

    /**
     * Resolve many Google News links to image URLs, in three parallel stages:
     * article page -> publisher URL -> publisher page. Returns key => image URL|null.
     */
    function resolve_images(array $links): array {
        // Stage 1: Google article pages (for the decode signature).
        $pages = http_request_many(array_map(fn($l) => [$l, null], $links));
        $decodeRequests = [];
        foreach ($links as $key => $link) {
            $params = $pages[$key] !== null ? google_decode_params($pages[$key]) : null;
            if ($params !== null) {
                $articleId = basename(parse_url($link, PHP_URL_PATH));
                $decodeRequests[$key] = [DECODE_ENDPOINT, google_decode_body($articleId, $params)];
            }
        }

        // Stage 2: decode to publisher URLs.
        $decoded = http_request_many($decodeRequests);
        $publisherRequests = [];
        foreach ($decoded as $key => $response) {
            $publisherUrl = $response !== null ? google_decode_result($response) : null;
            if ($publisherUrl !== null) {
                $publisherRequests[$key] = [$publisherUrl, null];
            }
        }

        // Stage 3: publisher pages -> og:image and friends.
        $publisherPages = http_request_many($publisherRequests);
        $images = [];
        foreach ($links as $key => $_) {
            $html = $publisherPages[$key] ?? null;
            $images[$key] = $html !== null ? extract_image_url($html) : null;
        }
        return $images;
    }

    // ---- Single article image: fetch.php?url=... ----------------------------
    if (isset($_GET['url'])) {
        $articleUrl = (string)$_GET['url'];
        if (!is_google_news_url($articleUrl)) {
            respond(['error' => 'url must be a https://news.google.com/ link'], 400);
        }
        $image = resolve_images([$articleUrl])[0] ?? null;
        // No image found: return an empty object (no image_url key).
        respond((object)($image !== null ? ['image_url' => $image] : []));
    }

    // ---- News feed: fetch.php?q=... -----------------------------------------
    if (!isset($_GET['q']) || trim((string)$_GET['q']) === '') {
        respond(['error' => 'missing q parameter'], 400);
    }

    $feedUrl = 'https://news.google.com/rss/search?q=' . rawurlencode((string)$_GET['q'])
             . '&hl=en-US&gl=US&ceid=US:en';
    $rss = http_request_one($feedUrl);
    if ($rss === null) {
        respond(['error' => 'could not fetch news feed'], 502);
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($rss);
    if ($xml === false || !isset($xml->channel)) {
        respond(['error' => 'could not parse news feed'], 502);
    }

    $articles = [];
    foreach ($xml->channel->item as $item) {
        $articles[] = [
            'id_u'  => count($articles),
            'title' => (string)$item->title,
            'link'  => (string)$item->link,
        ];
    }

    if (!isset($_GET['images']) || $_GET['images'] !== '0') {
        $links = array_column($articles, 'link', 'id_u');
        $images = resolve_images($links);
        foreach ($articles as $i => $article) {
            // Only add image_url when an image was found.
            if (!empty($images[$article['id_u']])) {
                $articles[$i]['image_url'] = $images[$article['id_u']];
            }
        }
    }

    respond(['news' => $articles]);
