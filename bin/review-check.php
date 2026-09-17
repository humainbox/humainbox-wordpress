#!/usr/bin/env php
<?php
/**
 * The .org review, run before the .org review runs it.
 *
 * Every rule here is one the plugin review team rejects on, and every rejection costs
 * two to six weeks of waiting followed by another queue. The point is not to be clever
 * about static analysis — it is that the expensive mistakes are a short, known list,
 * and none of them should ever reach a human reviewer.
 *
 * Run: php bin/review-check.php
 *
 * @package Humainbox
 */

$root = dirname( __DIR__ );

$files = array();
$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );

foreach ( $it as $file ) {
	$path = $file->getPathname();

	if ( str_contains( $path, '/bin/' ) || str_contains( $path, '/.git/' ) ) {
		continue;
	}

	if ( 'php' === pathinfo( $path, PATHINFO_EXTENSION ) ) {
		$files[] = $path;
	}
}

sort( $files );

$problems = array();

/** Report one failure. */
$fail = function ( $rule, $detail ) use ( &$problems ) {
	$problems[] = array( 'rule' => $rule, 'detail' => $detail );
};

$rel = fn( $path ) => ltrim( str_replace( $root, '', $path ), '/' );

foreach ( $files as $path ) {
	$source = (string) file_get_contents( $path );
	$name   = $rel( $path );

	// ── Direct access. Every PHP file, no exceptions, including the uninstaller
	//    (which uses its own constant).
	if ( ! str_contains( $source, "defined( 'ABSPATH' )" ) && ! str_contains( $source, "defined( 'WP_UNINSTALL_PLUGIN' )" ) ) {
		$fail( 'direct access', "{$name} can be requested directly" );
	}

	// ── PHP short tags: not universally enabled, and a reviewer will not test it.
	if ( preg_match( '/<\?(?!php|=|xml)/', $source ) ) {
		$fail( 'short tags', $name );
	}

	// ── Raw superglobals reaching code without unslashing. WordPress slashes every
	//    one on the way in, so sanitizing before wp_unslash sanitizes the escaping.
	/*
	 * ⚠️ THE LINE THE HIT IS ON, not a window around it.
	 *
	 * The first version of this check read 260 characters either side and looked at
	 * the second line of that window, which is a different line from the one that
	 * matched. It reported eight problems in code that was already correct — and a
	 * check that cries wolf is one somebody switches off, taking the real finding
	 * with it.
	 */
	foreach ( explode( "\n", $source ) as $number => $line ) {
		if ( ! preg_match( '/\$_(POST|GET|REQUEST|COOKIE)\s*\[/', $line ) ) {
			continue;
		}

		// isset() alone reads nothing, so it is not a use that needs unslashing.
		$reads = preg_replace( '/isset\(\s*\$_(POST|GET|REQUEST|COOKIE)\s*\[[^\]]*\]\s*\)/', '', $line );

		if ( preg_match( '/\$_(POST|GET|REQUEST|COOKIE)\s*\[/', (string) $reads )
			&& ! str_contains( (string) $reads, 'wp_unslash' ) ) {
			$fail( 'unslash', "{$name}:" . ( $number + 1 ) . ' ' . trim( $line ) );
		}
	}

	// ── HTTP that is not WordPress's. cURL and file_get_contents on a URL both get
	//    rejected: they ignore the site's proxy, its filters and its timeouts.
	foreach ( array( 'curl_init', 'curl_exec', 'fsockopen' ) as $banned ) {
		if ( str_contains( $source, $banned . '(' ) ) {
			$fail( 'http', "{$name} uses {$banned}()" );
		}
	}

	// ── Direct database access where an API exists.
	if ( preg_match( '/\$wpdb->(query|get_results|get_var|get_row)\s*\(/', $source ) ) {
		$fail( 'database', "{$name} queries the database directly" );
	}

	// ── Code that cannot be read is code that is not accepted.
	foreach ( array( 'base64_decode', 'eval(', 'gzinflate', 'str_rot13', 'create_function' ) as $banned ) {
		if ( str_contains( $source, $banned ) ) {
			$fail( 'obfuscation', "{$name} uses {$banned}" );
		}
	}

	// ── Anything global must carry the prefix. A function called activate() in the
	//    global namespace is the single most common rejection there is.
	if ( preg_match_all( '/^function\s+([a-zA-Z0-9_]+)/m', $source, $m ) ) {
		foreach ( $m[1] as $fn ) {
			if ( ! str_starts_with( $fn, 'humainbox_' ) ) {
				$fail( 'prefix', "{$name}: function {$fn}()" );
			}
		}
	}

	if ( preg_match_all( '/^(?:abstract\s+|final\s+)?class\s+([a-zA-Z0-9_]+)/m', $source, $m ) ) {
		foreach ( $m[1] as $class ) {
			if ( ! str_starts_with( $class, 'Humainbox_' ) ) {
				$fail( 'prefix', "{$name}: class {$class}" );
			}
		}
	}

	if ( preg_match_all( "/define\(\s*'([A-Z0-9_]+)'/", $source, $m ) ) {
		foreach ( $m[1] as $const ) {
			if ( ! str_starts_with( $const, 'HUMAINBOX_' ) ) {
				$fail( 'prefix', "{$name}: constant {$const}" );
			}
		}
	}

	foreach ( array( 'get_option', 'update_option', 'delete_option' ) as $fn ) {
		if ( preg_match_all( "/{$fn}\(\s*'([a-z0-9_]+)'/", $source, $m ) ) {
			foreach ( $m[1] as $option ) {
				if ( ! str_starts_with( $option, 'humainbox_' ) ) {
					$fail( 'prefix', "{$name}: option {$option}" );
				}
			}
		}
	}

	// ── Text domain. It must equal the plugin slug, everywhere, or translations
	//    silently do nothing.
	if ( preg_match_all( "/__\(\s*'[^']*'\s*,\s*'([a-z0-9-]+)'\s*\)/", $source, $m ) ) {
		foreach ( $m[1] as $domain ) {
			if ( 'humainbox' !== $domain ) {
				$fail( 'text domain', "{$name}: '{$domain}'" );
			}
		}
	}
}

