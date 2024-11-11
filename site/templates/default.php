<?php
use Kirby\Filesystem\F;
?>
<!DOCTYPE html>
<html>
<body>
  
<?= markdown(F::read(kirby()->root() . '/README.md')) ?>