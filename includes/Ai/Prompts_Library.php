<?php
/**
 * Versioned prompt templates and output schemas for every DEV3 AI feature of the plugin.
 *
 * Override with the `dev3re_prompt_<id>` filter (e.g. `dev3re_prompt_dev3re_describe`).
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Ai;

use Dev3\AiRealEstate\Core3\Api\Prompts;

defined( 'ABSPATH' ) || exit;

/**
 * Registers prompts.
 */
class Prompts_Library {

	const DESCRIBE      = 'dev3re_describe';
	const NEIGHBOURHOOD = 'dev3re_neighbourhood';
	const NL_SEARCH     = 'dev3re_nl_search';

	/**
	 * String schema helper.
	 *
	 * @param int $max Max length.
	 * @return array
	 */
	public static function str( $max ) {
		return array(
			'type'      => 'STRING',
			'maxLength' => $max,
		);
	}

	/**
	 * String list helper.
	 *
	 * @param int $items Max items.
	 * @param int $max   Max length per item.
	 * @return array
	 */
	public static function list_of( $items, $max ) {
		return array(
			'type'     => 'ARRAY',
			'maxItems' => $items,
			'items'    => self::str( $max ),
		);
	}

	/**
	 * Registers all templates.
	 */
	public static function register() {
		Prompts::register(
			self::DESCRIBE,
			array(
				'version'     => '1',
				'task'        => 'describe',
				'profile'     => 'heavy',
				'max_tokens'  => 6144,
				'temperature' => 0.6,
				'system'      => 'You are an experienced real estate copywriter. You write honest, specific, vivid property descriptions that help buyers and renters picture living there. Use only what you can see in the photos and what the facts and agent notes state. Never invent measurements, prices, room counts, legal or planning details, school catchments or views you cannot see. Avoid discriminatory or steering language (no references to the ideal buyer\'s family status, religion, ethnicity, age or disability) and avoid clichés like "nestled" or "boasting". Write in {{language}}.',
				'template'    => "Write a {{tone}} listing for this {{listing}} using the attached photos ({{photo_count}}), the facts and the agent's notes.\n\nOutput:\n- title: an appealing headline (max 80 characters), no price.\n- excerpt: one or two sentences for listing cards (max 300 characters).\n- description: 3-5 short paragraphs separated by a blank line (250-400 words): first impression and layout, the living spaces and kitchen, bedrooms and bathrooms, outdoor space and extras, then location only from the facts.\n- highlights: up to 6 short selling points (max 60 characters each).\n- features: choose only from this list what the photos or notes clearly show: {{features}}.\n- observations: up to 6 short notes about what each photo shows (e.g. \"Open-plan kitchen with island\"), for the agent to check.\n\nFACTS\n{{facts}}\n\nAGENT NOTES\n{{notes}}\n\nCURRENT DESCRIPTION (improve it, keep its facts)\n{{current}}",
				'schema'      => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'title'        => self::str( 80 ),
						'excerpt'      => self::str( 300 ),
						'description'  => self::str( 3000 ),
						'highlights'   => self::list_of( 6, 60 ),
						'features'     => self::list_of( 16, 60 ),
						'observations' => self::list_of( 6, 120 ),
					),
					'required'   => array( 'title', 'excerpt', 'description', 'highlights', 'features' ),
				),
			)
		);

		Prompts::register(
			self::NEIGHBOURHOOD,
			array(
				'version'     => '1',
				'task'        => 'neighbourhood',
				'profile'     => 'fast',
				'max_tokens'  => 2048,
				'temperature' => 0.4,
				'system'      => 'You write short, factual neighbourhood overviews for property listings. Base every statement on the nearby places list (from OpenStreetMap) and the address. If the list is empty, keep to general, non-specific statements about the city or area and do not name places. Never claim school quality, crime levels, demographics or future developments. Write in {{language}}.',
				'template'    => "Address: {{address}}\nNeighbourhood/city: {{area}}\n\nNearby places within about 1 km (category: count; nearest names with distance):\n{{amenities}}\n\nWrite:\n- summary: 2-3 sentences (max 600 characters) about everyday life around the property: shops, transport, green space, schools, food.\n- highlights: up to 6 short bullet points (max 90 characters) naming real nearby places from the list with walking-distance hints (80 m ≈ 1 minute walk).",
				'schema'      => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'summary'    => self::str( 600 ),
						'highlights' => self::list_of( 6, 90 ),
					),
					'required'   => array( 'summary', 'highlights' ),
				),
			)
		);

		Prompts::register(
			self::NL_SEARCH,
			array(
				'version'     => '1',
				'task'        => 'nl_search',
				'profile'     => 'fast',
				'max_tokens'  => 1024,
				'temperature' => 0.1,
				'cache'       => DAY_IN_SECONDS,
				'system'      => 'You turn a home seeker\'s request into search filters for a property website. Use only values from the allowed lists. Leave a filter empty (empty string, 0 or []) when the request does not mention it. Prices are numbers in {{currency}}; "500k" = 500000, "1.2m" = 1200000. Areas are in {{unit}}.',
				'template'    => "Request: \"{{query}}\"\n\nAllowed property types: {{types}}\nAllowed features: {{features}}\nAllowed locations: {{locations}}\n\nReturn listing_type (\"sale\", \"rent\" or \"\"), types, features and locations (from the allowed lists only), price_min, price_max, beds (minimum), baths (minimum), area_min, area_max, year_min, keywords (remaining words that matter, max 40 characters) and sort (\"newest\", \"price_asc\", \"price_desc\", \"area_desc\").",
				'schema'      => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'listing_type' => self::str( 4 ),
						'types'        => self::list_of( 4, 60 ),
						'features'     => self::list_of( 8, 60 ),
						'locations'    => self::list_of( 4, 80 ),
						'price_min'    => array( 'type' => 'NUMBER' ),
						'price_max'    => array( 'type' => 'NUMBER' ),
						'beds'         => array( 'type' => 'INTEGER' ),
						'baths'        => array( 'type' => 'NUMBER' ),
						'area_min'     => array( 'type' => 'NUMBER' ),
						'area_max'     => array( 'type' => 'NUMBER' ),
						'year_min'     => array( 'type' => 'INTEGER' ),
						'keywords'     => self::str( 40 ),
						'sort'         => self::str( 12 ),
					),
					'required'   => array( 'listing_type', 'types', 'features', 'locations' ),
				),
			)
		);
	}

	/**
	 * Output language from the site locale.
	 *
	 * @return string
	 */
	public static function language() {
		$names = array(
			'en' => 'English',
			'es' => 'Spanish',
			'fr' => 'French',
			'de' => 'German',
			'it' => 'Italian',
			'pt' => 'Portuguese',
			'nl' => 'Dutch',
			'ar' => 'Arabic',
			'hi' => 'Hindi',
			'ja' => 'Japanese',
			'ko' => 'Korean',
			'zh' => 'Chinese',
			'ru' => 'Russian',
			'tr' => 'Turkish',
			'pl' => 'Polish',
			'el' => 'Greek',
			'sv' => 'Swedish',
		);
		$code  = strtolower( substr( determine_locale(), 0, 2 ) );
		return isset( $names[ $code ] ) ? $names[ $code ] : 'English';
	}
}
