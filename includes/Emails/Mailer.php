<?php
/**
 * Transactional emails (simple HTML, filterable).
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Emails;

use Dev3\AiRealEstate\Data\Agents;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Static mailer.
 */
class Mailer {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 10, 3 );
	}

	/**
	 * Sends one email.
	 *
	 * @param string $id      Email id (filter context).
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $body    HTML body (already escaped).
	 * @param array  $headers Extra headers.
	 * @return bool
	 */
	public static function send( $id, $to, $subject, $body, array $headers = array() ) {
		if ( ! is_email( $to ) ) {
			return false;
		}
		$mail = array(
			'to'      => $to,
			'subject' => $subject,
			'body'    => self::wrap( $subject, $body ),
			'headers' => array_merge( array( 'Content-Type: text/html; charset=UTF-8' ), $headers ),
		);
		/**
		 * Filters an email before sending (return false to skip).
		 *
		 * @param array|false $mail { to, subject, body, headers }.
		 * @param string      $id   Email id: lead_agent, lead_autoreply, pending, approved, search_confirm, alert.
		 */
		$mail = apply_filters( 'dev3re_email', $mail, $id );
		if ( ! $mail ) {
			return false;
		}
		return (bool) wp_mail( $mail['to'], $mail['subject'], $mail['body'], $mail['headers'] );
	}

	/**
	 * Basic HTML layout.
	 *
	 * @param string $title Title.
	 * @param string $body  Body.
	 * @return string
	 */
	public static function wrap( $title, $body ) {
		$accent = esc_attr( (string) Settings::get( 'accent_color', '#0f766e' ) );
		return '<!doctype html><html><body style="margin:0;background:#f4f5f7;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937">'
			. '<div style="max-width:600px;margin:0 auto;padding:24px 16px"><div style="background:#fff;border-radius:10px;padding:24px;border-top:4px solid ' . $accent . '">'
			. '<h1 style="font-size:20px;margin:0 0 16px">' . esc_html( $title ) . '</h1>' . $body . '</div>'
			. '<p style="font-size:12px;color:#6b7280;text-align:center;margin-top:16px">' . esc_html( get_bloginfo( 'name' ) ) . '</p></div></body></html>';
	}

	/**
	 * A button.
	 *
	 * @param string $url   URL.
	 * @param string $label Label.
	 * @return string
	 */
	private static function button( $url, $label ) {
		return '<p><a href="' . esc_url( $url ) . '" style="display:inline-block;background:' . esc_attr( (string) Settings::get( 'accent_color', '#0f766e' ) ) . ';color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:600">' . esc_html( $label ) . '</a></p>';
	}

	/**
	 * New lead: agent (or admin) + optional copy to admin + visitor auto-reply.
	 *
	 * @param array|null $lead Lead.
	 */
	public static function new_lead( $lead ) {
		if ( ! $lead ) {
			return;
		}
		$agent_email = Agents::email( (int) $lead['agent_id'] );
		$admin       = Settings::notify_email();
		$to          = $agent_email ? $agent_email : $admin;
		$kind        = 'viewing' === $lead['kind'] ? __( 'Viewing request', 'dev3-ai-real-estate' ) : __( 'New enquiry', 'dev3-ai-real-estate' );
		$subject     = $lead['property'] ? $kind . ': ' . $lead['property'] : $kind;
		$rows        = array(
			__( 'Name', 'dev3-ai-real-estate' )     => $lead['name'],
			__( 'Email', 'dev3-ai-real-estate' )    => $lead['email'],
			__( 'Phone', 'dev3-ai-real-estate' )    => $lead['phone'],
			__( 'Property', 'dev3-ai-real-estate' ) => $lead['property'],
			__( 'Viewing', 'dev3-ai-real-estate' )  => $lead['viewing_at'],
		);
		$body        = '<table style="border-collapse:collapse;width:100%;font-size:14px">';
		foreach ( $rows as $label => $value ) {
			if ( '' !== (string) $value ) {
				$body .= '<tr><td style="padding:6px 8px;color:#6b7280;width:110px">' . esc_html( $label ) . '</td><td style="padding:6px 8px">' . esc_html( $value ) . '</td></tr>';
			}
		}
		$body .= '</table>';
		if ( '' !== $lead['message'] ) {
			$body .= '<p style="white-space:pre-wrap;background:#f9fafb;padding:12px;border-radius:6px">' . esc_html( $lead['message'] ) . '</p>';
		}
		$body .= self::button( (string) Settings::page_url( 'dashboard' ), __( 'Open the lead inbox', 'dev3-ai-real-estate' ) );
		$reply = array( 'Reply-To: ' . str_replace( array( "\r", "\n" ), '', $lead['name'] ) . ' <' . $lead['email'] . '>' );
		self::send( 'lead_agent', $to, $subject, $body, $reply );
		if ( $agent_email && $agent_email !== $admin && Settings::get( 'email_admin_leads', true ) ) {
			self::send( 'lead_admin', $admin, $subject, $body, $reply );
		}
		if ( Settings::get( 'leads_autoreply', true ) ) {
			$auto = '<p>' . esc_html(
				sprintf(
					/* translators: %s: visitor name */
					__( 'Hi %s, thanks for your message. We have passed it to the agent, who will reply soon.', 'dev3-ai-real-estate' ),
					$lead['name']
				)
			) . '</p>';
			$auto .= $lead['property_url'] ? self::button( $lead['property_url'], __( 'View the property', 'dev3-ai-real-estate' ) ) : '';
			self::send( 'lead_autoreply', $lead['email'], __( 'We received your message', 'dev3-ai-real-estate' ), $auto );
		}
	}

	/**
	 * Pending listing → admin; pending → publish → author.
	 *
	 * @param string   $new_status New.
	 * @param string   $old_status Old.
	 * @param \WP_Post $post       Post.
	 */
	public static function on_transition( $new_status, $old_status, $post ) {
		if ( ! $post instanceof \WP_Post || Post_Types::PROPERTY !== $post->post_type || $new_status === $old_status ) {
			return;
		}
		if ( 'pending' === $new_status && Settings::get( 'email_admin_pending', true ) ) {
			$body = '<p>' . esc_html(
				sprintf(
					/* translators: %s: property title */
					__( 'A new property is waiting for review: %s', 'dev3-ai-real-estate' ),
					$post->post_title
				)
			) . '</p>' . self::button( admin_url( 'admin.php?page=' . DEV3RE_SLUG . '#/properties/' . $post->ID ), __( 'Review it', 'dev3-ai-real-estate' ) );
			self::send( 'pending', Settings::notify_email(), __( 'Property awaiting review', 'dev3-ai-real-estate' ), $body );
		}
		if ( 'publish' === $new_status && 'pending' === $old_status ) {
			$author = get_userdata( (int) $post->post_author );
			if ( $author && ! user_can( $author, 'manage_options' ) ) {
				$body = '<p>' . esc_html(
					sprintf(
						/* translators: %s: property title */
						__( 'Good news: your property "%s" is now live.', 'dev3-ai-real-estate' ),
						$post->post_title
					)
				) . '</p>' . self::button( (string) get_permalink( $post ), __( 'View listing', 'dev3-ai-real-estate' ) );
				self::send( 'approved', $author->user_email, __( 'Your property is live', 'dev3-ai-real-estate' ), $body );
			}
		}
	}

	/**
	 * Confirmation link for a guest's saved search.
	 *
	 * @param array  $search Search.
	 * @param string $token  Token.
	 * @return bool
	 */
	public static function search_confirm( array $search, $token ) {
		$url  = add_query_arg(
			array(
				'dev3re_alert' => 'confirm',
				'sid'          => $search['id'],
				'token'        => $token,
			),
			home_url( '/' )
		);
		$body = '<p>' . esc_html(
			sprintf(
				/* translators: %s: search label */
				__( 'Please confirm that you want email alerts for: %s', 'dev3-ai-real-estate' ),
				$search['label']
			)
		) . '</p>' . self::button( $url, __( 'Confirm alerts', 'dev3-ai-real-estate' ) ) . '<p style="font-size:12px;color:#6b7280">' . esc_html__( 'If you did not ask for this, ignore this email.', 'dev3-ai-real-estate' ) . '</p>';
		return self::send( 'search_confirm', $search['email'], __( 'Confirm your property alerts', 'dev3-ai-real-estate' ), $body );
	}

	/**
	 * Digest of new matches.
	 *
	 * @param array $search Search (with token).
	 * @param int[] $ids    Properties.
	 * @param int   $total  Total matches.
	 * @return bool
	 */
	public static function alert_digest( array $search, array $ids, $total ) {
		$body = '<p>' . esc_html(
			sprintf(
				/* translators: 1: number of listings, 2: search label */
				_n( '%1$d new property matches "%2$s":', '%1$d new properties match "%2$s":', $total, 'dev3-ai-real-estate' ),
				$total,
				$search['label']
			)
		) . '</p>';
		foreach ( $ids as $id ) {
			$c     = Property::card( $id );
			$facts = array_filter(
				array(
					/* translators: %d: bedrooms */
					$c['beds'] ? sprintf( _n( '%d bed', '%d beds', $c['beds'], 'dev3-ai-real-estate' ), $c['beds'] ) : '',
					/* translators: %s: bathrooms */
					$c['baths'] ? sprintf( __( '%s bath', 'dev3-ai-real-estate' ), $c['baths'] + 0 ) : '',
					$c['area'],
				)
			);
			$body .= '<table style="width:100%;border-collapse:collapse;margin:0 0 12px;border:1px solid #e5e7eb;border-radius:8px"><tr>'
				. ( $c['image'] ? '<td style="width:150px;padding:0"><a href="' . esc_url( $c['url'] ) . '"><img src="' . esc_url( $c['image'] ) . '" alt="" width="150" style="display:block;width:150px;height:110px;object-fit:cover;border-radius:8px 0 0 8px"></a></td>' : '' )
				. '<td style="padding:10px 12px;vertical-align:top"><a href="' . esc_url( $c['url'] ) . '" style="font-weight:600;color:#111827;text-decoration:none">' . esc_html( $c['title'] ) . '</a>'
				. '<div style="font-size:16px;font-weight:700;margin:4px 0">' . esc_html( $c['price_html'] ) . '</div>'
				. '<div style="font-size:13px;color:#6b7280">' . esc_html( implode( ' · ', $facts ) ) . ( $c['address'] ? '<br>' . esc_html( $c['address'] ) : '' ) . '</div></td></tr></table>';
		}
		$body .= self::button( $search['url'], __( 'See all matches', 'dev3-ai-real-estate' ) );
		$unsub = add_query_arg(
			array(
				'dev3re_alert' => 'unsubscribe',
				'sid'          => $search['id'],
				'token'        => $search['token'],
			),
			home_url( '/' )
		);
		$body .= '<p style="font-size:12px;color:#6b7280"><a href="' . esc_url( $unsub ) . '" style="color:#6b7280">' . esc_html__( 'Stop these alerts', 'dev3-ai-real-estate' ) . '</a></p>';
		$sent  = self::send(
			'alert',
			$search['email'],
			sprintf(
				/* translators: %s: search label */
				__( 'New properties for "%s"', 'dev3-ai-real-estate' ),
				$search['label']
			),
			$body,
			array( 'List-Unsubscribe: <' . esc_url_raw( $unsub ) . '>' )
		);
		return $sent;
	}
}
