/**
 * Builds a release zip: dist/whats-on-production-<version>.zip (the name /deploy looks for) containing only what WordPress needs.
 * Run with `npm run package` (does a production asset build first).
 */
import { execFileSync } from 'node:child_process';
import { cpSync, mkdirSync, readFileSync, rmSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL( '..', import.meta.url ).pathname.replace( /^\/([A-Za-z]:)/, '$1' );
const header = readFileSync( join( root, 'whats-on.php' ), 'utf8' );
const version = header.match( /^\s*\*\s*Version:\s*(\S+)/m )?.[ 1 ];
if ( ! version ) {
	throw new Error( 'No Version: in the whats-on.php header.' );
}

const SHIP = [ 'whats-on.php', 'readme.txt', 'src', 'modules-json', 'assets/build', 'languages' ];

const stage = join( root, 'dist', 'whats-on' );
rmSync( join( root, 'dist' ), { recursive: true, force: true } );
mkdirSync( stage, { recursive: true } );

for ( const path of SHIP ) {
	const from = join( root, path );
	if ( existsSync( from ) ) {
		cpSync( from, join( stage, path ), { recursive: true } );
	}
}

const zip = join( root, 'dist', `whats-on-production-${ version }.zip` );
// bsdtar writes zip (Windows' built-in tar, macOS tar). Git Bash puts GNU tar first on PATH, which can't.
if ( process.platform === 'win32' ) {
	const tar = join( process.env.SystemRoot || 'C:\\Windows', 'System32', 'tar.exe' );
	execFileSync( tar, [ '-a', '-c', '-f', zip, '-C', join( root, 'dist' ), 'whats-on' ], { stdio: 'inherit' } );
} else {
	execFileSync( 'zip', [ '-rq', zip, 'whats-on' ], { cwd: join( root, 'dist' ), stdio: 'inherit' } );
}
rmSync( stage, { recursive: true, force: true } );

console.log( `Wrote ${ zip }` );
