import { describe, it, expect } from 'vitest';
import { builderOptions, queryString, isOn, toInt } from '../../assets/src/builder/options';

describe( 'builderOptions', () => {
	it( 'falls back to the module defaults for empty attributes', () => {
		const options = builderOptions( {} );
		expect( options ).toMatchObject( {
			count: 9,
			categories: '',
			featuredOnly: false,
			showTime: true,
			loop: false,
			showArrows: true,
			headingLevel: 'h3',
			autoplay: true,
			autoplaySeconds: 5,
			autoplayInterval: 5000,
		} );
	} );

	it( 'reads builder attribute values', () => {
		const options = builderOptions( {
			events: {
				advanced: {
					count: { desktop: { value: '4' } },
					categories: { desktop: { value: 'social, meetings' } },
					featuredOnly: { desktop: { value: 'on' } },
					showTime: { desktop: { value: 'off' } },
				},
			},
			carousel: {
				advanced: {
					loop: { desktop: { value: 'on' } },
					autoplaySpeed: { desktop: { value: '8' } },
				},
			},
			arrows: { advanced: { show: { desktop: { value: 'off' } } } },
			title: { decoration: { font: { font: { desktop: { value: { headingLevel: 'h2' } } } } } },
		} );

		expect( options ).toMatchObject( {
			count: 4,
			categories: 'social, meetings',
			featuredOnly: true,
			showTime: false,
			loop: true,
			showArrows: false,
			headingLevel: 'h2',
			autoplaySeconds: 8,
			autoplayInterval: 8000,
		} );
	} );

	it( 'turns auto scroll off and clamps its interval', () => {
		const off = builderOptions( { carousel: { advanced: { autoplay: { desktop: { value: 'off' } } } } } );
		expect( off.autoplayInterval ).toBe( 0 );

		const slow = builderOptions( { carousel: { advanced: { autoplaySpeed: { desktop: { value: '600' } } } } } );
		expect( slow.autoplayInterval ).toBe( 60000 );
	} );

	it( 'never renders an arbitrary tag as the heading', () => {
		const attrs = { title: { decoration: { font: { font: { desktop: { value: { headingLevel: 'script' } } } } } } };
		expect( builderOptions( attrs ).headingLevel ).toBe( 'h3' );
	} );

	it( 'builds the REST query string', () => {
		expect( queryString( builderOptions( {} ) ) ).toBe(
			'count=9&categories=&featuredOnly=off&dateFormat=&showTime=on'
		);
	} );
} );

describe( 'helpers', () => {
	it( 'isOn understands Divi toggle values', () => {
		expect( isOn( 'on' ) ).toBe( true );
		expect( isOn( 'off' ) ).toBe( false );
		expect( isOn( undefined, true ) ).toBe( true );
	} );

	it( 'toInt clamps and strips units', () => {
		expect( toInt( '12px', 1, 1, 6 ) ).toBe( 6 );
		expect( toInt( '0', 3, 1, 6 ) ).toBe( 1 );
		expect( toInt( 'abc', 3, 1, 6 ) ).toBe( 3 );
	} );
} );
