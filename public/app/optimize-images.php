<?php
declare(strict_types=1);

$assetDir = $argv[1] ?? '';
if ($assetDir === '' || !is_dir($assetDir) || !class_exists(Imagick::class)) {
    fwrite(STDERR, "Image optimization unavailable\n");
    exit(1);
}

foreach (glob(rtrim($assetDir, '/') . '/hero-*.jpg') ?: [] as $source) {
    $image = new Imagick($source);
    $image->setIteratorIndex(0);
    $image->autoOrient();
    $image->thumbnailImage(1280, 0, true);
    $image->setImageFormat('webp');
    $image->setImageCompressionQuality(68);
    $image->stripImage();
    $target = preg_replace('/\.jpg$/i', '.webp', $source) ?: ($source . '.webp');
    $image->writeImage($target);
    $image->clear();
    $image->destroy();
}

$logo = rtrim($assetDir, '/') . '/logo.webp';
if (is_file($logo)) {
    $image = new Imagick($logo);
    $image->setIteratorIndex(0);
    $image->thumbnailImage(360, 0, true);
    $image->setImageFormat('webp');
    $image->setImageCompressionQuality(75);
    $image->stripImage();
    $image->writeImage(rtrim($assetDir, '/') . '/logo-360.webp');
    $image->clear();
    $image->destroy();
}

echo "ARKAN_IMAGES_OPTIMIZED\n";
