# google-news-json

google-news-json is a PHP RSS-to-JSON parser/converter that converts the response from Google News' RSS to JSON, making it easier for integration with all kinds of applications.

The library also features an image-scraper that works with ~85% of websites. I'm working on expanding it to even more sites soon.

## Installation

Just host the PHP files on your own web hosting. There are also great free alternatives for web hosting that should also work.

## Usage

Everything lives in one file, `fetch.php`.

**News feed (with images):** make requests to
**https://{yourdomain}/fetch.php?q={search_query}**

The search query can be anything. For example, cooking news should be **fetch.php?q=cooking**. Spaces and special characters are URL-encoded automatically.

Add `&images=0` to skip image lookups for a faster response.

**Image for one article:** make requests to
**https://{yourdomain}/fetch.php?url={article_url}**

The article URL is the one beginning with "https://news.google.com/".

### Response structures

**`fetch.php?q=...`** returns an object with a `news` array:

| Field | Type | Description |
|---|---|---|
| `news` | array | One entry per article in the feed |
| `news[].id_u` | integer | Position of the article in the result, starting at 0 |
| `news[].title` | string | Article headline |
| `news[].link` | string | Google News link to the article |
| `news[].image_url` | string (optional) | Image URL. Only present if an image was found |

```json
{
  "news": [
    {
      "id_u": 0,
      "title": "Example headline - Example Publisher",
      "link": "https://news.google.com/rss/articles/...",
      "image_url": "https://example.com/image.jpg"
    },
    {
      "id_u": 1,
      "title": "Another headline - Other Publisher",
      "link": "https://news.google.com/rss/articles/..."
    }
  ]
}
```

In the second entry, no image was found, so `image_url` is left out.

**`fetch.php?url=...`** returns a single object:

| Field | Type | Description |
|---|---|---|
| `image_url` | string (optional) | Image URL. Only present if an image was found |

```json
{"image_url": "https://example.com/image.jpg"}
```

or, when no image was found:

```json
{}
```

**Errors** return an object with an `error` string and an HTTP status code:

| Status | Meaning | Example body |
|---|---|---|
| 400 | Missing `q`, or `url` is not a `https://news.google.com/` link | `{"error": "missing q parameter"}` |
| 502 | The news feed or article could not be fetched or parsed | `{"error": "could not fetch news feed"}` |

Image lookups decode each Google News link to its publisher URL and read that page's `og:image` (or similar) meta tag. Results are fetched in parallel when the PHP curl extension is available.

## Contributing

Pull requests are welcome. For major changes, please open an issue first
to discuss what you would like to change.


## License

[MIT](https://choosealicense.com/licenses/mit/)
