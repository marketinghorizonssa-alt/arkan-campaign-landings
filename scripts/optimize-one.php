<?php
declare(strict_types=1);

if ($argc < 4) {
    fwrite(STDERR, "Usage: optimize-one.php <source> <target> <width> [quality]\n");
    exit(1);
}
[$script, $source, $target, $widthArg] = $argv;
$width = max(1, (int)$widthArg);
$quality = isset($argv[4]) ? max(1, min(100, (int)$argv[4])) : 68;
if (!is_file($source)) {
    fwrite(STDERR, "Missing source: $source\n");
    exit(1);
}
$info = getimagesize($source);
if ($info === false) {
    fwrite(STDERR, "Unreadable image: $source\n");
    exit(1);
}
$mime = $info['mime'] ?? '';
$image = match ($mime) {
    'image/jpeg' => imagecreatefromjpeg($source),
    'image/png' => imagecreatefrompng($source),
    'image/webp' => imagecreatefromwebp($source),
    default => false,
};
if ($image === false) {
    fwrite(STDERR, "Unsupported image: $source\n");
    exit(1);
}
$sourceWidth = imagesx($image);
$sourceHeight = imagesy($image);
$targetWidth = min($width, $sourceWidth);
$targetHeight = max(1, (int)round($sourceHeight * ($targetWidth / $sourceWidth)));
$resized = imagecreatetruecolor($targetWidth, $targetHeight);
imagealphablending($resized, false);
imagesavealpha($resized, true);
imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
if (!imagewebp($resized, $target, $quality)) {
    fwrite(STDERR, "Failed to write: $target\n");
    exit(1);
}
imagedestroy($resized);
imagedestroy($image);
echo basename($target) . ' ' . filesize($target) . "\n";
