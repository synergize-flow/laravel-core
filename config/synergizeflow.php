<?php

use App\Models\User;
use SynergizeFlow\Laravel\Actions\DefaultInsertBlogAction;

return [
    'website_name' => env('APP_NAME', 'Laravel App'),
    'website_url' => env('APP_URL', 'http://localhost'),
    'website_description' => env('SYNERGIZEFLOW_SITE_DESC', 'A Laravel Application'),
    'website_language' => env('APP_LOCALE', 'en'),

    /**
     * Security key used by SynergizeFlow / n8n to authenticate with this app.
     * Matches the domain license/security token stored in the SynergizeFlow SaaS.
     */
    'security_key' => env('SYNERGIZEFLOW_SECURITY_KEY'),

    /**
     * Specific URLs or page paths to scan during onboarding.
     *
     * By default, SynergizeFlow scans the website homepage (website_url).
     * If your Laravel application has key landing pages (e.g. /about, /features, /pricing)
     * that provide rich context about your business, list them here.
     *
     * Supported formats:
     * 1. Simple array of paths or full URLs:
     *    'scan_urls' => [
     *        '/',
     *        '/about',
     *        '/features',
     *        '/pricing',
     *    ],
     *
     * 2. Key-value pairs with descriptive titles:
     *    'scan_urls' => [
     *        '/' => 'Homepage',
     *        '/about' => 'About Us',
     *        '/pricing' => 'Pricing & Plans',
     *    ],
     *
     * Relative paths will automatically be resolved against website_url.
     */
    'scan_urls' => [
        '/',
    ],

    /**
     * Content and model providers for SynergizeFlow.
     * Maps host models and fields to standard SynergizeFlow response structures.
     */
    'content_providers' => [
        // 'blog' => [
        //     'name' => 'Blog Posts',
        //     'model' => \App\Models\Post::class,
        //     'fields' => [
        //         'id' => 'id',
        //         'title' => 'title',
        //         'content' => 'content',
        //         'slug' => 'slug',
        //     ],
        //     'limit' => 5,
        // ],
        // 'page' => [
        //     'name' => 'Pages Content',
        //     'model' => \App\Models\Page::class,
        //     'fields' => [
        //         'id' => 'id',
        //         'title' => 'title',
        //         'content' => 'content',
        //     ],
        //     'limit' => 10,
        // ],
        // 'category' => [
        //     'model' => \App\Models\Category::class,
        //     'fields' => [
        //         'id' => 'id',
        //         'name' => 'name',
        //     ],
        // ],
        'author' => [
            'model' => User::class,
            'fields' => [
                'id' => 'id',
                'name' => 'name',
            ],
        ],
    ],

    /**
     * Action classes for executing tasks triggered by SynergizeFlow.
     */
    'actions' => [
        /**
         * Must implement \SynergizeFlow\Laravel\Contracts\InsertBlogContract
         *
         * By default, SynergizeFlow provides a plug-and-play DefaultInsertBlogAction
         * that automatically creates articles using the 'content_providers.blog' mapping.
         *
         * You can override this with your own custom Action class if your application
         * requires custom saving logic (e.g. multi-tenancy, translations, or event dispatches):
         * Example: \App\Actions\InsertBlogAction::class
         * Or if using the headless blog extension: \SynergizeFlow\Blog\Actions\InsertHeadlessBlogAction::class
         */
        'insert_blog' => DefaultInsertBlogAction::class,
    ],

    /**
     * Storage disk used for saving downloaded assets (like featured images).
     * Defaults to 'public' (accessible via php artisan storage:link).
     * Can be set to 's3' or any other disk configured in config/filesystems.php.
     */
    'storage_disk' => env('SYNERGIZEFLOW_STORAGE_DISK', 'public'),

    /**
     * Directory path on the storage disk where downloaded images are saved.
     */
    'storage_path' => env('SYNERGIZEFLOW_STORAGE_PATH', 'blogs'),
];
