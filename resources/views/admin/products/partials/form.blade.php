@php
    $isActive = old('is_active', $product->exists ? $product->is_active : true);
    $isFeatured = old('is_featured', $product->is_featured);
    $publishedAt = old('published_at', $product->published_at?->format('Y-m-d\TH:i'));
    $showImagesSection = $showImagesSection ?? true;
    $hasAnyErrors = $errors->any();
    $sectionHasErrors = fn (array $fields) => collect($fields)->contains(fn (string $field) => $errors->has($field));
    $mainSectionHasErrors = $sectionHasErrors(['name', 'slug', 'sku', 'short_description', 'category_id', 'brand_id']);
    $salesSectionHasErrors = $sectionHasErrors(['price', 'compare_at_price', 'stock', 'published_at', 'is_active', 'is_featured']);
    $contentSectionHasErrors = $sectionHasErrors(['description', 'benefits', 'ingredients', 'usage_instructions']);
    $mainSectionOpen = ! $hasAnyErrors || $mainSectionHasErrors;
    $salesSectionOpen = ! $hasAnyErrors || $salesSectionHasErrors;
    $contentSectionOpen = ! $hasAnyErrors || $contentSectionHasErrors;
@endphp

<div class="d-flex align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h2 class="h5 fw-black mb-1">Informacion del producto</h2>
        <p class="text-muted mb-0">Completa los datos necesarios para vender y publicar este producto.</p>
    </div>
    <div class="small text-muted"><span class="required-mark" aria-hidden="true">*</span> Campo obligatorio</div>
</div>

