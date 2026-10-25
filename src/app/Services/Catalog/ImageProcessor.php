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
        $image = $this->load($sourcePath);

        try {
            $path = "products/{$name}.webp";
            $thumbPath = "products/{$name}-thumb.webp";

            Storage::disk('public')->put($path, $this->encode($image, ProductImage::LARGE_WIDTH));
            Storage::disk('public')->put($thumbPath, $this->encode($image, ProductImage::THUMB_WIDTH));
        } finally {
            imagedestroy($image);
        }

        return ['path' => $path, 'thumb_path' => $thumbPath];
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
