# PHP-Based "Transparent Proxy" CDN + Optional on-the-fly Image to WEBP conversion

This project can be used to serve **static assets** (like, for example, `css`, `js`, `jpg` files) of your website from a **"self-hosted CDN"**, that will act as a **transparent proxy** from the origin server to the client's browser.

The peculiarity of this project is that, for every image requested **from the transparent proxy**, it will **convert the source image in WEBP format**, serve it, and then for every other request all images will be **permanent-redirected to the webp version**.

## What is the purpose of this project?

The purpose of this project is to **serve static assets from a CDN in your control**, without having to **touch the code of the origin server**.

This is useful, for example, if you:

1. Don't want to use a third-party CDN like Cloudflare or Cloudfront
2. Don't want to touch the code of the origin server - in the sense that you don't want to deeply change the way static assets are referenced in the HTML, but only write a quick and dirty rewrite rule to redirect all static assets to the CDN itself (or a buffer post-processing function, if you are using a CMS)
3. Want to serve **webp** images to browsers that support it, and **jpg** images to browsers that don't support it, without having to touch the code of the origin server

## Request lifecycle

1. The client requests a _Web Page_ of your website
2. That _Web Page_ contains references to static assets (images, css, js, etc) that are re-routed to the CDN in a 1:1 pattern, like `https://my-project.example.com/images/whatever/resource.jpg` -> `https://static.mycdn.com/images/whatever/resource.jpg`
3. The _Transparent Proxy CDN_ receives the _Request_ (file path) and checks if the requested _Resource_ is already present in its filesystem
4. If the _Resource_ is physically present on the CDN's filesystem, it is served to the client and optimized via the `.htaccess` _Expires_ and _Cache-Control_ directives
5. If the _Resource_ is not present on the CDN's filesystem, it is **automatically fetched from the origin server** and served to the _Client_, and then it is **cached** on the CDN's filesystem. In this case, _headers_ are blindly mirrored from the origin server to the client, and the _Expires_ and _Cache-Control_ directives are set via PHP

## Can you give me an example of what's going on?

Suppose you:

1. Have an **origin server** called `https://my-project.example.com`
2. Clone this repository to a VirtualHost called `https://static.mycdn.com`
3. Want to make all images of `https://my-project.example.com/images/whatever/*.jpg` automatically converted to **webp** and served by the CDN at `https://static.mycdn.com/images/whatever/*.webp`

Then, you would only need to make sure that **images** in the **origin server** are referenced in the HTML this way:

```html
<!--
The HTML of a page inside https://my-project.example.com
-->
<img src="https://static.mycdn.com/images/whatever/resource.jpg" />
```

Instead of

```html
<img src="/images/whatever/resource.jpg" />
```

Then, voilà, **your origin server is webp-enabled without touching a single line of code** (apart from redirecting static assets to the CDN itself, but **that is easy**)

## What files can be served from the static CDN transparent proxy?

You define it via the **.env** file

## Installation

1. Clone this repo inside your _LAMP_ server with a VirtualHost pointed to `/proj/dir/public`
2. Make sure `mod_rewrite`, `mod_headers` and `mod_expires` are enabled ( `sudo a2enmod rewrite headers expires` )
3. Make sure you have `curl` support in your _PHP enabled modules_
4. Make sure you're running at least **PHP 7.2.5**
5. Run `composer install`
6. Install my other project [https://github.com/mauriziofonte/php-image-to-webp-conversion-api](https://github.com/mauriziofonte/php-image-to-webp-conversion-api) into a VirtualHost of its own - _even better in another dev server with PHP's `exec()` enabled_ and configure it accordingly to the project's readme
7. Clone the `.env.example` into `.env` and modify it accordingly to your needs

Specifically, if you want to use the **Image to WEBP conversion API**, you need to set the `WEBP_API_SERVER` and `WEBP_API_KEY` variables in the `.env` file.

> **Heads up!**
>
> The application **automatically caches** the `.env` file into `.cached.env.php` to avoid reading the `.env` file on every request.
>
> If you modify the `.env` file, you need to **manually delete** the `.cached.env.php` file to force the application to re-read the `.env` file.

## What to do on the frontend, to rewrite static assets to the CDN

It depends on your application. If it is a CMS, refer to the CMS's output buffering and/or output filter functionalities. If it's something custom, you can do the following (untested!):

```php
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

```text
DEBUG = true|false
    Defines the error reporting of the application. NEEDS TO BE TURNED OFF IN PRODUCTION, otherwise static assets output may be poisoned from the PHP error output

SOURCE_ORIGIN = "https://main.origin.server.biz"
    Defines the main host where the "original" static assets are physically present and can be cloned from

CACHEABLE_EXTENSIONS = "css,js,jpg,jpeg,png,gif,bmp,webp,ttf,woff,woff2,otf,eot,svg,ico"
    Define the extensions that will be enabled on the transparent proxy. Potentially, you could use this to cache also HTML or ZIP files

CACHE_EXPIRY = 2592000
    Defines the expiry time of static assets served from the trasparent CDN. Please keep in mind that this directive will be set via PHP only the "first time" a static asset does not exists on the CDN filesystem. Subsequent calls will be cached accordingly to what the .htaccess defines. (1 month)

CHARSET  = "UTF-8"        # Self-explanatory. Valid values are "UTF-8" or "ISO-8859-1"
TIMEZONE = "Europe/Rome"  # Self-explanatory. Valid values are the ones defined in https://www.php.net/manual/en/timezones.php
LOCALE   = "en_US"        # Self-explanatory. Valid values are the ones defined in https://www.php.net/manual/en/function.setlocale.php

WEBP_API_SERVER = "https://my.webp.conversion.microservice.biz"
    Defines the Image to WEBP api url, that can be cloned from https://github.com/mauriziofonte/php-image-to-webp-conversion-api . Refer to the documentation of that project to know more.

WEBP_API_KEY = "a-very-strong-api-key"
    Same as WEBP_API_SERVER. Refer to the documentation of "php-image-to-webp-conversion-api" to know more.
```
