@php
    $isActive = old('is_active', $category->exists ? $category->is_active : true);
    $isFeatured = old('is_featured', $category->is_featured);
    $selectedIcon = old('icon_class', $category->icon_class ?: 'bi-grid');
@endphp

<div class="row g-4">
    <div class="col-lg-7">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Nombre <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span></label>
                <input
                    class="form-control @error('name') is-invalid @enderror"
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name', $category->name) }}"
                    data-slug-source
                    data-slug-target="#slug"
                    data-slug-url="{{ route('admin.categories.suggest-slug') }}"
                    data-slug-ignore="{{ $category->exists ? $category->id : '' }}"
                    required
                >
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="slug">Slug</label>
                <input class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" type="text" value="{{ old('slug', $category->slug) }}" placeholder="Se sugiere desde el nombre" data-slug-manual>
                <div class="form-text">Puedes ajustarlo antes de guardar.</div>
                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Descripcion</label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4">{{ old('description', $category->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label">Icono</label>
                <div class="icon-picker @error('icon_class') is-invalid @enderror">
                    @foreach($iconOptions as $icon => $label)
                        <label class="icon-option">
                            <input name="icon_class" type="radio" value="{{ $icon }}" @checked($selectedIcon === $icon)>
                            <span><i class="bi {{ $icon }}"></i>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('icon_class')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label" for="sort_order">Orden</label>
                <input class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', $category->sort_order ?? 0) }}">
                @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked((bool) $isActive)>
                    <label class="form-check-label" for="is_active">Categoria activa</label>
                </div>
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_featured" value="0">
                    <input class="form-check-input" id="is_featured" name="is_featured" type="checkbox" value="1" @checked((bool) $isFeatured)>
                    <label class="form-check-label" for="is_featured">Mostrar destacada</label>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-media-upload-panel">
            <label class="form-label" for="image">Imagen de categoria</label>
            <div
                class="image-cropper"
                data-image-cropper
                data-cropper-width="800"
                data-cropper-height="600"
                data-cropper-aspect="1.333333"
                @if($category->has_custom_image) data-cropper-preview-url="{{ $category->image_source }}" @endif
            >
                <input class="form-control @error('image') is-invalid @enderror" id="image" name="image" type="file" accept="image/*" data-cropper-input>
                <input name="cropped_image" type="hidden" data-cropper-output>
                @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('cropped_image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                @if($category->has_custom_image)
                    <div class="small text-muted mt-2" data-current-media>
                        Hay una imagen cargada. Puedes reemplazarla subiendo una nueva.
                    </div>
                    <input name="remove_image" type="hidden" value="0" data-cropper-remove-input>
                    <button class="btn btn-sm btn-outline-danger mt-2" type="button" data-cropper-remove>
                        <i class="bi bi-x-lg me-1"></i>Quitar imagen
                    </button>
                @else
                    <input name="remove_image" type="hidden" value="0" data-cropper-remove-input>
                    <div class="small text-muted mt-2">Si no subes una imagen, se mostrara la imagen default.</div>
                @endif

                <div class="cropper-frame cropper-frame-category mt-3">
                    <div class="cropper-placeholder" data-cropper-placeholder>
                        <i class="bi bi-image" aria-hidden="true"></i>
                        <span>Sube una imagen para ver su vista previa</span>
                    </div>
                    <canvas data-cropper-canvas width="800" height="600"></canvas>
                </div>
                <label class="form-label small mt-2" for="crop_zoom">Zoom</label>
                <input class="form-range" id="crop_zoom" type="range" min="1" max="2" value="1" step="0.05" data-cropper-zoom>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}">Cancelar</a>
    <button class="btn btn-vn" type="submit">Guardar categoria</button>
</div>
