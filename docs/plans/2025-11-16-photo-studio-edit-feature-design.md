# Photo Studio Edit Feature Design

**Date:** 2025-11-16
**Status:** Approved
**Author:** Design session with user

## Overview

Add the ability to edit existing Photo Studio generated images by using them as reference for new AI generations with user-provided modification prompts.

## User Flow

1. User views a generated image in the Photo Studio gallery
2. Clicks the "Edit" icon on the image card
3. A dedicated edit modal opens showing:
   - The original image (large preview)
   - Original metadata (product, prompt) as reference
   - Text field: "Describe what you want to change"
4. User enters their modification prompt (up to 600 characters)
5. Clicks "Generate Edited Version"
6. System generates a new image using the original image + new prompt
7. New image appears in gallery with "Edited version" badge
8. Edit relationship is tracked in the database

## Technical Design

### 1. Database Schema

**Migration:** Add parent tracking to `photo_studio_generations` table

```php
Schema::table('photo_studio_generations', function (Blueprint $table) {
    $table->foreignId('parent_generation_id')
        ->nullable()
        ->after('product_ai_job_id')
        ->constrained('photo_studio_generations')
        ->nullOnDelete();
});
```

**Model updates:** `PhotoStudioGeneration`

- Add `parent_generation_id` to `$fillable`
- Add relationships:
  - `parent()`: BelongsTo - references the original generation
  - `edits()`: HasMany - all versions edited from this image
  - `isEdit()`: Boolean helper
  - `editCount()`: Count of edits

**Design rationale:**
- Self-referencing foreign key allows edit chains (edit an edit)
- `nullOnDelete` ensures edited versions survive if original is deleted
- `nullable` because root generations have no parent

### 2. UI Components

**Gallery Card Enhancement:**
Add edit button next to download icon (positioned at `right-11 top-2`):

```html
<button
    type="button"
    @click="$wire.openEditModal({{ $entry['id'] }})"
    class="absolute right-11 top-2 inline-flex items-center rounded-full border border-white/70 bg-white/90 p-1 text-gray-600 shadow-sm ring-1 ring-black/10 transition hover:bg-white"
    title="Edit this image"
>
    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none">
        <path d="m13.5 6.5-7 7V17h3.5l7-7m-3.5-3.5 2-2a1.5 1.5 0 0 1 2.121 0l1.379 1.379a1.5 1.5 0 0 1 0 2.121l-2 2m-3.5-3.5 3.5 3.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</button>
```

**Edit Modal:**
New Alpine.js-powered modal with:
- Component state: `editModalOpen`, `editingGeneration`, `editPrompt`
- Layout: Similar to existing detail overlay
- Content:
  - Original image preview (large, centered)
  - Reference section showing original prompt and product
  - Textarea: "Describe what you want to change" (600 char limit)
  - Actions: Cancel and "Generate Edited Version" buttons

**Gallery Visual Indicators:**
- Badge on edited images: "Edited version" (purple theme)
- Detail overlay shows parent prompt when viewing edited images

### 3. Backend Logic

**Livewire Component (`PhotoStudio`):**

New properties:
```php
public bool $editModalOpen = false;
public ?array $editingGeneration = null;
public string $editPrompt = '';
```

New methods:

**`openEditModal(int $generationId)`**
- Validate team access
- Load generation record into `$editingGeneration`
- Reset `$editPrompt`
- Open modal

**`closeEditModal()`**
- Reset all edit properties
- Close modal

**`generateEditedImage()`**
- Validate: team access, prompt not empty, max 600 chars
- Fetch original generation for image URL
- Inherit from original:
  - `product_id`
  - Image URL as `imageInput`
  - Set `source_type = 'edited_image'`
  - Set `source_reference` to original URL
- Create `ProductAiJob` record
- Dispatch `GeneratePhotoStudioImage` with:
  - Original image URL as reference
  - User's new prompt
  - Parent generation ID
- Close modal and refresh gallery

**Gallery data enhancement:**
Update `refreshProductGallery()` to eager load:
```php
->with(['product:id,title,sku,brand', 'parent:id,prompt'])
```

Include in gallery array:
- `parent_id`
- `parent_prompt`

### 4. Job Updates

**`GeneratePhotoStudioImage` Job:**

Add parameter:
```php
public ?int $parentGenerationId = null
```

Update generation record creation:
```php
PhotoStudioGeneration::create([
    // ... existing fields ...
    'parent_generation_id' => $this->parentGenerationId,
]);
```

**No other changes needed:**
- Job already supports URL-based `imageInput`
- Job already handles fetching images from URLs
- Job already supports custom `source_type` and `source_reference`

### 5. Prompt Strategy

**Direct Generation Approach:**
- User provides complete new prompt describing desired changes
- No AI merging or vision model analysis of changes
- Original image URL is passed directly as reference
- Fast, predictable, gives user full control

**Alternative considered but rejected:**
Vision-assisted approach (AI analyzes original + modification request) was rejected because:
- Adds extra API call and latency
- Less predictable results
- User already knows what they want when editing

## Success Criteria

- [ ] Users can click edit icon on any generated image
- [ ] Edit modal opens with original image and prompt field
- [ ] Edited images inherit product association from original
- [ ] Edit relationship tracked with `parent_generation_id`
- [ ] Gallery displays "Edited version" badge
- [ ] Detail overlay shows original prompt for context
- [ ] All team scoping and permissions respected
- [ ] Edit history is preserved even if original is deleted

## Future Enhancements (Out of Scope)

- Edit chain visualization (tree view of all edits)
- Side-by-side comparison view
- Batch editing multiple images
- Edit templates for common modifications
- Undo/revert to previous version
