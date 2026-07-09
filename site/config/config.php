<?php

require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../..");
$dotenv->load();

$devUrl = null;

if (!empty($_ENV['VITE_DEV'])) {
	$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8888';
	$devUrl = 'http://' . $host;
}

return [

	// General
	'debug' => !empty($_ENV['VITE_DEV']),
	'url'   => $devUrl,

	// Stripe
	'stripe' => [
		'publicKey' => !empty($_ENV['VITE_DEV']) ? $_ENV['STRIPE_TEST_PUBLIC_KEY'] : $_ENV['STRIPE_LIVE_PUBLIC_KEY'],
		'secretKey' => !empty($_ENV['VITE_DEV']) ? $_ENV['STRIPE_TEST_SECRET_KEY'] : $_ENV['STRIPE_LIVE_SECRET_KEY'],
	],

	// Images
	'thumbs' => [
		'srcsets' => [
			'default' => [
				'300w'  => ['width' => 300],
				'600w'  => ['width' => 600],
				'900w'  => ['width' => 900],
				'1200w' => ['width' => 1200],
				'1800w' => ['width' => 1800],
			],
			'webp' => [
				'300w'  => ['width' => 300,  'format' => 'webp'],
				'600w'  => ['width' => 600,  'format' => 'webp'],
				'900w'  => ['width' => 900,  'format' => 'webp'],
				'1200w' => ['width' => 1200, 'format' => 'webp'],
				'1800w' => ['width' => 1800, 'format' => 'webp'],
			],
		],
	],

	// Transport SMTP : https://getkirby.com/docs/guide/emails
	'email' => [
		'transport' => [
			'type'     => 'smtp',
			'host'     => $_ENV['EMAIL_HOST'],
			'port'     => $_ENV['EMAIL_PORT'],
			'security' => 'ssl',
			'auth'     => true,
			'username' => $_ENV['EMAIL_USERNAME'],
			'password' => $_ENV['EMAIL_PASSWORD'],
		],
	],

];
