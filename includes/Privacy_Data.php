<?php
/**
 * GDPR exporter/eraser for leads and saved searches.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate;

use Dev3\AiRealEstate\Core3\Core\Privacy;
use Dev3\AiRealEstate\Data\Leads;
use Dev3\AiRealEstate\Data\Searches;

defined( 'ABSPATH' ) || exit;

/**
 * Static registration.
 */
class Privacy_Data {

	/**
	 * Registers exporter + eraser and the policy text.
	 */
	public static function init() {
		Privacy::add_exporter( __( 'Property enquiries and alerts', 'dev3-ai-real-estate' ), array( __CLASS__, 'export' ) );
		Privacy::add_eraser( __( 'Property enquiries and alerts', 'dev3-ai-real-estate' ), array( __CLASS__, 'erase' ) );
		add_filter( 'dev3re_privacy_policy_text', array( __CLASS__, 'policy' ) );
	}

	/**
	 * Policy snippet addition.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function policy( $text ) {
		return $text . '<p>' . esc_html__( 'When you send an enquiry about a property we store your name, email, phone number and message and email them to the listing agent. When you save a search we store your email and the search filters to send you alerts; every alert contains an unsubscribe link. Maps are loaded from OpenStreetMap (or Google Maps if the site uses it).', 'dev3-ai-real-estate' ) . '</p>';
	}

	/**
	 * Exporter.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function export( $email, $page = 1 ) {
		unset( $page );
		$items = array();
		foreach ( Leads::by_email( $email ) as $l ) {
			$items[] = array(
				'group_id'    => 'dev3re-leads',
				'group_label' => __( 'Property enquiries', 'dev3-ai-real-estate' ),
				'item_id'     => 'dev3re-lead-' . $l['id'],
				'data'        => array(
					array(
						'name'  => __( 'Date', 'dev3-ai-real-estate' ),
						'value' => $l['created_at'],
					),
					array(
						'name'  => __( 'Name', 'dev3-ai-real-estate' ),
						'value' => $l['name'],
					),
					array(
						'name'  => __( 'Phone', 'dev3-ai-real-estate' ),
						'value' => $l['phone'],
					),
					array(
						'name'  => __( 'Property', 'dev3-ai-real-estate' ),
						'value' => $l['property_id'] ? get_the_title( (int) $l['property_id'] ) : '',
					),
					array(
						'name'  => __( 'Message', 'dev3-ai-real-estate' ),
						'value' => $l['message'],
					),
				),
			);
		}
		foreach ( Searches::query(
			array(
				'email'    => $email,
				'per_page' => 100,
			)
		)['items'] as $s ) {
			$items[] = array(
				'group_id'    => 'dev3re-searches',
				'group_label' => __( 'Property alerts', 'dev3-ai-real-estate' ),
				'item_id'     => 'dev3re-search-' . $s['id'],
				'data'        => array(
					array(
						'name'  => __( 'Search', 'dev3-ai-real-estate' ),
						'value' => $s['label'],
					),
					array(
						'name'  => __( 'Frequency', 'dev3-ai-real-estate' ),
						'value' => $s['frequency'],
					),
				),
			);
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Eraser.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function erase( $email, $page = 1 ) {
		unset( $page );
		$removed = Leads::anonymise( $email );
		foreach ( Searches::query(
			array(
				'email'    => $email,
				'per_page' => 200,
			)
		)['items'] as $s ) {
			Searches::delete( $s['id'] );
			++$removed;
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
