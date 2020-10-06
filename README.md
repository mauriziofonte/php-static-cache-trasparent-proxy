# PHP Image to WEBP Rewriter Transparent Proxy

This project can be used to serve **static assets** (like, for example, `css`, `js`, `jpg` files) of your website from a **"self-hosted CDN"**, that will act as a **transparent proxy** from the origin server to the client's browser.

The peculiarity of this project is that, for every image requested **from the transparent proxy**, it will **convert the source image in WEBP format**, serve it, and then for every other request all images will be **permanent-redirected to the webp version**.

## Can you give me an example of what's going on?

Suppose you:

1. Have an **origin server** called `https://my-project.example.com`
2. Clone this repository to a VirtualHost called `https://static.mycdn.com`
3. Want to make all images of `https://my-project.example.com/images/whatever/*.jpg` automatically converted to **webp** and served by the CDN at `https://static.mycdn.com/images/whatever/*.webp`

Then, you would only need to make sure that **images** in the **origin server** are referenced in the HTML this way:

```
...
The HTML of a page inside https://my-project.example.com
...
<img src="https://static.mycdn.com/images/whatever/*.jpg" />
```

Instead of

```
<img src="/images/whatever/*.jpg" />
```

Then, voilà, **your origin server is webp-enabled without touching a single line of code** (apart from redirecting static assets to the CDN itself, but **that is easy**)

## What files can be served from the static CDN transparent proxy?

You define it via the **.env** file

## Installation

1.  A lamp server with a valid https virtualhost
2.  Clone this repo to the virtualhost's root dir
3.  Make sure `mod_rewrite` and `mod_headers` are enabled ( `sudo a2enmod rewrite headers` )
4.  Make sure you have `curl` support in your PHP enabled modules
5.  Run `composer install`
6.  Install my other project, `https://github.com/mauriziofonte/php-image-to-webp-conversion-api` into a virtualhost of its own (maybe in another dev server with PHP's `exec()` enabled) and set it up reading the project's readme
7.  Clone the `.env.example` into `.env` and modify it accordingly to your needs

## What to do on the frontend, to rewrite static assets to the CDN

It depends on your application. If it is a CMS, refer to the CMS's output buffering and/or output filter functionalities. If it's something custom, you can do the following (untested!):

```
<?php
    ob_start('rewrite_static_assets');
    ... app ...
    ... app ...
    $html = ob_get_clean();
    echo $html;
    exit();

    function rewrite_static_assets($buffer) {
        // rewrite <img > assets (works only if images are referenced with absolute paths without protocol+domain)
        $buffer = preg_replace_callback('<img\s+src="([^"]+)"', function($matches) {
            $new_asset_uri = 'https://static.mycdn.com/' . ltrim($matches[1], '/');
            return str_replace($matches[1], $new_asset_uri, $matches[0]);
        }, $buffer);

        // rewrite <link rel="stylesheet" type="text/css"> assets (same as above)
        $buffer = preg_replace_callback('<link\s+rel="stylesheet"\s+type="text/css"\s+href="([^"]+)"', function($matches) {
            $new_asset_uri = 'https://static.mycdn.com/' . ltrim($matches[1], '/');
            return str_replace($matches[1], $new_asset_uri, $matches[0]);
        }, $buffer);

        // return the modified buffer
        return $buffer;
    }
?>
```

## How to configure the .env

```
DEBUG = true|false
    Defines the error reporting of the application. NEEDS TO BE TURNED OFF IN PRODUCTION, otherwise static assets output may be poisoned from the PHP error output
SOURCE_HOST = "https://main.origin.server.biz"
    Defines the main host where the "original" static assets are physically present and can be cloned from
CACHEABLE_EXTENSIONS = "css,js,jpg,jpeg,png,gif,bmp,webp,ttf,woff,woff2,otf,eot,svg,ico"
    Define the extensions that will be enabled on the transparent proxy. Potentially, you could use this to cache also HTML or ZIP files
CACHE_EXPIRY = 2592000
    Defines the expiry time of static assets served from the trasparent CDN. Please keep in mind that this directive will be set via PHP only the "first time" a static asset does not exists on the CDN filesystem. Subsequent calls will be cached accordingly to what the .htaccess defines. (1 month)
CHARSET = "UTF-8"
TIMEZONE = "Europe/Rome"
LOCALE = "en_US"
WEBP_API_SERVER = "https://github.com/mauriziofonte/php-image-to-webp-conversion-api"
    Defines the Image to WEBP api url, that can be cloned from https://github.com/mauriziofonte/php-image-to-webp-conversion-api . Refer to the documentation of that project to know more.
WEBP_API_KEY = "a-very-strong-api-key"
    Same as WEBP_API_SERVER. Refer to the documentation of "php-image-to-webp-conversion-api" to know more.
```
