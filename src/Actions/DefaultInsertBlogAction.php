<?php

namespace SynergizeFlow\Laravel\Actions;

use Illuminate\Support\Str;
use RuntimeException;
use SynergizeFlow\Laravel\Contracts\InsertBlogContract;

class DefaultInsertBlogAction implements InsertBlogContract
{
    /**
     * Handle the insertion of AI-generated blog content using the configured blog provider mapping.
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): mixed
    {
        $blogConfig = config('synergizeflow.content_providers.blog');

        if (empty($blogConfig['model']) || ! class_exists($blogConfig['model'])) {
            throw new RuntimeException('Cannot insert blog: No valid blog model configured under content_providers.blog in config/synergizeflow.php.');
        }

        $modelClass = $blogConfig['model'];
        $fields = $blogConfig['fields'] ?? [];
        $attributes = [];

        // 1. Title
        $titleField = $fields['title'] ?? 'title';
        $title = (string) ($payload['title'] ?? 'Untitled Article');
        $attributes[$titleField] = $title;

        // 2. Content
        $contentField = $fields['content'] ?? 'content';
        $content = (string) ($payload['content'] ?? '');
        $attributes[$contentField] = $content;

        // 3. Slug
        $slugField = $fields['slug'] ?? 'slug';
        if (isset($fields['slug']) || ! empty($payload['slug'])) {
            $attributes[$slugField] = ! empty($payload['slug'])
                ? Str::slug((string) $payload['slug'])
                : Str::slug($title);
        }

        // 4. Excerpt
        if (isset($fields['excerpt'])) {
            $attributes[$fields['excerpt']] = ! empty($payload['excerpt'])
                ? (string) $payload['excerpt']
                : Str::limit(strip_tags($content), 160);
        }

        // 5. Featured Image
        $imageField = $fields['featured_image'] ?? $fields['image'] ?? null;
        if ($imageField) {
            $attributes[$imageField] = $payload['featured_image'] ?? $payload['image'] ?? null;
        }

        // 6. Author ID
        $authorField = $fields['author_id'] ?? $fields['user_id'] ?? null;
        if ($authorField && ! empty($payload['author_id'] ?? $payload['wp_author_id'])) {
            $attributes[$authorField] = $payload['author_id'] ?? $payload['wp_author_id'];
        }

        // 7. Category ID
        $categoryField = $fields['category_id'] ?? null;
        if ($categoryField && ! empty($payload['category_id'])) {
            $attributes[$categoryField] = $payload['category_id'];
        }

        // 8. Status
        if (isset($fields['status'])) {
            $attributes[$fields['status']] = $payload['status'] ?? 'published';
        }

        // 9. Published At
        if (isset($fields['published_at'])) {
            $attributes[$fields['published_at']] = now();
        }

        // Insert into database via Eloquent
        return $modelClass::create($attributes);
    }
}
