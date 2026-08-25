#!/usr/bin/env node
/**
 * Validate built CSS assets.
 *
 * Parses every `build/**​/*.css` file with PostCSS and asserts balanced braces, so a
 * truncated or corrupted minified asset fails CI instead of shipping. Motivated by a
 * PR review that (wrongly) claimed a built RTL stylesheet was truncated — a cheap,
 * deterministic check settles that class of question and would catch a real one.
 *
 * Exits non-zero if any file fails to parse, has unbalanced braces, or if no CSS is
 * found under build/ (which would mean the build did not run).
 */
import { readdirSync, readFileSync } from 'fs';
import { join } from 'path';
import postcss from 'postcss';

const BUILD_DIR = 'build';

function cssFiles( dir ) {
	let entries;
	try {
		entries = readdirSync( dir, { recursive: true } );
	} catch {
		return [];
	}
	return entries
		.filter( ( name ) => typeof name === 'string' && name.endsWith( '.css' ) )
		.map( ( name ) => join( dir, name ) );
}

const files = cssFiles( BUILD_DIR );

if ( files.length === 0 ) {
	console.error( `✗ No CSS files found under ${ BUILD_DIR }/ — did the build run?` );
	process.exit( 1 );
}

let failed = 0;

for ( const file of files ) {
	const css = readFileSync( file, 'utf8' );
	try {
		// PostCSS throws CssSyntaxError ("Unclosed block", etc.) on malformed CSS.
		postcss.parse( css, { from: file } );

		// Belt-and-suspenders for truncation: braces must balance.
		const open = ( css.match( /{/g ) || [] ).length;
		const close = ( css.match( /}/g ) || [] ).length;
		if ( open !== close ) {
			throw new Error( `unbalanced braces: ${ open } '{' vs ${ close } '}' (truncated asset?)` );
		}

		console.log( `✓ ${ file }` );
	} catch ( error ) {
		failed += 1;
		console.error( `✗ ${ file }: ${ error.message }` );
	}
}

if ( failed > 0 ) {
	console.error( `\n${ failed } built CSS file(s) failed validation.` );
	process.exit( 1 );
}

console.log( `\nAll ${ files.length } built CSS file(s) valid.` );
