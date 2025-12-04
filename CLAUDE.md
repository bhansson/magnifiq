# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Magnifiq is a Laravel 12 + Jetstream (Livewire stack) application running on Laravel Octane with Swoole, designed for AI-powered product catalog management and marketing content generation. The platform features team-based multi-tenancy where users can import product feeds, generate marketing copy through AI templates, and create photorealistic product images via the Photo Studio feature.

## Development Environment

This project uses Docker Compose for local development with dedicated services for Octane, Vite, queue workers, scheduled tasks, and Postgres database.

### Essential Commands

**Start the development stack:**
```bash
docker compose up octane vite -d
```

**Run migrations:**
```bash
docker compose exec octane php artisan migrate
```

**Run tests:**
```bash
docker compose exec octane php artisan test
```

**Run a single test file:**
```bash
docker compose exec octane php artisan test --filter=TestClassName
```

**Access Octane container shell:**
```bash
docker compose exec octane bash
```

**Reload Octane workers (after code changes):**
```bash
docker compose exec octane php artisan octane:reload
```

**Start queue worker:**
```bash
docker compose up queue -d
```

**Run scheduler:**
```bash
docker compose up scheduler -d
```

**Compile production assets:**
```bash
docker compose exec vite npm run build
```

**Install PHP dependencies:**
```bash
docker compose exec octane composer install
```

**Install Node dependencies:**
```bash
docker compose run --rm vite npm install
```

**Ad-hoc Artisan commands:**
```bash
docker compose exec octane php artisan tinker
docker compose exec octane php artisan make:model Example
```

(Optional: Create a `bin/php.sh` wrapper script for convenience)

**IMPORTANT**: After completing any coding task, reload Octane workers to pick up changes:
```bash
docker compose exec octane php artisan octane:reload
```

## Architecture Overview

### Multi-Tenancy Model

The application uses team-based multi-tenancy through Jetstream. All product-related data (`ProductFeed`, `Product`, `PhotoStudioGeneration`, `ProductAiJob`, `ProductAiTemplate`) is scoped to a `team_id`. Users can belong to multiple teams and switch between them via Jetstream's built-in team switcher.

### Partner Whitelabel System

The platform supports partner (whitelabel) functionality allowing partners to own and manage multiple customer teams, earn revenue share, and provide custom branding.

**Architecture:**
- Teams have a `type` column: `'customer'` (default) or `'partner'`
- Partners can own multiple customer teams via the `parent_team_id` foreign key
- Partner branding includes:
  - `logo_path`: Custom logo stored in `storage/partners/logos/`
  - `partner_slug`: Unique URL-friendly identifier for branded auth pages

**Key Components:**
- **ManagePartners** (`app/Livewire/ManagePartners.php`): Admin UI for creating/managing partners, uploading logos
- **PartnerRevenueDashboard** (`app/Livewire/PartnerRevenueDashboard.php`): Revenue tracking and reporting dashboard
- **PartnerRevenue** model: Tracks revenue allocation between partners and customers
- **DetectPartnerContext** middleware: Detects `?partner=slug` query parameter and shares partner branding with views

**Access Control:**
- `TeamPolicy` updated to grant partners view/update access to owned customer teams
- Partners can view all teams they own via the `ownedTeams` relationship
- Customer teams can access their parent partner via the `parentTeam` relationship

**Whitelabel Branding:**
- Auth pages (login/register) display partner logos when accessed with `?partner=slug` query parameter
- Partner detection handled by `DetectPartnerContext` middleware
- Logo fallback to default app logo when no partner context detected

**Admin Routes:**
- `/admin/partners`: Partner management (create, edit, delete partners)
- `/admin/revenue`: Revenue dashboard and reporting

### Product Feed System

The product feed architecture centers around two models:

- **ProductFeed** (`app/Models/ProductFeed.php`): Stores feed metadata including URL, language, and field mappings
- **Product** (`app/Models/Product.php`): Individual product records imported from feeds

The `ManageProductFeeds` Livewire component (`app/Livewire/ManageProductFeeds.php`) handles:
- Fetching feeds from remote URLs or uploaded files
- Parsing both XML (Google Merchant format, RSS) and CSV formats
- Auto-detecting delimiters and field mappings
- Intelligent field mapping suggestions (e.g., `g:id` → `sku`, `g:title` → `title`)
- Refreshing feeds by re-importing with preserved mappings

