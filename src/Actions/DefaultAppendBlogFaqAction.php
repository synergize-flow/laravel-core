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
            $css = "<style>\n" .
                   "    .sf-faq-wrapper { font-family: inherit; margin-top: 2rem; }\n" .
                   "    .sf-faq-wrapper h2 { margin-bottom: 1.5rem; }\n" .
                   "    .sf-faq-item { border-bottom: 1px solid #eaeaea; padding: 1rem 0; }\n" .
                   "    .sf-faq-item:first-of-type { border-top: 1px solid #eaeaea; }\n" .
                   "    .sf-faq-question {\n" .
                   "        font-weight: 600; font-size: 1.125rem; cursor: pointer; display: flex; \n" .
                   "        justify-content: space-between; align-items: center; list-style: none; margin: 0;\n" .
                   "    }\n" .
                   "    .sf-faq-question::-webkit-details-marker { display: none; }\n" .
                   "    .sf-faq-icon { font-size: 1.5rem; font-weight: 400; line-height: 1; color: #555; }\n" .
                   "    .sf-faq-item[open] .sf-faq-icon::before { content: \"-\"; }\n" .
                   "    .sf-faq-item:not([open]) .sf-faq-icon::before { content: \"+\"; }\n" .
                   "    .sf-faq-answer { margin-top: 1rem; color: #555; line-height: 1.6; }\n" .
                   "    .sf-faq-answer p { margin: 0; }\n" .
                   "    .sf-faq-item[open] .sf-faq-answer { animation: sf-faq-fade-slide 0.3s ease-in-out forwards; }\n" .
                   "    @keyframes sf-faq-fade-slide {\n" .
                   "        0% { opacity: 0; transform: translateY(-5px); }\n" .
                   "        100% { opacity: 1; transform: translateY(0); }\n" .
                   "    }\n" .
                   "</style>";
            $faqHtml = "\n\n" . $css . "\n" . '<div class="sf-faq-wrapper">' . "\n  <h2>Frequently Asked Questions</h2>";
            foreach ($faq['mainEntity'] as $item) {
                if (isset($item['name']) && isset($item['acceptedAnswer']['text'])) {
                    $question = htmlspecialchars($item['name']);
                    $answer = $item['acceptedAnswer']['text'];
                    $faqHtml .= "\n  <details class=\"sf-faq-item\">\n" .
                                "    <summary class=\"sf-faq-question\">\n" .
                                "      {$question}\n" .
                                "      <span class=\"sf-faq-icon\"></span>\n" .
                                "    </summary>\n" .
                                "    <div class=\"sf-faq-answer\"><p>{$answer}</p></div>\n" .
                                "  </details>";
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
