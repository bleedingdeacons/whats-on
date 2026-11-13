/**
 * Builds assets/build/{builder.js, carousel.js, carousel.css}.
 *
 *   node build.mjs           production (minified)
 *   node build.mjs --dev     unminified with inline source maps
 *   node build.mjs --watch   dev build, rebuilding on change
 *
 * The builder bundle must not ship its own React or Divi packages: Divi 5 exposes them on
 * window (window.vendor.*, window.divi.*), so those imports are mapped to globals.
 */
import * as esbuild from 'esbuild';

const watch = process.argv.includes( '--watch' );
const dev = watch || process.argv.includes( '--dev' );

const GLOBALS = {
	react: 'window.vendor.React',
	'react-dom': 'window.vendor.ReactDOM',
	lodash: 'window.lodash',
	'@wordpress/hooks': 'window.vendor.wp.hooks',
	'@wordpress/i18n': 'window.vendor.wp.i18n',
	'@divi/module': 'window.divi.module',
	'@divi/module-library': 'window.divi.moduleLibrary',
	'@divi/module-utils': 'window.divi.moduleUtils',
	'@divi/field-library': 'window.divi.fieldLibrary',
	'@divi/data': 'window.divi.data',
};

const escape = ( s ) => s.replace( /[.*+?^${}()|[\]\\/]/g, '\\$&' );

const globalsPlugin = {
	name: 'divi-globals',
	setup( build ) {
		const filter = new RegExp( `^(${ Object.keys( GLOBALS ).map( escape ).join( '|' ) })$` );
		build.onResolve( { filter }, ( args ) => ( { path: args.path, namespace: 'divi-globals' } ) );
		build.onLoad( { filter: /.*/, namespace: 'divi-globals' }, ( args ) => ( {
			contents: `module.exports = ${ GLOBALS[ args.path ] };`,
			loader: 'js',
		} ) );
	},
};

const common = {
	bundle: true,
	format: 'iife',
	target: [ 'es2020' ],
	minify: ! dev,
	sourcemap: dev ? 'inline' : false,
	logLevel: 'info',
	legalComments: 'none',
};

const configs = [
	{
		...common,
		entryPoints: { builder: 'assets/src/builder/index.js' },
		outdir: 'assets/build',
		jsx: 'transform',
		jsxFactory: 'React.createElement',
		jsxFragment: 'React.Fragment',
		loader: { '.js': 'jsx', '.jsx': 'jsx' },
		plugins: [ globalsPlugin ],
	},
	{
		...common,
		entryPoints: { carousel: 'assets/src/frontend/carousel.js' },
		outdir: 'assets/build',
	},
];

if ( watch ) {
	const contexts = await Promise.all( configs.map( ( c ) => esbuild.context( c ) ) );
	await Promise.all( contexts.map( ( c ) => c.watch() ) );
	console.log( 'Watching for changes…' );
} else {
	await Promise.all( configs.map( ( c ) => esbuild.build( c ) ) );
}