**Key architectural detail**: When importing or refreshing a feed, all existing products for that feed are deleted and replaced. Products are inserted in batches of 100 for performance.

### Product Catalog System

Product Catalogs allow grouping multiple product feeds together, enabling multi-language and multi-market product management. Products with the same SKU across feeds in the same catalog are recognized as different language versions of the same logical product.

**Architecture:**
- **ProductCatalog** (`app/Models/ProductCatalog.php`): Groups feeds by team, representing a single product line across multiple languages/markets
- **ProductFeed** has optional `product_catalog_id` foreign key (nullable for backward compatibility)
- Products are matched across feeds by SKU within the same catalog

**Key Relationships:**
- `ProductCatalog->feeds()`: All feeds in the catalog
- `ProductCatalog->products()`: All products across all feeds (HasManyThrough)
- `ProductFeed->catalog()`: The catalog this feed belongs to (nullable)
- `Product->siblingProducts()`: Other language versions of the same SKU in the catalog
- `Product->allLanguageVersions()`: All language versions including self

**Helper Methods:**
- `ProductCatalog::distinctProducts($primaryLanguage)`: Get one product per SKU, preferring the primary language
- `ProductCatalog::languages()`: Get all unique languages in the catalog
- `Product::isInCatalog()`, `ProductFeed::isInCatalog()`: Check if in a catalog

**UI Components:**
- `ManageProductFeeds`: Now supports creating/editing/deleting catalogs, moving feeds between catalogs
- `ProductsIndex`: Catalog filter dropdown, language badges showing all available versions
- `ProductShow`: Language tabs to switch between language versions of the same product

**Activity Logging:**
- `TeamActivity::TYPE_CATALOG_CREATED`: When a catalog is created
- `TeamActivity::TYPE_CATALOG_DELETED`: When a catalog is deleted
- `TeamActivity::TYPE_FEED_MOVED`: When a feed is moved to/from a catalog

### AI Content Generation System

The AI generation system supports two primary workflows:

1. **Product AI Templates** (`ProductAiTemplate` model): Reusable prompt templates for generating marketing content (summaries, descriptions, USPs, FAQs). Templates can be team-specific or global defaults. Managed via `ManageProductAiTemplates` Livewire component.

2. **Photo Studio** (`PhotoStudio` Livewire component): Multi-modal AI feature that:
   - Analyzes product images (uploaded or from catalog)
   - Extracts contextual prompts using vision models (default: `openai/gpt-4.1`)
   - Generates photorealistic product renders using image generation models (default: `google/gemini-2.5-flash-image`)
   - Stores generations in `PhotoStudioGeneration` model with team-scoped gallery
   - Supports optional creative briefs to guide prompt extraction

**Queue Architecture**: All AI generation jobs flow through the `ProductAiJob` model which tracks status, progress, and metadata. Jobs are dispatched to the `ai` queue and processed by the queue worker with configurable retries and timeouts.

The `GeneratePhotoStudioImage` job (`app/Jobs/GeneratePhotoStudioImage.php`) handles complex AI provider response parsing, supports multiple image payload formats (base64, URLs, attachment references), automatically converts PNGs to JPGs with white backgrounds, and stores final images on the configured disk (S3 by default).

### Livewire Component Patterns

Livewire components follow these conventions:
- Located in `app/Livewire/`
- Views in `resources/views/livewire/` (kebab-case)
- Component classes use PascalCase, views use kebab-case
- Heavy use of `#[Validate]` attributes for inline validation rules
- Real-time updates via `updatedPropertyName()` lifecycle hooks
- Team scoping enforced in `mount()` and query methods

Key components:
- `ManageProductFeeds`: Product feed import and management
- `ProductsIndex`: Product browsing with search
- `ProductShow`: Individual product detail view
- `ManageProductAiTemplates`: AI template CRUD
- `PhotoStudio`: Image analysis and generation workflow
- `AiJobsIndex`: AI job status monitoring

### Storage and File Handling

