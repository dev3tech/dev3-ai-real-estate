<?php
/**
 * Front-end property submission for agents ([dev3re_submit]).
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Front;

use Dev3\AiRealEstate\Core3\Api\Ai;
use Dev3\AiRealEstate\Data\Agents;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Templates helpers return escaped markup.

/**
 * Static renderer.
 */
class Submission {

	/**
	 * Login / permission gate. Returns '' when the user may submit.
	 *
	 * @return string
	 */
	public static function gate() {
		if ( ! Settings::get( 'submission_enabled', true ) ) {
			return '<div class="dev3re">' . Templates::empty_state( __( 'Property submission is closed.', 'dev3-ai-real-estate' ) ) . '</div>';
		}
		if ( ! is_user_logged_in() ) {
			$html  = '<div class="dev3re dev3re-gate"><p class="dev3re-gate__title">' . esc_html__( 'Sign in to list a property', 'dev3-ai-real-estate' ) . '</p><p>' . esc_html__( 'Agents can add and manage their listings here.', 'dev3-ai-real-estate' ) . '</p><p class="dev3re-gate__btns">';
			$html .= '<a class="dev3re-btn dev3re-btn--primary" href="' . esc_url( wp_login_url( (string) get_permalink() ) ) . '">' . esc_html__( 'Log in', 'dev3-ai-real-estate' ) . '</a>';
			if ( get_option( 'users_can_register' ) ) {
				$html .= '<a class="dev3re-btn dev3re-btn--ghost" href="' . esc_url( wp_registration_url() ) . '">' . esc_html__( 'Create an account', 'dev3-ai-real-estate' ) . '</a>';
			}
			return $html . '</p></div>';
		}
		if ( ! Agents::is_agent( get_current_user_id() ) && ! current_user_can( 'edit_others_posts' ) ) {
			$html = '<div class="dev3re dev3re-gate"><p class="dev3re-gate__title">' . esc_html__( 'Only agents can list properties', 'dev3-ai-real-estate' ) . '</p>';
			if ( Settings::get( 'agent_signup', false ) ) {
				$html .= '<p>' . esc_html__( 'Create your agent profile to start listing.', 'dev3-ai-real-estate' ) . '</p><p class="dev3re-gate__btns"><button type="button" class="dev3re-btn dev3re-btn--primary" data-dev3re-become-agent>' . esc_html__( 'Become an agent', 'dev3-ai-real-estate' ) . '</button></p>';
			} else {
				$html .= '<p>' . esc_html__( 'Ask the site owner to add you as an agent.', 'dev3-ai-real-estate' ) . '</p>';
			}
			return $html . '</div>';
		}
		return '';
	}

