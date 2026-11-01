<?php
/**
 * Mortgage maths (same formula as src/frontend/mortgage.js).
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Search;

defined( 'ABSPATH' ) || exit;

/**
 * Static helpers.
 */
class Mortgage {

	/**
	 * Monthly repayment of an annuity loan.
	 *
	 * @param float $principal Loan amount.
	 * @param float $rate      Annual interest rate in percent.
	 * @param int   $years     Term in years.
	 * @return float
	 */
	public static function monthly( $principal, $rate, $years ) {
		$principal = max( 0, (float) $principal );
		$n         = max( 1, (int) round( (float) $years * 12 ) );
		$r         = max( 0, (float) $rate ) / 100 / 12;
		if ( $principal <= 0 ) {
			return 0.0;
		}
		if ( $r <= 0 ) {
			return round( $principal / $n, 2 );
		}
		return round( $principal * $r / ( 1 - pow( 1 + $r, -$n ) ), 2 );
	}

	/**
	 * Full breakdown.
	 *
	 * @param float $price Price.
	 * @param float $down  Down payment amount.
	 * @param float $rate  Annual rate %.
	 * @param int   $years Years.
	 * @return array { loan, monthly, total_paid, total_interest, schedule[ { year, principal, interest, balance } ] }
	 */
	public static function breakdown( $price, $down, $rate, $years ) {
		$loan    = max( 0, (float) $price - max( 0, (float) $down ) );
		$monthly = self::monthly( $loan, $rate, $years );
		$r       = max( 0, (float) $rate ) / 100 / 12;
		$n       = max( 1, (int) round( (float) $years * 12 ) );
		$balance = $loan;
		$rows    = array();
		$year    = array(
			'principal' => 0,
			'interest'  => 0,
		);
		for ( $m = 1; $m <= $n; $m++ ) {
			$interest           = $balance * $r;
			$princ              = min( $balance, $monthly - $interest );
			$balance            = max( 0, $balance - $princ );
			$year['principal'] += $princ;
			$year['interest']  += $interest;
			if ( 0 === $m % 12 || $m === $n ) {
				$rows[] = array(
					'year'      => (int) ceil( $m / 12 ),
					'principal' => round( $year['principal'], 2 ),
					'interest'  => round( $year['interest'], 2 ),
					'balance'   => round( $balance, 2 ),
				);
				$year   = array(
					'principal' => 0,
					'interest'  => 0,
				);
			}
		}
		$total = round( $monthly * $n, 2 );
		return array(
			'loan'           => round( $loan, 2 ),
			'monthly'        => $monthly,
			'total_paid'     => $total,
			'total_interest' => round( max( 0, $total - $loan ), 2 ),
			'schedule'       => $rows,
		);
	}
}
