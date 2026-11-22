<?php
/**
 * PHP branch end-of-life dates (end of security support).
 *
 * Update this file when php.net changes the schedule.
 *
 * @package KhabarSEO
 */

defined( 'ABSPATH' ) || exit;

return array(
	'verified_on' => '2026-11-19',
	'source'      => 'https://www.php.net/supported-versions.php',
	// 8.1 and 8.2 dates were confirmed from the WordPress hosting handbook (7.0 compatibility page).
	// 8.3, 8.4 and 8.5 follow php.net's fixed schedule and were not individually re-read: [VERIFY].
	'unverified'  => array( '7.4', '8.0', '8.3', '8.4', '8.5' ),
	'branches'    => array(
		'7.4' => '2022-11-28',
		'8.0' => '2023-11-26',
		'8.1' => '2025-12-31',
		'8.2' => '2026-12-31',
		'8.3' => '2027-12-31',
		'8.4' => '2028-12-31',
		'8.5' => '2029-12-31',
	),
);
