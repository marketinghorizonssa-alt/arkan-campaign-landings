<?php
declare(strict_types=1);

$assetDir = $argv[1] ?? '';
if ($assetDir === '' || !is_dir($assetDir) || !class_exists(Imagick::class)) {
    fwrite(STDERR, "Image optimization unavailable\n");
    exit(1);
}

foreach (glob(rtrim($assetDir, '/') . '/hero-*.jpg') ?: [] as $source) {
    $base = preg_replace('/\.jpg$/i', '', $source) ?: $source;
    $original = new Imagick($source);
    $original->setIteratorIndex(0);

    $mobile = clone $original;
    $mobile->thumbnailImage(768, 0, true);
    $mobile->setImageFormat('webp');
    $mobile->setImageCompressionQuality(64);
    $mobile->stripImage();
    $mobile->writeImage($base . '-768.webp');
    $mobile->clear();

    $original->thumbnailImage(1280, 0, true);
    $original->setImageFormat('webp');
    $original->setImageCompressionQuality(68);
    $original->stripImage();
    $original->writeImage($base . '.webp');
    $original->clear();
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
}

echo "ARKAN_IMAGES_OPTIMIZED\n";
