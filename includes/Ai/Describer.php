<?php
/**
 * Listing description from photos + notes (Gemini vision), with a template fallback.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Ai;

use Dev3\AiRealEstate\Core3\Api\Prompts;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Static service.
 */
class Describer {

	const MAX_PHOTOS = 8;
	const MAX_BYTES  = 3500000;
	const MIMES      = array( 'image/jpeg', 'image/png', 'image/webp' );

	/**
	 * Generates a draft. Nothing is saved.
	 *
	 * @param array  $listing { title, content, fields{}, type[] ids, features[] ids, locations[] ids }.
	 * @param array  $opts    { tone }.
	 * @param string $context admin|submission.
	 * @param int    $user_id When set, only this user's images are sent.
	 * @return array|\WP_Error { draft{ title, excerpt, content, highlights, features }, observations, photos, model, tokens, cached }
	 */
	public static function generate( array $listing, array $opts = array(), $context = 'admin', $user_id = 0 ) {
		$fields = isset( $listing['fields'] ) && is_array( $listing['fields'] ) ? $listing['fields'] : array();
		$images = self::images( (array) ( $fields['gallery'] ?? array() ), $user_id, (array) ( $opts['trusted'] ?? array() ) );
		$notes  = mb_substr( sanitize_textarea_field( (string) ( $fields['notes'] ?? '' ) ), 0, 3000 );
		if ( ! $images && '' === trim( $notes ) ) {
			return new \WP_Error( 'dev3re_need_input', __( 'Add some photos or a few notes about the property first.', 'dev3-ai-real-estate' ), array( 'status' => 400 ) );
		}
		$tones = array(
			'warm'         => 'warm and inviting',
			'professional' => 'professional and factual',
			'luxury'       => 'refined, luxury',
			'concise'      => 'concise, to-the-point',
		);
		$tone  = isset( $opts['tone'] ) && isset( $tones[ $opts['tone'] ] ) ? $opts['tone'] : (string) Settings::get( 'ai_tone', 'warm' );
		$vocab = wp_list_pluck( Post_Types::terms( Post_Types::FEATURE ), 'name' );
		$res   = Prompts::run(
			Prompts_Library::DESCRIBE,
			array(
				'tone'        => $tones[ $tone ] ?? $tones['warm'],
				'listing'     => self::kind( $listing ),
				'photo_count' => count( $images ) . ' photos',
				'features'    => $vocab ? implode( ', ', array_slice( $vocab, 0, 120 ) ) : '(none)',
				'facts'       => self::facts( $listing ),
				'notes'       => '' !== trim( $notes ) ? $notes : '(none)',
				'current'     => mb_substr( wp_strip_all_tags( (string) ( $listing['content'] ?? '' ) ), 0, 3000 ),
				'language'    => Prompts_Library::language(),
			),
			$context,
			array(
				'images' => $images,
				'cache'  => DAY_IN_SECONDS,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = is_array( $res['data'] ?? null ) ? $res['data'] : array();
		return array(
			'draft'        => array(
				'title'      => mb_substr( sanitize_text_field( (string) ( $data['title'] ?? '' ) ), 0, 120 ),
				'excerpt'    => mb_substr( sanitize_textarea_field( (string) ( $data['excerpt'] ?? '' ) ), 0, 300 ),
				'content'    => self::to_html( (string) ( $data['description'] ?? '' ) ),
				'highlights' => implode( "\n", Property::clean( 'highlights', (array) ( $data['highlights'] ?? array() ) ) ),
				'features'   => implode( "\n", self::feature_names( (array) ( $data['features'] ?? array() ) ) ),
			),
			'feature_ids'  => self::feature_ids( (array) ( $data['features'] ?? array() ) ),
			'observations' => array_slice( array_map( 'sanitize_text_field', array_filter( (array) ( $data['observations'] ?? array() ), 'is_string' ) ), 0, 6 ),
			'photos'       => count( $images ),
			'model'        => $res['model'],
			'tokens'       => (int) ( $res['usage']['total'] ?? 0 ),
			'cached'       => ! empty( $res['cached'] ),
		);
	}

	/**
	 * Template description (no AI).
	 *
	 * @param array $listing Listing.
	 * @return array { draft, fallback: true }
	 */
	public static function template( array $listing ) {
		$f     = self::field_values( $listing );
		$kind  = self::kind( $listing );
		$place = $f['city'] ? $f['city'] : '';
		$beds  = $f['beds'] ? sprintf( /* translators: %d: bedrooms */ _n( '%d-bedroom', '%d-bedroom', $f['beds'], 'dev3-ai-real-estate' ), $f['beds'] ) . ' ' : '';
		$deal  = 'rent' === $f['listing_type'] ? __( 'for rent', 'dev3-ai-real-estate' ) : __( 'for sale', 'dev3-ai-real-estate' );
		$title = trim( ucfirst( $beds . $kind ) . ' ' . $deal . ( $place ? ' ' . sprintf( /* translators: %s: city */ __( 'in %s', 'dev3-ai-real-estate' ), $place ) : '' ) );
		$facts = array();
		if ( $f['beds'] ) {
			/* translators: %d: bedrooms */
			$facts[] = sprintf( _n( '%d bedroom', '%d bedrooms', $f['beds'], 'dev3-ai-real-estate' ), $f['beds'] );
		}
		if ( $f['baths'] ) {
			/* translators: %s: bathrooms */
			$facts[] = sprintf( __( '%s bathrooms', 'dev3-ai-real-estate' ), $f['baths'] + 0 );
		}
		if ( $f['area'] ) {
			/* translators: %s: area */
			$facts[] = sprintf( __( '%s of living space', 'dev3-ai-real-estate' ), Property::area_label( Property::area_m2( $f['area'], $f['area_unit'] ) ) );
		}
		if ( $f['garages'] ) {
			/* translators: %d: parking spaces */
			$facts[] = sprintf( _n( '%d parking space', '%d parking spaces', $f['garages'], 'dev3-ai-real-estate' ), $f['garages'] );
		}
		$p1 = sprintf(
			/* translators: 1: property kind, 2: facts list */
			__( 'This %1$s offers %2$s.', 'dev3-ai-real-estate' ),
			$kind,
			$facts ? implode( ', ', $facts ) : __( 'a practical layout', 'dev3-ai-real-estate' )
		);
		if ( $f['year_built'] ) {
			/* translators: %d: year */
			$p1 .= ' ' . sprintf( __( 'It was built in %d.', 'dev3-ai-real-estate' ), $f['year_built'] );
		}
		$names = self::term_names( (array) ( $listing['features'] ?? array() ), Post_Types::FEATURE );
		$p2    = $names ? sprintf( /* translators: %s: features */ __( 'Features include %s.', 'dev3-ai-real-estate' ), implode( ', ', array_map( 'mb_strtolower', $names ) ) ) : '';
		$p3    = $place ? sprintf( /* translators: %s: city */ __( 'Located in %s. Contact us to arrange a viewing.', 'dev3-ai-real-estate' ), $place ) : __( 'Contact us to arrange a viewing.', 'dev3-ai-real-estate' );
		return array(
			'draft'    => array(
				'title'      => $title,
				'excerpt'    => $p1,
				'content'    => self::to_html( implode( "\n\n", array_filter( array( $p1, $p2, $p3 ) ) ) ),
				'highlights' => implode( "\n", array_slice( $facts, 0, 6 ) ),
				'features'   => implode( "\n", $names ),
			),
			'fallback' => true,
		);
	}

	/**
	 * Base64 images of attachments (medium-large size preferred), owned by the user when given.
	 *
	 * @param int[] $ids     Attachments.
	 * @param int   $user_id Owner filter.
	 * @param int[] $trusted Attachments already vetted (gallery of a property the user may edit).
	 * @return array[] [ { mime, data } ]
	 */
	public static function images( array $ids, $user_id = 0, array $trusted = array() ) {
		$out = array();
		foreach ( array_slice( array_unique( array_map( 'intval', $ids ) ), 0, self::MAX_PHOTOS ) as $id ) {
			$post = get_post( $id );
			if ( ! $post || 'attachment' !== $post->post_type || ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			if ( $user_id && (int) $post->post_author !== (int) $user_id && ! user_can( $user_id, 'edit_others_posts' ) && ! in_array( (int) $id, array_map( 'intval', $trusted ), true ) ) {
				continue;
			}
			$path = self::path( $id );
			if ( ! $path ) {
				continue;
			}
			$mime = (string) wp_check_filetype( $path )['type'];
			if ( ! in_array( $mime, self::MIMES, true ) ) {
				continue;
			}
			$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local upload.
			if ( false === $bytes || '' === $bytes ) {
				continue;
			}
			$out[] = array(
				'mime' => $mime,
				'data' => base64_encode( $bytes ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Gemini inline image.
			);
		}
		return $out;
	}

	/**
	 * Smallest useful local file of an image (≥ 768px when available, under the byte cap).
	 *
	 * @param int $id Attachment.
	 * @return string
	 */
	private static function path( $id ) {
		$file = get_attached_file( $id );
		if ( ! $file || ! is_readable( $file ) ) {
			return '';
		}
		$meta = wp_get_attachment_metadata( $id );
		foreach ( array( 'medium_large', 'large', '1536x1536' ) as $size ) {
			if ( ! empty( $meta['sizes'][ $size ]['file'] ) ) {
				$candidate = path_join( dirname( $file ), $meta['sizes'][ $size ]['file'] );
				if ( is_readable( $candidate ) && filesize( $candidate ) <= self::MAX_BYTES ) {
					return $candidate;
				}
			}
		}
		return filesize( $file ) <= self::MAX_BYTES ? $file : '';
	}

	/**
	 * Field values merged with defaults.
	 *
	 * @param array $listing Listing.
	 * @return array
	 */
	private static function field_values( array $listing ) {
		$out = array();
		foreach ( Property::FIELDS as $key => $def ) {
			$out[ $key ] = isset( $listing['fields'][ $key ] ) ? Property::clean( $key, $listing['fields'][ $key ] ) : $def[1];
		}
		return $out;
	}

	/**
	 * "house", "apartment" … from the type terms.
	 *
	 * @param array $listing Listing.
	 * @return string
	 */
	public static function kind( array $listing ) {
		$names = self::term_names( (array) ( $listing['type'] ?? array() ), Post_Types::TYPE );
		return $names ? mb_strtolower( $names[0] ) : __( 'property', 'dev3-ai-real-estate' );
	}

	/**
	 * Facts block for the prompt (no contact details, no private notes).
	 *
	 * @param array $listing Listing.
	 * @return string
	 */
	public static function facts( array $listing ) {
		$f     = self::field_values( $listing );
		$lines = array();
		if ( ! empty( $listing['title'] ) ) {
			$lines[] = 'Working title: ' . sanitize_text_field( (string) $listing['title'] );
		}
		$lines[] = 'Listing: ' . ( 'rent' === $f['listing_type'] ? 'for rent' : 'for sale' );
		$types   = self::term_names( (array) ( $listing['type'] ?? array() ), Post_Types::TYPE );
		if ( $types ) {
			$lines[] = 'Type: ' . implode( ', ', $types );
		}
		if ( ! $f['price_on_request'] && $f['price'] ) {
			$lines[] = 'Price: ' . wp_strip_all_tags( Property::price_label( $f ) );
		}
		foreach ( array( 'beds' => 'Bedrooms', 'baths' => 'Bathrooms', 'garages' => 'Parking spaces', 'year_built' => 'Year built', 'floor' => 'Floor', 'floors' => 'Floors in building', 'energy_class' => 'Energy class' ) as $key => $label ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			if ( ! empty( $f[ $key ] ) ) {
				$lines[] = $label . ': ' . $f[ $key ];
			}
		}
		if ( $f['area'] ) {
			$lines[] = 'Living area: ' . Property::area_label( Property::area_m2( $f['area'], $f['area_unit'] ) );
		}
		if ( $f['lot_size'] ) {
			$lines[] = 'Lot size: ' . number_format_i18n( $f['lot_size'] ) . ' ' . Property::unit_label();
		}
		$place = array_filter( array( $f['city'], $f['region'], $f['country'] ) );
		if ( $place ) {
			$lines[] = 'Location: ' . implode( ', ', $place );
		}
		$features = self::term_names( (array) ( $listing['features'] ?? array() ), Post_Types::FEATURE );
		if ( $features ) {
			$lines[] = 'Features: ' . implode( ', ', $features );
		}
		if ( $f['floor_plans'] ) {
			$lines[] = 'Floor plans: ' . implode( '; ', wp_list_pluck( $f['floor_plans'], 'title' ) );
		}
		return mb_substr( implode( "\n", $lines ), 0, 3000 );
	}

	/**
	 * Term names for ids.
	 *
	 * @param int[]  $ids      Ids.
	 * @param string $taxonomy Taxonomy.
	 * @return string[]
	 */
	private static function term_names( array $ids, $taxonomy ) {
		$out = array();
		foreach ( $ids as $id ) {
			$t = get_term( (int) $id, $taxonomy );
			if ( $t && ! is_wp_error( $t ) ) {
				$out[] = $t->name;
			}
		}
		return $out;
	}

	/**
	 * Keeps only feature names that exist on the site (case-insensitive), with their canonical spelling.
	 *
	 * @param array $names Names from the model.
	 * @return string[]
	 */
	public static function feature_names( array $names ) {
		$out = array();
		foreach ( self::feature_ids( $names ) as $id ) {
			$out[] = get_term( $id, Post_Types::FEATURE )->name;
		}
		return $out;
	}

	/**
	 * Feature names → existing term ids.
	 *
	 * @param array $names Names.
	 * @return int[]
	 */
	public static function feature_ids( array $names ) {
		$map = array();
		foreach ( Post_Types::terms( Post_Types::FEATURE ) as $t ) {
			$map[ mb_strtolower( $t['name'] ) ] = $t['id'];
		}
		$ids = array();
		foreach ( $names as $name ) {
			$key = mb_strtolower( trim( is_scalar( $name ) ? (string) $name : '' ) );
			if ( isset( $map[ $key ] ) && ! in_array( $map[ $key ], $ids, true ) ) {
				$ids[] = $map[ $key ];
			}
		}
		return $ids;
	}

	/**
	 * Plain paragraphs → <p> HTML.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function to_html( $text ) {
		$paras = array_filter( array_map( 'trim', preg_split( "/\n\s*\n/", trim( (string) $text ) ) ) );
		$out   = '';
		foreach ( $paras as $p ) {
			$out .= '<p>' . esc_html( preg_replace( '/\s*\n\s*/', ' ', $p ) ) . "</p>\n";
		}
		return trim( $out );
	}
}