// ── Every admin_post handler needs a nonce action, and every form that posts to one
//    needs the matching field. A mismatch fails open in the worst possible way: the
//    action still runs.
$settings = (string) file_get_contents( $root . '/includes/class-humainbox-settings.php' );
$page     = (string) file_get_contents( $root . '/admin/settings-page.php' );

preg_match_all( "/admin_post_([a-z0-9_]+)'/", $settings, $actions );

foreach ( array_unique( $actions[1] ) as $action ) {
	/*
	 * Directly, or through the one helper that does it. guard() takes the action name
	 * and calls check_admin_referer() with it — insisting on the literal call would
	 * have pushed the code towards repeating the capability check by hand in every
	 * handler, which is how one of them eventually gets forgotten.
	 */
	$checked = preg_match( "/check_admin_referer\(\s*'{$action}'/", $settings )
		|| preg_match( "/guard\(\s*'{$action}'\s*\)/", $settings );

	if ( ! $checked ) {
		$fail( 'nonce', "nothing verifies a nonce for {$action}" );
	}

	if ( ! str_contains( $page, "wp_nonce_field( '{$action}' )" ) ) {
		$fail( 'nonce', "no wp_nonce_field('{$action}') in the form" );
	}
}

// Capability checks: one per handler, plus the render method.
$handlers = preg_match_all( '/public function handle_[a-z_]+\(/', $settings );
$guards   = preg_match_all( '/\$this->guard\(/', $settings );

if ( $handlers !== $guards ) {
	$fail( 'capability', "{$handlers} handlers but {$guards} guard() calls" );
}

// ── Unescaped output. Anything echoed in the view must pass through an escaper.
if ( preg_match_all( '/<\?php\s+echo\s+([a-z_]+)\(/', $page, $m ) ) {
	foreach ( $m[1] as $fn ) {
		if ( ! str_starts_with( $fn, 'esc_' ) && 'printf' !== $fn && 'sprintf' !== $fn ) {
			$fail( 'escaping', "settings-page.php echoes {$fn}() unescaped" );
		}
	}
}

if ( preg_match( '/<\?=\s*\$/', $page ) || preg_match( '/echo\s+\$[a-z_]+\s*;/', $page ) ) {
	$fail( 'escaping', 'settings-page.php echoes a bare variable' );
}

// ── readme.txt: the header the directory parses, and the sections a reviewer reads.
$readme = (string) file_get_contents( $root . '/readme.txt' );

foreach ( array( 'Stable tag:', 'Requires at least:', 'Requires PHP:', 'License:', 'Tested up to:', '== Description ==', '== Changelog ==' ) as $needle ) {
	if ( ! str_contains( $readme, $needle ) ) {
		$fail( 'readme', "missing {$needle}" );
	}
}

// Stable tag and the plugin header must agree, or the directory serves a version that
// does not exist.
preg_match( '/Stable tag:\s*([0-9.]+)/', $readme, $tag );
preg_match( '/Version:\s*([0-9.]+)/', (string) file_get_contents( $root . '/humainbox.php' ), $version );

if ( ( $tag[1] ?? 'a' ) !== ( $version[1] ?? 'b' ) ) {
	$fail( 'version', sprintf( 'readme says %s, plugin header says %s', $tag[1] ?? '?', $version[1] ?? '?' ) );
}

// ── The service disclosure. A plugin backed by a paid service is fine; one that does
//    not say so is rejected, and rightly.
if ( ! str_contains( $readme, '== External services ==' ) ) {
	$fail( 'disclosure', 'readme has no External services section' );
}

// ── Nothing may reach the front end. No enqueues outside admin, no shortcodes, no
//    the_content filters — this plugin is a configurator.
foreach ( $files as $path ) {
	$source = (string) file_get_contents( $path );

	foreach ( array( 'wp_enqueue_script', 'wp_enqueue_style', 'add_shortcode', "add_filter( 'the_content'" ) as $banned ) {
		if ( str_contains( $source, $banned ) ) {
			$fail( 'front end', $rel( $path ) . " uses {$banned}" );
		}
	}
}

// ── Report.
echo PHP_EOL;

if ( empty( $problems ) ) {
	printf( "  %d files checked. Nothing a reviewer would reject on.%s%s", count( $files ), PHP_EOL, PHP_EOL );
	exit( 0 );
}

printf( "  %d problem(s):%s%s", count( $problems ), PHP_EOL, PHP_EOL );

foreach ( $problems as $problem ) {
	printf( "    %-14s %s%s", $problem['rule'], $problem['detail'], PHP_EOL );
}

echo PHP_EOL;
exit( 1 );
