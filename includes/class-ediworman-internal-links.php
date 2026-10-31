<?php
/**
 * Deterministic, offline internal-link counting.
 *
 * @package EditorialWorkflowManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts literal anchors without rendering content or loading destinations.
 */
final class EDIWORMAN_Internal_Links {

	/**
	 * Use the prospective published URL even while the publication gate holds a draft.
	 *
	 * @param int $post_id Saved post ID.
	 * @return string
	 */
	public static function get_post_url( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return home_url( '/' );
		}
		// Never change the cached WP_Post object or persist a synthetic status.
		$sample = clone $post;
		if ( ! in_array( $sample->post_status, array( 'publish', 'private', 'future' ), true ) ) {
			$sample->post_name = sanitize_title( $sample->post_name ? $sample->post_name : $sample->post_title, $sample->ID );
			$sample->post_name = wp_unique_post_slug( $sample->post_name, $sample->ID, 'publish', $sample->post_type, $sample->post_parent );
		}
		$sample->post_status = 'publish';
		$sample->filter = 'sample';
		$url = get_permalink( $sample );
		return $url ? $url : home_url( '/' );
	}

	/**
	 * Canonical HTML5 named references shared with the editor.
	 *
	 * @return array<string,string>
	 */
	public static function get_entities() {
		return array_merge(
			array_flip( get_html_translation_table( HTML_ENTITIES, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
			array( '&AMP;' => '&', '&LT;' => '<', '&GT;' => '>', '&QUOT;' => '"' )
		);
	}

	/**
	 * Count distinct internal destinations in literal saved HTML.
	 *
	 * @param string $content  Saved or edited content.
	 * @param string $home_url Current site's home URL.
	 * @param string $post_url Current post permalink, when available.
	 * @return int
	 * @throws RuntimeException When URL context or tokenization fails.
	 */
	public static function count( $content, $home_url, $post_url = '' ) {
		$home = self::absolute_url( $home_url );
		if ( null === $home ) {
			throw new RuntimeException( 'Invalid internal-link site URL.' );
		}
		$base = self::absolute_url( $post_url ) ?? $home;
		$self = self::destination_key( $base );
		$destinations = array();
		// The same lexical grammar is used in assets/js/ediworman-internal-links.js.
		$pattern = '~<!--[\s\S]*?(?:-->|$)|\[/?[A-Za-z_][A-Za-z0-9_:-]*(?=[\x09\x0a\x0c\x0d\x20/\]])(?:"[^"]*"|\x27[^\x27]*\x27|[^\]\x27"])*\]|</?[A-Za-z](?:"[^"]*"|\x27[^\x27]*\x27|[^\x27">])*>~';
		$raw = '';
		$template_depth = 0;
		$offset = 0;
		$content = (string) $content;
		$content_length = strlen( $content );
		while ( $offset < $content_length ) {
			// Raw-text closing tags terminate even inside JavaScript/CSS quotes.
			$next_pattern = '' !== $raw ? '~</' . $raw . '(?=[\x09\x0a\x0c\x0d\x20/>])[^>]*>~i' : $pattern;
			$found = preg_match( $next_pattern, $content, $next, PREG_OFFSET_CAPTURE, $offset );
			if ( false === $found ) {
				throw new RuntimeException( 'Internal-link content could not be parsed.' );
			}
			if ( ! $found ) {
				break;
			}
			$token = $next[0][0];
			$offset = $next[0][1] + strlen( $token );
			if ( ! preg_match( '~^<(/?)([A-Za-z][A-Za-z0-9:-]*)(?=[\x09\x0a\x0c\x0d\x20/>])~', $token, $tag ) ) {
				continue;
			}
			$name = strtolower( $tag[2] );
			$closing = '/' === $tag[1];
			if ( '' !== $raw ) {
				if ( $closing && $name === $raw ) {
					$raw = '';
				}
				continue;
			}
			if ( 'template' === $name ) {
				$template_depth = max( 0, $template_depth + ( $closing ? -1 : 1 ) );
				continue;
			}
			if ( ! $closing && in_array( $name, array( 'script', 'style', 'textarea', 'title' ), true ) ) {
				$raw = $name;
				continue;
			}
			if ( $template_depth || $closing || 'a' !== $name ) {
				continue;
			}
			$href = self::get_href( substr( $token, strlen( $tag[0] ), -1 ) );
			if ( null === $href ) {
				continue;
			}
			$url = self::resolve( self::decode_attribute( $href ), $base );
			if ( null === $url || $url['host'] !== $home['host'] || $url['port'] !== $home['port'] ) {
				continue;
			}
			$boundary = rtrim( $home['path'], '/' );
			if ( '' !== $boundary && $url['path'] !== $boundary && 0 !== strpos( $url['path'], $boundary . '/' ) ) {
				continue;
			}
			$key = self::destination_key( $url );
			if ( $key !== $self ) {
				$destinations[ $key ] = true;
			}
		}
		return count( $destinations );
	}

	/**
	 * Extract the first href attribute, never data-href or attribute text.
	 *
	 * @param string $attributes Raw attributes.
	 * @return string|null
	 */
	private static function get_href( $attributes ) {
		$pattern = '~\G[\x09\x0a\x0c\x0d\x20]+([^\x09\x0a\x0c\x0d\x20/=<>\x27"`]+)(?:[\x09\x0a\x0c\x0d\x20]*=[\x09\x0a\x0c\x0d\x20]*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\x09\x0a\x0c\x0d\x20<>\x27"`=]+)))?~';
		$offset = 0;
		while ( preg_match( $pattern, $attributes, $match, PREG_UNMATCHED_AS_NULL, $offset ) ) {
			$offset += strlen( $match[0] );
			if ( 'href' === strtolower( $match[1] ) ) {
				return $match[2] ?? $match[3] ?? $match[4] ?? '';
			}
		}
		return null;
	}

	/**
	 * Decode semicolon-terminated references using the shared vocabulary.
	 *
	 * @param string $value Attribute value.
	 * @return string
	 */
	private static function decode_attribute( $value ) {
		static $entities = null;
		if ( null === $entities ) {
			$entities = self::get_entities();
		}
		return preg_replace_callback(
			'~&(?:\#[xX][0-9a-fA-F]+|\#[0-9]+|[A-Za-z][A-Za-z0-9]*);~',
			static function ( $reference ) use ( $entities ) {
				return $entities[ $reference[0] ] ?? ( 0 === strpos( $reference[0], '&#' ) ? html_entity_decode( $reference[0], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : $reference[0] );
			},
			$value
		);
	}

	/**
	 * Normalize escapes, Unicode and dot segments without decoding separators.
	 *
	 * @param string $value Path or query.
	 * @return string
	 */
	private static function normalize_component( $value ) {
		$value = preg_replace_callback(
			'~[^\x00-\x7f]+~u',
			static function ( $characters ) {
				return rawurlencode( $characters[0] );
			},
			$value
		);
		return preg_replace_callback(
			'~%([0-9a-fA-F]{2})~',
			static function ( $escape ) {
				$character = chr( hexdec( $escape[1] ) );
				return preg_match( '~^[A-Za-z0-9._\~-]$~', $character ) ? $character : strtoupper( $escape[0] );
			},
			(string) $value
		);
	}

	/**
	 * Normalize a rooted path, retaining significant case and inner slashes.
	 *
	 * @param string $path URL path.
	 * @return string
	 */
	private static function normalize_path( $path ) {
		$parts = explode( '/', self::normalize_component( $path ) );
		$result = array();
		foreach ( array_slice( $parts, 1 ) as $part ) {
			if ( '..' === $part ) {
				array_pop( $result );
			} elseif ( '.' !== $part ) {
				$result[] = $part;
			}
		}
		return '/' . implode( '/', $result );
	}

	/**
	 * Parse an absolute HTTP(S) URL with deliberately shared strict syntax.
	 *
	 * @param string $value URL.
	 * @return array|null
	 */
	private static function absolute_url( $value ) {
		if ( preg_match( '~[\x00-\x20\x7f<>\\\\]|%(?![0-9a-fA-F]{2})~', $value ) || ! preg_match( '~^(https?)://(\[[0-9a-f:]+\]|[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?)(?::([0-9]{1,5}))?(/[^?\#]*)?(\?[^\#]*)?(?:\#.*)?$~iD', $value, $match ) ) {
			return null;
		}
		$host = strtolower( $match[2] );
		if ( '[' === $host[0] ) {
			$ip = substr( $host, 1, -1 );
			$groups = array_filter( explode( ':', $ip ), 'strlen' );
			if ( false !== strpos( $ip, ':::' ) || substr_count( $ip, '::' ) > 1 || ( false !== strpos( $ip, '::' ) ? count( $groups ) >= 8 : count( $groups ) !== 8 ) ) {
				return null;
			}
			foreach ( $groups as $group ) {
				if ( strlen( $group ) > 4 ) {
					return null;
				}
			}
		} elseif ( preg_match( '~(?:^|\.)-|-(?:\.|$)|\.\.~', $host ) ) {
			return null;
		}
		$port = isset( $match[3] ) && '' !== $match[3] ? (int) $match[3] : 0;
		if ( $port > 65535 || ( isset( $match[3] ) && '' !== $match[3] && $port < 1 ) ) {
			return null;
		}
		if ( ( 'http' === strtolower( $match[1] ) && 80 === $port ) || ( 'https' === strtolower( $match[1] ) && 443 === $port ) ) {
			$port = 0;
		}
		return array(
			'scheme' => strtolower( $match[1] ),
			'host' => $host,
			'port' => $port,
			'path' => self::normalize_path( $match[4] ?? '/' ),
			'query' => self::normalize_component( $match[5] ?? '' ),
		);
	}

	/**
	 * Resolve a link using the post's directory, never the admin page URL.
	 *
	 * @param string $href Decoded link.
	 * @param array  $base Absolute post URL.
	 * @return array|null
	 */
	private static function resolve( $href, $base ) {
		$href = trim( $href, " \t\n\r\0\x0B\x0C" );
		if ( '' === $href || '#' === $href[0] || '?' === $href[0] ) {
			return null;
		}
		if ( preg_match( '~^[A-Za-z][A-Za-z0-9+.-]*:~', $href ) ) {
			return self::absolute_url( $href );
		}
		if ( 0 === strpos( $href, '//' ) ) {
			return self::absolute_url( $base['scheme'] . ':' . $href );
		}
		$origin = $base['scheme'] . '://' . $base['host'] . ( $base['port'] ? ':' . $base['port'] : '' );
		$path = '/' === $href[0] ? $href : substr( $base['path'], 0, strrpos( $base['path'], '/' ) + 1 ) . $href;
		return self::absolute_url( $origin . $path );
	}

	/**
	 * Stable identity independent of fragment, protocol and trailing slash.
	 *
	 * @param array $url Parsed URL.
	 * @return string
	 */
	private static function destination_key( $url ) {
		return $url['host'] . ':' . $url['port'] . rtrim( $url['path'], '/' ) . $url['query'];
	}
}
