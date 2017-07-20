<?php

function D($v)
{
	print '<pre>';
	var_dump($v);
	print '</pre>';
}

function DX($v)
{
	D($v);
	die;
}

class VideoStream
{
	/**
	 * @var string Video path and name; e.g.: /videos/sample.avi
	 */
	protected $video = '';
	//private $path = "";

	/**
 	 * @var int Contains current stream
	 */
	private $stream = "";

	/**
	 * @var int Current stream start from
	 */
	private $start = -1;

	/**
	 * @var int Current stream end with
	 */
	private $end = -1;

	/**
	 * @var int Video size	 
	 */
	private $size = 0;

	/**
	 * @var string Detected mime type
	 */
	private $mime = '';

	/**
	 * @var Buffer size	 
	 */
	protected $buffer = 102400;
 
 	/**
 	 * Constructor
 	 *
 	 * @param string $video Video path and name; e.g.: /videos/sample.avi
 	 */
	public function __construct($video) 
	{
		$this->video = $video;

		return $this;
	}

	/**
	* Open stream
	*/
	private function open()
	{
		if (!($this->stream = fopen($this->video, 'rb'))) {
			die('Could not open stream for reading');
		}

	}

	/**
	 * Auto detect mime type
	 */
	protected function detectMime()
	{
		$this->mime = mime_content_type($this->video);
	}

	/**
	* Set proper header to serve the video content
	*/
	private function setHeader()
	{
		ob_get_clean();
		header("Content-Type: " . $this->mime);
		header("Cache-Control: max-age=2592000, public");
		//header("Cache-Control: max-age=1, public");
		header("Expires: " . gmdate('D, d M Y H:i:s', time()+1) . ' GMT');
		header("Last-Modified: " . gmdate('D, d M Y H:i:s', @filemtime($this->video)) . ' GMT' );

		$this->start = 0;
		$this->size = filesize($this->video);
		$this->end = $this->size - 1;
		header("Accept-Ranges: 0-" . $this->end);

		if (isset($_SERVER['HTTP_RANGE']))
		{
			$c_start = $this->start;
			$c_end = $this->end;

			list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);

			if (strpos($range, ',') !== false)
			{
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $this->start-$this->end/$this->size");
				exit;
			}

			if ($range == '-')
			{
				$c_start = $this->size - substr($range, 1);
			} 
			else
			{
				$range = explode('-', $range);
				$c_start = $range[0];

				$c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $c_end;
			}

			$c_end = ($c_end > $this->end) ? $this->end : $c_end;

			if ($c_start > $c_end || $c_start > $this->size - 1 || $c_end >= $this->size)
			{
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $this->start-$this->end/$this->size");
				exit;
			}

			$this->start = $c_start;
			$this->end = $c_end;
			$length = $this->end - $this->start + 1;

			fseek($this->stream, $this->start);
			header('HTTP/1.1 206 Partial Content');
			header("Content-Length: ".$length);
			header("Content-Range: bytes $this->start-$this->end/".$this->size);
		}
		else
		{
			header("Content-Length: ".$this->size);
		}

	}

	/**
	* Close curretly opened stream
	*/
	private function end()
	{
		fclose($this->stream);
		exit;
	}

	/**
	* Perform the streaming of calculated range
	*/
	private function stream()
	{
		$i = $this->start;
		set_time_limit(0);

		while (!feof($this->stream) && $i <= $this->end)
		{
			$bytesToRead = $this->buffer;

			if (($i+$bytesToRead) > $this->end)
			{
				$bytesToRead = $this->end - $i + 1;
			}

			$data = fread($this->stream, $bytesToRead);
			echo $data;
			flush();
			$i += $bytesToRead;
		}
	}

	/**
	* Start streaming video content
	*/
	public function streamIt()
	{
		$this->detectMime();
		$this->open();
		$this->setHeader();
		$this->stream();
		$this->end();
	}

}

class User
{
	/**
	 * Check the user has access to the $video
	 *
	 * @param string $video Name alias of video (DB stored name)
	 *
	 * @return bool
	 */
	public function hasAccessToVideo($videoAlias) : bool
	{
		switch ($videoAlias)
		{
			case 'sample-1':
				return true;
				break;
			case 'sample-2':
				return false;
				break;
			default :
				return false;
				break;
		}
	}
}

class VideoResolver
{
	protected $noAccessVideo = 'no-access.mp4';

	protected $videoAlias = '';

	protected $videoList = [
		'sample-1' => [
			'path' => 'protected-area/sample-1.mp4'
		],
		'sample-2' => [
			'path' => 'protected-area/sample-2.mp4'
		]
	];

	public function __construct($videoAlias)
	{
		$this->videoAlias = $videoAlias;
	}

	/**
	 * Resolve video url
	 */
	public function resolve($user = false)
	{
		if ($user === false)
		{
			$video = $this->getVideo();
		}
		else {
			if ($user->hasAccessToVideo($this->videoAlias))
			{
				$video = $this->getVideo();
			}
			else
			{
				$video = $this->noAccessVideo;
			}
		}

		$stream = new VideoStream($video);

		return $stream->streamIt();
	}

	protected function getVideo()
	{
		if (array_key_exists($this->videoAlias, $this->videoList))
		{
			return $this->videoList[$this->videoAlias]['path'];
		}

		return $this->noAccessVideo;	
	}
}

$user = new User();

if (empty($_REQUEST['v']))
{
	die('Missing ?v=... from url');
}

$videoAlias = $_REQUEST['v'];

(new VideoResolver($videoAlias))->resolve($user);

?>