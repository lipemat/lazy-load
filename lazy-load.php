<?php
/**
 * Plugin Name: Lazy Load
 * Description: Lazy load images to improve page load times. Uses jQuery.sonar to only load an image when it's visible in the viewport.
 * Version: 0.7.3
 * Text Domain: lazy-load
 * Author: Automattic
 * Contributors: Mat Lipe
 *
 * Code by the WordPress.com VIP team, TechCrunch 2011 Redesign team, Jake Goldman, and Mat Lipe.
 * Uses jQuery.sonar by Dave Artz (AOL): http://www.artzstudio.com/files/jquery-boston-2010/jquery.sonar/
 *
 * License: GPL2
 */

if ( ! class_exists( 'LazyLoad_Images' ) ) :

class LazyLoad_Images {

	const version = '0.7.2';
	protected static $enabled = true;
	protected static $background_support = true;

	static function init() {
		if ( is_admin() )
			return;

		if ( ! apply_filters( 'lazyload_is_enabled', true ) ) {
			self::$enabled = false;
			return;
		}

		if ( ! apply_filters( 'lazyload_support_background_images', true ) ) {
			self::$background_support = false;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'add_scripts' ) );
		add_action( 'wp_head', array( __CLASS__, 'setup_filters' ), 9999 ); // we don't really want to modify anything in <head> since it's mostly all metadata, e.g. OG tags
	}

	public static function setup_filters() {
		add_filter( 'the_content', array( __CLASS__, 'add_image_placeholders' ), 99 ); // run this later, so other content filters have run, including image_add_wh on WP.com
		if ( self::$background_support ) {
			add_filter( 'the_content', [ __CLASS__, 'add_background_placeholders' ], 99 );
		}
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'add_image_placeholders' ), 11 );
		add_filter( 'get_avatar', array( __CLASS__, 'add_image_placeholders' ), 11 );

		add_filter( 'wp_kses_allowed_html', array( __CLASS__, 'allow_data_attributes' ) );

	}


	/**
	 * Explicitly allow the required data attributes through kses to handle
	 * caches where the content is put through kses before outputted.
	 *
	 * @param array $allowed
	 *
	 * @author Mat Lipe
	 *
	 * @since 0.7.2
	 *
	 * @static
	 *
	 * @return mixed
	 */
	public static function allow_data_attributes( $allowed ) {
		$allowed['img']['data-lazy-disable'] = [];
		$allowed['img']['data-lazy-src']     = [];

		return $allowed;
	}

	static function add_scripts() {
		wp_enqueue_script( 'wpcom-lazy-load-images',  self::get_url( 'js/lazy-load.js' ), array( 'jquery', 'jquery-sonar' ), self::version, true );
		wp_enqueue_script( 'jquery-sonar', self::get_url( 'js/jquery.sonar.min.js' ), array( 'jquery' ), self::version, true );

		/**
		 * @since 0.7.3
		 */
		wp_localize_script( 'wpcom-lazy-load-images', 'WPComLazyLoadConfig', apply_filters( 'wpcom-lazy-load-images/config', array(
			'distance'   => 200,
			'distanceBG' => 300,
		) ) );
	}


	protected static function is_allowed_area() {
		if ( ! self::is_enabled() ) {
			return false;
		}

		// Don't lazyload for feeds, previews
		if ( is_feed() || is_preview() ) {
			return false;
		}

		// Don't lazyload for amp-wp content
		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			false;
		}

		return true;
	}


	/**
	 * Convert any elements within post content that have
	 * style="background[-image]" to lazy load attributes so they
	 * will be picked up by the JS and the background images will be lazy loaded.
	 *
	 * To exclude a background image, add a `lazy-load-disable;` within the style attribute.
	 *
	 * @author Mat Lipe
	 *
	 * @param $content
	 *
	 * @since 0.7.1
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function add_background_placeholders( $content ) {
		if ( ! self::is_allowed_area() ) {
			return $content;
		}

		// Don't lazy-load if the content has already been run through previously
		if ( false !== strpos( $content, 'data-lazy-background' ) ) {
			return $content;
		}

		preg_match_all( '~\bstyle=[\'|"].*?\s?background(-image)?\s*:.*?(?<css>url\s?\(\s*(\'|")?(?<image>.*?)\3?\s*\))~i', $content, $matches, PREG_SET_ORDER );

		if ( empty( $matches ) ) {
			return $content;
		}

		foreach ( $matches as $match ) {
			if ( false !== strpos( $match[0], 'lazy-load-disable' ) ){
				continue;
			}
			$bg_less_match  = str_replace( $match['css'], '', $match[0] );
			$new_attributes = 'data-lazy-background="' . $match['image'] . '" ' . $bg_less_match;
			$content        = str_replace( $match[0], $new_attributes, $content );
		}

		return $content;

	}


	public static function add_image_placeholders( $content ) {
		if ( ! self::is_allowed_area() ) {
			return $content;
		}
		// Don't lazy-load if the content has already been run through previously
		if ( false !== strpos( $content, 'data-lazy-src' ) )
			return $content;

		// This is a pretty simple regex, but it works
		$content = preg_replace_callback( '#<(img)([^>]+?)(>(.*?)</\\1>|[\/]?>)#si', array( __CLASS__, 'process_image' ), $content );

		return $content;
	}


	/**
	 * Change image attributes to support the lazy loading
	 *
	 * To disable a particular image, add a `data-lazy-disable` as an
	 * image attribute
	 *
	 * @since 0.6.1
	 * @since 0.7.2 - support disabling per image
	 *
	 * @param array $matches
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function process_image( $matches ) {
		$old_attributes_str = $matches[2];
		$old_attributes_kses_hair = wp_kses_hair( $old_attributes_str, wp_allowed_protocols() );
		//skip any image with a 'data-lazy-disable' attribute
		if ( isset( $old_attributes_kses_hair['data-lazy-disable'] ) ) {
			return $matches[0];
		}

		if ( empty( $old_attributes_kses_hair['src'] ) ) {
			return $matches[0];
		}

		$old_attributes = self::flatten_kses_hair_data( $old_attributes_kses_hair );
		$new_attributes = $old_attributes;

		// Set placeholder and lazy-src
		$new_attributes['src'] = self::get_placeholder_image();
		$new_attributes['data-lazy-src'] = $old_attributes['src'];

		// Handle `srcset`
		if ( ! empty( $new_attributes['srcset'] ) ) {
			$new_attributes['data-lazy-srcset'] = $old_attributes['srcset'];
			unset( $new_attributes['srcset'] );
		}

		// Handle `sizes`
		if ( ! empty( $new_attributes['sizes'] ) ) {
			$new_attributes['data-lazy-sizes'] = $old_attributes['sizes'];
			unset( $new_attributes['sizes'] );
		}

		$new_attributes_str = self::build_attributes_string( $new_attributes );

		return sprintf( '<img %1$s><noscript>%2$s</noscript>', $new_attributes_str, $matches[0] );
	}

	private static function get_placeholder_image() {
		return apply_filters( 'lazyload_images_placeholder_image', self::get_url( 'images/1x1.trans.gif' ) );
	}

	private static function flatten_kses_hair_data( $attributes ) {
		$flattened_attributes = array();
		foreach ( $attributes as $name => $attribute ) {
			$flattened_attributes[ $name ] = $attribute['value'];
		}
		return $flattened_attributes;
	}

	private static function build_attributes_string( $attributes ) {
		$string = array();
		foreach ( $attributes as $name => $value ) {
			if ( '' === $value ) {
				$string[] = sprintf( '%s', $name );
			} else {
				$string[] = sprintf( '%s="%s"', $name, esc_attr( $value ) );
			}
		}
		return implode( ' ', $string );
	}

	static function is_enabled() {
		return self::$enabled;
	}

	static function get_url( $path = '' ) {
		return plugins_url( ltrim( $path, '/' ), __FILE__ );
	}
}

function lazyload_images_add_placeholders( $content ) {
	return LazyLoad_Images::add_image_placeholders( $content );
}

if ( ! function_exists( 'get_the_post_thumbnail_no_lazy_load' ) ) {
	/**
	 * Get a post thumbnail with lazy load disabled
	 *
	 * @param int|WP_Post  $post
	 * @param string       $size
	 * @param string|array $attr
	 *
	 * @author Mat Lipe
	 *
	 * @since 0.7.2
	 *
	 * @return string
	 */
	function get_the_post_thumbnail_no_lazy_load( $post = null, $size = 'post-thumbnail', $attr = '' ) {
		$attr                      = (array) $attr;
		$attr['data-lazy-disable'] = 'true';

		return get_the_post_thumbnail( $post, $size, $attr );
	}

	/**
	 * Get an HTML img element with lazy load disabled
	 *
	 * @param int          $attachment_id
	 * @param string       $size
	 * @param bool         $icon
	 * @param string|array $attr
	 *
	 * @author Mat Lipe
	 *
	 * @since 0.7.2
	 *
	 * @return string
	 */
	function wp_get_attachment_image_no_lazy_load( $attachment_id, $size = 'thumbnail', $icon = false, $attr = '' ) {
		$attr                      = (array) $attr;
		$attr['data-lazy-disable'] = 'true';

		return wp_get_attachment_image( $attachment_id, $size, $icon, $attr );
	}

}
add_action( 'init', array( 'LazyLoad_Images', 'init' ) );

endif;