<div class="d-flex flex-column gap-3">
    <section class="admin-form-section admin-form-section-collapsible" data-admin-section data-section-open="{{ $mainSectionOpen ? 'true' : 'false' }}">
        <div class="admin-form-section-header">
            <div class="admin-form-section-heading">
                <h3 class="h6 fw-black mb-1">Datos principales</h3>
                <p class="text-muted mb-0">Identificacion basica para catalogo, inventario y busqueda.</p>
            </div>
            <button class="admin-section-toggle" type="button" aria-expanded="{{ $mainSectionOpen ? 'true' : 'false' }}" aria-controls="product-main-section" data-section-toggle>
                <i class="bi bi-chevron-up" aria-hidden="true"></i>
                <span class="visually-hidden">Mostrar u ocultar Datos principales</span>
            </button>
        </div>

        <div class="admin-form-section-body" id="product-main-section" data-section-body @if(! $mainSectionOpen) hidden @endif>
        <div class="row g-3">
            <div class="col-lg-6">
                <label class="form-label" for="name">Nombre <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span></label>
                <input
                    class="form-control @error('name') is-invalid @enderror"
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name', $product->name) }}"
                    data-slug-source
                    data-slug-target="#slug"
                    data-slug-url="{{ route('admin.products.suggest-slug') }}"
                    data-slug-ignore="{{ $product->exists ? $product->id : '' }}"
                    required
                >
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-lg-3 col-md-6">
                <label class="form-label" for="sku">SKU <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span></label>
                <input class="form-control @error('sku') is-invalid @enderror" id="sku" name="sku" type="text" value="{{ old('sku', $product->sku) }}" required>
                @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-lg-3 col-md-6">
                <label class="form-label" for="short_description">Presentacion</label>
                <input class="form-control @error('short_description') is-invalid @enderror" id="short_description" name="short_description" type="text" value="{{ old('short_description', $product->short_description) }}" placeholder="120 capsulas">
                @error('short_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-lg-4 col-md-6">
                <label class="form-label" for="category_id">Categoria <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span></label>
                <select class="form-select @error('category_id') is-invalid @enderror" id="category_id" name="category_id" required>
                    <option value="">Selecciona una categoria</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>
                            {{ $category->name }}{{ $category->is_active ? '' : ' (inactiva)' }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-lg-4 col-md-6">
                <label class="form-label" for="brand_id">Marca</label>
                <select class="form-select @error('brand_id') is-invalid @enderror" id="brand_id" name="brand_id">
                    <option value="">Sin marca</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected((string) old('brand_id', $product->brand_id) === (string) $brand->id)>
                            {{ $brand->name }}{{ $brand->is_active ? '' : ' (inactiva)' }}
                        </option>
                    @endforeach
                </select>
                @error('brand_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-lg-4">
                <label class="form-label" for="slug">Slug</label>
                <input class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" type="text" value="{{ old('slug', $product->slug) }}" placeholder="Se sugiere desde el nombre" data-slug-manual>
                <div class="form-text">Puedes ajustarlo antes de guardar.</div>
                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        </div>
    </section>

    <section class="admin-form-section admin-form-section-emphasis admin-form-section-collapsible" data-admin-section data-section-open="{{ $salesSectionOpen ? 'true' : 'false' }}">
        <div class="admin-form-section-header">
            <div class="admin-form-section-heading">
                <h3 class="h6 fw-black mb-1">Venta y visibilidad</h3>
                <p class="text-muted mb-0">Campos operativos que afectan precio, inventario y aparicion publica.</p>
            </div>
            <button class="admin-section-toggle" type="button" aria-expanded="{{ $salesSectionOpen ? 'true' : 'false' }}" aria-controls="product-sales-section" data-section-toggle>
                <i class="bi bi-chevron-up" aria-hidden="true"></i>
                <span class="visually-hidden">Mostrar u ocultar Venta y visibilidad</span>
            </button>
        </div>

        <div class="admin-form-section-body" id="product-sales-section" data-section-body @if(! $salesSectionOpen) hidden @endif>
        <div class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label" for="price">Precio <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span></label>
                <input class="form-control @error('price') is-invalid @enderror" id="price" name="price" type="number" min="0" step="0.01" value="{{ old('price', $product->price) }}" required>
                @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-lg-3 col-md-6">
                <label class="form-label" for="compare_at_price">Precio antes</label>
                <input class="form-control @error('compare_at_price') is-invalid @enderror" id="compare_at_price" name="compare_at_price" type="number" min="0" step="0.01" value="{{ old('compare_at_price', $product->compare_at_price) }}">
                @error('compare_at_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-lg-3 col-md-6">
                @if($product->exists)
                    <label class="form-label d-flex align-items-center gap-1" for="stock_readonly">
                        Stock actual
                        <i
                            class="bi bi-question-circle text-muted"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            data-bs-title="Para modificar el stock registra un movimiento desde la pantalla de stock."
                            aria-label="Para modificar el stock registra un movimiento desde la pantalla de stock."
                        ></i>
                    </label>
                    <input class="form-control bg-body-secondary text-muted" id="stock_readonly" type="number" value="{{ $product->stock ?? 0 }}" readonly>
                @else
                    <label class="form-label" for="stock">Stock base</label>
                    <input class="form-control @error('stock') is-invalid @enderror" id="stock" name="stock" type="number" min="0" value="{{ old('stock', $product->stock ?? 0) }}">
                    @error('stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @endif
            </div>

            <div class="col-lg-3 col-md-6">
                <label class="form-label" for="published_at">Fecha de Publicación</label>
                <input class="form-control @error('published_at') is-invalid @enderror" id="published_at" name="published_at" type="datetime-local" value="{{ $publishedAt }}">
                @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>


            <div class="col-lg-6">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked((bool) $isActive)>
                    <label class="form-check-label fw-bold" for="is_active">Producto activo</label>
                    <div class="form-text">Si esta apagado, no aparece en el catalogo aunque tenga fecha de publicacion.</div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_featured" value="0">
                    <input class="form-check-input" id="is_featured" name="is_featured" type="checkbox" value="1" @checked((bool) $isFeatured)>
                    <label class="form-check-label fw-bold" for="is_featured">Mostrar como destacado</label>
                    <div class="form-text">Usalo para productos que deben tener mayor exposicion en tienda.</div>
                </div>
            </div>

            @if($product->exists && $product->visibility_status === 'oculto')
                <div class="col-12">
                    <div class="alert alert-warning d-flex align-items-start gap-2 mb-0 py-2 px-3 small" role="alert">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1" aria-hidden="true"></i>
                        <span>
                            <strong>Producto no visible en tienda.</strong><br>
                            {{ $product->visibility_tooltip }}
                        </span>
                    </div>
                </div>
            @endif
        </div>
        </div>
    </section>

    <section class="admin-form-section admin-form-section-collapsible" data-admin-section data-section-open="{{ $contentSectionOpen ? 'true' : 'false' }}">
        <div class="admin-form-section-header">
            <div class="admin-form-section-heading">
                <h3 class="h6 fw-black mb-1">Contenido del producto</h3>
                <p class="text-muted mb-0">Informacion que se muestra en la ficha y ayuda a decidir la compra.</p>
            </div>
            <button class="admin-section-toggle" type="button" aria-expanded="{{ $contentSectionOpen ? 'true' : 'false' }}" aria-controls="product-content-section" data-section-toggle>
                <i class="bi bi-chevron-up" aria-hidden="true"></i>
                <span class="visually-hidden">Mostrar u ocultar Contenido del producto</span>
            </button>
        </div>

        <div class="admin-form-section-body" id="product-content-section" data-section-body @if(! $contentSectionOpen) hidden @endif>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="description">Descripcion</label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4">{{ old('description', $product->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-lg-4">
                <label class="form-label" for="benefits">Beneficios</label>
                <textarea class="form-control @error('benefits') is-invalid @enderror" id="benefits" name="benefits" rows="5">{{ old('benefits', $product->benefits) }}</textarea>
                @error('benefits')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-lg-4">
                <label class="form-label" for="ingredients">Ingredientes</label>
                <textarea class="form-control @error('ingredients') is-invalid @enderror" id="ingredients" name="ingredients" rows="5">{{ old('ingredients', $product->ingredients) }}</textarea>
                @error('ingredients')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-lg-4">
                <label class="form-label" for="usage_instructions">Modo de uso</label>
                <textarea class="form-control @error('usage_instructions') is-invalid @enderror" id="usage_instructions" name="usage_instructions" rows="5">{{ old('usage_instructions', $product->usage_instructions) }}</textarea>
                @error('usage_instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        </div>
    </section>

    @if($showImagesSection)
    <section class="admin-form-section">
        <div class="admin-form-section-header">
            <h3 class="h6 fw-black mb-1">Imagenes del producto</h3>
            <p class="text-muted mb-0">Sube la imagen principal. Podras agregar imagenes adicionales despues de guardar.</p>
        </div>

        <div
            class="image-cropper"
            data-image-cropper
            data-cropper-width="900"
            data-cropper-height="900"
            data-cropper-aspect="1"
            @if($product->primaryImage) data-cropper-preview-url="{{ $product->primaryImage->url }}" @endif
        >
            <div class="row g-3 align-items-start">
                <div class="col-lg-4">
                    <label class="form-label" for="main_image">Subir imagen principal</label>
                    <input class="form-control @error('main_image') is-invalid @enderror" id="main_image" name="main_image" type="file" accept="image/*" data-cropper-input>
                    <input name="cropped_main_image" type="hidden" data-cropper-output>
                    @error('main_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @error('cropped_main_image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    @if($product->primaryImage)
                        <div class="mt-3" data-current-media>
                            <span class="small fw-bold d-block mb-2">Imagen actual</span>
                            <img class="img-fluid rounded-2 border" src="{{ $product->primaryImage->url }}" alt="{{ $product->primaryImage->alt_text ?: $product->name }}">
                            <input name="remove_main_image" type="hidden" value="0" data-cropper-remove-input>
                            <button class="btn btn-sm btn-outline-danger mt-2" type="button" data-cropper-remove>
                                <i class="bi bi-x-lg me-1"></i>Quitar imagen
                            </button>
                        </div>
                    @else
                        <input name="remove_main_image" type="hidden" value="0" data-cropper-remove-input>
                    @endif
                </div>

                <div class="col-lg-8">
                    <div class="cropper-frame cropper-frame-square mt-3">
                        <div class="cropper-placeholder" data-cropper-placeholder>
                            <i class="bi bi-image" aria-hidden="true"></i>
                            <span>Sube una imagen para ver su vista previa</span>
                        </div>
                        <canvas data-cropper-canvas width="900" height="900"></canvas>
                    </div>
                    <label class="form-label small mt-2" for="main_crop_zoom">Zoom</label>
                    <input class="form-range" id="main_crop_zoom" type="range" min="1" max="2" value="1" step="0.05" data-cropper-zoom>
                </div>
            </div>
        </div>
    </section>
    @endif
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-outline-secondary" href="{{ route('admin.products.index') }}">Cancelar</a>
    <button class="btn btn-vn" type="submit">Guardar producto</button>
</div>
