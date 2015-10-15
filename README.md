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
	- `$clipW`; the width to clip (optional)
	- `$clipH`; the height to clip (optional)

####Return value:

The path to the screenshot.

####Note on Clipping

If you don't set a clip width & height, the resulting screenshot will be as tall as the web page, regardless of the `$height` setting. In most cases, you'll probably want to set `$clipW` and `$clipH` to match `$width` and `$height` respectively.

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
    'clipW' => 1024,
    'clipH' => 768
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
    'clipW' => 640,
    'clipH' => 480
  ]
);
```

###Additional Options

####Wait time

Phantomjs loads a page and then waits a certain length of time before taking the screenshot. This is to allow the page to be fully rendered, for images and fonts to be loaded, and so on. 

By default it waits for one second. To set the wait time to something else, call the following method:

```
setWait( $value )
```

The value should be in *milliseconds*, for example:

```
$screenshotter->setWait( 3000 ); // wait for three seconds
```

####SSL Protocol

Phantomjs sometimes has difficulty connecting to HTTPS sites. Chances are if that's the case, the process will appear to suceed but the screenshot will be blank.

In many cases this is because by default Phantomjs uses SSLv3 to connect, which is often either unsupported or - thanks to the [POODLE attack](https://community.qualys.com/blogs/securitylabs/2014/10/15/ssl-3-is-dead-killed-by-the-poodle-attack) - has been disabled.

To get around this, you can use the `setSSLProtocol()` method to explictly tell Phantomjs which SSL protocol to use; the following values are valid:

* `sslv3`
* `sslv2`
* `tlsv1`
* `any`

In most cases, the simplest approach is simply to set it to `all`, i.e.:

```
$screenshotter->setSSLProtocol( 'any' );
```

####Ignore SSL Errors

Certain SSL errors can also cause difficulty; notably expired or self-signed certificates. You can tell Phantomjs to ignore SSL errors with the following:

```
$screenshotter->ignoreSSLErrors();
// or
$screenshotter->ignoreSSLErrors( TRUE );
```

To tell it *not* to ignore SSL errors - which is the default behaviour - simply set it to `FALSE`:

```
$screenshotter->ignoreSSLErrors( FALSE );
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