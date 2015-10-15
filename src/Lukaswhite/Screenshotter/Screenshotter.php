<?php

namespace Lukaswhite\Screenshotter;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Twig_Loader_Filesystem;
use Twig_Environment;

class Screenshotter {

  /**
   * The output path; i.e., where to put the resulting screenshots
   *
   * @var string
   */
  private $outputPath;

  /**
   * The cache (temporary) path
   *
   * @var string
   */
 	private $cachePath;

  /**
   * The default width
   *
   * @var int
   */
  public $width = 1024;

  /**
   * The default height
   *
   * @var int
   */
  public $height = 768;

  /**
   * The default timeout
   *
   * @var int
   */
  public $timeout = 10;

  /**
   * The default wait time (ms)
   *
   * @var int
   */
  public $wait = 1000;

  /**
   * The SSL protocol to use for secure connections
   * 
   * @var string (sslv3|sslv2|tlsv1|any)
   */
  public $ssl_protocol;

  /**
   * Whether to ignore SSL errors, e.g. such as expired or self-signed certificate errors
   * 
   * @var boolean
   */
  public $ssl_ignore = TRUE;


  /**
   * Create a new instance of the screenshotter service.
   *
   * Note that both of the provided paths (output & cache) should exist and be writeable.
   * 
   * @param string $outputPath File path for where the screenshots could go
   * @param string $cachePath  Cache (temporary) path
   */
  public function __construct($outputPath, $cachePath)
  {    

    $this->setOutputPath($outputPath); 

		$this->setCachePath($cachePath);

  }

  /**
   * Sets the output path.
   * 
   * @param string $path
   * @return \Lukaswhite\Screenshotter\Screenshotter
   */
  public function setOutputPath($path)
  {
  	$this->outputPath = $this->getPathWithTrailingSlash($path);
  	return $this;
  }

  /**
   * Sets the cache path.
   * 
   * @param string $path
   * @return \Lukaswhite\Screenshotter\Screenshotter
   */
  public function setCachePath($path)
  {
  	$this->cachePath = $this->getPathWithTrailingSlash($path);
  	return $this;
  }

  /**
   * Set the default width
   *
   * @param  int
   * @return \Lukaswhite\Screenshotter\Screenshotter
   */
  public function setWidth($width)
  {
    $this->width = intval($width);
    return $this;
  }

  /**
   * Set the default height
   *
   * @param  int
   * @return \Lukaswhite\Screenshotter\Screenshotter
   */
  public function setHeight($height)
  {
    $this->height = intval($height);
    return $this;
  }

  /**
   * Set the timeout
   *
   * @param  int  The timeout in seconds
   * @return \Lukaswhite\Screenshotter\Screenshotter
   */
  public function setTimeout($timeout)
  {
    $this->timeout = intval($timeout);
    return $this;
  }

  /**
   * Set the wait time. That is to say, how long the process should wait for the page to be 
   * rendered before taking the screenshot.
   *
   * @param  int  The wait time in milliseconds
   * @return \Lukaswhite\Screenshotter\Screenshotter
   */
  public function setWait($wait)
  {
    $this->wait = intval($wait);
    return $this;
  }

  /**
   * Sets the SSL protocol
   * 
   * @param string $ssl_protocol (sslv3|sslv2|tlsv1|any)
   * @return \Lukaswhite\Screenshotter\Screenshotter
   * @throws \Exception
   */
  public function setSSLProtocol($ssl_protocol)
  {    
    $valid_protocols = ['sslv3', 'sslv2', 'tlsv1', 'any'];
    if (!in_array($ssl_protocol, $valid_protocols)) {
      throw new \Exception(sprintf('Protocol must be one of %s', implode(', ', $valid_protocols)));
    }
    $this->ssl_protocol = $ssl_protocol;
    return $this;
  }

  /**
   * Whether to ignore SSL errors
   * 
   * @param  boolean $ssl_ignore
   * @return \Lukaswhite\Screenshotter\Screenshotter
   */
  public function ignoreSSLErrors($ssl_ignore = TRUE)
  {
    $this->ssl_ignore = TRUE;
    return $this;
  }

