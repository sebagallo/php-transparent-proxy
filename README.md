# PHP Transparent Proxy Script

## Installation

#### Through composer

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/sebagallo/php-transparent-proxy"
        }
    ]
}

```

#### Manual Installation

Just take the file **src/Seba/Routing/TransparentProxy.php** and shove it at the desired place

## Usage

* **url**: endpoint you wish to forward you request to (get/post)
* **port**(_optional_): if not provided, will default to 80 or 443

```php
$proxy = new Seba\Routing\TransparentProxy($url, $port);
$result = $proxy->makeRequest();
```

**makeRequest** will return the response.

Check the **tests/test.php** file for a working example.
