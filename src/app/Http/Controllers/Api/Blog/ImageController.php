<?php

namespace App\Http\Controllers\Api\Blog;

use App\Http\Resources\Blog\ImageResource;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Services\Blog\BlogImageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageController extends BlogApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['search' => ['nullable', 'string', 'max:200'], 'unused' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:200']]);

        $images = BlogImage::withCount('posts')
            ->when($request->string('search')->toString(), fn (Builder $q, string $s) => $q->where(fn (Builder $w) => $w
                ->where('alt', 'like', "%{$s}%")->orWhere('caption', 'like', "%{$s}%")->orWhere('original_name', 'like', "%{$s}%")))
            ->when($request->boolean('unused'), fn (Builder $q) => $q->doesntHave('posts'))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 50))
            ->withQueryString();

        return ImageResource::collection($images);
    }

    /**
     * Upload a file, or give a public address to fetch the picture from.
     */
    public function store(Request $request, BlogImageService $images): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required_without:source_url', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:'.(BlogImageService::MAX_BYTES / 1024)],
            'source_url' => ['required_without:file', 'url:http,https', 'max:2000'],
            'alt' => ['nullable', 'string', 'max:500'],
            'caption' => ['nullable', 'string', 'max:500'],
            'credit' => ['nullable', 'string', 'max:255'],
        ]);

        $details = array_intersect_key($data, array_flip(['alt', 'caption', 'credit']));
        $actor = $this->actor($request);

        $image = $this->attempt(fn () => $request->hasFile('file')
            ? $images->storeUpload($request->file('file'), $details, $actor)
            : $images->storeFromUrl($data['source_url'], $details, $actor), $request->hasFile('file') ? 'file' : 'source_url');

        return ImageResource::make($image)->response()->setStatusCode(201);
    }

    public function show(BlogImage $image): ImageResource
    {
        return ImageResource::make($image->loadCount('posts'));
    }

    public function update(Request $request, BlogImage $image): ImageResource
    {
        $image->update($request->validate([
            'alt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:500'],
            'credit' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]));

        Log::info("blog: {$this->actor($request)->name} updated image #{$image->id}");

        return ImageResource::make($image->loadCount('posts'));
    }

    /**
     * Delete an image nobody uses. Images in a post or used as a cover are kept.
     */
    public function destroy(Request $request, BlogImage $image): JsonResponse
    {
        if ($image->posts()->exists() || BlogPost::withTrashed()->where('cover_image_id', $image->id)->exists()) {
            return response()->json(['message' => 'This image is used by a post. Remove it from the post first.'], 409);
        }

        Storage::disk('public')->delete([$image->path, $image->thumb_path]);
        $image->delete();
        Log::info("blog: {$this->actor($request)->name} deleted image #{$image->id}");

        return response()->json(['deleted' => true]);
    }
}
