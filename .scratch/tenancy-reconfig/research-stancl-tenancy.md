# Research — stancl/tenancy v3: Pendekatan Resmi untuk Rekonfigurasi Multi-Tenant opti-works

**Tanggal:** 2026-10-06
**Metode:** audit primer ke (a) dokumentasi resmi `tenancyforlaravel.com/docs/v3/*`, (b) source resmi `archtechx/tenancy` yang terpasang di `vendor/stancl/tenancy` (tag `v3.10.1`), dan (c) source repo dokumen resmi `stancl/tenancy-docs`. Tidak ada blog/tutorial/StackOverflow sebagai sumber.
**Catatan substitusi Context7:** Context7 MCP **tidak tersedia** di harness sesi ini (tidak ada tool `resolve-library-id` / `query-docs` yang ter-expose). Diganti WebFetch langsung ke domain resmi + pembacaan source package terpasang — untuk detail API ini justru lebih otoritatif karena persis versi yang terpasang. Disclose.
**Ruang lingkup:** Menjawab 7 pertanyaan di brief. Semua klaim diberi sitasi (URL doc atau `path/to/file`). Tidak ada kode aplikasi yang diubah; ini riset saja.

**Latar yang mengikat:** opti-works memakai `stancl/tenancy` mode **single-database** dengan `BelongsToTenant` + kolom `tenant_id`, tenant diidentifikasi via **path** `/{tenant}/...` — lihat `docs/adr/0002-tenancy-stancl-single-database.md`.

---

## 1. Installation & version

### 1.1 Versi

| Item | Nilai | Sumber |
| --- | --- | --- |
| Major version aktif | **3.x** (stabil sejak Feb 2019) | https://tenancyforlaravel.com/ (FAQ: "in its third major version and has been stable since February 2019") |
| Release terbaru | **v3.10.1**, published 2026-08-05 | https://github.com/archtechx/tenancy/releases (verified via GitHub API) |
| Terpasang di opti-works | **v3.10.1** | `composer show stancl/tenancy` |
| Laravel terpasang | **13.34.0** | `php artisan --version` |
| PHP terpasang | **8.4.23** | `php --version` |

Artinya project kita **sudah di release terbaru**. Tidak ada work upgrading package yang perlu dilakukan.

### 1.2 Dukungan Laravel / PHP

| Sumber | Pernyataan |
| --- | --- |
| Doc `/docs/v3/installation` | "Laravel 9.0 or higher is needed." |
| `vendor/stancl/tenancy/composer.json` | `php ^8.0`, `illuminate/support ^10.0 \| ^11.0 \| ^12.0 \| ^13.0` |

**Bacaan yang benar:** angka "Laravel 9" di doc adalah *floor* yang belum di-refresh, bukan constraint sebenarnya. Constraint Composer adalah sumber otoritatif dan **sudah mencakup Laravel 13** — persis yang terpasang. Doc `installation` memang tertinggal di sini; bukan tanda setup kita salah.

### 1.3 Composer dependencies (langsung)

Dari `composer show stancl/tenancy`:

| Package | Constraint | Terpasang |
| --- | --- | --- |
| `php` | `^8.0` | 8.4.23 |
| `ext-json` | `*` | — |
| `facade/ignition-contracts` | `^1.0.2` | 1.0.2 |
| `illuminate/support` | `^10.0 \| ^11.0 \| ^12.0 \| ^13.0` | Laravel 13.34.0 |
| `ramsey/uuid` | `^4.7.3` | 4.9.4 |
| `stancl/jobpipeline` | `^1.8.0` | 1.9.0 |
| `stancl/virtualcolumn` | `^1.5.0` | 1.5.0 |

`stancl/jobpipeline` = paket terpisah milik author yang sama; doc `/docs/v3/event-system`: "The `JobPipeline` is a simple, yet **extremely powerful** class that lets you **convert any (series of) jobs into event listeners**". Dev-requires (tidak ikut di app): `doctrine/dbal`, `laravel/framework`, `league/flysystem-aws-s3-v3`, `orchestra/testbench`, `spatie/valuestore`.

### 1.4 Langkah instalasi persis

Doc `/docs/v3/installation`:

1. `composer require stancl/tenancy`
2. `php artisan tenancy:install`
3. `php artisan migrate`
4. Daftarkan provider di `bootstrap/providers.php`:
   ```php
   return [
       App\Providers\AppServiceProvider::class,
       App\Providers\TenancyServiceProvider::class, // <-- here
   ];
   ```
5. Kalau central DB bukan `DB_CONNECTION` di `.env`: beri nama connection central (mis. `central`) dan **pastikan sama** dengan `tenancy.central_connection`.

---

## 2. Database models & migrations

### 2.1 Dua "aplikasi"

