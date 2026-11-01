<?php
/**
 * Front-end dashboard ([dev3re_dashboard]): agents' listings, lead inbox and profile; everyone's favourites and alerts.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Front;

use Dev3\AiRealEstate\Data\Agents;
use Dev3\AiRealEstate\Data\Favourites;
use Dev3\AiRealEstate\Data\Leads;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Data\Searches;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Templates helpers return escaped markup.

/**
 * Static renderer.
 */
class Dashboard {

	/**
	 * Property ids managed by a user (authored or assigned to their agent profile).
	 *
	 * @param int $user_id User.
	 * @return int[]
	 */
	public static function property_ids( $user_id ) {
		$agent     = Agents::agent_for_user( $user_id );
		$args      = array(
			'post_type'      => Post_Types::PROPERTY,
			'post_status'    => array( 'publish', 'pending', 'draft' ),
			'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'fields'         => 'ids',
			'orderby'        => 'modified',
		);
		$mine      = get_posts( array_merge( $args, array( 'author' => (int) $user_id ) ) );
		$agent_ids = $agent ? get_posts(
			array_merge(
				$args,
				array(
					'meta_key'   => '_dev3re_agent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
					'meta_value' => $agent, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
				)
			)
		) : array();
		return array_values( array_unique( array_map( 'intval', array_merge( $mine, $agent_ids ) ) ) );
	}

	/**
	 * Renders the dashboard.
	 *
	 * @return string
	 */
	public static function render() {
		if ( ! is_user_logged_in() ) {
			return '<div class="dev3re dev3re-gate"><p class="dev3re-gate__title">' . esc_html__( 'Sign in to see your dashboard', 'dev3-ai-real-estate' ) . '</p><p class="dev3re-gate__btns"><a class="dev3re-btn dev3re-btn--primary" href="' . esc_url( wp_login_url( (string) get_permalink() ) ) . '">' . esc_html__( 'Log in', 'dev3-ai-real-estate' ) . '</a></p></div>';
		}
		$user     = get_current_user_id();
		$is_agent = Agents::is_agent( $user );
		$tabs     = array();
		if ( $is_agent ) {
			$tabs['listings'] = __( 'My listings', 'dev3-ai-real-estate' );
			$tabs['leads']    = __( 'Leads', 'dev3-ai-real-estate' );
			$tabs['profile']  = __( 'Profile', 'dev3-ai-real-estate' );
		}
		$tabs['favourites'] = __( 'Saved', 'dev3-ai-real-estate' );
		if ( Settings::get( 'saved_searches_enabled', true ) ) {
			$tabs['searches'] = __( 'Alerts', 'dev3-ai-real-estate' );
		}
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab   = isset( $tabs[ $tab ] ) ? $tab : (string) array_key_first( $tabs );
		$html  = '<div class="dev3re dev3re-dash" data-dev3re-dash>';
		$html .= '<nav class="dev3re-tabs" aria-label="' . esc_attr__( 'Dashboard', 'dev3-ai-real-estate' ) . '">';
		foreach ( $tabs as $key => $label ) {
			$badge = '';
			if ( 'leads' === $key ) {
				$new   = Leads::count(
					array(
						'agent_id' => Agents::agent_for_user( $user ),
						'status'   => 'new',
					)
				);
				$badge = $new && Agents::agent_for_user( $user ) ? ' <span class="dev3re-count">' . (int) $new . '</span>' : '';
			}
			$html .= '<a href="' . esc_url( add_query_arg( 'tab', $key, (string) get_permalink() ) ) . '"' . ( $key === $tab ? ' aria-current="page" class="is-active"' : '' ) . '>' . esc_html( $label ) . $badge . '</a>';
		}
		$html .= '</nav><div class="dev3re-dash__panel">';
		switch ( $tab ) {
			case 'listings':
				$html .= self::listings( $user );
				break;
			case 'leads':
				$html .= self::leads( $user );
				break;
			case 'profile':
				$html .= self::profile( $user );
				break;
			case 'searches':
				$html .= self::searches( $user );
				break;
			default:
				$ids   = Favourites::get( $user );
				$html .= $ids ? Templates::cards( $ids, 3 ) : Templates::empty_state( __( 'No saved properties yet', 'dev3-ai-real-estate' ), __( 'Tap the heart on any property to save it here.', 'dev3-ai-real-estate' ) );
		}
		return $html . '</div></div>';
	}

