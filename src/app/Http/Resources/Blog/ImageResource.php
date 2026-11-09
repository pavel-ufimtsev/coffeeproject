<?php

namespace App\Http\Resources\Blog;

use App\Models\BlogImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A blog image with the Markdown line to paste into a post.
 *
 * @mixin BlogImage
 */
class ImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $path = '/storage/'.$this->path;
        $alt = str_replace([']', '['], '', (string) $this->alt);

        return [
            'id' => $this->id,
            'url' => url($path),
            'path' => $path,
            'thumb_url' => url('/storage/'.$this->thumb_path),
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->alt,
            'caption' => $this->caption,
            'credit' => $this->credit,
            'uploaded_by' => $this->uploaded_by,
            'markdown' => "![{$alt}]({$path})",
            'used_by' => $this->whenCounted('posts'),
        ];
    }
}
