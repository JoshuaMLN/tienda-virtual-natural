# Sprint 2 - Roadmap

Fecha: 2026-06-30

Estado: Completado

## Objetivo

Convertir el panel admin de catalogo de vistas mock a gestion real de categorias, marcas, productos e imagenes conectadas a base de datos.

## Punto de partida

- Sprint 1 cerrado al 100%.
- El catalogo publico ya lee `Category`, `Brand`, `Product` y `ProductImage` desde DB.
- Las pantallas admin existen visualmente, pero usan datos mock y rutas `Route::view`.
- No se debe adelantar stock, pedidos, pagos, banners ni cuenta de cliente en este sprint.

## Alcance funcional

- CRUD de categorias.
- CRUD de marcas.
- CRUD de productos.
- Activar y desactivar categorias, marcas y productos.
- Publicar y despublicar productos usando `is_active` y `published_at`.
- Subida de imagen principal y galeria de productos.
- Validaciones con Form Requests.
- Listados admin con filtros basicos, paginacion y mensajes de confirmacion.
- Tests feature para los flujos principales.

## Fuera de alcance

- Movimientos de inventario y stock minimo. Eso corresponde al Sprint 3.
- Carrito y validacion de stock en compra. Eso corresponde al Sprint 4.
- Pedidos reales, pagos y Culqi. Eso corresponde a Sprints 5 y 7.
- Promociones, banners dinamicos y cupones. Eso corresponde al Sprint 8.
- Sistema completo de clientes, login publico y policies finales. Eso corresponde a Sprints 6 y 9.

## Decisiones recomendadas

- Usar controladores admin reales en `App\Http\Controllers\Admin`.
- Reemplazar las rutas admin mock por rutas `resource`.
- Mantener hard delete solo cuando no rompa relaciones; si una entidad tiene productos, preferir desactivar.
- Guardar imagenes subidas en `storage/app/public` y exponerlas con `storage:link`.
- Mantener el admin real para datos de catalogo; los modulos de pedidos, pagos, stock y banners siguen mock por ahora.
- Proteger el admin con una solucion minima cuando empecemos a escribir datos. Si la autenticacion completa se deja para Sprint 6, documentar la limitacion en este sprint.

## Fase 1: Base admin real

Objetivo: preparar la estructura backend para reemplazar mocks sin romper el layout actual.

Tareas:
- Crear controladores:
  - `Admin\CategoryController`
  - `Admin\BrandController`
  - `Admin\ProductController`
- Crear Form Requests:
  - `StoreCategoryRequest`
  - `UpdateCategoryRequest`
  - `StoreBrandRequest`
  - `UpdateBrandRequest`
  - `StoreProductRequest`
  - `UpdateProductRequest`
- Cambiar rutas admin de catalogo a `Route::resource`.
- Agregar rutas puntuales para activar/desactivar y manejar imagenes.
- Agregar mensajes flash de exito/error en layout admin si aun no existen.

Criterio de salida:
- `php artisan route:list` muestra rutas admin reales para categorias, marcas y productos.

## Fase 2: CRUD de categorias

Objetivo: administrar categorias reales usadas por el catalogo publico.

Tareas:
- Reemplazar datos mock en `admin.categories.index` por consulta real.
- Crear vistas o componentes para crear y editar categorias.
- Validar `name`, `slug`, `description`, `image_url`, `icon_class`, `is_active`, `is_featured`, `sort_order`.
- Generar slug sugerido desde nombre, manteniendo opcion editable.
- Mostrar conteo real de productos por categoria.
- Bloquear eliminacion si la categoria tiene productos; permitir desactivar.
- Al desactivar una categoria, sus productos dejan de mostrarse en home, catalogo, busqueda, relacionados y detalle por slug.
- La imagen de categoria se sube al sistema y se recorta con el cropper reutilizable.
- El icono se elige desde un selector visual de iconos permitidos.

