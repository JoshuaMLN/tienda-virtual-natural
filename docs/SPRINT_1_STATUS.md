# Sprint 1 - Estado

Fecha: 2026-06-30

Estado: Cerrado al 100%

## Objetivo

Convertir el catalogo publico de VitaNatural de datos mock en vistas conectadas a base de datos.

## Implementado

- Modelos:
  - `App\Models\Category`
  - `App\Models\Brand`
  - `App\Models\Product`
  - `App\Models\ProductImage`
- Migraciones:
  - `categories`
  - `brands`
  - `products`
  - `product_images`
- Relaciones:
  - Categoria tiene muchos productos.
  - Marca tiene muchos productos.
  - Producto pertenece a categoria y marca.
  - Producto tiene muchas imagenes.
  - Producto tiene imagen principal.
- Seeders:
  - `CatalogSeeder`
  - 8 categorias.
  - 6 marcas.
  - 15 productos.
  - 16 imagenes.
- Controladores publicos:
  - `HomeController`
  - `CatalogController`
  - `ProductController`
- Rutas dinamicas:
  - `/`
  - `/catalogo`
  - `/producto/{product:slug}`
- Vistas conectadas a DB:
  - `shop.index`
  - `shop.catalog`
  - `shop.product-detail`
- Filtros iniciales:
  - busqueda por texto
  - categoria
  - marca
  - precio minimo
  - precio maximo
  - ordenamiento
- Tests:
  - home con datos destacados
  - catalogo paginado
  - filtro por categoria y busqueda
  - detalle por slug
  - slug inexistente devuelve 404

## Validaciones realizadas

```bash
php artisan migrate
php artisan db:seed
php artisan migrate:status
php artisan route:list
php artisan view:cache
php artisan test
```

Resultado de tests:

```txt
7 tests, 7 passed, 15 assertions
```

Conteo actual de datos:

```txt
categories=8
brands=6
products=15
images=16
```

## Decisiones de cierre

- Filtros validados en desktop y mobile.
- Se mantienen 15 productos semilla por el momento.
- El admin mock seguira como esta hasta Sprint 2, donde se conectara a datos reales.

## Resultado

Sprint 1 cerrado al 100%.
