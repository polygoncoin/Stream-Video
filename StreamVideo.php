<?php
/**
 * Class to stream video contents on web.
 * 
 * @category   Streaming
 * @package    StreamVideo
 * @author     Ramesh Narayan Jangid
 * @copyright  Ramesh Narayan Jangid
 * @version    Release: @1.0.0@
 * @since      Class available since Release 1.0.0
 */
class StreamVideo
{
    /**
     * Video Folder
     * 
     * The folder location outside docroot
     * without a slash at the end
     * 
     * @var string
     */
    private $videosFolderLocation = '/var/www/videos';

    /**
     * Supported Video mime types
     * 
     * @var array
     */
    private $supportedMimes = [
        'video/quicktime'
    ];

    /**
     * Streamed Video cache duration.
     * 
     * @var integer
     */
    private $streamCacheDuration = 7 * 24 * 3600; // 1 week

    /**
     * Streamed Video size for first request.
     * 
     * @var integer
     */
    private $firstChunkSize = 128 * 1024; // 128 KB

    /**
     * Streamed Video size per request.
     * 
     * @var integer
     */
    private $chunkSize = 4 * 1024 * 1024; // 4 MB

    /**
     * File details required in class.
     */
    public $absoluteFilePath = "";
    public $fileName = "";
    public $fileMime = "";
    public $fileModifiedTimeStamp = 0;
    public $fileSize = 0;
    public $streamFrom = 0;
    public $streamTill = 0;

    /**
     * Initalise
     *
     * @param string $relativeFilePath File path in video folder with leading slash(/)
     * @return void
     */
    public function initFile($relativeFilePath)
    {
        //escapeing filepath
        $relativeFilePath = '/' . trim(str_replace('../','',urldecode($relativeFilePath)), './');
        // Check Range header
        $headers = getallheaders();
        if (!isset($headers['Range']) && strpos($headers['Range'], 'bytes=') !== false) {
            header("HTTP/1.1 400 Bad Request");
            die();
        }
        // Set buffer Range
        $range = explode('=', $headers['Range'])[1];
        list($this->streamFrom, $this->streamTill) = explode('-', $range);
        // Check path of file to be served
        $this->absoluteFilePath = $absoluteFilePath = $this->videosFolderLocation . $relativeFilePath;
        if (!is_file($absoluteFilePath)) {
            header("HTTP/1.1 404 Not Found");
            die();
        }
        //Set details of file to be served.
        // Set file name
        $this->fileName = basename($absoluteFilePath);
        // Get file mime
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $this->fileMime = finfo_file($fileInfo, $absoluteFilePath);
        finfo_close($fileInfo);
        // Get file modified time
        $this->fileModifiedTimeStamp = filemtime($absoluteFilePath);
        // Get file size
        $this->fileSize = filesize($absoluteFilePath);
    }

    /**
     * Validate File related details
     *
     * @return void
     */
    public function validateFile()
    {
        if (!in_array($this->fileMime, $this->supportedMimes)) {
            header("HTTP/1.1 400 Bad Request");
            die();
        }
        if ($this->streamFrom >= $this->fileSize ) {
            header("HTTP/1.1 416 Range Not Satisfiable");
            die();
        }
    }
    
    /**
     * Set headers on successful validation
     *
     * @return void
     */
    public function setHeaders()
    {
        header('Content-Type: ' . $this->fileMime);
        header('Cache-Control: max-age=' . $this->streamCacheDuration . ', public');
        header('Expires: '.gmdate('D, d M Y H:i:s', time() + $this->streamCacheDuration) . ' GMT');
        header('Last-Modified: ' . gmdate("D, d M Y H:i:s", $this->fileModifiedTimeStamp) . ' GMT'); 
        header('Accept-Ranges: 0-' . ($this->fileSize - 1));
        if ($this->streamFrom == 0) {
            $this->chunkSize = $this->firstChunkSize;
        }
        if ($this->streamFrom == 0 && in_array($this->streamTill, ['', '1'])) {
            //Mac Safari does not support HTTP/1.1 206 response for first request while fetching video content.
            //Regex pattern from https://regex101.com/r/gRLirS/1
            $safariBrowserPattern = '`(\s|^)AppleWebKit/[\d\.]+\s+\(.+\)\s+Version/(1[0-9]|[2-9][0-9]|\d{3,})(\.|$|\s)`i';
            $safariBrowser = preg_match($safariBrowserPattern, $_SERVER['HTTP_USER_AGENT']);
            if ($safariBrowser) {
                $this->streamTill = $this->fileSize - 1;
                header('Content-Length: ' . $this->fileSize);
                return;
            } else {
                $chunkSize = $this->fileSize > $this->chunkSize ? $this->chunkSize : $this->fileSize;
                $this->streamTill = $chunkSize - 1;
                $streamSize = $this->streamTill - $this->streamFrom + 1;    
            }
        } else {
            if ($this->fileSize > ($this->streamFrom + $this->chunkSize)) {
                $this->streamTill = $this->streamFrom + $this->chunkSize;
            } else {
                $this->streamTill = $this->fileSize - 1;
            }
            $streamSize = $this->streamTill - $this->streamFrom + 1;
        }
        header('HTTP/1.1 206 Partial Content');
        header('Content-Length: ' . $streamSize);
        header('Content-Range: bytes ' . $this->streamFrom . '-' . $this->streamTill . '/' . $this->fileSize);
    }

    /**
     * Stream video file content
     *
     * @return void
     */
    public function streamContent()
    {
        if (!($srcStream = fopen($this->absoluteFilePath, 'rb'))) {
            if (!headers_sent() ) {
                header_remove();
                header("HTTP/1.1 500 Internal Server Error");
            }
            die();
        }
        $destStream = fopen('php://output', 'wb');
        $totalBytes = $this->streamTill - $this->streamFrom + 1;
        stream_copy_to_stream($srcStream, $destStream, $totalBytes, $this->streamFrom);
        fclose($destStream);
        fclose($srcStream);
    }
}
