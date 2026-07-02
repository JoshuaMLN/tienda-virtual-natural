@extends('layouts.admin')

@section('title', 'Editar producto | VitaNatural Admin')
@section('adminActive', 'products')

@section('content')
@php
    $galleryImages = $product->images->where('is_primary', false);
    $hasPrimaryImage = (bool) $product->primaryImage;
@endphp

<div class="d-flex justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="h3 fw-black mb-1">Editar producto</h1>
        <p class="text-muted mb-0">{{ $product->name }}</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.products.index') }}"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<div class="admin-card p-4">
    <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.products.partials.form', [
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
            'showImagesSection' => false,
        ])
    </form>
</div>

<div class="admin-card p-4 mt-4">
    <div class="d-flex justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h2 class="h5 fw-black mb-1">Imagenes del producto</h2>
            <p class="text-muted mb-0">Gestiona la imagen principal y las imagenes adicionales del detalle.</p>
        </div>
    </div>

    <section class="admin-form-section mb-4">
        <div class="admin-form-section-header">
            <h3 class="h6 fw-black mb-1">Carga de imagenes</h3>
            <p class="text-muted mb-0">La imagen principal se mantiene separada de las imagenes adicionales.</p>
        </div>

        <div class="row g-4">
            <div class="col-xl-5">
                <form method="POST" action="{{ route('admin.products.main-image.update', $product) }}" enctype="multipart/form-data" class="admin-media-upload-panel admin-media-upload-panel-primary">
                    @csrf
                    @method('PATCH')
                    <div
                        class="image-cropper"
                        data-image-cropper
                        data-cropper-width="900"
                        data-cropper-height="900"
                        data-cropper-aspect="1"
                        @if($hasPrimaryImage) data-cropper-preview-url="{{ $product->primaryImage->url }}" @endif
                    >
                        <label class="form-label" for="main_image">Subir imagen principal</label>
                        <input class="form-control @error('main_image') is-invalid @enderror" id="main_image" name="main_image" type="file" accept="image/*" data-cropper-input>
                        <input name="cropped_main_image" type="hidden" data-cropper-output>
                        @error('main_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @error('cropped_main_image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                        @if($hasPrimaryImage)
                            <div class="small text-muted mt-2" data-current-media>
                                Hay una imagen principal cargada. Puedes reemplazarla subiendo una nueva.
                            </div>
                            <input name="remove_main_image" type="hidden" value="0" data-cropper-remove-input>
                        @else
                            <input name="remove_main_image" type="hidden" value="0" data-cropper-remove-input>
                            <div class="small text-muted mt-2">Si no subes una imagen principal, se mostrara la imagen default.</div>
                        @endif

                        <div class="cropper-frame cropper-frame-square mt-3">
                            <div class="cropper-placeholder" data-cropper-placeholder>
                                <i class="bi bi-image" aria-hidden="true"></i>
                                <span>Sube una imagen para ver su vista previa</span>
                            </div>
                            <canvas data-cropper-canvas width="900" height="900"></canvas>
                        </div>
                        <label class="form-label small mt-2" for="main_crop_zoom">Zoom</label>
                        <input class="form-range" id="main_crop_zoom" type="range" min="1" max="2" value="1" step="0.05" data-cropper-zoom>

                        <div class="d-flex justify-content-end mt-3">
                            <button class="btn btn-vn" type="submit"><i class="bi bi-upload me-1"></i>Guardar principal</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-xl-7">
                <form method="POST" action="{{ route('admin.products.images.store', $product) }}" enctype="multipart/form-data" class="admin-media-upload-panel">
                    @csrf
                    <div
                        class="image-cropper"
                        data-image-cropper
                        data-cropper-width="900"
                        data-cropper-height="900"
                        data-cropper-aspect="1"
                    >
                        <div class="row g-3 align-items-start">
                            <div class="col-lg-5">
                                <label class="form-label" for="gallery_image">Subir imagen adicional</label>
                                <input class="form-control @error('image') is-invalid @enderror" id="gallery_image" name="image" type="file" accept="image/*" data-cropper-input>
                                <input name="cropped_image" type="hidden" data-cropper-output>
                                @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @error('cropped_image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                                <label class="form-label mt-3" for="alt_text">Texto alternativo</label>
                                <input class="form-control @error('alt_text') is-invalid @enderror" id="alt_text" name="alt_text" type="text" value="{{ old('alt_text', $product->name) }}">
                                @error('alt_text')<div class="invalid-feedback">{{ $message }}</div>@enderror

                                <label class="form-label mt-3" for="sort_order">Orden</label>
                                <input class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', $product->images->max('sort_order') + 1) }}">
                                @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-lg-7">
                                <div class="cropper-frame cropper-frame-square">
                                    <div class="cropper-placeholder" data-cropper-placeholder>
                                        <i class="bi bi-image" aria-hidden="true"></i>
                                        <span>Sube una imagen para ver su vista previa</span>
                                    </div>
                                    <canvas data-cropper-canvas width="900" height="900"></canvas>
                                </div>
                                <label class="form-label small mt-2" for="gallery_crop_zoom">Zoom</label>
                                <input class="form-range" id="gallery_crop_zoom" type="range" min="1" max="2" value="1" step="0.05" data-cropper-zoom>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button class="btn btn-vn" type="submit"><i class="bi bi-upload me-1"></i>Agregar adicional</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="admin-form-section">
        <div class="admin-form-section-header">
            <h3 class="h6 fw-black mb-1">Galeria de imagenes</h3>
            <p class="text-muted mb-0">Primero se muestra la imagen principal; luego, las imagenes adicionales.</p>
        </div>

        <div class="admin-gallery-grid">
            <div class="admin-gallery-card border rounded-2 p-3">
                <button
                    class="admin-gallery-preview rounded-2 border mb-3"
                    type="button"
                    data-image-preview
                    data-image-url="{{ $product->main_image_url }}"
                    data-image-alt="{{ $product->name }}"
                    aria-label="Ampliar imagen principal de {{ $product->name }}"
                >
                    <span style="background-image: url('{{ $product->main_image_url }}')"></span>
                </button>
                <div class="d-flex justify-content-between gap-2 mb-2">
                    <strong class="small text-truncate">Imagen principal</strong>
                    <span class="badge text-bg-success">{{ $hasPrimaryImage ? 'Subido' : 'Default' }}</span>
                </div>
                <div class="small text-muted mb-3">{{ $hasPrimaryImage ? 'Foto principal del producto.' : 'No se ha subido una imagen principal hasta el momento.' }}</div>
                @if($hasPrimaryImage)
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('admin.products.main-image.update', $product) }}" onsubmit="return confirm('Deseas eliminar la imagen principal?');">
                            @csrf
                            @method('PATCH')
                            <input name="remove_main_image" type="hidden" value="1">
                            <button class="btn btn-sm btn-light text-danger" type="submit"><i class="bi bi-trash me-1"></i>Eliminar</button>
                        </form>
                    </div>
                @endif
            </div>

            @foreach($galleryImages as $image)
            <div class="admin-gallery-card border rounded-2 p-3">
                    <button
                        class="admin-gallery-preview rounded-2 border mb-3"
                        type="button"
                        data-image-preview
                        data-image-url="{{ $image->url }}"
                        data-image-alt="{{ $image->alt_text ?: $product->name }}"
                        aria-label="Ampliar {{ $image->alt_text ?: $product->name }}"
                    >
                        <span style="background-image: url('{{ $image->url }}')"></span>
                    </button>
                    <div class="d-flex justify-content-between gap-2 mb-2">
                        <strong class="small text-truncate">{{ $image->alt_text ?: $product->name }}</strong>
                    </div>
                    <div class="small text-muted mb-3">Orden: {{ $image->sort_order }}</div>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('admin.products.images.destroy', [$product, $image]) }}" onsubmit="return confirm('Deseas eliminar esta imagen?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-light text-danger" type="submit"><i class="bi bi-trash me-1"></i>Eliminar</button>
                        </form>
                    </div>
            </div>
            @endforeach

            @if($galleryImages->isEmpty())
                <div class="admin-gallery-empty">
                    <div class="alert alert-light border mb-0">Este producto aun no tiene imagenes adicionales.</div>
                </div>
            @endif
        </div>
    </section>
</div>

<div class="modal fade" id="adminImagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6 mb-0">Vista previa</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-center">
                <img class="admin-image-preview-modal img-fluid rounded-2" src="" alt="" data-image-preview-modal-image>
            </div>
        </div>
    </div>
</div>
@endsection
