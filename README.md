# SynergizeFlow Onboarding & Core Client for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/synergizeflow/laravel-onboarding.svg?style=flat-square)](https://packagist.org/packages/synergizeflow/laravel-onboarding)
[![Total Downloads](https://img.shields.io/packagist/dt/synergizeflow/laravel-onboarding.svg?style=flat-square)](https://packagist.org/packages/synergizeflow/laravel-onboarding)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

The official Laravel client package for **SynergizeFlow**. It provides seamless integration between your Laravel application, the SynergizeFlow SaaS platform, and automated n8n AI workflows.

---

## Features

- **Automated Onboarding Audits**: Exposes API endpoints for SynergizeFlow to analyze your website, landing pages, and business context.
- **Multiple Page Scanning (`scan_urls`)**: Configure specific landing pages (`/about`, `/pricing`, `/features`) for the AI to scan.
- **Flexible Content Mapping (BYOB - Bring Your Own Blog)**: Map your application's existing Eloquent models (Posts, Categories, Users) into SynergizeFlow's standard schema.
- **AI Content Insertion Action**: Receive AI-generated blog posts directly into your database via custom Action classes.
- **Timing-Safe Token Authentication**: Protected routes using a static license key (`SYNERGIZEFLOW_SECURITY_KEY`) to bypass session and CSRF guards.

---

## Requirements

- **PHP**: `^8.1` or higher
- **Laravel Framework**: `^10.0`, `^11.0`, `^12.0`, or `^13.0`

---

## 1. Installation

Install the package via Composer into your Laravel application:

```bash
composer require synergizeflow/laravel-onboarding
```

The package uses Laravel's package auto-discovery, so its service provider will automatically register.

---

## 2. Publish the Configuration

Run the following Artisan command to publish the `synergizeflow.php` configuration file to your `config/` directory:

```bash
php artisan vendor:publish --tag="synergizeflow-config"
```

This creates `config/synergizeflow.php`.

---

## 3. Environment Configuration

Add your SynergizeFlow domain license key to your `.env` file:

```dotenv
# Your domain license key from the SynergizeFlow Dashboard
SYNERGIZEFLOW_SECURITY_KEY=your_license_key_here

# (Optional) Custom website description for AI context
SYNERGIZEFLOW_SITE_DESC="A modern SaaS platform for team collaboration"
```

> **Important**: All incoming API requests from SynergizeFlow must include this key in the `X-SynergizeFlow-Key` header or request body. If the key is not configured, the endpoints will fail closed with a `500 Server Error`.

---

## 4. Configuring `config/synergizeflow.php`

Open `config/synergizeflow.php` to customize your application's settings:

### A. Define Pages to Scan (`scan_urls`)
If your application does not have a blog, or if you want SynergizeFlow to scan multiple landing pages for rich onboarding context, specify them in `scan_urls`:

```php
'scan_urls' => [
    '/',
    '/about',
    '/features',
    '/pricing',
    '/services',
],
```

You can also use an associative array to provide descriptive page titles:

```php
'scan_urls' => [
    '/' => 'Homepage',
    '/about-us' => 'About Our Company',
    '/pricing' => 'Pricing & Plans',
],
```

*Relative paths (e.g. `/about`) will automatically be resolved to full URLs using your application's `APP_URL` / `website_url`.*

---

### B. Map Existing Eloquent Models (`content_providers`)
If your Laravel app already has a blog, authors, or categories, map them so SynergizeFlow can ingest existing articles for AI style training and populate dropdown options in the SaaS dashboard:

```php
'content_providers' => [
    // Map your existing Blog Post model (optional)
    'blog' => [
        'name' => 'Blog Posts',
        'model' => \App\Models\Post::class,
        'fields' => [
            'id' => 'id',
            'title' => 'title',
            'content' => 'content',
            'slug' => 'slug',
        ],
        'limit' => 5,
    ],

    // Map your Categories model (optional)
    'category' => [
        'model' => \App\Models\Category::class,
        'fields' => [
            'id' => 'id',
            'name' => 'name',
        ],
    ],

    // Map your Authors/Users model (defaults to App\Models\User)
    'author' => [
        'model' => \App\Models\User::class,
        'fields' => [
            'id' => 'id',
            'name' => 'name',
        ],
    ],
],
```

---

### C. Configure AI Blog Insertion (`actions.insert_blog`)

By default, the package uses `DefaultInsertBlogAction` to automatically insert AI-generated articles using the Eloquent model and fields defined in your `content_providers.blog` mapping. **For standard, single-domain Laravel applications, no further action is required.**

#### 🛠️ For Complex or Multi-Domain Applications (Advanced)

If your Laravel application uses a complex database architecture—such as multi-tenancy (requiring a `tenant_id`), multi-domain structures (requiring a `domain_id`), or translation packages (like `spatie/laravel-translatable`)—the default `Model::create()` action will likely fail because it does not know how to populate these specialized fields.

To handle complex database insertions, you can easily override the default action with a Custom Action Class:

1. **Create a Custom Action:**
   Create a class that implements `SynergizeFlow\Laravel\Contracts\InsertBlogContract` and define your custom insertion logic.

   ```php
   namespace App\Actions;

   use SynergizeFlow\Laravel\Contracts\InsertBlogContract;
   use App\Models\BlogPost;
   use App\Models\Domain;

   class InsertComplexBlogAction implements InsertBlogContract
   {
       public function execute(array $payload): mixed
       {
           // Example: Dynamically resolve a domain_id based on the APP_URL
           $appHost = parse_url(env('APP_URL'), PHP_URL_HOST) ?? request()->getHost();
           $domain = Domain::where('name', 'LIKE', '%' . $appHost . '%')->first();
           
           return BlogPost::create([
               'domain_id' => $domain->id ?? 1,
               'title' => $payload['title'],
               'content' => $payload['content'],
               'category_id' => $payload['category_id'] ?? null,
           ]);
       }
   }
   ```

2. **Register Your Custom Action:**
   Open `config/synergizeflow.php` and override the `insert_blog` action:

   ```php
   'actions' => [
       // Replace default actions with your custom classes
       'insert_blog' => \App\Actions\InsertComplexBlogAction::class,
       // 'append_blog_faq' => \App\Actions\AppendComplexBlogFaqAction::class,
       // 'get_single_post' => \App\Actions\GetComplexSinglePostAction::class,
   ],
   ```

*(Alternatively, if your app does not have a blog database, install the optional companion package `synergizeflow/laravel-onboarding-blog` which provides ready-to-use migrations and models).*

---

## 5. API Endpoints

This package registers the following routes under the `/api/synergizeflow/v2/` prefix:

| Method | Endpoint | Description |
|---|---|---|
| `GET` / `POST` | `/api/synergizeflow/v2/validate-license` | Connection check / handshake endpoint confirming license key validity (`{"is_valid": "yes"}`). |
| `POST` | `/api/synergizeflow/v2/get-website-data` | Returns site metadata, scan URLs, and existing content for AI onboarding. |
| `POST` | `/api/synergizeflow/v2/helper-data` | Returns category, user, and post title dropdown lists for the SaaS dashboard. |
| `POST` | `/api/synergizeflow/v2/insert-blog` | Receives generated article payloads from n8n and invokes your mapped Action. |
| `POST` | `/api/synergizeflow/v2/submit-blog-faq` | Receives generated FAQ schema to append to an existing blog. |
| `GET` / `POST` | `/api/synergizeflow/v2/get-single-post` | Retrieves a single blog post's data (title, content, image, link) for external use. |

All routes are secured by the `VerifySynergizeFlowToken` middleware and require authentication via the `X-SynergizeFlow-Key` header or `key`/`license` in the request payload.

---

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
