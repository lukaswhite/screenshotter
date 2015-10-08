#Screenshotter

A PHP class for creating screenshots of web pages.

Behind the scenes it uses [PhantomJS](http://phantomjs.org/), which is installed for you when you install this package. (Linux, OSX and Windows).

##Installation

```
composer require lukaswhite/screenshotter
```

Ensure that the `bin/` directory is executable:

```
chmod -R +x vendor/lukaswhite/screenshotter/src/bin
```

(Note that Composer _should_ take care of this for you as a post-install command.)

##Usage

###1. Create an instance of the class:

```
$screenshotter = new \Lukaswhite\Screenshotter\Screenshotter(
  $ouputPath, 
  $cachePath
);
```

####Parameters

* `$outputPath` is the directory you want to save the resulting screenshot to (without the filename). It should exist, and be writeable.
* `$cachePath` is a directory used for caching, and is required. Again, it should exist and be writeable.

####Example:

```
$screenshotter = new \Lukaswhite\Screenshotter\Screenshotter(
  '/var/www/example.com/app/storage/screenshots/',
  '/var/tmp/'
);
```

###2. To take a screenshot:

```
$screenshot = $screenshotter->capture(
  $url, 
  $filename, 
  $options = []
);
```

####Parameters:

* `$url` is the URL of the website / web page you want to take a screenshot of.
* `$filename` is the filename to save the screenshot to.
* `$options` is an optional array of additional options:
- `$width`; if you don't provide this, it's set to 1024px
- `$height`; if you don't provide this, it's set to 768px
- `$clipW`; the width to crop (optional)
- `$height`; the height to crop (optional)

####Return value:

The path to the screenshot.

####Note on Cropping

If you don't set a crop width & height, the resulting screenshot will be as tall as the web page, regardless of the `$height` setting. In most cases, you'll probably want to set `$cropW` and `$cropH` to match `$width` and `$height` respectively.

####Examples

```
$screenshot = $screenshotter->capture(
  'http://www.lukaswhite.com',
  'lukaswhitedotcom.png'
);
```

```
$screenshot = $screenshotter->capture(
  'http://www.lukaswhite.com',
  'lukaswhitedotcom.png',
  [
    'cropW' => 1024,
    'cropH' => 768
  ]
);
```

```
$screenshot = $screenshotter->capture(
  'http://www.lukaswhite.com',
  'lukaswhitedotcom.png',
  [
    'width' => 640,
    'height' => 480,
    'cropW' => 640,
    'cropH' => 480
  ]
);
```

###Exceptions

If the screenshot process fails, it'll throw an Exception. Most likely this will be an instance of:

```
Symfony\Component\Process\Exception\ProcessTimedOutException
```

This type of exception (a timeout) can happen for a variety of reasons, and not just a timeout; because PhantomJS is an external process, it's not always easy to know what failed, so check your parameters. You can also increase the timeout:

```
$screenshotter->setTimeout(60); // Set timeout to 60 seconds, instead of the defaut 10
```