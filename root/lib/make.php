<?php
if(isset($skin)) {
} else {
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?php echo $title; ?></title>
</head>
<body>
<h1><?php echo $title; ?></h1>
<?php echo $content; ?>
</body>
<?php
}
?>
