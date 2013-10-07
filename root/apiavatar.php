<?php
require_once "lib/image.php";
$image = new image('avatar');
require_once "config/image.php";
$image->ispic()? $image->returnpic(): $image->close();
?>
