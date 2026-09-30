<?php

declare(strict_types=1);

$outDir = __DIR__.'/../public';
$w = 32;
$h = 32;

$im = imagecreatetruecolor($w, $h);
imagesavealpha($im, true);
$transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
imagefill($im, 0, 0, $transparent);

$brand = imagecolorallocate($im, 220, 207, 255);
$brandSoft = imagecolorallocatealpha($im, 220, 207, 255, 55);
$brandMid = imagecolorallocatealpha($im, 220, 207, 255, 85);

imagesetthickness($im, 1);
imageellipse($im, 16, 16, 29, 24, $brandMid);
imageellipse($im, 16, 16, 22, 22, $brandSoft);
imageellipse($im, 16, 16, 14, 14, $brand);
imagefilledellipse($im, 16, 16, 7, 7, $brand);

$png32 = $outDir.'/favicon-32.png';
$pngApple = $outDir.'/apple-touch-icon.png';
imagepng($im, $png32);
imagepng($im, $pngApple);
imagedestroy($im);

$png = file_get_contents($png32);
if ($png === false) {
    fwrite(STDERR, "failed to read png\n");
    exit(1);
}

// ICO with embedded PNG (Vista+)
$pngLen = strlen($png);
$offset = 6 + 16; // ICONDIR + one ICONDIRENTRY
$ico = pack('vvv', 0, 1, 1);
$ico .= pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, $pngLen, $offset);
$ico .= $png;

$icoPath = $outDir.'/favicon.ico';
file_put_contents($icoPath, $ico);

echo 'png32='.filesize($png32).PHP_EOL;
echo 'apple='.filesize($pngApple).PHP_EOL;
echo 'ico='.filesize($icoPath).PHP_EOL;
