<?php
/**
 * Evaluate alternative text on individual image occurrences.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stored image markup and the legacy editor's block attribute.
 */
final class EDIWORMAN_Image_Alt {

	const ATTRIBUTE = 'ediwormanDecorativeImage';
	const RAW_ELEMENTS = array( 'script', 'style', 'textarea', 'title', 'iframe', 'xmp' );
	const BLANK_ENTITIES = array( '&Tab;', '&NewLine;', '&nbsp;', '&NonBreakingSpace;', '&ensp;', '&emsp;', '&emsp13;', '&emsp14;', '&numsp;', '&puncsp;', '&thinsp;', '&ThinSpace;', '&hairsp;', '&VeryThinSpace;', '&MediumSpace;', '&ThickSpace;' );

	/**
	 * Register the fallback attribute before core registers the Image block.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_filter( 'register_block_type_args', array( __CLASS__, 'register_attribute' ), 10, 2 );
	}

	/**
	 * Keep the fallback attribute readable even after native support is available.
	 *
	 * @param array  $args Block registration settings.
	 * @param string $name Block type name.
	 * @return array
	 */
	public static function register_attribute( $args, $name ) {
		if ( 'core/image' === $name ) {
			$args['attributes'][ self::ATTRIBUTE ] = array( 'type' => 'boolean', 'default' => false );
		}
		return $args;
	}

	/**
	 * Inspect literal stored images in document order without rendering blocks.
	 *
	 * @param string $content Serialized post content.
	 * @return array<int, array{id:int,alt:?string,marked:bool,interactive:bool}>
	 */
	public static function collect( $content ) {
		$images = array();
		$state  = array( 'anchor' => false, 'raw' => '', 'template_depth' => 0, 'lightbox_default' => null );
		self::walk( parse_blocks( $content ), $state, $images );
		return $images;
	}

	/**
	 * Read the default as core would for an Image block without an override.
	 *
	 * @return bool
	 */
	public static function is_lightbox_enabled_by_default() {
		if ( ! function_exists( 'block_core_image_get_lightbox_settings' ) ) {
			return false;
		}
		$settings = block_core_image_get_lightbox_settings( array( 'attrs' => array() ) );
		return true === ( $settings['enabled'] ?? false );
	}

	/**
	 * Interleave each parent's HTML fragments with its child blocks.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param array $state  Scanner state across fragments.
	 * @param array $images Collected images.
	 * @return void
	 */
	private static function walk( $blocks, &$state, &$images ) {
		foreach ( $blocks as $block ) {
			$is_image = 'core/image' === $block['blockName'];
			$attrs    = $block['attrs'] ?? array();
			$marked   = $is_image && ( true === ( $attrs['isDecorative'] ?? false ) || true === ( $attrs[ self::ATTRIBUTE ] ?? false ) );
			$child    = 0;
			$first    = count( $images );
			foreach ( $block['innerContent'] ?? array( $block['innerHTML'] ?? '' ) as $fragment ) {
				if ( is_string( $fragment ) ) {
					self::scan( $fragment, $state, $images );
				} elseif ( isset( $block['innerBlocks'][ $child ] ) ) {
					self::walk( array( $block['innerBlocks'][ $child ] ), $state, $images );
					++$child;
				}
			}
			// Only a single image owned by a core Image block can use the designation.
			if ( $is_image && empty( $block['innerBlocks'] ) && count( $images ) === $first + 1 ) {
				if ( $marked && ! isset( $attrs['lightbox'] ) && null === $state['lightbox_default'] ) {
					$state['lightbox_default'] = self::is_lightbox_enabled_by_default();
				}
				$images[ $first ]['marked'] = $marked;
				$images[ $first ]['interactive'] = $images[ $first ]['interactive'] ||
					in_array( $attrs['linkDestination'] ?? '', array( 'media', 'attachment', 'custom' ), true ) ||
					( isset( $attrs['lightbox'] ) ? true === ( $attrs['lightbox']['enabled'] ?? false ) : ( $marked && $state['lightbox_default'] ) );
				if ( is_int( $attrs['id'] ?? null ) && $attrs['id'] > 0 ) {
					$images[ $first ]['id'] = $attrs['id'];
				}
			}
		}
	}

