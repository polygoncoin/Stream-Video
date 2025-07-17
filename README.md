# **Stream Audio / Video**

Stream large audio / video files in browser.


## Example

video-gallery.html

```HTML
<video width="100%" height="100%" preload="none" poster="template.jpg" controls="">
    <source src="stream.php?file=/somevideo.mov">
</video>
```

stream.php

```PHP
<?php
require_once __DIR__ . '/Autoload.php';

use StreamVideo\StreamVideo;

$file = $_GET['file'];

$obj = new StreamVideo();
$obj->initFile($file);
$obj->validateFile();
$obj->setHeaders();
$obj->streamContent();
```
> Note: Update the supportedMimes of media files in the class.