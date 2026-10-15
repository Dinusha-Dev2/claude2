<?php
/**
 * Page layouts: which sections each page renders, in order.
 *
 * Keys are page slugs. 'header' / 'footer' control the shared site header and
 * footer; 'sections' are files in parts/sections/ (without .html); 'shortcode'
 * renders a shortcode instead (used by the full-screen Trip Planner). 'entry'
 * applies the content-area defaults the home page was designed with.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'home' => array(
		'entry'    => true,
		'sections' => array(
			'home-hero',
			'home-welcome',
			'home-map',
			'home-packages',
			'home-journey',
			'home-reviews',
		),
	),
	'about-us' => array(
		'sections' => array(
			'about-01',
			'about-02',
			'about-03',
			'about-04',
			'about-05',
			'about-06',
		),
	),
	'tours' => array(
		'sections' => array(
			'tours',
		),
	),
	'destinations' => array(
		'sections' => array(
			'destinations',
		),
	),
	'experiences' => array(
		'sections' => array(
			'experiences',
		),
	),
	'contact-us' => array(
		'sections' => array(
			'contact',
		),
	),
	'trip-planner' => array(
		'header'    => false,
		'footer'    => false,
		'shortcode' => '[pixel_ceylon_trip_planner]',
	),
	'ancient-heritage-hill-country' => array(
		'sections' => array(
			'tour-ancient-heritage-hill-country-01',
			'tour-ancient-heritage-hill-country-02',
			'tour-ancient-heritage-hill-country-03',
			'tour-ancient-heritage-hill-country-04',
			'tour-ancient-heritage-hill-country-05',
			'tour-ancient-heritage-hill-country-06',
			'tour-ancient-heritage-hill-country-07',
		),
	),
	'canton-fair-phase-1-business-tour' => array(
		'sections' => array(
			'tour-canton-fair-phase-1-business-tour-01',
			'tour-canton-fair-phase-1-business-tour-02',
			'tour-canton-fair-phase-1-business-tour-03',
			'tour-canton-fair-phase-1-business-tour-04',
			'tour-canton-fair-phase-1-business-tour-05',
		),
	),
	'canton-fair-yiwu-business-tour' => array(
		'sections' => array(
			'tour-canton-fair-yiwu-business-tour-01',
			'tour-canton-fair-yiwu-business-tour-02',
			'tour-canton-fair-yiwu-business-tour-03',
			'tour-canton-fair-yiwu-business-tour-04',
		),
	),
	'first-time-in-sri-lanka' => array(
		'sections' => array(
			'tour-first-time-in-sri-lanka-01',
			'tour-first-time-in-sri-lanka-02',
			'tour-first-time-in-sri-lanka-03',
			'tour-first-time-in-sri-lanka-04',
			'tour-first-time-in-sri-lanka-05',
			'tour-first-time-in-sri-lanka-06',
			'tour-first-time-in-sri-lanka-07',
		),
	),
	'heritage-wildlife-sunset-beaches' => array(
		'sections' => array(
			'tour-heritage-wildlife-sunset-beaches-01',
			'tour-heritage-wildlife-sunset-beaches-02',
			'tour-heritage-wildlife-sunset-beaches-03',
			'tour-heritage-wildlife-sunset-beaches-04',
			'tour-heritage-wildlife-sunset-beaches-05',
			'tour-heritage-wildlife-sunset-beaches-06',
			'tour-heritage-wildlife-sunset-beaches-07',
		),
	),
	'kuala-lumpur-genting-family-tour' => array(
		'sections' => array(
			'tour-kuala-lumpur-genting-family-tour-01',
			'tour-kuala-lumpur-genting-family-tour-02',
			'tour-kuala-lumpur-genting-family-tour-03',
			'tour-kuala-lumpur-genting-family-tour-04',
			'tour-kuala-lumpur-genting-family-tour-05',
			'tour-kuala-lumpur-genting-family-tour-06',
			'tour-kuala-lumpur-genting-family-tour-07',
		),
	),
	'sri-lanka-adventure-explorer' => array(
		'sections' => array(
			'tour-sri-lanka-adventure-explorer-01',
			'tour-sri-lanka-adventure-explorer-02',
			'tour-sri-lanka-adventure-explorer-03',
			'tour-sri-lanka-adventure-explorer-04',
			'tour-sri-lanka-adventure-explorer-05',
			'tour-sri-lanka-adventure-explorer-06',
			'tour-sri-lanka-adventure-explorer-07',
		),
	),
	'sri-lanka-coastal-surf-map' => array(
		'sections' => array(
			'tour-sri-lanka-coastal-surf-map-01',
			'tour-sri-lanka-coastal-surf-map-02',
			'tour-sri-lanka-coastal-surf-map-03',
			'tour-sri-lanka-coastal-surf-map-04',
			'tour-sri-lanka-coastal-surf-map-05',
			'tour-sri-lanka-coastal-surf-map-06',
			'tour-sri-lanka-coastal-surf-map-07',
		),
	),
	'wildlife-safari-adventure' => array(
		'sections' => array(
			'tour-wildlife-safari-adventure-01',
			'tour-wildlife-safari-adventure-02',
			'tour-wildlife-safari-adventure-03',
			'tour-wildlife-safari-adventure-04',
			'tour-wildlife-safari-adventure-05',
			'tour-wildlife-safari-adventure-06',
			'tour-wildlife-safari-adventure-07',
		),
	),
	'yiwu-shanghai-business-tour' => array(
		'sections' => array(
			'tour-yiwu-shanghai-business-tour-01',
			'tour-yiwu-shanghai-business-tour-02',
			'tour-yiwu-shanghai-business-tour-03',
			'tour-yiwu-shanghai-business-tour-04',
			'tour-yiwu-shanghai-business-tour-05',
		),
	),
);
