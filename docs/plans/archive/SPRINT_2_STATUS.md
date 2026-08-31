# Sprint 2 - Estado

Fecha: 2026-07-01

Estado: Completado

## Fase 1: Base admin real

Estado: Completada

Implementado:
- Controladores admin para categorias, marcas y productos.
- Form Requests base para store/update.
- Rutas admin de catalogo cambiadas a rutas resource.
- Rutas puntuales de estado, publicacion e imagenes.
- Mensajes flash en layout admin.
- Vista base de marcas para que la ruta `admin.brands.index` exista.

Validaciones:
- `php artisan route:list --name=admin`
- `php artisan view:cache`
- `php artisan test`

Resultado de tests:

```txt
7 tests, 7 passed, 15 assertions
```

## Fase 2: CRUD real de categorias

Estado: Completada

Implementado:
- Listado admin de categorias con datos reales, conteo de productos, filtros por texto y estado.
- Formulario de creacion de categorias.
- Formulario de edicion de categorias.
- Slug sugerido desde el nombre, editable por el administrador y con sufijo unico cuando ya existe.
- Validaciones de categoria con Form Requests.
- Activacion y desactivacion de categorias.
- Productos de categorias inactivas ocultos del home, catalogo, busqueda, relacionados y detalle por slug.
- Imagen local de categoria guardada en disco `public` mediante cropper reutilizable.
- Opcion para quitar la imagen actual y dejar la categoria sin imagen.
- Selector visual de iconos con conjunto permitido.
- Eliminacion de categorias sin productos asociados.
- Bloqueo de eliminacion cuando la categoria tiene productos asociados.
- Tests feature para listado, creacion, actualizacion, slug sugerido, imagen local, iconos, cambio de estado, eliminacion y visibilidad publica.

Validaciones:
- `php -l` en controladores, requests y tests feature.
- `php artisan route:list --name=admin.categories`
- `php artisan view:cache`
- `php artisan test`
- `php artisan migrate`
- `node --check public/js/app.js`
- `npm.cmd run build`

Resultado de tests:

```txt
31 tests, 31 passed, 113 assertions
```

## Fase 3: CRUD real de marcas

Estado: Completada

Implementado:
- Listado admin de marcas con datos reales, conteo de productos, filtros por texto y estado.
- Formulario de creacion de marcas.
- Formulario de edicion de marcas.
- Slug sugerido desde el nombre, editable por el administrador y con sufijo unico cuando ya existe.
- Validaciones de marca con Form Requests.
- Activacion y desactivacion de marcas.
- Productos de marcas inactivas ocultos del home, catalogo, filtros, busqueda y detalle por slug.
- Logo local de marca guardado en disco `public` mediante cropper reutilizable.
- Opcion para quitar el logo actual y dejar la marca sin imagen.
- Eliminacion de marcas sin productos asociados.
- Bloqueo de eliminacion cuando la marca tiene productos asociados.
- Home preparado para mostrar logo de marca cuando exista y nombre como fallback.

Validaciones:
- `php -l` en modelo, controlador, requests y test feature de marcas.
- `php artisan route:list --name=admin.brands`
- `php artisan view:cache`
- `php artisan migrate`
- `node --check public/js/app.js`
- `npm.cmd run build`
- `php artisan test`

Resultado de tests:

```txt
31 tests, 31 passed, 113 assertions
```

## Fase 4: CRUD real de productos

Estado: Completada

Implementado:
- Listado admin de productos con datos reales, imagen principal, categoria, marca, precio, stock, estado y publicacion.
- Filtros admin por texto/SKU, categoria, marca, estado y publicacion.
- Formulario de creacion de productos.
- Formulario de edicion de productos.
- Slug sugerido desde el nombre, editable por el administrador y con sufijo unico cuando ya existe.
- Validaciones de producto con Form Requests.
- Normalizacion de marca opcional, precio comparativo opcional, booleans, stock y fecha de publicacion.
- Activacion y desactivacion de productos.
- Publicacion y despublicacion con `published_at`.
- Eliminacion de productos.
- Producto creado desde admin visible en home, catalogo y detalle publico cuando esta activo, destacado y publicado.
- Tabla admin preparada para enlazar a la tienda publica por slug.
- Las rutas de imagenes de producto quedan preparadas para Fase 5.

Validaciones:
- `php -l` en modelo, controlador, requests y test feature de productos.
- `php artisan route:list --name=admin.products`
- `php artisan route:list --name=admin`
- `php artisan view:cache`
- `node --check public/js/app.js`
- `npm.cmd run build`
- `php artisan test`

Resultado de tests:

```txt
40 tests, 40 passed, 157 assertions
```

## Fase 5: Imagen principal y galeria de productos

Estado: Completada

Implementado:
- Imagen principal en el formulario de creacion de productos.
- Imagen principal editable en el formulario de edicion de productos.
- Opcion para quitar la imagen principal actual.
- Galeria de imagenes adicionales desde la edicion del producto.
- Imagenes guardadas en disco `public` con ruta local en `product_images.image_path`.
- Registro de imagenes en `product_images` con `url`, `alt_text`, `is_primary` y `sort_order`.
- Marcado de una imagen como principal, desmarcando las demas.
- Eliminacion de imagenes de galeria y borrado del archivo local.
- Proteccion para que una imagen no pueda gestionarse desde otro producto.
- Eliminacion de archivos locales al eliminar un producto.
- Cropper reutilizable aplicado a imagen principal y galeria.

Validaciones:
- `php artisan migrate`
- `php -l` en controlador, requests, modelo de imagen y test feature de productos.
- `php artisan route:list --name=admin.products.images`
- `php artisan view:cache`
- `node --check public/js/app.js`
- `npm.cmd run build`
- `php artisan test`

Resultado de tests:

```txt
45 tests, 45 passed, 182 assertions
```

## Fase 6: Validaciones, UX y pruebas finales

Estado: Completada

Implementado:
- Revision final de formularios admin con mensajes de error por campo y conservacion de valores enviados.
- Confirmacion de filtros y paginacion con `withQueryString()` en categorias, marcas y productos.
- Pruebas feature adicionales para filtros admin de categorias y marcas.
- Prueba de limpieza del archivo local al eliminar una categoria con imagen.
- Pruebas de visibilidad publica para productos inactivos, sin publicar o con publicacion futura.
- Prueba de uso de imagen default cuando el producto no tiene imagen principal.
- Auditoria de rutas admin reales para catalogo.

Validaciones:
- `php artisan migrate:status`
- `php artisan route:list --name=admin`
- `php artisan view:cache`
- `php -l` en archivos modificados.
- `node --check public/js/app.js`
- `npm.cmd run build`
- `git diff --check`
- `php artisan test`

Resultado de tests:

```txt
49 tests, 49 passed, 201 assertions
```

## Resultado final

Sprint 2 completado. El panel admin ya puede mantener categorias, marcas, productos, imagen principal y galeria con datos reales, manteniendo sincronizada la visibilidad del catalogo publico.

## Siguiente fase

Sprint 3: Stock e inventario.
