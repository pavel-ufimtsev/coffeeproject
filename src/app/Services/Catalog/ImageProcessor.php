<?php

namespace App\Services\Catalog;

use App\Models\ProductImage;
use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Turns a source photo into the WebP sizes the storefront uses.
 */
class ImageProcessor
{
    /**
     * Store the large and thumbnail versions of a photo on the public disk.
     *
     * @return array{path: string, thumb_path: string}
     */
    public function store(string $sourcePath, string $name): array
    {
        $stored = $this->storeSizes($sourcePath, "products/{$name}", ProductImage::LARGE_WIDTH, ProductImage::THUMB_WIDTH);

        return ['path' => $stored['path'], 'thumb_path' => $stored['thumb_path']];
    }

    /**
     * Store a large and a thumbnail WebP of a photo at {$base}.webp and
     * {$base}-thumb.webp on the public disk, and report the large size.
     *
     * @return array{path: string, thumb_path: string, width: int, height: int}
     */
    public function storeSizes(string $sourcePath, string $base, int $largeWidth, int $thumbWidth): array
    {
        $image = $this->load($sourcePath);

        try {
            $path = "{$base}.webp";
            $thumbPath = "{$base}-thumb.webp";

            $width = imagesx($image);
            $height = imagesy($image);
            $scale = $width > $largeWidth ? $largeWidth / $width : 1;

            Storage::disk('public')->put($path, $this->encode($image, $largeWidth));
            Storage::disk('public')->put($thumbPath, $this->encode($image, $thumbWidth));
        } finally {
            imagedestroy($image);
        }

        return [
            'path' => $path,
            'thumb_path' => $thumbPath,
            'width' => (int) round($width * $scale),
            'height' => (int) round($height * $scale),
        ];
    }

    /**
     * Read a JPEG, PNG or WebP file.
     */
    private function load(string $path): GdImage
    {
        $data = @file_get_contents($path);
        $image = $data === false ? false : @imagecreatefromstring($data);

        if (! $image instanceof GdImage) {
            throw new RuntimeException("Cannot read image {$path}.");
        }

        // WebP needs full colour: convert palette PNGs and GIFs, keeping transparency.
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return $image;
    }

    /**
     * Scale the image down to the given width (never up) and encode it as WebP.
     */
    private function encode(GdImage $image, int $maxWidth): string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $target = $image;

        if ($width > $maxWidth) {
            $target = imagescale($image, $maxWidth, (int) round($height * $maxWidth / $width), IMG_BICUBIC);

            if (! $target instanceof GdImage) {
                throw new RuntimeException('Cannot resize image.');
            }
        }

        ob_start();
        imagewebp($target, null, 82);
        $webp = (string) ob_get_clean();

        if ($target !== $image) {
            imagedestroy($target);
        }

        return $webp;
    }
}