	/**
	 * My listings.
	 *
	 * @param int $user User.
	 * @return string
	 */
	private static function listings( $user ) {
		$ids  = self::property_ids( $user );
		$html = '<div class="dev3re-dash__head"><p class="dev3re-dash__title">' . esc_html( Templates::count_label( count( $ids ) ) ) . '</p><a class="dev3re-btn dev3re-btn--primary" href="' . esc_url( Settings::page_url( 'submit' ) ) . '">' . Templates::icon( 'home' ) . '<span>' . esc_html__( 'Add property', 'dev3-ai-real-estate' ) . '</span></a></div>';
		if ( ! $ids ) {
			return $html . Templates::empty_state( __( 'You have no listings yet', 'dev3-ai-real-estate' ), __( 'Add your first property: upload photos and let the form help you write it.', 'dev3-ai-real-estate' ) );
		}
		$statuses = array(
			'publish' => __( 'Live', 'dev3-ai-real-estate' ),
			'pending' => __( 'In review', 'dev3-ai-real-estate' ),
			'draft'   => __( 'Draft', 'dev3-ai-real-estate' ),
		);
		$html    .= '<ul class="dev3re-rows">';
		foreach ( $ids as $id ) {
			$c      = Property::card( $id );
			$status = get_post_status( $id );
			$thumb  = get_the_post_thumbnail( $id, 'thumbnail', array( 'class' => 'dev3re-rows__img', 'alt' => '', 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			$html  .= '<li class="dev3re-rows__item" data-id="' . (int) $id . '">' . ( $thumb ? $thumb : '<span class="dev3re-rows__img dev3re-prop__ph">' . Templates::icon( 'home', 20 ) . '</span>' );
			$html  .= '<div class="dev3re-rows__main"><a class="dev3re-rows__title" href="' . esc_url( $c['url'] ) . '">' . esc_html( $c['title'] ) . '</a><span class="dev3re-rows__meta">' . esc_html( $c['price_html'] ) . ' · <span class="dev3re-pill dev3re-pill--' . esc_attr( $status ) . '">' . esc_html( $statuses[ $status ] ?? $status ) . '</span>' . ( 'available' !== $c['market'] ? ' · ' . esc_html( Property::market_label( $c['market'] ) ) : '' ) . '</span>';
			$html  .= '<span class="dev3re-rows__meta">' . Templates::icon( 'eye', 14 ) . esc_html( number_format_i18n( (int) get_post_meta( $id, '_dev3re_views', true ) ) ) . ' · ' . Templates::icon( 'mail', 14 ) . esc_html( (string) Leads::count( array( 'property_id' => $id ) ) ) . '</span></div>';
			$html  .= '<div class="dev3re-rows__actions"><a class="dev3re-btn dev3re-btn--ghost dev3re-btn--sm" href="' . esc_url( add_query_arg( 'property', $id, Settings::page_url( 'submit' ) ) ) . '">' . Templates::icon( 'edit', 16 ) . '<span>' . esc_html__( 'Edit', 'dev3-ai-real-estate' ) . '</span></a>';
			$html  .= '<label class="dev3re-sr" for="dev3re-mk-' . (int) $id . '">' . esc_html__( 'Availability', 'dev3-ai-real-estate' ) . '</label><select id="dev3re-mk-' . (int) $id . '" class="dev3re-select-sm" data-dev3re-market="' . (int) $id . '">';
			foreach ( array( 'available', 'under_offer', 'sold', 'rented' ) as $m ) {
				$html .= '<option value="' . esc_attr( $m ) . '"' . selected( $c['market'], $m, false ) . '>' . esc_html( Property::market_label( $m ) ) . '</option>';
			}
			$html .= '</select><button type="button" class="dev3re-btn dev3re-btn--ghost dev3re-btn--sm dev3re-btn--danger" data-dev3re-delete-property="' . (int) $id . '" aria-label="' . esc_attr( sprintf( /* translators: %s: title */ __( 'Delete %s', 'dev3-ai-real-estate' ), $c['title'] ) ) . '">' . Templates::icon( 'trash', 16 ) . '</button></div></li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Lead inbox.
	 *
	 * @param int $user User.
	 * @return string
	 */
	private static function leads( $user ) {
		$agent = Agents::agent_for_user( $user );
		if ( ! $agent ) {
			return Templates::empty_state( __( 'Complete your agent profile to receive leads.', 'dev3-ai-real-estate' ) );
		}
		$res    = Leads::query(
			array(
				'agent_id' => $agent,
				'per_page' => 50,
			)
		);
		$labels = array(
			'new'       => __( 'New', 'dev3-ai-real-estate' ),
			'contacted' => __( 'Contacted', 'dev3-ai-real-estate' ),
			'viewing'   => __( 'Viewing booked', 'dev3-ai-real-estate' ),
			'closed'    => __( 'Closed', 'dev3-ai-real-estate' ),
			'spam'      => __( 'Spam', 'dev3-ai-real-estate' ),
		);
		if ( ! $res['items'] ) {
			return Templates::empty_state( __( 'No leads yet', 'dev3-ai-real-estate' ), __( 'Enquiries from your listings will show up here and in your email.', 'dev3-ai-real-estate' ) );
		}
		$html = '<ul class="dev3re-leads">';
		foreach ( $res['items'] as $l ) {
			$html .= '<li class="dev3re-lead' . ( 'new' === $l['status'] ? ' is-new' : '' ) . '" data-id="' . (int) $l['id'] . '"><div class="dev3re-lead__head"><strong>' . esc_html( $l['name'] ) . '</strong><span class="dev3re-muted">' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $l['created_at'] . ' UTC' ) ) ) . '</span></div>';
			$html .= '<p class="dev3re-lead__meta">' . ( 'viewing' === $l['kind'] ? '<span class="dev3re-pill dev3re-pill--viewing">' . Templates::icon( 'calendar', 14 ) . esc_html( __( 'Viewing', 'dev3-ai-real-estate' ) . ( $l['viewing_at'] ? ' · ' . $l['viewing_at'] : '' ) ) . '</span> ' : '' ) . ( $l['property'] ? '<a href="' . esc_url( $l['property_url'] ) . '">' . esc_html( $l['property'] ) . '</a>' : '' ) . '</p>';
			if ( '' !== $l['message'] ) {
				$html .= '<p class="dev3re-lead__msg">' . esc_html( $l['message'] ) . '</p>';
			}
			$html .= '<div class="dev3re-lead__actions"><a class="dev3re-btn dev3re-btn--primary dev3re-btn--sm" href="mailto:' . esc_attr( $l['email'] ) . '?subject=' . rawurlencode( $l['property'] ? $l['property'] : get_bloginfo( 'name' ) ) . '">' . Templates::icon( 'mail', 16 ) . '<span>' . esc_html__( 'Reply', 'dev3-ai-real-estate' ) . '</span></a>';
			if ( $l['phone'] ) {
				$html .= '<a class="dev3re-btn dev3re-btn--ghost dev3re-btn--sm" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $l['phone'] ) ) . '">' . Templates::icon( 'phone', 16 ) . '<span>' . esc_html( $l['phone'] ) . '</span></a>';
			}
			$html .= '<label class="dev3re-sr" for="dev3re-ls-' . (int) $l['id'] . '">' . esc_html__( 'Lead status', 'dev3-ai-real-estate' ) . '</label><select id="dev3re-ls-' . (int) $l['id'] . '" class="dev3re-select-sm" data-dev3re-lead-status="' . (int) $l['id'] . '">';
			foreach ( $labels as $k => $label ) {
				$html .= '<option value="' . esc_attr( $k ) . '"' . selected( $l['status'], $k, false ) . '>' . esc_html( $label ) . '</option>';
			}
			$html .= '</select></div></li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Agent profile form.
	 *
	 * @param int $user User.
	 * @return string
	 */
	private static function profile( $user ) {
		$agent = Agents::ensure_agent( $user );
		$a     = $agent ? Agents::get( $agent ) : null;
		if ( ! $a ) {
			return '';
		}
		$labels = array(
			'position'  => __( 'Job title', 'dev3-ai-real-estate' ),
			'email'     => __( 'Email for enquiries', 'dev3-ai-real-estate' ),
			'phone'     => __( 'Phone', 'dev3-ai-real-estate' ),
			'mobile'    => __( 'Mobile', 'dev3-ai-real-estate' ),
			'whatsapp'  => __( 'WhatsApp number', 'dev3-ai-real-estate' ),
			'license'   => __( 'License number', 'dev3-ai-real-estate' ),
			'languages' => __( 'Languages', 'dev3-ai-real-estate' ),
			'website'   => __( 'Website', 'dev3-ai-real-estate' ),
		);
		$html   = '<form class="dev3re-profileform" data-dev3re-profile><div class="dev3re-field"><label for="dev3re-pf-name">' . esc_html__( 'Name', 'dev3-ai-real-estate' ) . '</label><input id="dev3re-pf-name" name="name" required value="' . esc_attr( $a['name'] ) . '"></div><div class="dev3re-formgrid">';
		foreach ( $labels as $key => $label ) {
			$html .= '<div class="dev3re-field"><label for="dev3re-pf-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><input id="dev3re-pf-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="' . ( 'email' === $key ? 'email' : ( 'website' === $key ? 'url' : 'text' ) ) . '" value="' . esc_attr( $a['fields'][ $key ] ) . '"></div>';
		}
		$html .= '</div><div class="dev3re-field"><label for="dev3re-pf-bio">' . esc_html__( 'About you', 'dev3-ai-real-estate' ) . '</label><textarea id="dev3re-pf-bio" name="bio" rows="5">' . esc_textarea( wp_strip_all_tags( $a['bio'] ) ) . '</textarea></div>';
		$html .= '<div class="dev3re-inline"><button type="submit" class="dev3re-btn dev3re-btn--primary">' . esc_html__( 'Save profile', 'dev3-ai-real-estate' ) . '</button><a class="dev3re-btn dev3re-btn--link" href="' . esc_url( $a['url'] ) . '">' . esc_html__( 'View public profile', 'dev3-ai-real-estate' ) . '</a></div><p role="status" class="dev3re-muted" data-dev3re-profile-msg></p></form>';
		return $html;
	}

	/**
	 * Saved searches.
	 *
	 * @param int $user User.
	 * @return string
	 */
	private static function searches( $user ) {
		$res = Searches::query(
			array(
				'user_id'  => $user,
				'per_page' => 50,
			)
		);
		if ( ! $res['items'] ) {
			return Templates::empty_state( __( 'No property alerts yet', 'dev3-ai-real-estate' ), __( 'Use “Save search” on the search page to get emails about new listings.', 'dev3-ai-real-estate' ) );
		}
		$freq = array(
			'instant' => __( 'As listed', 'dev3-ai-real-estate' ),
			'daily'   => __( 'Daily', 'dev3-ai-real-estate' ),
			'weekly'  => __( 'Weekly', 'dev3-ai-real-estate' ),
		);
		$html = '<ul class="dev3re-rows">';
		foreach ( $res['items'] as $s ) {
			$html .= '<li class="dev3re-rows__item" data-id="' . (int) $s['id'] . '"><span class="dev3re-rows__img dev3re-prop__ph">' . Templates::icon( 'bell', 20 ) . '</span><div class="dev3re-rows__main"><a class="dev3re-rows__title" href="' . esc_url( $s['url'] ) . '">' . esc_html( $s['label'] ) . '</a><span class="dev3re-rows__meta">' . esc_html( $freq[ $s['frequency'] ] ?? $s['frequency'] ) . ( $s['confirmed'] ? '' : ' · ' . esc_html__( 'Waiting for email confirmation', 'dev3-ai-real-estate' ) ) . '</span></div><div class="dev3re-rows__actions"><button type="button" class="dev3re-btn dev3re-btn--ghost dev3re-btn--sm dev3re-btn--danger" data-dev3re-delete-search="' . (int) $s['id'] . '">' . Templates::icon( 'trash', 16 ) . '<span>' . esc_html__( 'Delete', 'dev3-ai-real-estate' ) . '</span></button></div></li>';
		}
		return $html . '</ul>';
	}
}
