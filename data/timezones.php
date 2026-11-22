<?php
/**
 * Time zones whose UTC offset is fixed all year since a tz database change.
 * The Status page warns when the server's tz data still applies old rules
 * (for example a summer offset), because machine-readable dates would then be
 * off by an hour.
 *
 * Add entries by pull request with a source. Keep `verified_on` current.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

return array(
	'verified_on' => '2026-11-22',
	'zones'       => array(
		// Iran stopped observing daylight saving time in 2022 (IANA tz database 2022b).
		// Confirmed on the test server: PHP's bundled tz data gives +03:30 for January and July 2026. [VERIFY tzdata NEWS wording].
		'Asia/Tehran' => array(
			'offset' => '+03:30',
			'since'  => '2022',
		),
	),
);
