<?php

namespace SynergizeFlow\Laravel\Actions;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SynergizeFlow\Laravel\Contracts\GetSinglePostContract;

class DefaultGetSinglePostAction implements GetSinglePostContract
{
    /**
     * Handle fetching a single post by ID.
     *
     * @param mixed $id
     * @return array<string, mixed>
     */
    public function execute(mixed $id): array
    {
        if (empty($id)) {
            throw new RuntimeException('Post ID is required.');
        }

        $blogConfig = config('synergizeflow.content_providers.blog');
        if (empty($blogConfig['model']) || ! class_exists($blogConfig['model'])) {
            throw new RuntimeException('Cannot fetch post: No valid blog model configured under content_providers.blog in config/synergizeflow.php.');
        }

        $modelClass = $blogConfig['model'];
        $fields = $blogConfig['fields'] ?? [];
        
        $post = $modelClass::find($id);
        if (! $post) {
            throw new RuntimeException("Post not found with ID: {$id}");
        }

        $titleField = $fields['title'] ?? 'title';
        $contentField = $fields['content'] ?? 'content';
        $slugField = $fields['slug'] ?? 'slug';
        $imageField = $fields['featured_image'] ?? $fields['image'] ?? null;

        $image = null;
        if ($imageField && !empty($post->{$imageField})) {
            $image = $post->{$imageField};
            // If the image is not a full URL, attempt to generate the full URL using the configured storage disk
            if (! filter_var($image, FILTER_VALIDATE_URL)) {
                $disk = config('synergizeflow.storage_disk', 'public');
                $image = Storage::disk($disk)->url($image);
            }
        }

        return [
            'title' => (string) ($post->{$titleField} ?? ''),
            'content' => (string) ($post->{$contentField} ?? ''),
            'image' => $image ? (string) $image : null,
            'link' => url('/blog/' . ($post->{$slugField} ?? $post->slug ?? $id)),
        ];
    }
}
