# 02: Make Division page read-only + sync-only

**Status:** pending
**Blocked by:** 01
**Depends on:** 01

## Why

User wants Division page to be read-only and sync-only, matching Department pattern.

## Spec

### 2.1 Controller changes

Remove CRUD methods from `DivisionController`:
- Remove `create()`, `store()`, `update()`, `destroy()`
- Keep only `index()`, `show()`, `sync()`
- Add `last_synced_at` to index response (reuse tenant column from ticket 01)
- Add Gate check in sync action

### 2.2 Route changes

In `routes/web.php`, change:
```php
Route::resource('divisions', DivisionController::class)->names('divisions');
```
To:
```php
Route::get('divisions', [DivisionController::class, 'index'])->name('divisions.index');
Route::get('divisions/{division}', [DivisionController::class, 'show'])->name('divisions.show');
Route::post('divisions/sync', [DivisionController::class, 'sync'])->name('divisions.sync');
```

### 2.3 Frontend changes

Rewrite `resources/js/pages/divisions/index.tsx`:
- Remove "Tambah Divisi" button
- Add sync button (like Department page)
- Show last synced time
- Keep table with: Kode, Nama, Departments count

### 2.4 Remove CRUD pages

Delete or keep as orphan (not routed):
- `resources/js/pages/divisions/create.tsx`
- `resources/js/pages/divisions/edit.tsx`
- Keep `show.tsx` for detail view

## Acceptance

- [ ] No create/edit/delete buttons/actions
- [ ] Sync button works
- [ ] Last synced displayed
- [ ] Page loads without errors
