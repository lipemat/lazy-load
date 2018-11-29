# Lazy Load WordPress Content Or Thumbnail Images Automatically

## Purpose of this fork
To support background images within content and generally keep this repository up to date with the latest improvements which 
rarely actually make in into the [offical WordPress plugin](https://wordpress.org/plugins/lazy-load/).


## Enhancements (non exhaustive)
1. Lazy load background images found in content
2. Lazy load avatars 

## Frequently Asked Questions

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
