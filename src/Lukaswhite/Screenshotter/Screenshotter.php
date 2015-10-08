<?php

namespace Lukaswhite\Screenshotter;

use Symfony\Component\Process\Process;
use Twig_Loader_Filesystem;
use Twig_Environment;

class Screenshotter {

	/**
   * The filesystem instance.
   *
   * @var \Illuminate\Filesystem\Filesystem
   */
  protected $files;

  /**
   * The cache (temporary) path
   *
   * @var string
   */
 	private $cachePath;

 	/**
   * The output path; i.e., where to put the resulting screenshots
   *
   * @var string
   */
 	private $outputPath;

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
   * Create a new invoice instance.
   *
   * 
   * @return void
   */
  
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
   * Adds a trailing slash to a path, if it doesn't already have one.
   * 
   * @param  string $path
   * @return string
   */
  private function getPathWithTrailingSlash($path) {
  	return rtrim($path, '/') . '/';
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
   * @return \Lukaswhite\Screenshotter
   */
  public function setHeight($height)
  {
    $this->height = intval($height);
    return $this;
  }

  /**
   * Set the timeout
   *
   * @param  int
   * @return \Lukaswhite\Screenshotter
   */
  public function setTimeout($timeout)
  {
    $this->timeout = intval($timeout);
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
   */
	public function capture($url, $filename, $options = [])
	{
		// Start building an array of parameters
		$params = [
			'url'				=>	$url,
			'filename'	=>	$filename,
			'width' 		=> 	$this->width,
			'height' 		=> 	$this->height,
		];
	
		// Optionally override with provided values, calling intval() because
		// they all represent sizes in pixels
		foreach(['width', 'height', 'clipW', 'clipH'] as $property) {
			if (isset($options[$property])) {
				$params[$property] = intval($options[$property]);
			}			
		}		

		// Get the path to the job file, or create it if it doesn't already exist
		$jobPath = $this->getJobFile($params);

		// Build the destination path
		$filepath = $this->outputPath . $filename;

		$this->getPhantomProcess($jobPath, $filepath)->setTimeout($this->timeout)->mustRun();		

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
   */
  public function getPhantomProcess($jobPath, $screenshotPath)
  {    
    return new Process(
    	sprintf(
    		'%s %s %s', 
    		$this->getPhantomjsPath(),
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
      $this->getExtension($system)
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
  protected function getExtension($system)
  {
		return $system == 'windows' ? '.exe' : '';
  }

}