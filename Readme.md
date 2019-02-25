# Lazy Load WordPress Content Or Thumbnail Images Automatically

## Purpose of this fork
To support background images within content and generally keep this repository up to date with the latest improvements which 
rarely actually make in into the [offical WordPress plugin](https://wordpress.org/plugins/lazy-load/).


## Enhancements (non exhaustive)
1. Lazy load background images found in content
2. Lazy load avatars 
3. Exclude images using `data-lazy-disable` attributes
4. Allow data attributes through `kses`
5. Template tags for automatically rendering images with lazy load disabled

## Frequently Asked Questions

### How do I exclude images
Add a `lazy-load-disable` attribute to any image to exclude it. Attributes may be passed to common image functions like `get_the_post_thumbnail()` or within the markup of content.

```php
get_the_post_thumbnail($id, 'full', [ 'lazy-load-disable' => 'true' ] );
```

For background images, add the `lazy-load-disable` as part of the `style` attribute.

```html
<div style="lazy-load-disable;background: url( '../bg.png' );">Content</div>
```

Use one of the temlate tags to render an image excluded from lazy-loading.

```php
get_the_post_thumbnail_no_lazy_load( $id );
```
or
```php
wp_get_attachment_image_no_lazy_load( $id );
```

### How do I change the placeholder image

```php
add_filter( 'lazyload_images_placeholder_image', 'my_custom_lazyload_placeholder_image' );
function my_custom_lazyload_placeholder_image( $image ) {
	return 'http://url/to/image';
}
```

### How do I lazy load other images in my theme?

You can use the lazyload_images_add_placeholders helper function:


```php
if ( function_exists( 'lazyload_images_add_placeholders' ) )
	$content = lazyload_images_add_placeholders( $content );
```

Or, you can add an attribute called "data-lazy-src" with the source of the image URL and set the actual image URL to a transparent 1x1 pixel.

You can also use output buffering, though this isn't recommended:

```php
if ( function_exists( 'lazyload_images_add_placeholders' ) )
	ob_start( 'lazyload_images_add_placeholders' );
```

This will lazy load <em>all</em> your images.
