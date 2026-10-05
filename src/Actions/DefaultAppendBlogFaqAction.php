<?php

namespace SynergizeFlow\Laravel\Actions;

use RuntimeException;
use SynergizeFlow\Laravel\Contracts\AppendBlogFaqContract;

class DefaultAppendBlogFaqAction implements AppendBlogFaqContract
{
    /**
     * Handle appending FAQ content and schema to an existing blog.
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): mixed
    {
        $blogConfig = config('synergizeflow.content_providers.blog');

        if (empty($blogConfig['model']) || ! class_exists($blogConfig['model'])) {
            throw new RuntimeException('Cannot append blog FAQ: No valid blog model configured under content_providers.blog in config/synergizeflow.php.');
        }

        $modelClass = $blogConfig['model'];
        $fields = $blogConfig['fields'] ?? [];
        $idField = $fields['id'] ?? 'id';
        $contentField = $fields['content'] ?? 'content';

        $blogId = $payload['blog_id'] ?? null;
        if (! $blogId) {
            throw new RuntimeException('Missing blog_id parameter.');
        }

        $blog = $modelClass::where($idField, $blogId)->first();

        if (! $blog) {
            throw new RuntimeException("Blog post with ID {$blogId} not found.");
        }

        // Generate Schema
        $schema = $payload['schema'] ?? null;
        $schemaHtml = '';
        if ($schema && is_array($schema)) {
            $schemaHtml = "\n\n" . '<script type="application/ld+json">' . "\n" . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n" . '</script>';
        }

        // Generate FAQ HTML
        $faq = $payload['faq'] ?? null;
        $faqHtml = '';
        if ($faq && is_array($faq) && !empty($faq['mainEntity'])) {
            $faqHtml = "\n\n" . '<h2>Frequently Asked Questions</h2>' . "\n" . '<div class="faq-section">';
            foreach ($faq['mainEntity'] as $item) {
                if (isset($item['name']) && isset($item['acceptedAnswer']['text'])) {
                    $question = htmlspecialchars($item['name']);
                    $answer = $item['acceptedAnswer']['text'];
                    $faqHtml .= "\n  <h3>{$question}</h3>\n  <p>{$answer}</p>";
                }
            }
            $faqHtml .= "\n</div>";
        }

        if ($schemaHtml || $faqHtml) {
            $blog->{$contentField} .= $schemaHtml . $faqHtml;
            $blog->save();
        }

        $titleField = $fields['title'] ?? 'title';
        $slugField = $fields['slug'] ?? 'slug';

        return [
            'blog_title' => $blog->{$titleField} ?? '',
            'blog_id' => $blog->getKey(),
            'blog_url' => url('/blog/' . ($blog->{$slugField} ?? $blog->slug ?? '')),
        ];
    }
}