- Product feed files can be uploaded directly or fetched from URLs
- Photo Studio generations stored on configurable disk (`PHOTO_STUDIO_GENERATION_DISK`, defaults to `s3`)
- Storage paths follow pattern: `photo-studio/{team_id}/{Y/m/d}/{uuid}.{ext}`
- All generated images have public visibility for easy URL access

**Laravel Cloud Storage Configuration:**
- The Photo Studio S3/R2 bucket **MUST** be created with "Public" visibility in the Laravel Cloud dashboard
- Laravel Cloud enforces bucket-level visibility - file-level visibility settings in code don't override this
- Without public bucket visibility, images will return `Authorization` errors even though code sets `visibility => 'public'`
- The bucket is dedicated to Photo Studio generations (all public content), so public visibility is safe

### Authentication and Authorization

- Jetstream with Sanctum for API token auth
- Fortify handles registration, login, password resets
- Team policies in `app/Policies/TeamPolicy.php`
- All authenticated routes require `['auth:sanctum', 'verified']` middleware

### AI Provider Abstraction

The application uses a provider-agnostic AI abstraction layer (`App\Services\AI\AiManager`) that supports multiple backends. This enables per-feature provider selection and easy addition of new AI providers.

**Architecture:**
- **AiManager** (`app/Services/AI/AiManager.php`): Extends Laravel's Manager pattern for driver resolution
- **AI Facade** (`App\Facades\AI`): Provides `AI::forFeature('chat')`, `AI::forFeature('vision')`, `AI::forFeature('image_generation')`
- **Adapters** (`app/Services/AI/Adapters/`): Provider-specific implementations
  - `OpenAiAdapter`: Uses `openai-php/laravel` package for direct OpenAI API access (chat, vision, DALL-E)
  - `OpenRouterAdapter`: Wraps the `moe-mizrak/laravel-openrouter` package (fallback option)
  - `ReplicateAdapter`: Direct API integration with blocking poll for async predictions (image generation)
- **DTOs** (`app/DTO/AI/`): Normalized request/response objects (`ChatRequest`, `ChatResponse`, `ImageGenerationRequest`, `ImageGenerationResponse`, etc.)
- **Contracts** (`app/Contracts/AI/`): `AiProviderContract`, `SupportsAsyncPollingContract`

**Default Provider Strategy:**
| Feature | Default Provider | Model |
|---------|-----------------|-------|
| Chat | OpenAI | openai/gpt-5 |
| Vision | OpenAI | openai/gpt-4.1 |
| Image Generation | Replicate | (selected via Photo Studio UI) |

**Usage:**
```php
// Chat completion (Product AI templates)
$response = AI::forFeature('chat')->chat($request);

// Vision analysis (Photo Studio prompt extraction)
$response = AI::forFeature('vision')->chat(ChatRequest::multimodal(...));

// Image generation (Photo Studio renders)
$response = AI::forFeature('image_generation')->generateImage($request);
```

**Configuration:** See `config/ai.php` for provider and model configuration. Each feature (chat, vision, image_generation) can use a different provider and model.

### Database and Migrations

- Primary database: Supabase Postgres (bundled in Docker Compose as `supabase-db`)
- Host: `localhost:54322` (from host), `supabase-db:5432` (from containers)
- Test database: Same Postgres instance, configured in `.env.testing`
- Migrations in `database/migrations/`
- Set `RUN_MIGRATIONS=true` for automatic migration on container start

Key tables:
- `users`, `teams`, `team_user`, `team_invitations` (Jetstream)
- `product_catalogs`, `product_feeds`, `products` (catalog system)
- `product_ai_templates`, `product_ai_generations`, `product_ai_jobs` (AI content)
- `photo_studio_generations` (Photo Studio)

## Testing Strategy

This project uses **Pest** testing framework (built on PHPUnit). Tests are organized in `tests/Feature/` and `tests/Unit/`. Feature tests cover:
- Authentication flows (Jetstream/Fortify)
- Team management (creation, invitations, member removal)
- API token management
- Product feed parsing and import
- Product catalog management and language versions
- AI template management
- Photo Studio workflows
- Product browsing and search

**Important**: Tests run against the `supabase-db` service. Ensure it's running before executing tests:
```bash
docker compose up supabase-db -d
docker compose exec octane php artisan test
```

### Testing Patterns

