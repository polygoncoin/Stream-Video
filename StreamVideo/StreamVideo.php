<?php
/**
 * Stream Video
 * php version 7
 *
 * @category  StreamVideo
 * @package   StreamVideo
 * @author    Ramesh N Jangid <polygon.co.in@gmail.com>
 * @copyright 2025 Ramesh N Jangid
 * @license   MIT https://opensource.org/license/mit
 * @link      https://github.com/polygoncoin/Stream-Video
 * @since     Class available since Release 1.0.0
 */
namespace CacheHandler;

/**
 * StreamVideo
 * php version 7
 *
 * @category  StreamVideo
 * @package   StreamVideo
 * @author    Ramesh N Jangid <polygon.co.in@gmail.com>
 * @copyright 2025 Ramesh N Jangid
 * @license   MIT https://opensource.org/license/mit
 * @link      https://github.com/polygoncoin/Stream-Video
 * @since     Class available since Release 1.0.0
 */
class StreamVideo
{
    /**
     * Video Folder
     *
     * The folder location outside document root
     * without a slash at the end
     *
     * @var string
     */
    private $_videosFolderLocation = '/var/www/videos';

    /**
     * Supported Video mime types
     *
     * @var array
     */
    private $_supportedMimes = [
        'video/quicktime'
    ];

    /**
     * Streamed Video cache duration.
     *
     * @var integer
     */
    private $_streamCacheDuration = 7 * 24 * 3600; // 1 week

    /**
     * Streamed Video size for first request.
     *
     * @var integer
     */
    private $_firstChunkSize = 128 * 1024; // 128 KB

    /**
     * Streamed Video size per request.
     *
     * @var integer
     */
    private $_chunkSize = 4 * 1024 * 1024; // 4 MB

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
     * Initialize
     *
     * @param string $relativeFilePath File path in video folder with leading slash
     *
     * @return void
     */
    public function initFile($relativeFilePath): void
    {
        //escaping filepath
        $relativeFilePath = '/' . trim(
            string: str_replace(
                search: '../',
                replace: '',
                subject: urldecode(string: $relativeFilePath)
            ),
            characters: './'
        );
        // Check Range header
        $headers = getallheaders();
        if (!isset($headers['Range']) && strpos(
            haystack: $headers['Range'],
            needle: 'bytes='
        ) !== false
        ) {
            header(header: "HTTP/1.1 400 Bad Request");
            die();
        }
        // Set buffer Range
        $range = explode(separator: '=', string: $headers['Range'])[1];
        list($this->streamFrom, $this->streamTill) = explode(
            separator: '-',
            string: $range
        );
        // Check path of file to be served
        $this->absoluteFilePath = $absoluteFilePath = $this->_videosFolderLocation .
            $relativeFilePath;
        if (!is_file(filename: $absoluteFilePath)) {
            header(header: "HTTP/1.1 404 Not Found");
            die();
        }
        //Set details of file to be served.
        // Set file name
        $this->fileName = basename(path: $absoluteFilePath);
        // Get file mime
        $fileInfo = finfo_open(flags: FILEINFO_MIME_TYPE);
        $this->fileMime = finfo_file(finfo: $fileInfo, filename: $absoluteFilePath);
        finfo_close(finfo: $fileInfo);
        // Get file modified time
        $this->fileModifiedTimeStamp = filemtime(filename: $absoluteFilePath);
        // Get file size
        $this->fileSize = filesize(filename: $absoluteFilePath);
    }

    /**
     * Validate File related details
     *
     * @return void
     */
    public function validateFile(): void
    {
        if (!in_array(needle: $this->fileMime, haystack: $this->_supportedMimes)) {
            header(header: "HTTP/1.1 400 Bad Request");
            die();
        }
        if ($this->streamFrom >= $this->fileSize ) {
            header(header: "HTTP/1.1 416 Range Not Satisfiable");
            die();
        }
    }

    /**
     * Set headers on successful validation
     *
     * @return void
     */
    public function setHeaders(): void
    {
        $gmDate = gmdate(
            format: 'D, d M Y H:i:s',
            timestamp: time() + $this->_streamCacheDuration
        );
        header(header: 'Content-Type: ' . $this->fileMime);
        header(
            header: 'Cache-Control: max-age=' .
            $this->_streamCacheDuration . ', public'
        );
        header(header: "Expires: {$gmDate} GMT");
        $gmDate = gmdate(
            format: 'D, d M Y H:i:s',
            timestamp: $this->fileModifiedTimeStamp
        );
        header(header: "Last-Modified: {$gmDate} GMT");
        header(header: 'Accept-Ranges: 0-' . ($this->fileSize - 1));
        if ($this->streamFrom == 0) {
            $this->_chunkSize = $this->_firstChunkSize;
        }
        if ($this->streamFrom == 0
            && in_array(
                needle: $this->streamTill,
                haystack: ['', '1']
            )
        ) {
            // Mac Safari does not support HTTP/1.1 206 response for first
            // request while fetching video content.
            // Regex pattern from https://regex101.com/r/gRLirS/1
            $safariBrowserPattern = '`(\s|^)AppleWebKit/[\d\.]+\s+\(.+\)\s+' .
                'Version/(1[0-9]|[2-9][0-9]|\d{3,})(\.|$|\s)`i';
            $safariBrowser = preg_match(
                pattern: $safariBrowserPattern,
                subject: $_SERVER['HTTP_USER_AGENT']
            );
            if ($safariBrowser) {
                $this->streamTill = $this->fileSize - 1;
                header(header: 'Content-Length: ' . $this->fileSize);
                return;
            } else {
                $_chunkSize = $this->fileSize > $this->_chunkSize ?
                    $this->_chunkSize : $this->fileSize;
                $this->streamTill = $_chunkSize - 1;
                $streamSize = $this->streamTill - $this->streamFrom + 1;
            }
        } else {
            if ($this->fileSize > ($this->streamFrom + $this->_chunkSize)) {
                $this->streamTill = $this->streamFrom + $this->_chunkSize;
            } else {
                $this->streamTill = $this->fileSize - 1;
            }
            $streamSize = $this->streamTill - $this->streamFrom + 1;
        }
        header(header: 'HTTP/1.1 206 Partial Content');
        header(header: 'Content-Length: ' . $streamSize);
        header(
            header: 'Content-Range: bytes ' . $this->streamFrom . '-' .
            $this->streamTill . '/' . $this->fileSize
        );
    }

    /**
     * Stream video file content
     *
     * @return void
     */
    public function streamContent(): void
    {
        if (!($srcStream = fopen(filename: $this->absoluteFilePath, mode: 'rb'))) {
            if (!headers_sent() ) {
                header_remove();
                header(header: "HTTP/1.1 500 Internal Server Error");
            }
            die();
        }
        $destStream = fopen(filename: 'php://output', mode: 'wb');
        $totalBytes = $this->streamTill - $this->streamFrom + 1;
        stream_copy_to_stream(
            from: $srcStream,
            to: $destStream,
            length: $totalBytes,
            offset: $this->streamFrom
        );
        fclose(stream: $destStream);
        fclose(stream: $srcStream);
    }
}
