<?php

namespace App\Services\Blog;

use App\Models\BlogImage;
use App\Services\Catalog\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Stores blog images as WebP (a large size for articles and a thumbnail for
 * cards), from an upload or from a public web address.
 */
class BlogImageService
{
    public const MAX_BYTES = 15 * 1024 * 1024;

    public function __construct(private ImageProcessor $processor) {}

    /**
     * @param  array{alt?: string|null, caption?: string|null, credit?: string|null}  $details
     */
    public function storeUpload(UploadedFile $file, array $details, BlogActor $actor): BlogImage
    {
        return $this->store((string) $file->getRealPath(), $file->getClientOriginalName(), $details, $actor);
    }

    /**
     * Download a picture from a public http(s) address and store it.
     *
     * @param  array{alt?: string|null, caption?: string|null, credit?: string|null}  $details
     */
    public function storeFromUrl(string $url, array $details, BlogActor $actor): BlogImage
    {
        $this->assertPublicUrl($url);

        $response = Http::timeout(20)->withOptions(['allow_redirects' => false])->get($url);

        if (! $response->successful()) {
            throw new InvalidArgumentException("The image address answered {$response->status()}.");
        }
        if (! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            throw new InvalidArgumentException('The address does not point to an image.');
        }
        if (strlen($response->body()) > self::MAX_BYTES) {
            throw new InvalidArgumentException('The image is larger than 15 MB.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'blog-image-');
        file_put_contents($temp, $response->body());

        try {
            return $this->store($temp, basename((string) parse_url($url, PHP_URL_PATH)) ?: 'image', $details, $actor);
        } finally {
            @unlink($temp);
        }
    }

    /**
     * @param  array{alt?: string|null, caption?: string|null, credit?: string|null}  $details
     */
    private function store(string $source, string $originalName, array $details, BlogActor $actor): BlogImage
    {
        if (@getimagesize($source) === false) {
            throw new InvalidArgumentException('The file is not a picture we can read (JPEG, PNG, GIF or WebP).');
        }

        $base = 'blog/'.now()->format('Y/m').'/'.Str::lower(Str::random(16));
        $sizes = $this->processor->storeSizes($source, $base, BlogImage::LARGE_WIDTH, BlogImage::THUMB_WIDTH);

        $image = BlogImage::create([
            ...$sizes,
            'original_name' => Str::limit($originalName, 250, ''),
            'alt' => $details['alt'] ?? null,
            'caption' => $details['caption'] ?? null,
            'credit' => $details['credit'] ?? null,
            'uploaded_by' => $actor->name,
        ]);

        Log::info("blog: {$actor->name} uploaded image #{$image->id} {$image->path}");

        return $image;
    }

    /**
     * Only public internet addresses: no local network, no other schemes.
     */
    private function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || empty($parts['host'])) {
            throw new InvalidArgumentException('Only http and https image addresses are allowed.');
        }

        $ips = filter_var($parts['host'], FILTER_VALIDATE_IP) ? [$parts['host']] : (gethostbynamel($parts['host']) ?: []);
        if ($ips === []) {
            throw new InvalidArgumentException('The image address does not resolve.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Image addresses on private networks are not allowed.');
            }
        }
    }
}
