<?php

declare(strict_types=1);

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php scripts/build_logo_assets.php <source> <color-output> <white-output>\n");
    exit(1);
}

[$script, $sourcePath, $colorOutputPath, $whiteOutputPath] = $argv;

$source = imagecreatefrompng($sourcePath);

if ($source === false) {
    fwrite(STDERR, "Unable to read the generated logo source.\n");
    exit(1);
}

$width = imagesx($source);
$height = imagesy($source);
$colorLogo = imagecreatetruecolor($width, $height);
$whiteLogo = imagecreatetruecolor($width, $height);

foreach ([$colorLogo, $whiteLogo] as $image) {
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
    imagefill($image, 0, 0, $transparent);
}

for ($y = 0; $y < $height; $y++) {
    for ($x = 0; $x < $width; $x++) {
        $pixel = imagecolorat($source, $x, $y);
        $red = ($pixel >> 16) & 0xff;
        $green = ($pixel >> 8) & 0xff;
        $blue = $pixel & 0xff;
        $maximum = max($red, $green, $blue);
        $minimum = min($red, $green, $blue);
        $chroma = $maximum - $minimum;

        if ($chroma <= 3) {
            continue;
        }

        if ($green === $maximum) {
            $referenceChroma = 104;
        } elseif (($green - $blue) > ($red - $green)) {
            $referenceChroma = 126;
        } else {
            $referenceChroma = 142;
        }

        $opacity = min(1, max(0, ($chroma - 2) / ($referenceChroma - 2)));
        $pngAlpha = 127 - (int) round($opacity * 127);

        $colorPixel = imagecolorallocatealpha($colorLogo, $red, $green, $blue, $pngAlpha);
        $whitePixel = imagecolorallocatealpha($whiteLogo, 255, 255, 255, $pngAlpha);
        imagesetpixel($colorLogo, $x, $y, $colorPixel);
        imagesetpixel($whiteLogo, $x, $y, $whitePixel);
    }
}

$outputDirectory = dirname($colorOutputPath);

if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0777, true) && ! is_dir($outputDirectory)) {
    fwrite(STDERR, "Unable to create the logo output directory.\n");
    exit(1);
}

imagepng($colorLogo, $colorOutputPath, 9);
imagepng($whiteLogo, $whiteOutputPath, 9);

imagedestroy($source);
imagedestroy($colorLogo);
imagedestroy($whiteLogo);

fwrite(STDOUT, "Created {$colorOutputPath} and {$whiteOutputPath} ({$width}x{$height}).\n");