- **Pest syntax**: Use `test('description', function() { ... })` and `expect()` assertions
- **Setup hooks**: Use `beforeEach()` for test setup (not PHPUnit's `setUp()` method)
- **Helper functions**: Shared helpers are defined in `tests/Pest.php` (e.g., `createTestJpegBinary()`)
- Use factories for test data setup (located in `database/factories/`)
- Stub HTTP calls using `Http::fake()` for external services (AI providers, product feeds)
- AI calls can be stubbed via `Http::fake()` since all adapters use Laravel's HTTP client
- Team scoping is critical—always create and authenticate users with teams in tests
- Livewire component tests use `Livewire::test()` for interaction testing

## Configuration Notes

### Environment Variables

Key variables beyond standard Laravel config:

**AI Provider Configuration:**
- `AI_DEFAULT_PROVIDER`: Default AI provider (default: `openai`, options: `openai`, `openrouter`, `replicate`)
- `AI_CHAT_DRIVER`: Provider for chat/text completion (default: `openai`)
- `AI_VISION_DRIVER`: Provider for vision/image analysis (default: `openai`)
- `AI_IMAGE_GENERATION_DRIVER`: Provider for image generation (default: `replicate`)
- `AI_CHAT_MODEL`: Model for chat completion (default: `openai/gpt-5`)
- `AI_VISION_MODEL`: Model for vision analysis (default: `openai/gpt-4.1`)

**Photo Studio Configuration:**
- `PHOTO_STUDIO_DEFAULT_MODEL`: Default image generation model pre-selected in UI (default: `google/gemini-2.5-flash-image`). Users can change the model via the Photo Studio interface. Available models are configured in `config/photo-studio.php`.

**OpenAI Provider:**
- `OPENAI_API_KEY`: API key for OpenAI (**required** for chat/vision)
- `OPENAI_REQUEST_TIMEOUT`: Request timeout in seconds (default: `120`)

**OpenRouter Provider (fallback option):**
- `OPENROUTER_API_KEY`: API key for OpenRouter (required if using OpenRouter)
- `OPENROUTER_API_ENDPOINT`: Custom API endpoint (default: `https://openrouter.ai/api/v1/`)
- `OPENROUTER_API_TIMEOUT`: Request timeout in seconds (default: `120`)
- `OPENROUTER_API_TITLE`: App title for OpenRouter tracking (optional)
- `OPENROUTER_API_REFERER`: Referer header for OpenRouter (optional)

**Replicate Provider:**
- `REPLICATE_API_KEY`: API key for Replicate (**required** for image generation)
- `REPLICATE_API_ENDPOINT`: Custom API endpoint (default: `https://api.replicate.com/v1/`)
- `REPLICATE_API_TIMEOUT`: Request timeout in seconds (default: `60`)
- `REPLICATE_POLLING_TIMEOUT`: Max time to wait for async predictions (default: `300`)
- `REPLICATE_POLLING_INTERVAL`: Seconds between status checks (default: `2.0`)

**Storage:**
- `PHOTO_STUDIO_GENERATION_DISK`: Storage disk for generated images (default: `s3`)

**Email (Mailgun):**
- `MAIL_MAILER`: Email driver (default: `mailgun`)
- `MAIL_FROM_ADDRESS`: Default sender email address (required)
- `MAIL_FROM_NAME`: Default sender name (required)
- `MAILGUN_DOMAIN`: Your Mailgun domain (required for Mailgun)
- `MAILGUN_SECRET`: Your Mailgun API key (required for Mailgun)
- `MAILGUN_ENDPOINT`: Mailgun API endpoint (default: `https://api.eu.mailgun.net` for EU server)

**Database (Supabase):**
- `SUPABASE_DB_USER`: Postgres username (default: `postgres`)
- `SUPABASE_DB_PASSWORD`: Postgres password (default: `supabase`)
- `SUPABASE_URL`: Hosted Supabase URL (optional, for future integration)
- `SUPABASE_ANON_KEY`: Anon key for Supabase API (optional, keep empty for local dev)
- `SUPABASE_SERVICE_ROLE_KEY`: Service role key (optional, keep empty for local dev)

**Development:**
- `OCTANE_WATCH`: Enable file watching for auto-reload (default: `true`)
- `RUN_MIGRATIONS`: Auto-run migrations on container start (default: `false`)

**Important**: Rotate database credentials in lockstep across `.env`, Supabase configuration, and deployment secrets when moving to hosted services.

### Service Configuration

- **Octane**: Runs on port 8000 internally, and 80 externally, uses Swoole, file watching enabled by default
- **Vite**: Runs on port 5173, hot module replacement for frontend assets
- **Queue**: Uses `queue:work` with database driver (no Redis required)
- **Cache**: Uses database driver

## Frontend Stack

- **Tailwind CSS**: Utility-first styling
- **Alpine.js**: Lightweight JavaScript framework for interactivity
- **Livewire**: Server-side rendering with reactive components
- **Blade templates**: Server-side templating in `resources/views/`
- **Vite**: Asset bundling and hot reload

Frontend assets are in `resources/js/app.js` and `resources/css/app.css`. Livewire handles most interactivity, with Alpine sprinkled in for UI enhancements (dropdowns, modals, etc.).

## Coding Standards

### PHP Style

- **PSR-12** compliance with four-space indentation
- Namespace alignment with directory structure (e.g., `App\\Http\\Controllers`, `App\\Livewire`)
- Livewire components:
  - Class names: PascalCase (e.g., `ManageProductFeeds`)
  - Blade views: kebab-case under `resources/views/livewire/` (e.g., `manage-product-feeds.blade.php`)
- Nested ternary operator should not be used (maintainability issues)

### Code Quality Tools

If you introduce linting or formatting tools (Laravel Pint, ESLint, Prettier), document them and integrate via Composer/NPM scripts rather than relying on local IDE configuration. For example:
```bash
composer run lint    # Future: Laravel Pint
composer run analyse # Future: PHPStan or Larastan
```

### Testing Standards

- **Use Pest syntax** for all tests (not PHPUnit class-based tests)
- Mirror production namespaces in `tests/` for autoloader simplicity
- Target **~80% statement coverage**; document gaps in PR descriptions
- Use factories for test data setup (located in `database/factories/`)
- **Stub all outbound HTTP calls** using `Http::fake()` for:
  - Product feed ingestion
  - OpenRouter API calls
  - External service integrations
- Seed data via factory helpers instead of hardcoding IDs
- Always run tests inside the Octane container to match CI environment

### Commit Conventions

Use **Conventional Commits** format for clean changelog generation:
- `feat:` - New features
- `fix:` - Bug fixes
- `chore:` - Maintenance tasks
- `docs:` - Documentation updates
- `test:` - Test additions or updates

Scope each commit to a single logical change and describe the behavior adjustment, not the implementation details.

**Example:**
```
feat: add CSV delimiter auto-detection to product feed parser

Automatically detect comma, semicolon, tab, or pipe delimiters
when parsing uploaded CSV product feeds.
```

### Pull Request Requirements

PRs must include:
1. **Summary**: Clear description of changes and motivation
2. **Testing evidence**:
   - Output from `php artisan test` for backend changes
   - Screenshots or screen recordings for UI work
3. **Linked issues**: Reference related GitHub issues
4. **Rollback considerations**: Document any migration or deployment risks
5. **Review approval**: Request review before merging
6. **CI status**: Wait for all checks to pass

Use draft PRs for work in progress.

## IDE and Tooling Notes

### JetBrains Project Settings

JetBrains project settings already track PHP quality tools. **Avoid committing per-user files** under `.idea/` to the repository. Share tooling configuration via:
- Composer scripts for linting and analysis
- Docker Compose commands (documented above)
- Helper scripts in `bin/` directory if needed

This ensures consistent development experience across the team without IDE lock-in.

## Common Workflow Patterns

### Adding a New AI Feature

1. Create a job class extending `ShouldQueue` in `app/Jobs/`
2. Use the AI abstraction: `AI::forFeature('feature_name')->chat()` or `->generateImage()`
3. Add feature configuration in `config/ai.php` under `features` array
4. Update `ProductAiJob` model constants if needed
5. Add queue dispatch in relevant Livewire component
6. Create corresponding model for storing results
7. Add migration for new table
8. Add monitoring in `AiJobsIndex` component

### Adding a New AI Provider

1. Create adapter class in `app/Services/AI/Adapters/` extending `AbstractAiAdapter`
2. Implement `AiProviderContract` interface methods (`chat()`, `generateImage()`, etc.)
3. For async APIs, implement `SupportsAsyncPollingContract` with polling logic
4. Add driver creation method in `AiManager::create{Name}Driver()`
5. Add provider configuration in `config/ai.php` under `providers` array
6. Update `.env.example` with provider-specific environment variables
7. Add tests using `Http::fake()` to stub provider API calls

### Adding Product Feed Support for New Format

1. Update `ManageProductFeeds::parseFeed()` to detect new format
2. Add parser method (e.g., `parseJson()`) following existing patterns
3. Update `extractFieldsFromSample()` for field detection
4. Add test coverage in `tests/Unit/ManageProductFeedsTest.php`
5. Update field mapping suggestions in `suggestMappings()`

### Extending Product Attributes

1. Add column to `products` table via migration
2. Update `Product` model fillable/casts
3. Add mapping field to `ManageProductFeeds` component
4. Update `importFeed()` to map new field
5. Update product display views in `resources/views/products/`
6. Add corresponding tests

# MCP Setup
- remember that when using the chrome-devtools mcp or any other request to the test the app, use the specified APP_URL in .env, default port.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.15
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/octane (OCTANE) - v2
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- livewire/livewire (LIVEWIRE) - v3
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- alpinejs (ALPINEJS) - v3
- tailwindcss (TAILWINDCSS) - v3

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== livewire/core rules ===

## Livewire Core
- Use the `search-docs` tool to find exact version specific documentation for how to write Livewire & Livewire tests.
- Use the `php artisan make:livewire [Posts\CreatePost]` artisan command to create new components
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend, they're like regular HTTP requests. Always validate form data, and run authorization checks in Livewire actions.

## Livewire Best Practices
- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

    ```blade
    @foreach ($items as $item)
        <div wire:key="item-{{ $item->id }}">
            {{ $item->name }}
        </div>
    @endforeach
    ```

- Prefer lifecycle hooks like `mount()`, `updatedFoo()` for initialization and reactive side effects:

<code-snippet name="Lifecycle hook examples" lang="php">
    public function mount(User $user) { $this->user = $user; }
    public function updatedSearch() { $this->resetPage(); }
</code-snippet>


## Testing Livewire

<code-snippet name="Example Livewire component test" lang="php">
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->call('increment')
        ->assertSet('count', 1)
        ->assertSee(1)
        ->assertStatus(200);
</code-snippet>


    <code-snippet name="Testing a Livewire component exists within a page" lang="php">
        $this->get('/posts/create')
        ->assertSeeLivewire(CreatePost::class);
    </code-snippet>


=== livewire/v3 rules ===

## Livewire 3

### Key Changes From Livewire 2
- These things changed in Livewire 2, but may not have been updated in this application. Verify this application's setup to ensure you conform with application conventions.
    - Use `wire:model.live` for real-time updates, `wire:model` is now deferred by default.
    - Components now use the `App\Livewire` namespace (not `App\Http\Livewire`).
    - Use `$this->dispatch()` to dispatch events (not `emit` or `dispatchBrowserEvent`).
    - Use the `components.layouts.app` view as the typical layout path (not `layouts.app`).

### New Directives
- `wire:show`, `wire:transition`, `wire:cloak`, `wire:offline`, `wire:target` are available for use. Use the documentation to find usage examples.

### Alpine
- Alpine is now included with Livewire, don't manually include Alpine.js.
- Plugins included with Alpine: persist, intersect, collapse, and focus.

### Lifecycle Hooks
- You can listen for `livewire:init` to hook into Livewire initialization, and `fail.status === 419` for the page expiring:

<code-snippet name="livewire:load example" lang="js">
document.addEventListener('livewire:init', function () {
    Livewire.hook('request', ({ fail }) => {
        if (fail && fail.status === 419) {
            alert('Your session expired');
        }
    });

    Livewire.hook('message.failed', (message, component) => {
        console.error(message);
    });
});
</code-snippet>


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== phpunit/core rules ===

## PHPUnit Core

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files, these are core to the application.

### Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v3 rules ===

## Tailwind 3

- Always use Tailwind CSS v3 - verify you're using only classes supported by this version.
</laravel-boost-guidelines>
