# google-news-json

google-news-json is a PHP RSS-to-JSON parser/converter that converts the response from Google News' RSS to JSON, making it easier for integration with all kinds of applications.

The library also features an image-scraper that works with ~85% of websites. I'm working on expanding it to even more sites soon.

## Installation

Just host the PHP files on your own web hosting. There are also great free alternatives for web hosting that should also work.

## Usage

Make requests to 
**https://{yourdomain}/fetch.php?q={search_query}**

The search query can be anything. For example, cooking news should be **../fetch.php?q=cooking**. Make sure that if you have spaces in your query to replace them with "%20". (this will be automated in the script for future releases)

--
For images: Make requests to 
**https://{yourdomain}/image.php?url={article_url}**

The article URL is the one beginning with "news.google.com"

## Contributing

Pull requests are welcome. For major changes, please open an issue first
to discuss what you would like to change.


## License

[MIT](https://choosealicense.com/licenses/mit/)
