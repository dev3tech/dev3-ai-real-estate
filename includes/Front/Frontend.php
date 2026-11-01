<?php
/**
 * Front-end wiring: assets, shortcodes, Gutenberg blocks, alert links, archive redirects, default pages.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Front;

use Dev3\AiRealEstate\Core3\Core\Assets;
use Dev3\AiRealEstate\Core3\Core\Settings_Store;
use Dev3\AiRealEstate\Data\Favourites;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Searches;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- compact block attribute definitions.

/**
 * Static wiring.
 */
class Frontend {

	/**
	 * Shortcode tag => block name.
	 *
	 * @var array
	 */
	const SHORTCODES = array(
		'search'       => 'search',
		'quick_search' => 'quick-search',
		'properties'   => 'properties',
		'mortgage'     => 'mortgage',
		'agents'       => 'agents',
		'submit'       => 'submit',
		'dashboard'    => 'dashboard',
		'favourites'   => 'favourites',
		'compare'      => 'compare',
	);

	/**
	 * Whether assets were enqueued.
	 *
	 * @var bool
	 */
	private static $enqueued = false;

	/**
	 * Whether entries were registered.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
		add_action( 'template_redirect', array( __CLASS__, 'alert_links' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'archive_redirect' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
		add_action( 'wp_login', array( __CLASS__, 'mark_login' ), 10, 2 );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		Single::init();
		Schema_Markup::init();
	}

	/**
	 * Shortcodes + blocks + scripts.
	 */
	public static function register() {
		if ( ! self::$registered ) {
			self::$registered = true;
			Assets::register( array( 'frontend', 'block' ), self::config() );
		}
		foreach ( array_keys( self::SHORTCODES ) as $tag ) {
			add_shortcode( 'dev3re_' . $tag, array( __CLASS__, 'shortcode_' . $tag ) );
		}
		$blocks = array(
			'search'       => array(
				'listing_type' => array( 'type' => 'string', 'default' => '' ),
				'columns'      => array( 'type' => 'number', 'default' => 2 ),
				'per_page'     => array( 'type' => 'number', 'default' => 12 ),
				'map'          => array( 'type' => 'string', 'default' => 'yes' ),
			),
			'quick-search' => array(
				'listing_type' => array( 'type' => 'string', 'default' => 'sale' ),
			),
			'properties'   => array(
				'listing_type' => array( 'type' => 'string', 'default' => '' ),
				'type'         => array( 'type' => 'string', 'default' => '' ),
				'location'     => array( 'type' => 'string', 'default' => '' ),
				'featured'     => array( 'type' => 'string', 'default' => 'no' ),
				'sort'         => array( 'type' => 'string', 'default' => 'newest' ),
				'limit'        => array( 'type' => 'number', 'default' => 6 ),
				'columns'      => array( 'type' => 'number', 'default' => 3 ),
			),
			'mortgage'     => array(
				'price' => array( 'type' => 'number', 'default' => 400000 ),
			),
			'agents'       => array(
				'limit' => array( 'type' => 'number', 'default' => 12 ),
			),
			'submit'       => array(),
			'dashboard'    => array(),
			'favourites'   => array(),
			'compare'      => array(),
		);
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		$by_block = array_flip( self::SHORTCODES );
		foreach ( $blocks as $name => $attributes ) {
			if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'dev3re/' . $name ) ) {
				continue;
			}
			$tag = $by_block[ $name ];
			register_block_type(
				'dev3re/' . $name,
				array(
					'api_version'     => 2,
					'editor_script'   => Assets::handle( 'block' ),
					'attributes'      => $attributes + array(
						'className' => array( 'type' => 'string' ),
						'align'     => array( 'type' => 'string' ),
					),
					'supports'        => array( 'align' => array( 'wide', 'full' ) ),
					'render_callback' => static function ( $attrs ) use ( $tag ) {
						$html = call_user_func( array( __CLASS__, 'shortcode_' . $tag ), (array) $attrs );
						$cls  = array();
						if ( ! empty( $attrs['align'] ) && in_array( $attrs['align'], array( 'wide', 'full' ), true ) ) {
							$cls[] = 'align' . $attrs['align'];
						}
						if ( ! empty( $attrs['className'] ) ) {
							$cls[] = $attrs['className'];
						}
						return $cls && '' !== $html ? '<div class="' . esc_attr( implode( ' ', $cls ) ) . '">' . $html . '</div>' : $html;
					},
				)
			);
		}
	}

	/**
	 * Front-end boot config (no secrets: the Google Maps key is a public browser key).
	 *
	 * @return array
	 */
	public static function config() {
		$provider = (string) Settings::get( 'map_provider', 'osm' );
		return array(
			'map'        => array(
				'provider' => $provider,
				'key'      => 'google' === $provider ? (string) Settings::get( 'google_maps_key', '' ) : '',
				'lat'      => (float) Settings::get( 'map_center_lat', 39.7817 ),
				'lng'      => (float) Settings::get( 'map_center_lng', -89.6501 ),
				'zoom'     => (int) Settings::get( 'map_zoom', 12 ),
			),
			'loginUrl'   => wp_login_url(),
			'loggedIn'   => is_user_logged_in(),
			'favourites' => is_user_logged_in() ? Favourites::get( get_current_user_id() ) : array(),
			'syncFavs'   => is_user_logged_in() && get_user_meta( get_current_user_id(), '_dev3re_fav_sync', true ) ? true : false,
			'features'   => array(
				'favourites' => (bool) Settings::get( 'favourites_enabled', true ),
				'compare'    => (bool) Settings::get( 'compare_enabled', true ),
			),
			'pages'      => array(
				'compare'    => Settings::page_url( 'compare' ),
				'favourites' => Settings::page_url( 'favourites' ),
				'search'     => Settings::page_url( 'search' ),
			),
			'compareMax' => 4,
			'i18n'       => array(
				'loading'       => __( 'Searching…', 'dev3-ai-real-estate' ),
				'error'         => __( 'Something went wrong. Please try again.', 'dev3-ai-real-estate' ),
				'saved'         => __( 'Saved', 'dev3-ai-real-estate' ),
				'save'          => __( 'Save', 'dev3-ai-real-estate' ),
				'favAdd'        => __( 'Save to favourites', 'dev3-ai-real-estate' ),
				'favRemove'     => __( 'Remove from favourites', 'dev3-ai-real-estate' ),
				'cmpAdd'        => __( 'Add to compare', 'dev3-ai-real-estate' ),
				'cmpRemove'     => __( 'Remove from compare', 'dev3-ai-real-estate' ),
				/* translators: %d: max properties */
				'cmpFull'       => __( 'You can compare up to %d properties.', 'dev3-ai-real-estate' ),
				/* translators: %d: count */
				'cmpBar'        => __( '%d selected to compare', 'dev3-ai-real-estate' ),
				'cmpOpen'       => __( 'Compare now', 'dev3-ai-real-estate' ),
				'cmpClear'      => __( 'Clear', 'dev3-ai-real-estate' ),
				'copied'        => __( 'Link copied', 'dev3-ai-real-estate' ),
				'sent'          => __( 'Thanks! Your message was sent. The agent will reply by email.', 'dev3-ai-real-estate' ),
				'required'      => __( 'Please fill in your name and a valid email address.', 'dev3-ai-real-estate' ),
				'consent'       => __( 'Please tick the consent box.', 'dev3-ai-real-estate' ),
				'alertSaved'    => __( 'Saved. We will email you when new properties match.', 'dev3-ai-real-estate' ),
				'alertConfirm'  => __( 'Almost done: check your inbox and confirm the alert.', 'dev3-ai-real-estate' ),
				'understood'    => __( 'Searching for:', 'dev3-ai-real-estate' ),
				'close'         => __( 'Close', 'dev3-ai-real-estate' ),
				'prev'          => __( 'Previous photo', 'dev3-ai-real-estate' ),
				'next'          => __( 'Next photo', 'dev3-ai-real-estate' ),
				'gallery'       => __( 'Photo gallery', 'dev3-ai-real-estate' ),
				'map'           => __( 'Map', 'dev3-ai-real-estate' ),
				'list'          => __( 'List', 'dev3-ai-real-estate' ),
				'approx'        => __( 'Approximate location', 'dev3-ai-real-estate' ),
				'confirmDel'    => __( 'Delete this? This cannot be undone.', 'dev3-ai-real-estate' ),
				'uploading'     => __( 'Uploading…', 'dev3-ai-real-estate' ),
				'working'       => __( 'Working on it… this can take up to a minute.', 'dev3-ai-real-estate' ),
				'pinFailed'     => __( 'Address not found. Drag the pin to the right spot.', 'dev3-ai-real-estate' ),
				'savedListing'  => __( 'Saved. Redirecting…', 'dev3-ai-real-estate' ),
				'moveLeft'      => __( 'Move left', 'dev3-ai-real-estate' ),
				'moveRight'     => __( 'Move right', 'dev3-ai-real-estate' ),
				'remove'        => __( 'Remove', 'dev3-ai-real-estate' ),
				'cover'         => __( 'Cover', 'dev3-ai-real-estate' ),
				'ai'            => __( 'AI', 'dev3-ai-real-estate' ),
				'planImage'     => __( 'Plan image', 'dev3-ai-real-estate' ),
				'dTitle'        => __( 'Title', 'dev3-ai-real-estate' ),
				'beds'          => __( 'Bedrooms', 'dev3-ai-real-estate' ),
				'baths'         => __( 'Bathrooms', 'dev3-ai-real-estate' ),
				'area'          => __( 'Area', 'dev3-ai-real-estate' ),
				'dSummary'      => __( 'Summary', 'dev3-ai-real-estate' ),
				'dDesc'         => __( 'Description', 'dev3-ai-real-estate' ),
				'dHighlights'   => __( 'Highlights (one per line)', 'dev3-ai-real-estate' ),
				'dFromFacts'    => __( 'Draft written from your facts', 'dev3-ai-real-estate' ),
				'dFromPhotos'   => __( 'Draft written from your photos and notes', 'dev3-ai-real-estate' ),
				/* translators: %d: number of photos */
				'photos'        => __( '%d photos', 'dev3-ai-real-estate' ),
				'featuresSeen'  => __( 'Features spotted (ticked below when you use the text):', 'dev3-ai-real-estate' ),
				'notSaved'      => __( 'Not saved yet. Check every detail, edit it, then use it.', 'dev3-ai-real-estate' ),
				'useText'       => __( 'Use this text', 'dev3-ai-real-estate' ),
				'discard'       => __( 'Discard', 'dev3-ai-real-estate' ),
				'hoodAi'        => __( 'Suggested neighbourhood text', 'dev3-ai-real-estate' ),
				'hoodOsm'       => __( 'Nearby places from OpenStreetMap', 'dev3-ai-real-estate' ),
				'aiLabelNote'   => __( 'AI-generated: the listing will show an “AI-generated” label. Check it locally.', 'dev3-ai-real-estate' ),
				'titleRequired' => __( 'Add a title for the property.', 'dev3-ai-real-estate' ),
			),
		);
	}

	/**
	 * Enqueues front-end assets.
	 */
	public static function enqueue() {
		if ( self::$enqueued ) {
			return;
		}
		self::$enqueued = true;
		if ( ! self::$registered ) {
			self::register();
		}
		Assets::enqueue( 'frontend' );
		$accent = (string) Settings::get( 'accent_color', '#0f766e' );
		wp_add_inline_style( Assets::handle( 'frontend' ), '.dev3re{--dev3re-accent:' . esc_attr( $accent ) . ';}' );
	}

	/**
	 * Head enqueue for our pages and posts that use our shortcodes/blocks (so CSS loads before paint).
	 */
	public static function maybe_enqueue() {
		if ( is_singular( array( Post_Types::PROPERTY, Post_Types::AGENT, Post_Types::AGENCY ) ) ) {
			self::enqueue();
			return;
		}
		$post = get_post();
		if ( $post && is_singular() && ( false !== strpos( $post->post_content, '[dev3re_' ) || false !== strpos( $post->post_content, '<!-- wp:dev3re/' ) ) ) {
			self::enqueue();
		}
	}

	/**
	 * Editor assets.
	 */
	public static function editor_assets() {
		$opts = static function ( $tax ) {
			$out = array();
			foreach ( Post_Types::terms( $tax ) as $t ) {
				$out[] = array(
					'value' => $t['slug'],
					'label' => $t['name'],
				);
			}
			return $out;
		};
		wp_add_inline_script(
			Assets::handle( 'block' ),
			'window.dev3reBlock = ' . wp_json_encode(
				array(
					'types'     => $opts( Post_Types::TYPE ),
					'locations' => $opts( Post_Types::LOCATION ),
				)
			) . ';',
			'before'
		);
		if ( wp_style_is( Assets::handle( 'frontend' ), 'registered' ) ) {
			wp_enqueue_style( Assets::handle( 'frontend' ) );
		}
	}

	/**
	 * Saved-search confirmation / unsubscribe links from emails.
	 */
	public static function alert_links() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- token-verified email links.
		if ( empty( $_GET['dev3re_alert'] ) || empty( $_GET['sid'] ) || empty( $_GET['token'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_GET['dev3re_alert'] ) );
		$id     = absint( $_GET['sid'] );
		$token  = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		// phpcs:enable
		$ok     = 'confirm' === $action ? Searches::confirm( $id, $token ) : ( 'unsubscribe' === $action ? Searches::unsubscribe( $id, $token ) : false );
		$notice = $ok ? ( 'confirm' === $action ? 'confirmed' : 'unsubscribed' ) : 'invalid';
		wp_safe_redirect( add_query_arg( 'dev3re_notice', $notice, Settings::page_url( 'search' ) ) );
		exit;
	}

	/**
	 * Notice banner for ?dev3re_notice=.
	 *
	 * @return string
	 */
	public static function notice_html() {
		$key  = isset( $_GET['dev3re_notice'] ) ? sanitize_key( wp_unslash( $_GET['dev3re_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msgs = array(
			'confirmed'    => array( 'success', __( 'Your property alert is active. We will email you when new listings match.', 'dev3-ai-real-estate' ) ),
			'unsubscribed' => array( 'success', __( 'You will not receive these alerts any more.', 'dev3-ai-real-estate' ) ),
			'invalid'      => array( 'error', __( 'This link is not valid any more.', 'dev3-ai-real-estate' ) ),
		);
		if ( ! isset( $msgs[ $key ] ) ) {
			return '';
		}
		return '<div class="dev3re-notice dev3re-notice--' . esc_attr( $msgs[ $key ][0] ) . '" role="status">' . esc_html( $msgs[ $key ][1] ) . '</div>';
	}

	/**
	 * Property archive and taxonomy archives open the search page with that filter.
	 */
	public static function archive_redirect() {
		$page = (int) Settings::get( 'page_search', 0 );
		if ( ! $page || 'publish' !== get_post_status( $page ) ) {
			return;
		}
		$url = '';
		if ( is_tax( Post_Types::TYPE ) ) {
			$url = add_query_arg( 'type', get_queried_object()->slug, get_permalink( $page ) );
		} elseif ( is_tax( Post_Types::LOCATION ) ) {
			$url = add_query_arg( 'location', get_queried_object()->slug, get_permalink( $page ) );
		} elseif ( is_tax( Post_Types::FEATURE ) ) {
			$url = add_query_arg( 'features', get_queried_object()->slug, get_permalink( $page ) );
		} elseif ( is_post_type_archive( Post_Types::PROPERTY ) ) {
			$url = (string) get_permalink( $page );
		}
		if ( $url ) {
			wp_safe_redirect( $url, 302 );
			exit;
		}
	}

	/**
	 * Flags the first page load after login so browser favourites are merged into the account.
	 *
	 * @param string   $login Login.
	 * @param \WP_User $user  User.
	 */
	public static function mark_login( $login, $user ) {
		unset( $login );
		update_user_meta( $user->ID, '_dev3re_fav_sync', 1 );
	}

	/**
	 * Agents land on their front-end dashboard after logging in (unless a redirect was requested).
	 *
	 * @param string             $redirect  Redirect.
	 * @param string             $requested Requested redirect.
	 * @param \WP_User|\WP_Error $user      User.
	 * @return string
	 */
	public static function login_redirect( $redirect, $requested, $user ) {
		if ( ! $user instanceof \WP_User || ( $requested && admin_url() !== $requested && admin_url( 'profile.php' ) !== $requested ) ) {
			return $redirect;
		}
		if ( in_array( Post_Types::ROLE, (array) $user->roles, true ) && ! user_can( $user, 'edit_posts' ) && (int) Settings::get( 'page_dashboard', 0 ) ) {
			return Settings::page_url( 'dashboard' );
		}
		return $redirect;
	}

	/**
	 * Default pages (activation / setup button).
	 *
	 * @return array Created page ids by key.
	 */
	public static function create_pages() {
		$pages = array(
			'search'     => array( __( 'Property Search', 'dev3-ai-real-estate' ), '<!-- wp:dev3re/search {"align":"wide"} /-->' ),
			'submit'     => array( __( 'Submit Property', 'dev3-ai-real-estate' ), '<!-- wp:dev3re/submit {"align":"wide"} /-->' ),
			'dashboard'  => array( __( 'Agent Dashboard', 'dev3-ai-real-estate' ), '<!-- wp:dev3re/dashboard {"align":"wide"} /-->' ),
			'favourites' => array( __( 'Saved Properties', 'dev3-ai-real-estate' ), '<!-- wp:dev3re/favourites {"align":"wide"} /-->' ),
			'compare'    => array( __( 'Compare Properties', 'dev3-ai-real-estate' ), '<!-- wp:dev3re/compare {"align":"wide"} /-->' ),
		);
		$patch = array();
		foreach ( $pages as $key => $page ) {
			$current = (int) Settings::get( 'page_' . $key, 0 );
			if ( $current && get_post( $current ) && 'trash' !== get_post_status( $current ) ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $page[0],
					'post_content' => $page[1],
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$patch[ 'page_' . $key ] = (int) $id;
			}
		}
		if ( $patch ) {
			Settings_Store::update( $patch );
		}
		return $patch;
	}

	/**
	 * Shortcodes.
	 *
	 * @param array|string $atts Atts.
	 * @return string
	 */
	public static function shortcode_search( $atts ) {
		self::enqueue();
		return Templates::search( (array) $atts );
	}

	/**
	 * [dev3re_quick_search listing_type="sale"].
	 *
	 * @param array|string $atts Atts.
	 * @return string
	 */
	public static function shortcode_quick_search( $atts ) {
		self::enqueue();
		return Templates::quick_search( (array) $atts );
	}

	/**
	 * [dev3re_properties listing_type="" type="" location="" featured="no" sort="newest" limit="6" columns="3"].
	 *
	 * @param array|string $atts Atts.
	 * @return string
	 */
	public static function shortcode_properties( $atts ) {
		self::enqueue();
		return Templates::grid( (array) $atts );
	}

	/**
	 * [dev3re_mortgage price="400000"].
	 *
	 * @param array|string $atts Atts.
	 * @return string
	 */
	public static function shortcode_mortgage( $atts ) {
		self::enqueue();
		return Templates::mortgage( (array) $atts );
	}

	/**
	 * [dev3re_agents limit="12"].
	 *
	 * @param array|string $atts Atts.
	 * @return string
	 */
	public static function shortcode_agents( $atts ) {
		self::enqueue();
		return Templates::agents( (array) $atts );
	}

	/**
	 * [dev3re_submit].
	 *
	 * @return string
	 */
	public static function shortcode_submit() {
		self::enqueue();
		return Submission::render();
	}

	/**
	 * [dev3re_dashboard].
	 *
	 * @return string
	 */
	public static function shortcode_dashboard() {
		self::enqueue();
		return Dashboard::render();
	}

	/**
	 * [dev3re_favourites].
	 *
	 * @return string
	 */
	public static function shortcode_favourites() {
		self::enqueue();
		return Templates::favourites();
	}

	/**
	 * [dev3re_compare].
	 *
	 * @return string
	 */
	public static function shortcode_compare() {
		self::enqueue();
		return Templates::compare_shell();
	}
}
