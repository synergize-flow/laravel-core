<?php

namespace SynergizeFlow\Laravel\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
            $rawImage = $payload['featured_image'] ?? $payload['image'] ?? null;
            $attributes[$imageField] = $this->storeFeaturedImage($rawImage);
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
        $blog = $modelClass::create($attributes);

        return [
            'id' => $blog->getKey(),
            'link' => url('/blog/' . ($blog->{$slugField} ?? $blog->slug)),
        ];
    }

    /**
     * Download and store a remote featured image to the configured storage disk.
     */
    protected function storeFeaturedImage(?string $image): ?string
    {
        if (empty($image) || ! filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        $disk = config('synergizeflow.storage_disk', 'public');
        $directory = trim((string) config('synergizeflow.storage_path', 'blogs'), '/');

        try {
            $response = Http::timeout(20)->get($image);

            if (! $response->successful()) {
                Log::warning("SynergizeFlow: Failed to download featured image [{$image}], status: {$response->status()}");

                return $image;
            }

            $extension = $this->guessExtension(
                $response->header('Content-Type'),
                $image
            );

            $filename = ($directory !== '' ? $directory.'/' : '').Str::random(40).'.'.$extension;

            Storage::disk($disk)->put($filename, $response->body(), 'public');

            return Storage::disk($disk)->url($filename);
        } catch (\Throwable $e) {
            Log::warning("SynergizeFlow: Exception downloading featured image [{$image}]: ".$e->getMessage());

            return $image;
        }
    }

    /**
     * Guess the appropriate file extension based on MIME type or URL.
     */
    protected function guessExtension(?string $contentType, string $url): string
    {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
        ];

        if ($contentType) {
            $cleanMime = trim(explode(';', $contentType)[0]);
            if (isset($mimeMap[$cleanMime])) {
                return $mimeMap[$cleanMime];
            }
        }

        $urlPath = parse_url($url, PHP_URL_PATH);
        $pathExt = is_string($urlPath) ? strtolower(pathinfo($urlPath, PATHINFO_EXTENSION)) : '';
        if (in_array($pathExt, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) {
            return $pathExt === 'jpeg' ? 'jpg' : $pathExt;
        }

        return 'jpg';
    }
}