Istilah resmi package (https://tenancyforlaravel.com/docs/v3/the-two-applications):

- **central application** — dieksekusi **ketika tidak ada tenant**. Houses signup page tempat tenant dibuat, admin panel untuk mengelola tenant.
- **tenant application** — dieksekusi dalam tenant context (tenant DB/cache). Bagian terbesar dari aplikasi nyata.

Kriteria penentu ada di source: `Tenancy::$initialized` default `false` (`vendor/stancl/tenancy/src/Tenancy.php:25`). `TenantScope::apply()` bailout di `! tenancy()->initialized` (`vendor/stancl/tenancy/src/Database/TenantScope.php:16-18`).

### 2.2 "Default central tables" vs "default tenant tables" — TIDAK ADA di package

**Ini koreksi penting terhadap asumsi umum.** Package **tidak punya daftar tabel default central maupun default tenant**. Yang di-*own* package hanya **dua tabel di central**: `tenants` dan `domains`. Seluruh tabel aplikasi lain (users, roles, work_orders, ...) **100% keputusan aplikasi**; docs tidak pernah mendefinisikannya.

| Table | Di-*own* package? | Keterangan |
| --- | --- | --- |
| `tenants` | Ya | Dibuat `tenancy:install`. Central. Paksa central connection (`vendor/stancl/tenancy/src/Database/Models/Tenant.php:24`) |
| `domains` | Ya | Dibuat `tenancy:install`. Central. Paksa central connection (`vendor/stancl/tenancy/src/Database/Models/Domain.php:21`) |
| `tenant_user_impersonation_tokens` | Opsional | Hanya kalau `UserImpersonation` di-enable (`vendor/stancl/tenancy/assets/impersonation-migrations/`) |
| tabel app lain | Tidak | Penempatan sepenuhnya keputusan aplikasi |

Pemisahan central-vs-tenant tabel **hanya** datang dari mode yang dipilih:

- **Multi-database**: tabel terpisah karena connection-nya terpisah, bukan karena package. Cara achieve-nya: pindahkan tenant migrations ke `database/migrations/tenant` (https://tenancyforlaravel.com/docs/v3/quickstart §Migrations).
- **Single-database**: **tidak ada pemisahan sama sekali** — semua tabel di satu DB; yang membedakan primary vs global model adalah **apakah trait `BelongsToTenant` dipakai atau tidak** (lihat §3.4).

### 2.3 Schema `tenants`

Dari `vendor/stancl/tenancy/assets/migrations/2019_09_15_000010_create_tenants_table.php`:

| Column | Type | Catatan |
| --- | --- | --- |
| `id` | `string` | **primary key**. UUID di-generate `GeneratesIds` bila tidak disupply |
| — | — | `// your custom columns may go here` — tempat menambah kolom cabang |
| `created_at`, `updated_at` | `timestamp` | |
| `data` | `json` nullable | |

Model: `$guarded = []`, `$modelsShouldPreventAccessingMissingAttributes = false` (`src/Database/Models/Tenant.php:31-36`) — itulah yang membuat `Tenant::create(['plan' => 'free'])` tanpa kolom `plan` tetap aman (nilai masuk `data` JSON).

Untuk **autoincrement**, doc `/docs/v3/configuration#tenant-model` mensyaratkan **tiga hal sekaligus**: (1) `tenancy.id_generator` = `null` atau model tanpa trait `GeneratesIds`, (2) ubah kolom `id` di migration `tenants` ke tipe incrementing, (3) samakan tipe kolom `tenant_id` di migration `domains`. Lupa satu = tidak sinkron.

### 2.4 Schema `domains`

Dari `vendor/stancl/tenancy/assets/migrations/2019_09_15_000020_create_domains_table.php`:

| Column | Type | Catatan |
| --- | --- | --- |
| `id` | `increments` | primary |
| `domain` | `string(255)` | **`unique`** |
| `tenant_id` | `string` | **FK → `tenants.id`**, `onUpdate('cascade')`, `onDelete('cascade')` |
| `created_at`, `updated_at` | `timestamp` | |

`domains` **opsional** — doc `/docs/v3/domains`: "Domains are optional. If you're using path or request data identification, you don't need to worry about them." Relevan untuk opti-works: identifikasi via path ⇒ tabel `domains` **tidak dipakai sama sekali**.

### 2.5 Yang di-generate `tenancy:install`

Dari `vendor/stancl/tenancy/src/Commands/Install.php` (signature `tenancy:install`, tanpa argumen, `:16`):

| Output | Detail |
| --- | --- |
| `config/tenancy.php` | publish tag `config` dari `assets/config.php` (`Install.php:33-37`) |
| `routes/tenant.php` | publish tag `routes`, **hanya kalau belum ada** (`Install.php:39-47`) |
| `app/Providers/TenancyServiceProvider.php` | publish tag `providers` dari `assets/TenancyServiceProvider.stub.php` (`Install.php:49-53`) |
| 2 migration (`tenants`, `domains`) | publish tag `migrations` → `database/migrations` (`Install.php:55-59`) |
| folder `database/migrations/tenant` | `mkdir` bila belum ada (`Install.php:61-64`) |

Catatan: `tenancy:install` **selalu** membuat folder `database/migrations/tenant`, termasuk di mode single-database. Folder itu **tidak boleh dipakai** di single-database mode (lihat §7.1).

### 2.6 Model classes & kustomisasi

**`Tenant`** — default `Stancl\Tenancy\Database\Models\Tenant`. Trait bawaan: `CentralConnection`, `GeneratesIds`, `HasDataColumn`, `HasInternalKeys`, `TenantRun`, `InvalidatesResolverCache`. Dispatch Eloquent events → `Stancl\Tenancy\Events\*` (`CreatingTenant`, `TenantCreated`, …) lewat `$dispatchesEvents` (`:52-61`).

**`Domain`** — default `Stancl\Tenancy\Database\Models\Domain`. Trait: `CentralConnection`, `EnsuresDomainIsNotOccupied`, `ConvertsDomainsToLowercase`, `InvalidatesTenantsResolverCache`.

**Poin terpenting untuk mode single-database** — doc `/docs/v3/tenants` menyatakan eksplisit:

> "**If you don't need domains or databases, ignore the steps above.** Everything will work just as well."

Artinya **tidak wajib** membuat `App\Models\Tenant` kustom kalau tidak butuh domains/databases. Yang wajib: set `tenancy.tenant_model` bila memang memakai model kustom. opti-works memakai base model langsung (`config/tenancy.php:15`) — **sesuai** rekomendasi resmi untuk single-DB tanpa domains.

Kolom kustom (yang tidak masuk `data` JSON):
```php
public static function getCustomColumns(): array { return ['id', 'plan']; } // 'id' WAJIB ikut
```
Rename kolom `data` → override `getDataColumn()`. Query isi `data` butuh `where('data->foo', 'bar')`, tapi doc advises: kalau perlu di-`WHERE`, buat kolom dedicated ("This will improve performance and you won't have to think about the `data->` prefixing").

---

## 3. Central vs single-database mode

### 3.1 Definisi resmi

Doc `/docs/v3/single-database-tenancy`:

> "Single-database tenancy comes with **lower devops complexity, but larger code complexity** than multi-database tenancy, since you have to **scope things manually**, and won't be able to integrate some third-party packages."
>
> "It is preferable when you have **too many shared resources between tenants**, and don't want to make too many cross-database queries."

Yang **wajib** dimatikan — **dua hal, bukan satu**:

1. `DatabaseTenancyBootstrapper` — "make sure you **disable** the `DatabaseTenancyBootstrapper` which is responsible for switching database **connections** for tenants."
2. Job pembuatan DB di listener `TenantCreated` — "Also make sure you have **disabled the database creation jobs** (`CreateDatabase`, `MigrateDatabase`, `SeedDatabase` ...) from listening to the `TenantCreated` event."

Yang **boleh tetap jalan**: "You can still use the other tenancy bootstrappers to separate tenant caches, filesystems, etc."

### 3.2 Bagaimana package memutuskan memakai connection `tenant` — trace mechanism

Tidak dijawab eksplisit di docs, jadi ditelusuri ke source.

**Rantai pemicu:** identification middleware → `Tenancy::initialize($tenant)` (`src/Tenancy.php:32`) → set `$this->tenant` → fire `TenancyInitialized` → listener `BootstrapTenancy` → menjalankan tiap bootstrapper dari `config('tenancy.bootstrappers')`.

**DatabaseTenancyBootstrapper:**
```php
// src/Bootstrappers/DatabaseTenancyBootstrapper.php:23-36
public function bootstrap(Tenant $tenant) {
    if (app()->environment('local')) { /* guard: TenantDatabaseDoesNotExistException */ }
    $this->database->connectToTenant($tenant);
}
```

→ `DatabaseManager::connectToTenant()` (`src/Database/DatabaseManager.php:41-46`):
```php
$this->purgeTenantConnection();
$this->createTenantConnection($tenant);   // config['database.connections.tenant'] = $tenant->database()->connection()
$this->setDefaultConnection('tenant');    // config['database.default'] = 'tenant'
```

→ `setDefaultConnection()` (`:60-64`) mengubah `database.default` **dan** memanggil `$this->database->setDefaultConnection()`.

**Temuan kunci: tidak ada "cek" kondisional di dalam package.** Package tidak memeriksa "apakah aku di single-database mode". Dia hanya menjalankan bootstrapper yang **kamu listing** di config. Kalau `DatabaseTenancyBootstrapper` tidak ada di array, tidak ada yang pernah menyentuh connection. Ini switch murni konfigurasi — dan itulah sebabnya doc Tunggal memakai kata "disable".

Detail penting dari `/docs/v3/tenancy-bootstrappers`:

> "Note that only the **default** connection is switched. If you use another connection explicitly, be it using `DB::connection('...')`, a model `getConnectionName()` method, or a model trait like `CentralConnection`, **it will be respected.** The bootstrapper doesn't **force** any connections, it merely switches the default one."

Konsekuensi untuk single-DB: `BelongsToTenant` **tidak menyentuh connection sama sekali** — dengan sendirinya tidak akan men-switch apa pun.

### 3.3 `BelongsToTenant` — apa yang sebenarnya ia lakukan

Full trait, hanya 35 baris (`vendor/stancl/tenancy/src/Database/Concerns/BelongsToTenant.php`):

```php
trait BelongsToTenant
{
    public static $tenantIdColumn = 'tenant_id';          // configurable

    public function tenant() {                            // :17-20
        return $this->belongsTo(config('tenancy.tenant_model'), BelongsToTenant::$tenantIdColumn);
    }

    public static function bootBelongsToTenant()           // :22-34
    {
        static::addGlobalScope(new TenantScope);
        static::creating(function ($model) {
            if (! $model->getAttribute(BelongsToTenant::$tenantIdColumn) && ! $model->relationLoaded('tenant')) {
                if (tenancy()->initialized) {
                    $model->setAttribute(BelongsToTenant::$tenantIdColumn, tenant()->getTenantKey());
                    $model->setRelation('tenant', tenant());
                }
            }
        });
    }
}
```

Jadi persis **tiga hal**: (1) global scope, (2) auto-fill `tenant_id` saat create **hanya kalau tenancy initialized**, (3) relasi `tenant()`. **Nol** interaksi dengan database connection.

`TenantScope` (`src/Database/TenantScope.php:14-21`):
```php
public function apply(Builder $builder, Model $model) {
    if (! tenancy()->initialized) { return; }             // central context = unscoped
    $builder->where($model->qualifyColumn(BelongsToTenant::$tenantIdColumn), tenant()->getTenantKey());
}
```

Efek samping penting untuk keamanan: **di central context scope-nya mati total**, jadi query dari panel admin sentral tanpa sengaja bisa melihat semua tenant. Itu perilaku yang *dirancang*, bukan bug — tapi wajib diketahui.

### 3.4 Empat tipe model (konsep resmi)

Doc `/docs/v3/single-database-tenancy`:

| Tipe | Definisi | Cara enforce |
| --- | --- | --- |
| **Tenant** model | model tenant itu sendiri | — |
| **primary** | **directly** `belongsTo` tenant | trait `BelongsToTenant` |
| **secondary** | **indirectly** punya tenant (Comment → Post → Tenant) | diakses lewat parent; opsional trait `BelongsToPrimaryModel` |
| **global** | **tidak discope sama sekali** | **jangan** pakai trait apa pun |

### 3.5 Cara EXCLUDE tabel/model dari tenant scoping

**Mekanisme resmi = tidak ada trait.** Tidak ada allowlist, tidak ada config key, tidak ada "exclude table" list. Tabel/model disebut global **karena dan hanya karena `BelongsToTenant` tidak di-apply** — doc: "global models — models that are **not scoped** to any tenant whatsoever".

Konsekuensi yang harus disadari: tabel `users` shared antar tenant **tidak bisa** disaring lewat scope; filtering harus lewat authorization sendiri. opti-works sudah menerapkan ini persis untuk `User` (`app/Models/User.php` tidak memakai `BelongsToTenant`; lihat `docs/adr/0002` ¶2).

Trait pendukung lain dari package:

| Trait | Fungsi | Sumber |
| --- | --- | --- |
| `BelongsToPrimaryModel` | Scoping secondary model (`Comment::all()`) dengan memuat parent yang ter-scope | doc §Concepts |
| `HasScopedValidationRules` | Helper `$tenant->unique('posts')` / `$tenant->exists('posts')` | doc §Validation |
| `CentralConnection` | Paksa model ke `tenancy.database.central_connection` | `src/Database/Concerns/CentralConnection.php:9-12` |
| `TenantConnection` | Lawannya, untuk manual mode | doc `/docs/v3/manual-mode` |
| `TenantRun` | `$tenant->run(fn () => ...)` — atomic, revert ke context sebelumnya | `src/Database/Concerns/TenantRun.php:18-33` |

### 3.6 Escape hatch & kustomisasi kolom

- Disable scope per query: `Model::query()->withoutTenancy()` — macro didaftarkan di `TenantScope::extend()` (`src/Database/TenantScope.php:23-28`).
- Rename kolom global (mis. `team_id`): `BelongsToTenant::$tenantIdColumn = 'team_id';` di `boot()` service provider. Doc tegas: "this is **universal** to all your primary models … you can't use both `team_id` and `tenant_id`."

### 3.7 Tiga konsekuensi yang WAJIB di-handle manual

Doc `/docs/v3/single-database-tenancy` §"Database considerations":

**(a) Unique index harus di-scope ke tenant.**
```php
// primary model
$table->unique(['tenant_id', 'slug']);
// secondary model — TIDAK perlu tenant_id, cukup parent key
$table->unique(['post_id', 'user_id']);
```

**(b) Validasi `unique` / `exists` tidak ter-scope.**
```php
Rule::unique('posts', 'slug')->where('tenant_id', tenant('id'));
```

**(c) Raw query `DB::` tidak pernah ter-scope.**
> "The package can only provide scoping logic for the abstraction logic that Eloquent is, it can't do anything with low level database queries. **Be careful with using them.**"

---

## 4. Routing & tenant identification

### 4.1 Metode yang tersedia

Doc `/docs/v3/tenant-identification` — semua metode punya middleware sendiri:

| Metode | Middleware | Catatan |
| --- | --- | --- |
| Domain (`acme.com`) | `InitializeTenancyByDomain` | butuh trait `HasDomains` |
| Subdomain (`acme.yoursaas.com`) | `InitializeTenancyBySubdomain` | simpan **subdomain saja** di kolom `domain` |
| Domain atau subdomain | `InitializeTenancyByDomainOrSubdomain` | rekaman **ber-titik** = domain, tanpa titik = subdomain |
| **Path** (`yoursaas.com/acme/dashboard`) | `InitializeTenancyByPath` | **yang dipakai opti-works** |
| Request data (header/query) | `InitializeTenancyByRequestData` | default header `X-Tenant`, fallback query `tenant` |

Doc juga: "you're **free to write additional tenant resolvers**."

### 4.2 Path-based identification — konfigurasi presisi

```php
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

Route::group([
    'prefix' => '/{tenant}',
    'middleware' => [InitializeTenancyByPath::class],
], function () {
    Route::get('/foo', 'FooController@index');
});
```
sumber: https://tenancyforlaravel.com/docs/v3/tenant-identification §"Path identification"

Tiga detail source-level yang **tidak** disebut doc dan wajib diketahui:

**(a) `tenant` HARUS parameter PERTAMA.** `src/Middleware/InitializeTenancyByPath.php:36-45`:
```php
// Only initialize tenancy if tenant is the first parameter
// We don't want to initialize tenancy if the tenant is
// simply injected into some route controller action.
if ($route->parameterNames()[0] === PathTenantResolver::$tenantParameterName) {
    return $this->initializeTenancy($request, $next, $route);
}
throw new RouteIsMissingTenantParameterException;
```
Kalau tidak pertama → **`RouteIsMissingTenantParameterException`**, bukan diam-diam gagal.

**(b) Route parameter di-`forget()` setelah resolusi.** `src/Resolvers/PathTenantResolver.php:29-46` melakukan `forgetParameter('tenant')` dua kali (sebelum dan sesudah `tenancy()->find()`). Implications:
- Sumber kebenaran tenant bukan `$request->route('tenant')`, tapi **`tenant()` helper / `tenancy()->tenant`** (`src/helpers.php:16-35`; container binding `Tenant::class` di `src/TenancyServiceProvider.php:40-42`).
- Route **tetap butuh** prefix `/{tenant}` untuk **URL generation** — jadi `route('work-orders.show', ['tenant' => tenant()?->getTenantKey(), ...])` tetap wajib (memang sudah ada di `app/Http/Controllers/WorkOrderController.php:91`).

**(c) Cache resolver dimatikan untuk path.** `PathTenantResolver::$shouldCache = false` (`:16`), default TTL 3600 (`:19`), store `null` (`:22`). `$tenantParameterName` adalah static property yang bisa diubah (doc: "look into the `PathTenantResolver` for the public static property"), mis. ke `team`. Konsekuensi: tidak ada risiko stale-tenant dari cache resolver di mode path.

### 4.3 Urutan middleware stack

**Prioritas global**, di-set oleh `TenancyServiceProvider::makeTenancyMiddlewareHighestPriority()` (`assets/TenancyServiceProvider.stub.php:131-147`) — `prependToMiddlewarePriority()` untuk semua identification middleware + `PreventAccessFromCentralDomains` (di-prepend pertama ⇒ prioritas tertinggi):

```php
$tenancyMiddleware = [
    // Even higher priority than the initialization middleware
    Middleware\PreventAccessFromCentralDomains::class,
    Middleware\InitializeTenancyByDomain::class,
    Middleware\InitializeTenancyBySubdomain::class,
    Middleware\InitializeTenancyByDomainOrSubdomain::class,
    Middleware\InitializeTenancyByPath::class,
    Middleware\InitializeTenancyByRequestData::class,
];
foreach (array_reverse($tenancyMiddleware) as $middleware) {
    $this->app->make(Kernel::class)->prependToMiddlewarePriority($middleware);
}
```

**Stack default tenant routes** (`assets/tenant_routes.stub.php` dan doc `/docs/v3/routes`):
```php
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () { /* ... */ });
```

**`PreventAccessFromCentralDomains` TIDAK diperlukan untuk path identification.** Doc `/docs/v3/routes`: "Central routes are only available on central domains, and tenant routes are only available on tenant domains. **If you don't use domain identification, then all routes are always available** and you may skip the details about preventing access from other domains." Konsekuensi untuk opti-works: URL tenant bisa diakses via central domain; ini harus di-handle sendiri kalau tidak diinginkan.

**Bootstrap order:** `InitializingTenancy` → (`$this->tenant` di-set) → `TenancyInitialized` → `BootstrapTenancy` → tiap bootstrapper → `BootstrappingTenancy`/`TenancyBootstrapped` → `$next($request)`. Sumber: `src/Tenancy.php:52-58`, `src/Middleware/IdentificationMiddleware.php:22-37`, `assets/TenancyServiceProvider.stub.php:71-73`.

`Tenancy::initialize()` bersifat idempoten: kalau sudah initialized dengan tenant yang sama, return early (`:43-45`); kalau tenant berbeda, `end()` dulu (`:48-50`).

### 4.4 Referensi penuh `config/tenancy.php`

Default diambil verbatim dari `vendor/stancl/tenancy/assets/config.php` (file yang di-publish `tenancy:install`).

| Key | Default | Relevan untuk single-DB? |
| --- | --- | --- |
| `tenant_model` | `Stancl\Tenancy\Database\Models\Tenant::class` | Ya |
| `id_generator` | `Stancl\Tenancy\UUIDGenerator::class` | Ya (UUID default) |
| `domain_model` | `Stancl\Tenancy\Database\Models\Domain::class` | **Tidak** (path ID) |
| `central_domains` | `['127.0.0.1', 'localhost']` | Hanya untuk `PreventAccessFromCentralDomains` + subdomain ID |
| `bootstrappers` | `[DatabaseTenancyBootstrapper, CacheTenancyBootstrapper, FilesystemTenancyBootstrapper, QueueTenancyBootstrapper]` (`RedisTenancyBootstrapper` commented) | **Ya — kunci utama** |
| `database.central_connection` | `env('DB_CONNECTION', 'central')` | Ya — dipaksa oleh `CentralConnection` |
| `database.template_tenant_connection` | `null` | Tidak |
| `database.prefix` / `.suffix` | `'tenant'` / `''` | Tidak |
| `database.managers` | sqlite / mysql / mariadb / pgsql (+ commented `PermissionControlledMySQLDatabaseManager`, `PostgreSQLSchemaManager`) | Tidak |
| `cache.tag_base` | `'tenant'` | Ya |
| `filesystem.suffix_base` | `'tenant'` | Ya |
| `filesystem.disks` | `['local', 'public']` (+ commented `s3`) | Ya |
| `filesystem.root_override` | `['local' => '%storage_path%/app/', 'public' => '%storage_path%/app/public/']` | Ya |
| `filesystem.suffix_storage_path` | `true` | Ya |
| `filesystem.asset_helper_tenancy` | `true` | **Penting untuk Inertia/Vite** — lihat §7.6 |
| `redis.prefix_base` | `'tenant'` | Tidak |
| `redis.prefixed_connections` | `[]` | Tidak |
| `features` | semua commented (`UserImpersonation`, `TelescopeTags`, `UniversalRoutes`, `TenantConfig`, `CrossDomainRedirect`, `ViteBundler`) | Sesuai kebutuhan |
| `routes` | `true` | Ya |
| `migration_parameters` | `['--force' => true, '--path' => [database_path('migrations/tenant')], '--realpath' => true]` | **Tidak** di single-DB |
| `seeder_parameters` | `['--class' => 'DatabaseSeeder']` | Tidak |

Doc `/docs/v3/configuration` menambah: "whenever the class you're using has a `public static` property, **it's intended to be configured**" — static properties adalah surface konfigurasi kedua di luar config file.

---

## 5. Lifecycle

### 5.1 Command — PENTING: `tenants:new` / `tenants:delete` TIDAK ADA

Diverifikasi dua kali: (a) `php artisan list` di project, (b) listing file di `vendor/stancl/tenancy/src/Commands/`. **Tidak ada command CRUD tenant di v3.** Pembuatan tenant adalah operasi Eloquent biasa — doc `/docs/v3/tenants`: "You can create tenants like any other models: `$tenant = Tenant::create(['plan' => 'free']);`" Delete = `$tenant->delete()`.

Daftar command lengkap (hasil `php artisan list`, cocok dengan `src/TenancyServiceProvider.php:85-93`):

| Command | Signature / option | Sumber |
| --- | --- | --- |
| `tenancy:install` | tanpa argumen | `src/Commands/Install.php:16` |
| `tenants:list` | tanpa argumen | `src/Commands/TenantList.php` |
| `tenants:migrate` | extend `Illuminate\Database\Console\Migrations\MigrateCommand` + `--tenants` (array, optional) | `src/Commands/Migrate.php:16-25` + `src/Concerns/HasATenantsOption.php:15` |
| `tenants:migrate-fresh` | `--tenants` (array, optional), `--drop-views`, `--step` | `src/Commands/MigrateFresh.php:27-31` |
| `tenants:rollback` | extend `RollbackCommand` + `--tenants` | `src/Commands/Rollback.php` |
| `tenants:seed` | `{class?} {--class=Database\Seeders\DatabaseSeeder} {--database=} {--force}` (Laravel ≥ 13.24.0) | `src/Commands/Seed.php:32-43` |
| `tenants:run` | `{commandname} {--tenants} {--option=*} {--argument=*}` | `src/Commands/Run.php` |

Semua tenant-aware command: **default = semua tenant**, `--tenants=<id>` untuk subset. Catatan CLI dari doc: "To include multiple tenants using CLI, you can use **multiple** `--tenants=<...>` options. If you're calling the command using `Artisan::call()`, `--tenants` has to be an **array**."

`tenants:migrate-fresh` = `db:wipe --database=tenant` lalu `tenants:migrate` (`src/Commands/MigrateFresh.php:40-54`).

### 5.2 Provisioning database di single-database mode

**Tidak ada.** Doc `/docs/v3/single-database-tenancy` preskriptif: matikan `CreateDatabase`, `MigrateDatabase`, `SeedDatabase` dari `TenantCreated`. Tidak ada `TenantDatabaseManager` yang dipanggil, tidak ada `CREATE DATABASE`. Tabel tetap di `database/migrations` dan dijalankan dengan **`php artisan migrate` biasa**.

opti-works sudah benar di sini: `app/Providers/TenancyServiceProvider.php:35` dan `:41` → `Events\TenantCreated::class => []` dan `Events\TenantDeleted::class => []`, keduanya kosong.

### 5.3 Event map (default) & urutan bootstrap

`assets/TenancyServiceProvider.stub.php:23-92` adalah peta default lengkap. Yang wajib diketahui:

| Event | Default listener | Catatan |
| --- | --- | --- |
| `TenantCreated` | `JobPipeline([CreateDatabase, MigrateDatabase /*, SeedDatabase */])`, `shouldBeQueued(false)` | **harus dikosongkan di single-DB** |
| `TenantDeleted` | `JobPipeline([DeleteDatabase])`, `shouldBeQueued(false)` | **harus dikosongkan di single-DB** |
| `TenancyInitialized` | `Listeners\BootstrapTenancy` | menjalankan semua bootstrapper |
| `TenancyEnded` | `Listeners\RevertToCentralContext` | revert ke central |
| `SyncedResourceSaved` | `Listeners\UpdateSyncedResource` | resource syncing (multi-DB saja) |

Doc `/docs/v3/quickstart` menjelaskan alasan `JobPipeline`: mapping event-listener biasa akan menjalankan listener "in some stupid order that would result in things like the database being migrated before it's created".

Stub memberi komentar pada `->shouldBeQueued(false)`: "`false` by default, but you probably want to make this `true` for production."

**`DatabaseMigrated` / `DatabaseSeeded` / `DatabaseRolledBack` fire di TENANT context** — doc `/docs/v3/event-system` §Available events: "Depending on how your application bootstraps tenancy, you might need to be specific about interacting with the **central** database in these events' listeners".

### 5.4 Job queues

`QueueTenancyBootstrapper`: "adds the current tenant's ID to the queued job payloads, and **initializes tenancy based on this ID when jobs are being processed**" (doc `/docs/v3/tenancy-bootstrappers`).

- Static `$forceRefresh` default `false`; set `true` untuk re-init tiap job (berguna kalau state tenant berubah, mis. Tenant Config).
- **Tidak butuh phpredis.** Yang butuh phpredis adalah `RedisTenancyBootstrapper`. Lihat gotcha di §7.6.
- Job dispatched dari central context tetap central. Doc menyarankan **jangan mix queue connections** central & tenant. Untuk memaksa central: buat queue connection dengan key `'central' => true`, lalu `dispatch(new SomeJob(...))->onConnection('central')`.

### 5.5 Manual initialization

`tenancy()->initialize($tenant)` (doc `/docs/v3/manual-initialization`); helper di `src/helpers.php:8-14`. Companion API: `tenancy()->end()`, `tenancy()->central(callable)`, `tenancy()->runForMultiple($tenants, callable)`, `$tenant->run(callable)` — di `src/Tenancy.php` dan `src/Database/Concerns/TenantRun.php`.

---

## 6. Testing

### 6.1 Status dokumentasi resmi: INCOMPLETE

Halaman `/docs/v3/testing` secara literal memuat **"TODO: Review"** tepat di bawah judul. Ini fakta yang harus dicatat — jangan memperlakukan isi halaman itu sebagai acuan komprehensif. Yang ada:

**Central app** — "just write normal Laravel tests."

**Tenant app** — pattern dari doc:
```php
class TestCase {
    protected $tenancy = false;

    public function setUp(): void {
        parent::setUp();
        if ($this->tenancy) { $this->initializeTenancy(); }
    }

    public function initializeTenancy() {
        $tenant = Tenant::create();
        tenancy()->initialize($tenant);
    }
}

class FooTest extends TestCase {
    protected $tenancy = true;
    /** @test */
    public function some_test() { $this->assertTrue(...); }
}
```

**`Event::fake()`** — "the package makes heavy use of events, so if you use `Event::fake()` anywhere in your tests, tenancy initialization and related processes might break." Rekomendasi doc: selective, `Event::fake([MyEvent::class])`, bukan `Event::fake()`.

### 6.2 `RefreshDatabase` — batasan ini TIDAK berlaku untuk opti-works

Quote persis dari doc `/docs/v3/testing`:

> "Note: If you're using **multi-database tenancy & the automatic mode**, it's not possible to use `:memory:` SQLite databases or the `RefreshDatabase` trait due to the switching of default database."

Kondisinya **multi-database** + automatic mode (yaitu `DatabaseTenancyBootstrapper` aktif yang men-switch default connection). Di single-database mode **tidak ada connection switching**, jadi batasan ini **tidak berlaku secara mekanis** — opti-works boleh memakai `RefreshDatabase` + `:memory:` untuk test tenant-scoped. Ini **kesimpulan dari mekanisme source** (§3.2), **bukan pernyataan eksplisit di doc**. Lihat §9.

---

## 7. Gotchas / migration notes

### 7.1 `php artisan migrate` di setup multi-tenant — dan kenapa single-DB beda

| | Multi-database | Single-database |
| --- | --- | --- |
| Lokasi migration tenant | `database/migrations/tenant` | `database/migrations` (sama) |
| Command | `php artisan tenants:migrate` | **`php artisan migrate` biasa** |
| Migration central | `database/migrations` | `database/migrations` (sama) |
| Tabel per tenant | dibuat runtime oleh `CreateDatabase` | **tidak ada** — satu tabel, dibedakan `tenant_id` |

Gotcha: `tenancy:install` **selalu** membuat `database/migrations/tenant` bahkan di single-DB. Kalau folder itu dibiarkan berisi migration, file-nya **tidak** akan ikut jalan dari `php artisan migrate` (path berbeda) tapi tetap terbaca tooling/linter — sumber confusion. Rekomendasi: kosongkan folder itu di single-DB mode, dan (kalau `tenants:migrate` masih mungkin terpakai) set `migration_parameters['--path']` ke `database/migrations`.

**Namespace collision** (konteks multi-DB, tapi gotcha umum): doc `/docs/v3/migrations`: "all migrations share the same PHP namespace, so even if you use the same table name in the central and tenant databases, you have to use **different migration (class) names**."

### 7.2 Controller constructor DI jalan SEBELUM route middleware

Doc `/docs/v3/early-identification`:

> "**route-level middleware is executed after controller constructors.** The implication of this is if you're using dependency injection to inject some services in the controller constructors, **they will read from the central context**, because route-level middleware hasn't initialized tenancy yet."

Solusi yang doc prefer: **jangan** DI di constructor; inject di route action, atau pakai memoized method (`$this->cloudinary ??= app(Cloudinary::class)`). Sangat relevan untuk Inertia + service container.

### 7.3 TrustHosts middleware memblokir domain-based identification

Doc `/docs/v3/domains`: sejak Laravel 8, `TrustHost` middleware aktif default dan memblokir domain-based tenant ID karena request dianggap 'untrusted' — harus masuk `TrustHosts::hosts()` atau middleware-nya di-comment-out. **Tidak berlaku** untuk path identification.

### 7.4 `config:cache` / `route:cache` — TIDAK ADA dokumentasi resmi

**Pernyataan eksplisit: docs resmi v3 tidak membahas `config:cache` maupun `route:cache` sama sekali.** Sudah di-search di repo dokumen resmi (`stancl/tenancy-docs`) dan issue tracker (`archtechx/tenancy`) — **nol hasil**. Jadi: tidak ada guidance resmi; jangan menganggap ada.

Yang bisa dinyatakan dengan aman dari sumber yang ada:

- `config:cache` aman untuk hal yang benar-benar statis. `tenancy.central_domains`, `bootstrappers`, `cache.tag_base`, `filesystem.*` dibaca via `config()` saat runtime → aman ter-cache.
- `tenancy.database.central_connection` memakai `env()`; ini ter-resolve Laravel saat cache dibuat, jadi aman asal `.env` per environment benar.
- Doc `/docs/v3/routes` menyebut constraint nyata terkait route (bukan caching): "**If you're using multiple central domains, you can't use route names**, because different routes (= different combinations of domains & paths) can't share the same name."
- `TenancyServiceProvider::mapRoutes()` mendaftarkan `routes/tenant.php` di dalam `$this->app->booted(...)` callback (`src/TenancyServiceProvider.stub.php:121-129`) — tenant routes **ikut** ter-cache bersama route cache normal. Tidak ada dokumentasi yang menyuruh melakukan sesuatu yang berbeda.

**Rekomendasi (berbasis mekanisme, bukan docs):** verifikasi sendiri dengan `php artisan route:cache` + smoke test path tenant sebelum deploy. Jangan kutip ini sebagai "dokumentasi resmi".

### 7.5 Upgrade antar major version (2.x → 3.x)

Doc `/docs/v3/upgrading` — "**most of the package's code is different now**, which means that you will have to update all of the places where you interact with the package's classes." Yang berubah:

1. Prefix internal key tenant storage `_tenancy_` → `tenancy_` (jalankan script dengan kode 2.x, **app harus downtime**, backup DB dulu).
2. Migration baru: `domains` drop primary → tambah `increments('id')->first()` + `unique('domain')` + `timestamps()`; `tenants` ubah `data` jadi `json nullable` + tambah `timestamps`; backfill `created_at`/`updated_at` di `domains`.
3. **Replace `Http\Kernel` dengan versi stock** dari `laravel/laravel` — package v3 **tidak lagi menuntut** perubahan Kernel; hapus semua modifikasi 2.x.
4. Delete config, publish ulang, jalankan `php artisan tenancy:install`, register provider baru.
5. Buat ulang Tenant model.
6. Update routes ke middleware baru.

Doc juga menyatakan "automatic tenancy will still work the same way" — yang berubah terutama di *surface API*, bukan arsitektur.

Untuk v3.9 → v3.10 tidak ada migration notes di docs. Yang terlihat dari source: `src/Commands/Seed.php:32-43` conditionally mendefinisikan signature berdasarkan versi Laravel (`version_compare(app()->version(), '13.24.0', '>=')`, refer issue archtechx/tenancy#1474) — indikasi paket adapting ke perubahan signature `SeedCommand` di Laravel.

### 7.6 Gotcha spesifik pada konfigurasi opti-works sekarang

| Temuan | Risiko | Sumber |
| --- | --- | --- |
| `config/tenancy.php:44` — `QueueTenancyBootstrapper` di-comment dengan alasan "**Note: phpredis is needed**" | **Alasannya salah.** `QueueTenancyBootstrapper` **tidak** butuh phpredis; yang butuh phpredis adalah `RedisTenancyBootstrapper` (yang juga di-comment). Default config justru **mengaktifkan** Queue dan men-comment Redis (`assets/config.php:34-35`). | doc `/docs/v3/tenancy-bootstrappers` |
| `QueueTenancyBootstrapper` nonaktif padahal project pakai **Reverb (broadcast)** + `app_notifications` | Job yang di-dispatch dari tenant context tidak auto-re-init tenancy → bisa jalan di central context. Untuk notifikasi WO real-time ini memengaruhi kebenaran data. | doc `/docs/v3/queues` |
| `FilesystemTenancyBootstrapper` aktif → `storage_path()` di-suffix per tenant | `storage_path()` jadi `/storage/tenant{id}/` dan disk `local`/`public` root di-override. Log tetap di `storage/logs` ("Logs will be saved in `storage/logs` regardless of any changes to `storage_path()`"). Upload di `WorkOrderController.php:80` memakai `work-orders/{tenantId}` ⇒ path relatif terhadap root yang **sudah** di-suffix. | doc §Filesystem tenancy bootstrapper |
| `filesystem.asset_helper_tenancy => true` (default, tidak di-override project) | `asset()` diarahkan ke `TenantAssetsController` (`/tenancy/assets/...`). Untuk **Inertia + Vite** ini yang paling perlu diperiksa; ada feature class khusus `Stancl\Tenancy\Features\ViteBundler` yang belum di-enable. | doc §Assets + §Vite bundler |
| Tidak ada custom `App\Models\Tenant` | **Bukan masalah** untuk single-DB tanpa domains (doc eksplisit mengizinkan). Tapi berarti tidak ada `HasScopedValidationRules` (butuh override di Tenant model), sehingga validasi harus manual — yang memang sudah dilakukan di `DepartmentController.php:51`, `EmployeeController.php:52,59`, `DivisionController.php:45`. | doc §Validation |
| `Route::namespace(static::$controllerNamespace)` di `app/Providers/TenancyServiceProvider.php:116` (ikut stub package) | Di Laravel 11+ `Illuminate\Routing\Router::namespace()` **tidak ada lagi**. Routing jatuh ke `Router::__call()` → `RouteRegistrar::attribute('namespace', '')` — karena nilainya `''` menjadi **no-op**, lalu `RouteRegistrar::group()` meneruskan sebagai group attribute. Tidak break (terverifikasi: `php artisan route:list` tetap mendaftarkan `{tenant}/...`), tapi **niat "controller namespace" sudah hilang**. | `vendor/laravel/framework/src/Illuminate/Routing/RouteRegistrar.php:71-86` dan `:222-227` — **verifikasi lokal, BUKAN dokumentasi stancl** |

---

## 8. Ringkasan untuk keputusan rekonfigurasi

Yang **sudah sesuai** standar resmi di opti-works:

- `DatabaseTenancyBootstrapper` dimatikan (`config/tenancy.php:41`).
- Job `CreateDatabase`/`MigrateDatabase`/`DeleteDatabase` dilepas dari listener `TenantCreated`/`TenantDeleted` (`app/Providers/TenancyServiceProvider.php:35,41`).
- `BelongsToTenant` hanya di primary models; `User` global.
- Unique index + validation rule sudah tenant-scoped.
- Path identification dengan prefix `/{tenant}` dan `tenant` sebagai parameter pertama.

Yang **perlu diputuskan** saat rekonfigurasi (implikasi, bukan hasil riset):

1. `QueueTenancyBootstrapper` — nyalakan atau tidak, dengan alasan yang benar.
2. `filesystem.asset_helper_tenancy` + `ViteBundler` feature — relevan untuk Inertia.
3. Custom `App\Models\Tenant` — apakah perlu `getCustomColumns()` + `HasScopedValidationRules` (cabang sebagai kolom dedicated vs `data` JSON).
4. `domains` table & `domain_model` — hapus atau biarkan tidak terpakai saat rekonfigurasi.
5. `database/migrations/tenant` — kosongkan agar tidak membingungkan.

---

## 9. Belum terverifikasi / perlu konfirmasi

| Item | Status | Alasan |
| --- | --- | --- |
| `RefreshDatabase` + `:memory:` **aman** di single-DB mode | **Kesimpulan mekanis, bukan statement docs** | Docs hanya menyatakan kontra-indikasi untuk "multi-database tenancy & the automatic mode"; tidak ada affirmation eksplisit untuk single-DB. Perlu diuji dengan satu feature test sebelum diandalkan. |
| `config:cache` / `route:cache` | **Tidak ada guidance resmi sama sekali** | Nol hasil di docs repo + issue tracker resmi. Harus diverifikasi lokal via `route:cache` + smoke test. |
| Nomor Laravel minimum yang benar-benar didukung | Docs vs composer.json beda | Docs `/docs/v3/installation` bilang "Laravel 9.0 or higher"; `composer.json` bilang `^10 \| ^11 \| ^12 \| ^13`. Yang menang composer.json, tapi docs installation belum di-refresh. |
| Halaman `/docs/v3/testing` | **Resmi berstatus "TODO: Review"** | Jangan dipakai sebagai acuan coverage testing komprehensif. Ada halaman sponsor-only "Frictionless Testing Setup" (https://sponsors.tenancyforlaravel.com/frictionless-testing-setup) yang **tidak diakses** (berbayar). |
| Docs vs source version drift | Docs `v3` = branch `master` `stancl/tenancy-docs` | Dokumen bisa lebih baru dari tag `v3.10.1` (terpasang 2026-08-05). Untuk klaim version-specific yang kritis, cross-check ke `vendor/stancl/tenancy/src/`. |
| Apakah `tenants:delete` pernah ada di v2 lalu dihapus | Tidak diverifikasi | Yang terverifikasi: **tidak ada** di v3.10.1. |
| Dampak `QueueTenancyBootstrapper` nonaktif pada job Reverb/broadcast | Tidak ada di docs | murni implikasi arsitektur; perlu diuji. |

---

## 10. Catatan wiki-link

- Relasinya: `D:\SecondBrain\01 Projects\opti-works\Decisions\tenancy-reconfig.md` (belum dibuat — buat saat keputusan diambil).
