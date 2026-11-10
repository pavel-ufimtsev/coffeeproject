<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Http\Controllers\Controller;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\Blog\BlogActor;
use App\Services\Blog\BlogImageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * The blog's media library.
 */
class ImageController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'unused' => ['nullable', 'boolean'],
        ]);

        return Inertia::render('admin/blog/images/Index', [
            'images' => $this->query($filters, 48),
            'filters' => ['search' => $filters['search'] ?? '', 'unused' => (bool) ($filters['unused'] ?? false)],
            'total' => BlogImage::count(),
        ]);
    }

    /**
     * Images as JSON for the editor's picker.
     */
    public function library(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:200']]);

        return response()->json($this->query($filters, 36));
    }

    /**
     * Upload a picture. The editor gets JSON back so it can insert the image.
     */
    public function store(Request $request, BlogImageService $images): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:'.(BlogImageService::MAX_BYTES / 1024)],
            'alt' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $image = $images->storeUpload($request->file('file'), ['alt' => $data['alt'] ?? null], BlogActor::user($user));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json($this->image($image), 201);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Image uploaded.')]);

        return back();
    }

    public function update(Request $request, BlogImage $image): RedirectResponse
    {
        $image->update($request->validate([
            'alt' => ['nullable', 'string', 'max:500'],
            'caption' => ['nullable', 'string', 'max:500'],
            'credit' => ['nullable', 'string', 'max:255'],
        ]));

        return back();
    }

    /**
     * Delete an image no post uses.
     */
    public function destroy(Request $request, BlogImage $image): RedirectResponse
    {
        if ($image->posts()->exists() || BlogPost::withTrashed()->where('cover_image_id', $image->id)->exists()) {
            throw ValidationException::withMessages(['image' => __('This image is used by a post. Remove it from the post first.')]);
        }

        Storage::disk('public')->delete([$image->path, $image->thumb_path]);
        $image->delete();
        Log::info("blog: {$request->user()?->name} deleted image #{$image->id}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Image deleted.')]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function query(array $filters, int $perPage): LengthAwarePaginator
    {
        return BlogImage::query()
            ->with(['posts' => fn ($q) => $q->select('blog_posts.id', 'slug', 'title')])
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $w) => $w
                ->where('alt', 'like', "%{$s}%")->orWhere('caption', 'like', "%{$s}%")->orWhere('original_name', 'like', "%{$s}%")))
            ->when($filters['unused'] ?? false, fn (Builder $q) => $q->doesntHave('posts'))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (BlogImage $image) => $this->image($image));
    }

    /**
     * @return array<string, mixed>
     */
    private function image(BlogImage $image): array
    {
        $path = '/storage/'.$image->path;
        $alt = str_replace(['[', ']'], '', (string) $image->alt);

        return [
            ...$image->only(['id', 'width', 'height', 'alt', 'caption', 'credit', 'original_name', 'uploaded_by']),
            'url' => $image->url(),
            'thumb' => $image->thumbUrl(),
            'markdown' => "![{$alt}]({$path})",
            'posts' => $image->relationLoaded('posts')
                ? $image->posts->map(fn (BlogPost $post) => ['id' => $post->id, 'title' => $post->title])->values()
                : [],
        ];
    }
}
