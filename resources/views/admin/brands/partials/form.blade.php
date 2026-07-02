@php
    $isActive = old('is_active', $brand->exists ? $brand->is_active : true);
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
                    value="{{ old('name', $brand->name) }}"
                    data-slug-source
                    data-slug-target="#slug"
                    data-slug-url="{{ route('admin.brands.suggest-slug') }}"
                    data-slug-ignore="{{ $brand->exists ? $brand->id : '' }}"
                    required
                >
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="slug">Slug</label>
                <input class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" type="text" value="{{ old('slug', $brand->slug) }}" placeholder="Se sugiere desde el nombre" data-slug-manual>
                <div class="form-text">Puedes ajustarlo antes de guardar.</div>
                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="sort_order">Orden</label>
                <input class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', $brand->sort_order ?? 0) }}">
                @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked((bool) $isActive)>
                    <label class="form-check-label" for="is_active">Marca activa</label>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-media-upload-panel">
            <label class="form-label" for="logo">Logo de marca</label>
            <div
                class="image-cropper"
                data-image-cropper
                data-cropper-width="960"
                data-cropper-height="540"
                data-cropper-aspect="1.777777"
                @if($brand->logo_source) data-cropper-preview-url="{{ $brand->logo_source }}" @endif
            >
                <input class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" type="file" accept="image/*" data-cropper-input>
                <input name="cropped_logo" type="hidden" data-cropper-output>
                @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('cropped_logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                @if($brand->logo_source)
                    <div class="small text-muted mt-2" data-current-media>
                        Hay un logo cargado. Puedes reemplazarlo subiendo uno nuevo.
                    </div>
                    <input name="remove_logo" type="hidden" value="0" data-cropper-remove-input>
                    <button class="btn btn-sm btn-outline-danger mt-2" type="button" data-cropper-remove>
                        <i class="bi bi-x-lg me-1"></i>Quitar logo
                    </button>
                @else
                    <input name="remove_logo" type="hidden" value="0" data-cropper-remove-input>
                    <div class="small text-muted mt-2">Si no subes un logo, la marca se mostrara como texto.</div>
                @endif

                <div class="cropper-frame cropper-frame-brand mt-3">
                    <div class="cropper-placeholder" data-cropper-placeholder>
                        <i class="bi bi-image" aria-hidden="true"></i>
                        <span>Sube una imagen para ver su vista previa</span>
                    </div>
                    <canvas data-cropper-canvas width="960" height="540"></canvas>
                </div>
                <label class="form-label small mt-2" for="logo_crop_zoom">Zoom</label>
                <input class="form-range" id="logo_crop_zoom" type="range" min="1" max="2" value="1" step="0.05" data-cropper-zoom>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-outline-secondary" href="{{ route('admin.brands.index') }}">Cancelar</a>
    <button class="btn btn-vn" type="submit">Guardar marca</button>
</div>