	/**
	 * Read complete tags and keep link context across nested block boundaries.
	 *
	 * @param string $html   Stored HTML fragment.
	 * @param array  $state  Scanner state.
	 * @param array  $images Collected images.
	 * @return void
	 * @throws RuntimeException If PHP could not scan the markup.
	 */
	private static function scan( $html, &$state, &$images ) {
		// Match ASCII HTML whitespace and quoted attributes identically in PHP and JS.
		$pattern = '~<!--[\s\S]*?(?:-->|$)|</?[a-z][a-z0-9:-]*(?=[\x09\x0a\x0c\x0d\x20/>])(?:"[^"]*"|\x27[^\x27]*\x27|[^\x27"<>])*>~i';
		$length  = strlen( $html );
		$offset  = 0;
		while ( $offset < $length ) {
			$next = '' === $state['raw'] ? $pattern : '~</' . $state['raw'] . '(?=[\x09\x0a\x0c\x0d\x20/>])[^>]*>~i';
			$found = preg_match( $next, $html, $matches, PREG_OFFSET_CAPTURE, $offset );
			if ( false === $found ) {
				throw new RuntimeException( 'Image markup could not be parsed.' );
			}
			if ( ! $found ) {
				break;
			}
			$tag    = $matches[0][0];
			$offset = $matches[0][1] + strlen( $tag );
			if ( '' !== $state['raw'] ) {
				$state['raw'] = '';
				continue;
			}
			if ( ! preg_match( '~^<(/?)([a-z][a-z0-9:-]*)~i', $tag, $parts ) ) {
				continue;
			}
			$name    = strtolower( $parts[2] );
			$closing = '/' === $parts[1];
			if ( 'template' === $name ) {
				$state['template_depth'] = max( 0, $state['template_depth'] + ( $closing ? -1 : 1 ) );
			} elseif ( ! $closing && in_array( $name, self::RAW_ELEMENTS, true ) ) {
				$state['raw'] = $name;
			} elseif ( 0 === $state['template_depth'] && 'a' === $name ) {
				$state['anchor'] = ! $closing && array_key_exists( 'href', self::attributes( substr( $tag, strlen( $parts[0] ), -1 ) ) );
			} elseif ( 0 === $state['template_depth'] && 'img' === $name && ! $closing ) {
				$attributes = self::attributes( substr( $tag, strlen( $parts[0] ), -1 ) );
				$id         = preg_match( '/(?:^|[\x09\x0a\x0c\x0d\x20])wp-image-(\d+)(?=$|[\x09\x0a\x0c\x0d\x20])/', (string) ( $attributes['class'] ?? '' ), $attachment ) ? (int) $attachment[1] : 0;
				$images[]   = array(
					'id'          => $id,
					'alt'         => $attributes['alt'] ?? null,
					'marked'      => false,
					'interactive' => $state['anchor'],
				);
			}
		}
	}

	/**
	 * Read attributes by exact name with first-attribute precedence.
	 *
	 * @param string $markup Tag text after its name.
	 * @return array<string,string|null>
	 */
	private static function attributes( $markup ) {
		$pattern = '~\G[\x09\x0a\x0c\x0d\x20/]*([^\x09\x0a\x0c\x0d\x20/=<>"\x27]+)(?:[\x09\x0a\x0c\x0d\x20]*=[\x09\x0a\x0c\x0d\x20]*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\x09\x0a\x0c\x0d\x20<>]+)))?~';
		$attributes = array();
		$offset     = 0;
		while ( preg_match( $pattern, $markup, $match, PREG_UNMATCHED_AS_NULL, $offset ) ) {
			$name   = strtolower( $match[1] );
			$offset += strlen( $match[0] );
			if ( ! array_key_exists( $name, $attributes ) ) {
				$attributes[ $name ] = $match[2] ?? $match[3] ?? $match[4] ?? null;
			}
		}
		return $attributes;
	}

	/**
	 * Check ALT for characters other than whitespace.
	 *
	 * @param string|null $alt Attribute text, optionally HTML encoded.
	 * @param bool        $encoded Whether the value is from HTML markup.
	 * @return bool
	 */
	public static function has_alt( $alt, $encoded = true ) {
		if ( ! is_string( $alt ) ) {
			return false;
		}
		if ( $encoded ) {
			// Only whitespace references affect the blank check. Keep this vocabulary in sync with the editor.
			$alt = str_replace( self::BLANK_ENTITIES, ' ', $alt );
			$alt = preg_replace( '/&nbsp(?![a-zA-Z0-9=])/', ' ', $alt );
			$alt = preg_replace_callback(
				'/&#(?:([0-9]+)|[xX]([0-9a-fA-F]+));?/',
				static function ( $reference ) {
					$point = isset( $reference[2] ) ? hexdec( $reference[2] ) : (float) $reference[1];
					return ( ( 9 <= $point && 13 >= $point ) || ( 8192 <= $point && 8202 >= $point ) || ( $point <= 65279 && in_array( (int) $point, array( 32, 160, 5760, 8232, 8233, 8239, 8287, 12288, 65279 ), true ) ) ) ? ' ' : $reference[0];
				},
				$alt
			);
		} else {
			$alt = wp_strip_all_tags( $alt );
		}
		return 1 === preg_match( '/[^\p{Z}\x09-\x0d\x{85}\x{feff}]/u', (string) $alt );
	}

	/**
	 * Score a single stored content image.
	 *
	 * @param array $image Collected image.
	 * @return string pass, decorative, missing, or invalid.
	 */
	public static function status( $image ) {
		if ( $image['marked'] ) {
			return '' === $image['alt'] && ! $image['interactive'] ? 'decorative' : 'invalid';
		}
		return self::has_alt( $image['alt'] ) ? 'pass' : 'missing';
	}
}
