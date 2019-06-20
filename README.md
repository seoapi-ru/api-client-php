# API client for PHP

[![Build Status](https://secure.travis-ci.org/seoapi-ru/api-client-php.png)](http://travis-ci.org/seoapi-ru/api-client-php)

## Usage

Install the client with Composer:

`composer require seoapi-ru/api-client-php`

Create client with username and password to authenticate:

```php
$client = ApiClient::fromCredentials(
        'username',
        'password',
        new HttpClientFactory()
    );
```

...or using predefined session token.

```php
// ... first steps: see previous example

$client = ApiClient::fromToken(self::VALID_TOKEN, self::BASE_URL, new HttpClientFactory());
```

Use client methods to call API counterparts.

```php
$regions = $client->getRegions('москва');
$stats = $client->getDailyStatsReport('google', 2019, 2);
// etc, see tests
```

For expected JSON response formats refer JSON schemas located at tests/Functional/**.*Test.php