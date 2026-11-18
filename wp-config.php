<?php

if (function_exists('mysqli_report')) {
	mysqli_report(MYSQLI_REPORT_OFF);
}





/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'lab');

/** MySQL database username */
define('DB_USER', 'db');

/** MySQL database password */
define('DB_PASSWORD', 'db123');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'EB~E-]LB@D]@i)orHvzDa@cWn*u/TQg>oD7gcM/6LIs5`l#69?yQ =yE|X?^O5;;');
define('SECURE_AUTH_KEY',  '7)N_/w51W`35!`jvXDZ~`N.p!jiIsqC*UO4)!#(Ev!2Lo@tU#r1R]?z!L7@V;Mao');
define('LOGGED_IN_KEY',    'J|:zeW,`rw!JL8lsF.88~3y$zTr/Oo(y3<HIr{4hyz]pC,5<N<K+IKgB,5(xR6w9');
define('NONCE_KEY',        'obrmz|5YW9vueVDqENP;o>p_z<|9nrr$8B~])[uB?GAUOvX5PgN#iLpf%rKLsG7)');
define('AUTH_SALT',        ':JDHNxy>8ZHP{VA{:du2jg-hm792Z&gXxwAQ@%m?c+M=3o.Gd]4Zc6`&{[dmPzmw');
define('SECURE_AUTH_SALT', '%)uz(1pg!]483THhdy!Fql0Ylp:3Rz>KGMuzx%F.uzhaekGS&$@ Y4g}I%Cq?x8m');
define('LOGGED_IN_SALT',   '>V,A_)15fBNdASiMFCmH#`xE1Mu|wb}i%sik+(&#-oY*m%lwA_QH(qkA<|-!wmuZ');
define('NONCE_SALT',       '_q{/5o81{4@XT|R426?||cI5NGL@mkNH:d)GW9ru90(5Ften`R5(dd8#p=kZDVYz');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define('RECOVERY_MODE_EMAIL', 'trinh@ignitemedicaltech.com');
define('WP_DEBUG', isset($_GET['tr_debug']));
define('WP_ALLOW_MULTISITE', true);
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', true);
define('DOMAIN_CURRENT_SITE', 'lab.mylab.com');
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);
define('COOKIE_DOMAIN', false);
define('CI_ALL_BATCH_TYPES', array(
	//0 => 'tox',
	//1 => 'dna',
	2 => 'blood',
	//3 => 'allergy',
	// 5 => 'rpp_gi',
	//7 => 'wom',
	//14 => 'ngs',
	16 => 'pcr',
	//17 => 'study',
	//18 => 'gene',
	//19 => 'req',
));



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if (! defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
