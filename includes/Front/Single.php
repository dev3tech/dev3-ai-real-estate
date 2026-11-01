<?php
/**
 * Single property / agent / agency layouts, injected through the_content (block and classic themes).
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Front;

use Dev3\AiRealEstate\Data\Agents;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Search\Search;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Templates helpers return escaped markup.

/**
 * Static renderer.
 */
class Single {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'content' ), 20 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'hide_theme_thumbnail' ), 10, 2 );
		add_filter( 'render_block_core/post-featured-image', array( __CLASS__, 'hide_featured_block' ), 10, 2 );
		add_action( 'wp', array( __CLASS__, 'count_view' ) );
	}

	/**
	 * Counts a view.
	 */
	public static function count_view() {
		if ( is_singular( Post_Types::PROPERTY ) && ! is_preview() && ! current_user_can( 'edit_post', get_queried_object_id() ) ) {
			Property::count_view( (int) get_queried_object_id() );
		}
	}

	/**
	 * The gallery replaces the theme's featured image on single property pages.
	 *
	 * @param string $html    HTML.
	 * @param int    $post_id Post.
	 * @return string
	 */
	public static function hide_theme_thumbnail( $html, $post_id ) {
		if ( is_singular( array( Post_Types::PROPERTY, Post_Types::AGENT, Post_Types::AGENCY ) ) && (int) get_queried_object_id() === (int) $post_id && ! doing_filter( 'the_content' ) ) {
			return '';
		}
		return $html;
	}

	/**
	 * Same for the block theme's Post Featured Image block.
	 *
	 * @param string $html  Block HTML.
	 * @param array  $block Block.
	 * @return string
	 */
	public static function hide_featured_block( $html, $block ) {
		unset( $block );
		return is_singular( array( Post_Types::PROPERTY, Post_Types::AGENT, Post_Types::AGENCY ) ) && get_the_ID() === get_queried_object_id() ? '' : $html;
	}

	/**
	 * Wraps content on our single pages.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function content( $content ) {
		if ( ! is_singular( array( Post_Types::PROPERTY, Post_Types::AGENT, Post_Types::AGENCY ) ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() ) {
			return $content;
		}
		remove_filter( 'the_content', array( __CLASS__, 'content' ), 20 );
		$id   = (int) get_the_ID();
		$type = get_post_type( $id );
		if ( Post_Types::PROPERTY === $type ) {
			$html = self::property( $id, $content );
		} elseif ( Post_Types::AGENT === $type ) {
			$html = self::agent( $id, $content );
		} else {
			$html = self::agency( $id, $content );
		}
		add_filter( 'the_content', array( __CLASS__, 'content' ), 20 );
		return $html;
	}

	/**
	 * Gallery (hero mosaic on desktop, swipe carousel on phones) + lightbox data.
	 *
	 * @param int   $id  Property.
	 * @param int[] $ids Attachments.
	 * @return string
	 */
	public static function gallery( $id, array $ids ) {
		if ( ! $ids && has_post_thumbnail( $id ) ) {
			$ids = array( (int) get_post_thumbnail_id( $id ) );
		}
		$ids = array_values( array_filter( $ids, 'wp_attachment_is_image' ) );
		if ( ! $ids ) {
			return '';
		}
		$full = array();
		foreach ( $ids as $aid ) {
			$src    = wp_get_attachment_image_src( $aid, 'large' );
			$full[] = array(
				'src' => $src ? $src[0] : '',
				'w'   => $src ? (int) $src[1] : 0,
				'h'   => $src ? (int) $src[2] : 0,
				'alt' => (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ),
			);
		}
		$count = count( $ids );
		$html  = '<div class="dev3re-gallery dev3re-gallery--n' . min( 5, $count ) . '" data-dev3re-gallery="' . esc_attr( wp_json_encode( $full ) ) . '">';
		$html .= '<div class="dev3re-gallery__track" tabindex="0" role="group" aria-label="' . esc_attr__( 'Property photos', 'dev3-ai-real-estate' ) . '">';
		foreach ( $ids as $i => $aid ) {
			$alt   = (string) get_post_meta( $aid, '_wp_attachment_image_alt', true );
			$html .= '<button type="button" class="dev3re-gallery__item" data-index="' . (int) $i . '" aria-label="' . esc_attr( sprintf( /* translators: 1: photo number, 2: total */ __( 'Open photo %1$d of %2$d', 'dev3-ai-real-estate' ), $i + 1, $count ) ) . '">' . wp_get_attachment_image(
				$aid,
				0 === $i ? 'large' : 'medium_large',
				false,
				array(
					'loading'       => 0 === $i ? 'eager' : 'lazy',
					'fetchpriority' => 0 === $i ? 'high' : 'auto',
					'alt'           => $alt,
					'sizes'         => 0 === $i ? '(max-width: 768px) 100vw, 60vw' : '(max-width: 768px) 100vw, 25vw',
				)
			) . '</button>';
		}
		$html .= '</div>';
		$html .= '<span class="dev3re-gallery__counter" aria-hidden="true"><span data-dev3re-gcount>1</span> / ' . (int) $count . '</span>';
		if ( $count > 1 ) {
			$html .= '<button type="button" class="dev3re-btn dev3re-btn--light dev3re-gallery__all" data-index="0">' . Templates::icon( 'grid', 16 ) . '<span>' . esc_html( sprintf( /* translators: %d: photo count */ _n( 'Show %d photo', 'Show all %d photos', $count, 'dev3-ai-real-estate' ), $count ) ) . '</span></button>';
		}
		return $html . '</div>';
	}

	/**
	 * Property page.
	 *
	 * @param int    $id      Property.
	 * @param string $content Rendered description.
	 * @return string
	 */
	public static function property( $id, $content ) {
		Frontend::enqueue();
		$f        = Property::fields( $id );
		$c        = Property::card( $id );
		$agent_id = (int) $f['agent_id'];
		$types    = get_the_terms( $id, Post_Types::TYPE );
		$features = get_the_terms( $id, Post_Types::FEATURE );
		$tour     = Property::embed_url( $f['tour_url'] );
		$video    = Property::embed_url( $f['video_url'] );
		$closed   = in_array( $f['market'], array( 'sold', 'rented' ), true );
		$details  = array(
			__( 'Reference', 'dev3-ai-real-estate' )      => $f['reference'],
			__( 'Property type', 'dev3-ai-real-estate' )  => $c['type'],
			__( 'Status', 'dev3-ai-real-estate' )         => Templates::deal_label( $f['listing_type'] ) . ( 'available' !== $f['market'] ? ' · ' . Property::market_label( $f['market'] ) : '' ),
			__( 'Price', 'dev3-ai-real-estate' )          => $c['price_html'],
			__( 'Price per area', 'dev3-ai-real-estate' ) => $c['price_per'],
			__( 'Bedrooms', 'dev3-ai-real-estate' )       => $f['beds'] ? (string) $f['beds'] : '',
			__( 'Bathrooms', 'dev3-ai-real-estate' )      => $f['baths'] ? (string) ( $f['baths'] + 0 ) : '',
			__( 'Living area', 'dev3-ai-real-estate' )    => $c['area'],
			__( 'Lot size', 'dev3-ai-real-estate' )       => $f['lot_size'] ? number_format_i18n( $f['lot_size'] ) . ' ' . Property::unit_label() : '',
			__( 'Parking', 'dev3-ai-real-estate' )        => $f['garages'] ? (string) $f['garages'] : '',
			__( 'Year built', 'dev3-ai-real-estate' )     => $f['year_built'] ? (string) $f['year_built'] : '',
			__( 'Floor', 'dev3-ai-real-estate' )          => $f['floor'] ? ( $f['floors'] ? sprintf( /* translators: 1: floor, 2: floors */ __( '%1$d of %2$d', 'dev3-ai-real-estate' ), $f['floor'], $f['floors'] ) : (string) $f['floor'] ) : '',
			__( 'Energy class', 'dev3-ai-real-estate' )   => $f['energy_class'],
		);
		$details  = array_filter( $details, static fn( $v ) => '' !== (string) $v );
		ob_start();
		?>
		<div class="dev3re dev3re-single alignwide" data-dev3re-single="<?php echo (int) $id; ?>">
			<?php echo self::gallery( $id, $f['gallery'] ); ?>
			<div class="dev3re-single__top">
				<div class="dev3re-single__tags">
					<span class="dev3re-tag dev3re-tag--<?php echo esc_attr( $f['listing_type'] ); ?>"><?php echo esc_html( Templates::deal_label( $f['listing_type'] ) ); ?></span>
					<?php if ( $types && ! is_wp_error( $types ) ) : ?>
						<span class="dev3re-tag dev3re-tag--plain"><?php echo esc_html( $types[0]->name ); ?></span>
					<?php endif; ?>
					<?php if ( 'available' !== $f['market'] ) : ?>
						<span class="dev3re-tag dev3re-tag--<?php echo esc_attr( $f['market'] ); ?>"><?php echo esc_html( Property::market_label( $f['market'] ) ); ?></span>
					<?php endif; ?>
					<?php if ( $f['featured'] ) : ?>
						<span class="dev3re-tag dev3re-tag--featured"><?php esc_html_e( 'Featured', 'dev3-ai-real-estate' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="dev3re-single__headrow">
					<div>
						<p class="dev3re-single__price"><?php echo esc_html( $c['price_html'] ); ?></p>
						<?php if ( $c['address'] ) : ?>
							<p class="dev3re-single__addr"><?php echo Templates::icon( 'pin', 16 ); ?><span><?php echo esc_html( $c['address'] ); ?></span></p>
						<?php endif; ?>
					</div>
					<div class="dev3re-single__actions">
						<?php echo Templates::action_buttons( $id, 'single' ); ?>
						<button type="button" class="dev3re-act" data-dev3re-share data-title="<?php echo esc_attr( get_the_title( $id ) ); ?>" data-url="<?php echo esc_url( (string) get_permalink( $id ) ); ?>"><?php echo Templates::icon( 'share' ); ?><span><?php esc_html_e( 'Share', 'dev3-ai-real-estate' ); ?></span></button>
						<button type="button" class="dev3re-act dev3re-hide-sm" data-dev3re-print><?php echo Templates::icon( 'print' ); ?><span><?php esc_html_e( 'Print', 'dev3-ai-real-estate' ); ?></span></button>
					</div>
				</div>
				<?php echo Templates::facts( $c, 'dev3re-facts dev3re-facts--lg' ); ?>
			</div>

			<div class="dev3re-single__layout">
				<div class="dev3re-single__main">
					<nav class="dev3re-jump" aria-label="<?php esc_attr_e( 'On this page', 'dev3-ai-real-estate' ); ?>">
						<a href="#dev3re-overview"><?php esc_html_e( 'Overview', 'dev3-ai-real-estate' ); ?></a>
						<?php if ( $f['floor_plans'] ) : ?>
							<a href="#dev3re-plans"><?php esc_html_e( 'Floor plans', 'dev3-ai-real-estate' ); ?></a>
						<?php endif; ?>
						<?php if ( $tour || $video ) : ?>
							<a href="#dev3re-tour"><?php esc_html_e( 'Virtual tour', 'dev3-ai-real-estate' ); ?></a>
						<?php endif; ?>
						<?php if ( '' !== $f['lat'] ) : ?>
							<a href="#dev3re-location"><?php esc_html_e( 'Location', 'dev3-ai-real-estate' ); ?></a>
						<?php endif; ?>
						<?php if ( Settings::get( 'mortgage_enabled', true ) && 'sale' === $f['listing_type'] && $f['price'] && ! $closed ) : ?>
							<a href="#dev3re-mortgage"><?php esc_html_e( 'Mortgage', 'dev3-ai-real-estate' ); ?></a>
						<?php endif; ?>
					</nav>

					<section id="dev3re-overview" class="dev3re-sec">
						<h2 class="dev3re-sec__title"><?php esc_html_e( 'About this property', 'dev3-ai-real-estate' ); ?></h2>
						<?php if ( $f['highlights'] ) : ?>
							<ul class="dev3re-highlights">
								<?php foreach ( $f['highlights'] as $h ) : ?>
									<li><?php echo Templates::icon( 'check', 16 ); ?><span><?php echo esc_html( $h ); ?></span></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<div class="dev3re-prose"><?php echo $content; ?></div>
					</section>

					<?php if ( $details ) : ?>
						<section class="dev3re-sec">
							<h2 class="dev3re-sec__title"><?php esc_html_e( 'Details', 'dev3-ai-real-estate' ); ?></h2>
							<dl class="dev3re-details">
								<?php foreach ( $details as $label => $value ) : ?>
									<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
								<?php endforeach; ?>
							</dl>
						</section>
					<?php endif; ?>

					<?php if ( $features && ! is_wp_error( $features ) ) : ?>
						<section class="dev3re-sec">
							<h2 class="dev3re-sec__title"><?php esc_html_e( 'Features', 'dev3-ai-real-estate' ); ?></h2>
							<ul class="dev3re-features">
								<?php foreach ( $features as $t ) : ?>
									<li><?php echo Templates::icon( 'check', 16 ); ?><span><?php echo esc_html( $t->name ); ?></span></li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endif; ?>

					<?php if ( $f['floor_plans'] ) : ?>
						<section id="dev3re-plans" class="dev3re-sec">
							<h2 class="dev3re-sec__title"><?php esc_html_e( 'Floor plans', 'dev3-ai-real-estate' ); ?></h2>
							<div class="dev3re-plans">
								<?php foreach ( $f['floor_plans'] as $i => $plan ) : ?>
									<details class="dev3re-plan" <?php echo 0 === $i ? 'open' : ''; ?>>
										<summary><span class="dev3re-plan__title"><?php echo Templates::icon( 'plan', 16 ); ?><?php echo esc_html( $plan['title'] ? $plan['title'] : sprintf( /* translators: %d: plan number */ __( 'Plan %d', 'dev3-ai-real-estate' ), $i + 1 ) ); ?></span>
											<span class="dev3re-plan__facts">
												<?php
												$bits = array_filter(
													array(
														/* translators: %d: bedrooms */
														$plan['beds'] ? sprintf( _n( '%d bed', '%d beds', $plan['beds'], 'dev3-ai-real-estate' ), $plan['beds'] ) : '',
														/* translators: %s: bathrooms */
														$plan['baths'] ? sprintf( __( '%s bath', 'dev3-ai-real-estate' ), $plan['baths'] + 0 ) : '',
														$plan['area'] ? number_format_i18n( $plan['area'] ) . ' ' . Property::unit_label() : '',
													)
												);
												echo esc_html( implode( ' · ', $bits ) );
												?>
											</span>
										</summary>
										<div class="dev3re-plan__body">
											<?php if ( $plan['image'] ) : ?>
												<a href="<?php echo esc_url( (string) wp_get_attachment_image_url( $plan['image'], 'full' ) ); ?>" target="_blank" rel="noopener"><?php echo wp_get_attachment_image( $plan['image'], 'large', false, array( 'loading' => 'lazy', 'alt' => $plan['title'] ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound ?></a>
											<?php endif; ?>
											<?php if ( $plan['notes'] ) : ?>
												<p><?php echo esc_html( $plan['notes'] ); ?></p>
											<?php endif; ?>
										</div>
									</details>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endif; ?>

					<?php if ( $tour || $video ) : ?>
						<section id="dev3re-tour" class="dev3re-sec">
							<h2 class="dev3re-sec__title"><?php echo esc_html( $tour ? __( 'Virtual tour', 'dev3-ai-real-estate' ) : __( 'Video', 'dev3-ai-real-estate' ) ); ?></h2>
							<?php foreach ( array_filter( array( $tour, $video ) ) as $embed ) : ?>
								<div class="dev3re-embed" data-dev3re-embed="<?php echo esc_url( $embed ); ?>">
									<button type="button" class="dev3re-embed__play"><?php echo Templates::icon( $embed === $tour ? 'tour' : 'play', 28 ); ?><span><?php echo esc_html( $embed === $tour ? __( 'Start the 3D tour', 'dev3-ai-real-estate' ) : __( 'Play video', 'dev3-ai-real-estate' ) ); ?></span><small><?php echo esc_html( sprintf( /* translators: %s: host */ __( 'Loads content from %s', 'dev3-ai-real-estate' ), (string) wp_parse_url( $embed, PHP_URL_HOST ) ) ); ?></small></button>
								</div>
							<?php endforeach; ?>
						</section>
					<?php endif; ?>

					<?php if ( '' !== $f['lat'] && '' !== $f['lng'] ) : ?>
						<section id="dev3re-location" class="dev3re-sec">
							<h2 class="dev3re-sec__title"><?php esc_html_e( 'Location', 'dev3-ai-real-estate' ); ?></h2>
							<div class="dev3re-map dev3re-map--single" data-dev3re-map data-zoom="<?php echo $f['hide_address'] ? 13 : 15; ?>" data-markers="<?php echo esc_attr( wp_json_encode( array( array( 'id' => $id, 'lat' => (float) $f['lat'], 'lng' => (float) $f['lng'], 'title' => get_the_title( $id ), 'price' => $c['price_short'], 'approx' => (bool) $f['hide_address'] ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound ?>" role="region" aria-label="<?php esc_attr_e( 'Map', 'dev3-ai-real-estate' ); ?>"></div>
							<?php if ( $f['nearby'] ) : ?>
								<ul class="dev3re-nearby">
									<?php foreach ( $f['nearby'] as $line ) : ?>
										<li><?php echo Templates::icon( 'pin', 14 ); ?><span><?php echo esc_html( $line ); ?></span></li>
									<?php endforeach; ?>
								</ul>
								<p class="dev3re-muted dev3re-small"><?php esc_html_e( 'Nearby places from OpenStreetMap.', 'dev3-ai-real-estate' ); ?></p>
							<?php endif; ?>
						</section>
					<?php endif; ?>

					<?php if ( '' !== trim( $f['neighbourhood'] ) ) : ?>
						<section class="dev3re-sec dev3re-hood">
							<h2 class="dev3re-sec__title"><?php esc_html_e( 'The neighbourhood', 'dev3-ai-real-estate' ); ?></h2>
							<?php if ( $f['neighbourhood_ai'] ) : ?>
								<p class="dev3re-aimark"><?php echo Templates::icon( 'sparkles', 14 ); ?><span><?php esc_html_e( 'AI-generated summary. Please check details locally.', 'dev3-ai-real-estate' ); ?></span></p>
							<?php endif; ?>
							<div class="dev3re-prose"><?php echo wpautop( esc_html( $f['neighbourhood'] ) ); ?></div>
						</section>
					<?php endif; ?>

					<?php if ( Settings::get( 'mortgage_enabled', true ) && 'sale' === $f['listing_type'] && $f['price'] && ! $f['price_on_request'] && ! $closed ) : ?>
						<section id="dev3re-mortgage" class="dev3re-sec">
							<h2 class="dev3re-sec__title"><?php esc_html_e( 'Mortgage calculator', 'dev3-ai-real-estate' ); ?></h2>
							<?php echo Templates::mortgage( array( 'price' => $f['price'] ) ); ?>
						</section>
					<?php endif; ?>
				</div>

				<aside class="dev3re-single__aside">
					<div class="dev3re-box dev3re-box--sticky" id="dev3re-contact">
						<p class="dev3re-box__price"><?php echo esc_html( $c['price_html'] ); ?></p>
						<?php echo $agent_id ? Templates::agent_card( $agent_id ) : ''; ?>
						<?php if ( $closed ) : ?>
							<p class="dev3re-muted"><?php esc_html_e( 'This property is no longer available. Ask the agent about similar homes.', 'dev3-ai-real-estate' ); ?></p>
						<?php endif; ?>
						<?php echo Templates::enquiry_form( $id, $agent_id ); ?>
					</div>
				</aside>
			</div>
			<?php echo self::similar( $id, $f ); ?>
			<a class="dev3re-btn dev3re-btn--primary dev3re-mobilecta" href="#dev3re-contact"><?php echo Templates::icon( 'mail' ); ?><span><?php esc_html_e( 'Contact agent', 'dev3-ai-real-estate' ); ?></span></a>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Similar properties (same deal + type, nearby price).
	 *
	 * @param int   $id Property.
	 * @param array $f  Fields.
	 * @return string
	 */
	public static function similar( $id, array $f ) {
		$types = wp_get_object_terms( $id, Post_Types::TYPE, array( 'fields' => 'ids' ) );
		$locs  = wp_get_object_terms( $id, Post_Types::LOCATION, array( 'fields' => 'ids' ) );
		$args  = Search::sanitize(
			array(
				'listing_type' => $f['listing_type'],
				'type'         => is_wp_error( $types ) ? array() : $types,
				'location'     => is_wp_error( $locs ) ? array() : $locs,
				'per_page'     => 4,
			)
		);
		$ids   = array_values( array_diff( Search::run( $args )['ids'], array( $id ) ) );
		if ( count( $ids ) < 3 ) {
			$args['location'] = array();
			$ids              = array_values( array_unique( array_merge( $ids, array_diff( Search::run( $args )['ids'], array( $id ) ) ) ) );
		}
		$ids = array_slice( $ids, 0, 3 );
		if ( ! $ids ) {
			return '';
		}
		return '<section class="dev3re-sec dev3re-similar"><h2 class="dev3re-sec__title">' . esc_html__( 'Similar properties', 'dev3-ai-real-estate' ) . '</h2>' . Templates::cards( $ids, 3 ) . '</section>';
	}

	/**
	 * Agent page.
	 *
	 * @param int    $id      Agent.
	 * @param string $content Bio.
	 * @return string
	 */
	public static function agent( $id, $content ) {
		Frontend::enqueue();
		$a     = Agents::get( $id );
		$res   = Search::run(
			Search::sanitize(
				array(
					'agent'    => $id,
					'per_page' => 24,
				)
			)
		);
		$html  = '<div class="dev3re dev3re-profile alignwide"><div class="dev3re-profile__layout"><div class="dev3re-profile__main">';
		$html .= '<div class="dev3re-box">' . Templates::agent_card( $id ) . '</div>';
		$meta  = array_filter(
			array(
				__( 'License', 'dev3-ai-real-estate' )   => $a['fields']['license'],
				__( 'Languages', 'dev3-ai-real-estate' ) => $a['fields']['languages'],
			)
		);
		if ( $meta ) {
			$html .= '<dl class="dev3re-details">';
			foreach ( $meta as $label => $value ) {
				$html .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
			}
			$html .= '</dl>';
		}
		$html .= '<div class="dev3re-prose">' . $content . '</div>';
		$html .= '<h2 class="dev3re-sec__title">' . esc_html( Templates::count_label( $res['total'] ) ) . '</h2>';
		$html .= $res['ids'] ? Templates::cards( $res['ids'], 2 ) : Templates::empty_state( __( 'No active listings right now', 'dev3-ai-real-estate' ) );
		$html .= '</div><aside class="dev3re-profile__aside"><div class="dev3re-box dev3re-box--sticky"><p class="dev3re-box__title">' . esc_html(
			sprintf(
				/* translators: %s: agent name */
				__( 'Contact %s', 'dev3-ai-real-estate' ),
				$a['name']
			)
		) . '</p>' . Templates::enquiry_form( 0, $id ) . '</div></aside></div></div>';
		return $html;
	}

	/**
	 * Agency page.
	 *
	 * @param int    $id      Agency.
	 * @param string $content Description.
	 * @return string
	 */
	public static function agency( $id, $content ) {
		Frontend::enqueue();
		$a      = Agents::get( $id );
		$agents = Agents::agency_agents( $id );
		$ids    = array();
		foreach ( $agents as $agent ) {
			$ids = array_merge( $ids, Search::run( Search::sanitize( array( 'agent' => $agent, 'per_page' => 12 ) ) )['ids'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		}
		$ids  = array_slice( array_unique( $ids ), 0, 24 );
		$html = '<div class="dev3re dev3re-profile alignwide">';
		if ( $a['photo'] ) {
			$html .= '<div class="dev3re-profile__logo">' . wp_get_attachment_image( $a['photo'], 'medium', false, array( 'alt' => $a['name'] ) ) . '</div>'; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		}
		$info = array();
		foreach ( array( 'phone' => 'phone', 'email' => 'mail', 'website' => 'globe', 'address' => 'pin' ) as $key => $icon ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			$v = $a['fields'][ $key ];
			if ( '' === $v ) {
				continue;
			}
			if ( 'phone' === $key ) {
				$v = '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $v ) ) . '">' . esc_html( $v ) . '</a>';
			} elseif ( 'email' === $key ) {
				$v = '<a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>';
			} elseif ( 'website' === $key ) {
				$v = '<a href="' . esc_url( $v ) . '" target="_blank" rel="noopener">' . esc_html( (string) wp_parse_url( $v, PHP_URL_HOST ) ) . '</a>';
			} else {
				$v = esc_html( $v );
			}
			$info[] = '<li>' . Templates::icon( $icon, 16 ) . '<span>' . $v . '</span></li>';
		}
		if ( $info ) {
			$html .= '<ul class="dev3re-contactlist">' . implode( '', $info ) . '</ul>';
		}
		$html .= '<div class="dev3re-prose">' . $content . '</div>';
		if ( $agents ) {
			$html .= '<h2 class="dev3re-sec__title">' . esc_html__( 'Our agents', 'dev3-ai-real-estate' ) . '</h2>' . Templates::agents( array( 'agency' => $id ) );
		}
		$html .= '<h2 class="dev3re-sec__title">' . esc_html__( 'Listings', 'dev3-ai-real-estate' ) . '</h2>';
		$html .= $ids ? Templates::cards( $ids, 3 ) : Templates::empty_state( __( 'No active listings right now', 'dev3-ai-real-estate' ) );
		return $html . '</div>';
	}
}