	/**
	 * Submission form.
	 *
	 * @return string
	 */
	public static function render() {
		$gate = self::gate();
		if ( '' !== $gate ) {
			return $gate;
		}
		$user = get_current_user_id();
		$id   = isset( $_GET['property'] ) ? absint( $_GET['property'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data = null;
		if ( $id ) {
			if ( ! self::can_edit( $id, $user ) ) {
				return '<div class="dev3re">' . Templates::empty_state( __( 'You cannot edit this property.', 'dev3-ai-real-estate' ) ) . '</div>';
			}
			$data = Property::get( $id );
		}
		$f        = $data ? $data['fields'] : array_map( static fn( $d ) => $d[1], Property::FIELDS );
		$uid      = 'dev3re-f';
		$types    = Post_Types::terms( Post_Types::TYPE );
		$features = Post_Types::terms( Post_Types::FEATURE );
		$ai_on    = Ai::enabled( 'submission' ) && Ai::status()['key_set'];
		$publish  = 'publish' === Settings::get( 'submission_moderation', 'pending' ) || ( $data && 'publish' === $data['status'] );
		$boot     = array(
			'id'      => $id,
			'images'  => $data ? $data['images'] : array(),
			'plans'   => $data ? $data['fields']['floor_plans'] : array(),
			'lat'     => '' === $f['lat'] ? null : (float) $f['lat'],
			'lng'     => '' === $f['lng'] ? null : (float) $f['lng'],
			'ai'      => $ai_on,
			'aiHood'  => $ai_on && Settings::get( 'ai_neighbourhood', true ),
			'aiDesc'  => $ai_on && Settings::get( 'ai_describe', true ),
			'success' => Settings::page_url( 'dashboard' ),
		);
		$num      = static function ( $name, $label, $value, $attrs = '' ) use ( $uid ) {
			return '<div class="dev3re-field"><label for="' . esc_attr( $uid . '-' . $name ) . '">' . esc_html( $label ) . '</label><input id="' . esc_attr( $uid . '-' . $name ) . '" type="number" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ? (string) ( $value + 0 ) : '' ) . '" ' . $attrs . '></div>';
		};
		$text     = static function ( $name, $label, $value, $attrs = '' ) use ( $uid ) {
			return '<div class="dev3re-field"><label for="' . esc_attr( $uid . '-' . $name ) . '">' . esc_html( $label ) . '</label><input id="' . esc_attr( $uid . '-' . $name ) . '" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" ' . $attrs . '></div>';
		};
		ob_start();
		?>
		<form class="dev3re dev3re-submit" data-dev3re-submit="<?php echo esc_attr( wp_json_encode( $boot ) ); ?>" novalidate>
			<div class="dev3re-submit__head">
				<p class="dev3re-submit__title"><?php echo esc_html( $id ? __( 'Edit property', 'dev3-ai-real-estate' ) : __( 'List a property', 'dev3-ai-real-estate' ) ); ?></p>
				<a class="dev3re-btn dev3re-btn--link" href="<?php echo esc_url( Settings::page_url( 'dashboard' ) ); ?>"><?php esc_html_e( 'My dashboard', 'dev3-ai-real-estate' ); ?></a>
			</div>
			<div class="dev3re-notice dev3re-notice--error" data-dev3re-submit-error role="alert" hidden></div>

			<fieldset class="dev3re-fs">
				<legend><?php esc_html_e( 'Photos', 'dev3-ai-real-estate' ); ?></legend>
				<p class="dev3re-help"><?php esc_html_e( 'Add up to 30 photos. The first photo is the cover. Drag or use the arrows to reorder.', 'dev3-ai-real-estate' ); ?></p>
				<ul class="dev3re-uploads" data-dev3re-gallery-list></ul>
				<label class="dev3re-drop" data-dev3re-drop>
					<?php echo Templates::icon( 'upload', 24 ); ?>
					<span><?php esc_html_e( 'Drop photos here or tap to choose', 'dev3-ai-real-estate' ); ?></span>
					<input type="file" accept="image/jpeg,image/png,image/webp" multiple data-dev3re-upload class="dev3re-sr">
				</label>
			</fieldset>

			<fieldset class="dev3re-fs">
				<legend><?php esc_html_e( 'Basics', 'dev3-ai-real-estate' ); ?></legend>
				<fieldset class="dev3re-seg">
					<legend class="dev3re-sr"><?php esc_html_e( 'Listing type', 'dev3-ai-real-estate' ); ?></legend>
					<label><input type="radio" name="listing_type" value="sale" <?php checked( $f['listing_type'], 'sale' ); ?>><span><?php esc_html_e( 'For sale', 'dev3-ai-real-estate' ); ?></span></label>
					<label><input type="radio" name="listing_type" value="rent" <?php checked( $f['listing_type'], 'rent' ); ?>><span><?php esc_html_e( 'For rent', 'dev3-ai-real-estate' ); ?></span></label>
				</fieldset>
				<div class="dev3re-formgrid">
					<div class="dev3re-field">
						<label for="<?php echo esc_attr( $uid ); ?>-type"><?php esc_html_e( 'Property type', 'dev3-ai-real-estate' ); ?></label>
						<select id="<?php echo esc_attr( $uid ); ?>-type" name="type">
							<option value=""><?php esc_html_e( 'Choose…', 'dev3-ai-real-estate' ); ?></option>
							<?php foreach ( $types as $t ) : ?>
								<option value="<?php echo (int) $t['id']; ?>" <?php selected( $data && in_array( $t['id'], $data['type'], true ) ); ?>><?php echo esc_html( $t['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php if ( $id ) : ?>
						<div class="dev3re-field">
							<label for="<?php echo esc_attr( $uid ); ?>-market"><?php esc_html_e( 'Availability', 'dev3-ai-real-estate' ); ?></label>
							<select id="<?php echo esc_attr( $uid ); ?>-market" name="market">
								<?php foreach ( array( 'available', 'under_offer', 'sold', 'rented' ) as $m ) : ?>
									<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $f['market'], $m ); ?>><?php echo esc_html( Property::market_label( $m ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>
				</div>
				<?php echo $text( 'title', __( 'Title', 'dev3-ai-real-estate' ), $data ? $data['title'] : '', 'required maxlength="200"' ); ?>
				<div class="dev3re-field">
					<label for="<?php echo esc_attr( $uid ); ?>-content"><?php esc_html_e( 'Description', 'dev3-ai-real-estate' ); ?></label>
					<textarea id="<?php echo esc_attr( $uid ); ?>-content" name="content" rows="8"><?php echo esc_textarea( $data ? wp_strip_all_tags( str_replace( '</p>', "</p>\n\n", $data['content'] ) ) : '' ); ?></textarea>
				</div>
				<input type="hidden" name="excerpt" value="<?php echo esc_attr( $data ? $data['excerpt'] : '' ); ?>">
				<input type="hidden" name="highlights" value="<?php echo esc_attr( implode( "\n", (array) $f['highlights'] ) ); ?>">
				<div class="dev3re-aibox" data-dev3re-aibox>
					<div class="dev3re-field">
						<label for="<?php echo esc_attr( $uid ); ?>-notes"><?php esc_html_e( 'Notes for the description (private)', 'dev3-ai-real-estate' ); ?></label>
						<textarea id="<?php echo esc_attr( $uid ); ?>-notes" name="notes" rows="3" placeholder="<?php esc_attr_e( 'e.g. renovated kitchen 2023, south-facing garden, quiet street, new boiler', 'dev3-ai-real-estate' ); ?>"><?php echo esc_textarea( (string) $f['notes'] ); ?></textarea>
					</div>
					<div class="dev3re-aibox__btns">
						<?php if ( $boot['aiDesc'] ) : ?>
							<button type="button" class="dev3re-btn dev3re-btn--accent" data-dev3re-ai-describe><?php echo Templates::icon( 'sparkles' ); ?><span><?php esc_html_e( 'Write the description from my photos', 'dev3-ai-real-estate' ); ?></span></button>
						<?php endif; ?>
						<button type="button" class="dev3re-btn dev3re-btn--ghost" data-dev3re-template-describe><?php esc_html_e( 'Start from the facts', 'dev3-ai-real-estate' ); ?></button>
					</div>
					<div class="dev3re-draft" data-dev3re-draft hidden></div>
				</div>
			</fieldset>

			<fieldset class="dev3re-fs">
				<legend><?php esc_html_e( 'Price', 'dev3-ai-real-estate' ); ?></legend>
				<div class="dev3re-formgrid">
					<?php echo $num( 'price', sprintf( /* translators: %s: currency */ __( 'Price (%s)', 'dev3-ai-real-estate' ), (string) Settings::get( 'currency', 'USD' ) ), $f['price'], 'min="0" step="1" inputmode="decimal"' ); ?>
					<div class="dev3re-field" data-dev3re-rent-only>
						<label for="<?php echo esc_attr( $uid ); ?>-period"><?php esc_html_e( 'Rent per', 'dev3-ai-real-estate' ); ?></label>
						<select id="<?php echo esc_attr( $uid ); ?>-period" name="price_period">
							<?php foreach ( array( 'month' => __( 'Month', 'dev3-ai-real-estate' ), 'week' => __( 'Week', 'dev3-ai-real-estate' ), 'day' => __( 'Day', 'dev3-ai-real-estate' ), 'year' => __( 'Year', 'dev3-ai-real-estate' ) ) as $k => $label ) : // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['price_period'], $k ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<label class="dev3re-check"><input type="checkbox" name="price_on_request" value="1" <?php checked( $f['price_on_request'] ); ?>><span><?php esc_html_e( 'Show “Price on request” instead of the price', 'dev3-ai-real-estate' ); ?></span></label>
			</fieldset>

			<fieldset class="dev3re-fs">
				<legend><?php esc_html_e( 'Details', 'dev3-ai-real-estate' ); ?></legend>
				<div class="dev3re-formgrid dev3re-formgrid--4">
					<?php
					echo $num( 'beds', __( 'Bedrooms', 'dev3-ai-real-estate' ), $f['beds'], 'min="0" max="50" inputmode="numeric"' );
					echo $num( 'baths', __( 'Bathrooms', 'dev3-ai-real-estate' ), $f['baths'], 'min="0" max="50" step="0.5" inputmode="decimal"' );
					/* translators: %s: area unit */
					echo $num( 'area', sprintf( __( 'Living area (%s)', 'dev3-ai-real-estate' ), Property::unit_label() ), $f['area'], 'min="0" inputmode="decimal"' );
					/* translators: %s: area unit */
					echo $num( 'lot_size', sprintf( __( 'Lot size (%s)', 'dev3-ai-real-estate' ), Property::unit_label() ), $f['lot_size'], 'min="0" inputmode="decimal"' );
					echo $num( 'garages', __( 'Parking spaces', 'dev3-ai-real-estate' ), $f['garages'], 'min="0" max="50" inputmode="numeric"' );
					echo $num( 'year_built', __( 'Year built', 'dev3-ai-real-estate' ), $f['year_built'], 'min="1700" max="2100" inputmode="numeric"' );
					echo $num( 'floor', __( 'Floor', 'dev3-ai-real-estate' ), $f['floor'], 'min="0" max="300" inputmode="numeric"' );
					echo $text( 'reference', __( 'Reference', 'dev3-ai-real-estate' ), $f['reference'], 'maxlength="40"' );
					?>
				</div>
				<?php if ( $features ) : ?>
					<fieldset class="dev3re-checks">
						<legend><?php esc_html_e( 'Features', 'dev3-ai-real-estate' ); ?></legend>
						<?php foreach ( $features as $t ) : ?>
							<label><input type="checkbox" name="features[]" value="<?php echo (int) $t['id']; ?>" <?php checked( $data && in_array( $t['id'], $data['features'], true ) ); ?>><span><?php echo esc_html( $t['name'] ); ?></span></label>
						<?php endforeach; ?>
					</fieldset>
				<?php endif; ?>
			</fieldset>

			<fieldset class="dev3re-fs">
				<legend><?php esc_html_e( 'Location', 'dev3-ai-real-estate' ); ?></legend>
				<?php echo $text( 'street', __( 'Street address', 'dev3-ai-real-estate' ), $f['street'], 'autocomplete="street-address"' ); ?>
				<div class="dev3re-formgrid dev3re-formgrid--4">
					<?php
					echo $text( 'city', __( 'City', 'dev3-ai-real-estate' ), $f['city'], 'autocomplete="address-level2"' );
					echo $text( 'region', __( 'State / region', 'dev3-ai-real-estate' ), $f['region'], 'autocomplete="address-level1"' );
					echo $text( 'postcode', __( 'Postcode', 'dev3-ai-real-estate' ), $f['postcode'], 'autocomplete="postal-code"' );
					echo $text( 'country', __( 'Country', 'dev3-ai-real-estate' ), $f['country'], 'autocomplete="country-name"' );
					?>
				</div>
				<div class="dev3re-inline">
					<button type="button" class="dev3re-btn dev3re-btn--ghost" data-dev3re-geocode><?php echo Templates::icon( 'pin' ); ?><span><?php esc_html_e( 'Find on map', 'dev3-ai-real-estate' ); ?></span></button>
					<label class="dev3re-check"><input type="checkbox" name="hide_address" value="1" <?php checked( $f['hide_address'] ); ?>><span><?php esc_html_e( 'Hide the exact address (show the area only)', 'dev3-ai-real-estate' ); ?></span></label>
				</div>
				<div class="dev3re-map dev3re-map--picker" data-dev3re-picker role="application" aria-label="<?php esc_attr_e( 'Map: click or drag the pin to set the exact location', 'dev3-ai-real-estate' ); ?>"></div>
				<input type="hidden" name="lat" value="<?php echo esc_attr( (string) $f['lat'] ); ?>">
				<input type="hidden" name="lng" value="<?php echo esc_attr( (string) $f['lng'] ); ?>">
				<div class="dev3re-field">
					<label for="<?php echo esc_attr( $uid ); ?>-hood"><?php esc_html_e( 'About the neighbourhood', 'dev3-ai-real-estate' ); ?></label>
					<textarea id="<?php echo esc_attr( $uid ); ?>-hood" name="neighbourhood" rows="4"><?php echo esc_textarea( (string) $f['neighbourhood'] ); ?></textarea>
					<input type="hidden" name="neighbourhood_ai" value="<?php echo $f['neighbourhood_ai'] ? '1' : '0'; ?>">
					<input type="hidden" name="nearby" value="<?php echo esc_attr( implode( "\n", (array) $f['nearby'] ) ); ?>">
				</div>
				<div class="dev3re-aibox__btns">
					<?php if ( $boot['aiHood'] ) : ?>
						<button type="button" class="dev3re-btn dev3re-btn--accent" data-dev3re-ai-hood="1"><?php echo Templates::icon( 'sparkles' ); ?><span><?php esc_html_e( 'Suggest neighbourhood highlights', 'dev3-ai-real-estate' ); ?></span></button>
					<?php endif; ?>
					<button type="button" class="dev3re-btn dev3re-btn--ghost" data-dev3re-ai-hood="0"><?php esc_html_e( 'List nearby places', 'dev3-ai-real-estate' ); ?></button>
				</div>
				<div class="dev3re-draft" data-dev3re-hood-draft hidden></div>
			</fieldset>

			<fieldset class="dev3re-fs">
				<legend><?php esc_html_e( 'Floor plans, tour and video', 'dev3-ai-real-estate' ); ?></legend>
				<div class="dev3re-plans-edit" data-dev3re-plans></div>
				<button type="button" class="dev3re-btn dev3re-btn--ghost" data-dev3re-add-plan><?php echo Templates::icon( 'plan' ); ?><span><?php esc_html_e( 'Add a floor plan', 'dev3-ai-real-estate' ); ?></span></button>
				<div class="dev3re-formgrid">
					<?php
					echo $text( 'tour_url', __( 'Virtual tour link (Matterport, Kuula, YouTube…)', 'dev3-ai-real-estate' ), $f['tour_url'], 'inputmode="url" placeholder="https://"' );
					echo $text( 'video_url', __( 'Video link (YouTube or Vimeo)', 'dev3-ai-real-estate' ), $f['video_url'], 'inputmode="url" placeholder="https://"' );
					?>
				</div>
			</fieldset>

			<div class="dev3re-submit__foot">
				<button type="submit" class="dev3re-btn dev3re-btn--ghost" name="draft" value="1" data-dev3re-draft-btn><?php esc_html_e( 'Save draft', 'dev3-ai-real-estate' ); ?></button>
				<button type="submit" class="dev3re-btn dev3re-btn--primary"><?php echo esc_html( $publish ? __( 'Publish', 'dev3-ai-real-estate' ) : __( 'Submit for review', 'dev3-ai-real-estate' ) ); ?></button>
			</div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Whether the user may edit a property from the front end.
	 *
	 * @param int $id      Property.
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function can_edit( $id, $user_id ) {
		if ( Post_Types::PROPERTY !== get_post_type( $id ) ) {
			return false;
		}
		if ( user_can( $user_id, 'edit_others_posts' ) ) {
			return true;
		}
		$agent = Agents::agent_for_user( $user_id );
		return (int) get_post_field( 'post_author', $id ) === (int) $user_id || ( $agent && (int) Property::value( $id, 'agent_id' ) === $agent );
	}
}