  /**
   * Capture a screenshot
   * 
   * @param  string $url      The URL to take a screenshot of
   * @param  string $filename The filename to write the screenshot to
   * @param  array  $options  An optional array of parameters; e.g. width, height, clipW, clipH
   * @return string           The filepath of the screenshot
   * @throws \Symfony\Component\Process\Exception\ProcessTimedOutException
   * @throws \Symfony\Component\Process\Exception\ProcessFailedException
   */
	public function capture($url, $filename, $options = [])
	{
		// Start building an array of parameters
		$params = [
			'url'				=>	$url,
			'filename'	=>	$filename,
			'width' 		=> 	$this->width,
			'height' 		=> 	$this->height,
      'wait'      =>  $this->wait,
		];
	
		// Optionally override with provided values, calling intval() because
		// they all represent sizes in pixels
		foreach(['width', 'height', 'clipW', 'clipH', 'wait'] as $property) {
			if (isset($options[$property])) {
				$params[$property] = intval($options[$property]);
			}			
		}		

		// Get the path to the job file, or create it if it doesn't already exist
		$jobPath = $this->getJobFile($params);

		// Build the destination path
		$filepath = $this->outputPath . $filename;

		$process = $this->getPhantomProcess($jobPath, $filepath)->setTimeout($this->timeout);

    // Can thow ProcessFailedException or ProcessTimedOutException
    $process->mustRun();		
    
		return $filepath;

	}

	/**
	 * Get the job file. The job file is a .js file passed to Phantom which provides it with the necessary information
	 * to take the screenshot.
	 *
	 * If the file doesn't exist, it'll be created.
	 * 
	 * @param  array $params
	 * @return string
	 */
	private function getJobFile($params)
	{
		// Put the parameters in alphabetical order...
		arsort($params);
		// ...which we can use to generate a hash that presents the URL + parameters
		$hash = md5($params['url'] . implode('|', $params));

		// Determine the path to the job file
		$jobPath = sprintf('%s/%s.js', $this->cachePath, $hash);

		if (file_exists($jobPath)) {
			return $jobPath;
		}

		return $this->buildJobFile($jobPath, $params);

	}

	/**
	 * Given a set of parameters (and a filename), create a job file.
	 * 
	 * @param  string $jobPath
	 * @param  array  $params
	 * @throws \Exception 
	 * @return string
	 */
	private function buildJobFile($jobPath, $params)
	{		
		$loader = new Twig_Loader_Filesystem(__DIR__.'/../../templates');
		$twig = new Twig_Environment($loader, array(
			'cache' => $this->cachePath,
		));

		// Create the job file
		$status = file_put_contents(		
			$jobPath,
			$twig->render(
				'screenshot.twig.js', 
				$params			
			)
		);

    // Check that the file has saved properly, otherwise throw an exception.
		if ($status === FALSE) {
			throw new \Exception('Couldn\'t write the job file. Check that the provided cache path is correct, exists and is writeable.');
		}

		return $jobPath;
	}

	/**
   * Get the PhantomJS process instance.
   *
   * @param  string  $viewPath
   * @return \Symfony\Component\Process\Process
   * @todo Use Symfony's Process builde instead of string concatenation
   */
  public function getPhantomProcess($jobPath, $screenshotPath)
  {    
    
    // Start building the command, starting with the full path to the phantomjs executable
    $command = $this->getPhantomjsPath();

    // Optionally add a flag to specify the SSL protocol
    if ($this->ssl_protocol) {
      $command .= sprintf(' --ssl-protocol=%s', $this->ssl_protocol);
    }

    // Optionally set the flag to ignore SSL errors
    if ($this->ssl_ignore) {
      $command .= ' --ignore-ssl-errors=true';
    }

    return new Process(
    	sprintf(
    		'%s %s %s', 
    		$command,
    		$jobPath, 
    		$screenshotPath
    	),
    	__DIR__
    );
  }

  /**
   * Get the full path to the PhantomJS executable. 
   *
   * The actual path varies according to the OS.
   * 
   * @return string
   */
  public function getPhantomjsPath()
  {
    $system = $this->getSystem();

    return sprintf(
      '%s/bin/%s/phantomjs%s',
      __DIR__,
      $system, 
      $this->getPhantomJsExtension($system)
    );

  }

  /**
   * Get the operating system for the current platform.
   *
   * @return string
   */
  protected function getSystem()
  {
    $uname = strtolower(php_uname());

    if (str_contains($uname, 'darwin')) {
      return 'macosx';
    } elseif (str_contains($uname, 'win')) {
      return 'windows';
    } elseif (str_contains($uname, 'linux')) {
      return PHP_INT_SIZE === 4 ? 'linux-i686' : 'linux-x86_64';
    } else {
      throw new \RuntimeException('Unknown operating system.');
    }
  }

  /**
   * Get the binary extension for the system.
   *
   * @param  string  $system
   * @return string
   */
  protected function getPhantomJsExtension($system)
  {
		return $system == 'windows' ? '.exe' : '';
  }

  /**
   * Adds a trailing slash to a path, if it doesn't already have one.
   * 
   * @param  string $path
   * @return string
   */
  private function getPathWithTrailingSlash($path) {
    return rtrim($path, '/') . '/';
  }

}