Criterio de salida:
- Crear, editar, desactivar y listar categorias cambia lo que ve el catalogo publico.

## Fase 3: CRUD de marcas

Objetivo: administrar marcas reales y usarlas en filtros/productos.

Tareas:
- Crear pantalla admin de marcas, porque hoy no existe vista dedicada.
- Listar marcas con conteo de productos.
- Crear y editar `name`, `slug`, `logo_url`, `is_active`, `sort_order`.
- Validar slug unico y mantener ordenamiento.
- Definir comportamiento al eliminar: permitir si no tiene productos o desactivar si tiene productos.

Criterio de salida:
- Crear, editar, desactivar y listar marcas afecta filtros y productos del catalogo.

## Fase 4: CRUD de productos

Objetivo: administrar productos reales con sus relaciones principales.

Tareas:
- Reemplazar datos mock en `admin.products.index` por consulta real.
- Agregar filtros admin por texto, categoria, marca y estado.
- Crear vistas de crear/editar producto.
- Validar `category_id`, `brand_id`, `name`, `slug`, `sku`, descripciones, precio, precio comparativo, stock base, destacado, activo y fecha de publicacion.
- Mantener SKU y slug unicos.
- Mostrar imagen principal, categoria, marca, precio, stock y estado en la tabla.
- Permitir activar/desactivar productos sin eliminarlos.

Criterio de salida:
- Un producto creado o editado desde admin aparece correctamente en home, catalogo y detalle publico.

## Fase 5: Imagen principal y galeria

Objetivo: permitir que el admin gestione imagenes reales de productos.

Tareas:
- Subir imagen principal al crear/editar producto.
- Subir varias imagenes de galeria.
- Guardar registros en `product_images`.
- Permitir marcar una imagen como principal.
- Permitir eliminar imagenes de galeria.
- Validar tipo, peso y dimensiones razonables.
- Mantener `alt_text` y `sort_order`.

Criterio de salida:
- El detalle del producto y las tarjetas publicas usan la imagen principal subida desde admin.

## Fase 6: Validaciones, UX y pruebas

Objetivo: cerrar el sprint con confianza operativa.

Tareas:
- Validar errores de formularios en pantalla.
- Confirmar paginacion y filtros admin.
- Agregar tests feature de categorias, marcas, productos e imagen principal.
- Verificar que el catalogo publico no se rompe con productos inactivos o sin publicar.
- Ejecutar:
  - `php artisan route:list`
  - `php artisan view:cache`
  - `php artisan test`

Criterio de salida:
- Tests pasan y el panel admin puede mantener el catalogo real end to end.

## Rutas esperadas

- `admin.categories.index`
- `admin.categories.create`
- `admin.categories.store`
- `admin.categories.edit`
- `admin.categories.update`
- `admin.categories.destroy`
- `admin.brands.index`
- `admin.brands.create`
- `admin.brands.store`
- `admin.brands.edit`
- `admin.brands.update`
- `admin.brands.destroy`
- `admin.products.index`
- `admin.products.create`
- `admin.products.store`
- `admin.products.edit`
- `admin.products.update`
- `admin.products.destroy`
- `admin.products.images.store`
- `admin.products.images.destroy`
- `admin.products.images.primary`

## Criterios de cierre del Sprint 2

- Categorias administrables desde DB.
- Marcas administrables desde DB.
- Productos administrables desde DB.
- Imagen principal y galeria funcionales.
- Activacion/desactivacion reflejada en el catalogo publico.
- Form Requests cubren validaciones principales.
- Admin mock de catalogo reemplazado por datos reales.
- Pedidos, pagos, stock y banners siguen fuera de alcance.
- Tests y validaciones finales pasan.

## Orden recomendado de commits

1. `feat(admin): add catalog resource routes and controllers`
2. `feat(admin): manage categories from database`
3. `feat(admin): manage brands from database`
4. `feat(admin): manage products from database`
5. `feat(admin): support product image uploads`
6. `test(admin): cover catalog management flows`
