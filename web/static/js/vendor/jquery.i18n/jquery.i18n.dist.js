/*!
 * jQuery Internationalization library
 *
 * Copyright (C) 2012 Santhosh Thottingal
 *
 * jquery.i18n is dual licensed GPLv2 or later and MIT. You don't have to do
 * anything special to choose one license or the other and you don't have to
 * notify anyone which license you are using. You are free to use
 * UniversalLanguageSelector in commercial projects as long as the copyright
 * header is left intact. See files GPL-LICENSE and MIT-LICENSE for details.
 *
 * @licence GNU General Public Licence 2.0 or later
 * @licence MIT License
 */

( function ( $ ) {
	'use strict';

	var nav, I18N,
		slice = Array.prototype.slice;
	/**
	 * @constructor
	 * @param {Object} options
	 */
	I18N = function ( options ) {
		// Load defaults
		this.options = $.extend( {}, I18N.defaults, options );

		this.parser = this.options.parser;
		this.locale = this.options.locale;
		this.messageStore = this.options.messageStore;
		this.languages = {};

		this.init();
	};

	I18N.prototype = {
		/**
		 * Initialize by loading locales and setting up
		 * String.prototype.toLocaleString and String.locale.
		 */
		init: function () {
			var i18n = this;

			// Set locale of String environment
			String.locale = i18n.locale;

			// Override String.localeString method
			String.prototype.toLocaleString = function () {
				var localeParts, localePartIndex, value, locale, fallbackIndex,
					tryingLocale, message;

				value = this.valueOf();
				locale = i18n.locale;
				fallbackIndex = 0;

				while ( locale ) {
					// Iterate through locales starting at most-specific until
					// localization is found. As in fi-Latn-FI, fi-Latn and fi.
					localeParts = locale.split( '-' );
					localePartIndex = localeParts.length;

					do {
						tryingLocale = localeParts.slice( 0, localePartIndex ).join( '-' );
						message = i18n.messageStore.get( tryingLocale, value );

						if ( message ) {
							return message;
						}

						localePartIndex--;
					} while ( localePartIndex );

					if ( locale === 'en' ) {
						break;
					}

					locale = ( $.i18n.fallbacks[ i18n.locale ] && $.i18n.fallbacks[ i18n.locale ][ fallbackIndex ] ) ||
						i18n.options.fallbackLocale;
					$.i18n.log( 'Trying fallback locale for ' + i18n.locale + ': ' + locale );

					fallbackIndex++;
				}

				// key not found
				return '';
			};
		},

		/*
		 * Destroy the i18n instance.
		 */
		destroy: function () {
			$.removeData( document, 'i18n' );
		},

		/**
		 * General message loading API This can take a URL string for
		 * the json formatted messages. Example:
		 * <code>load('path/to/all_localizations.json');</code>
		 *
		 * To load a localization file for a locale:
		 * <code>
		 * load('path/to/de-messages.json', 'de' );
		 * </code>
		 *
		 * To load a localization file from a directory:
		 * <code>
		 * load('path/to/i18n/directory', 'de' );
		 * </code>
		 * The above method has the advantage of fallback resolution.
		 * ie, it will automatically load the fallback locales for de.
		 * For most usecases, this is the recommended method.
		 * It is optional to have trailing slash at end.
		 *
		 * A data object containing message key- message translation mappings
		 * can also be passed. Example:
		 * <code>
		 * load( { 'hello' : 'Hello' }, optionalLocale );
		 * </code>
		 *
		 * A source map containing key-value pair of languagename and locations
		 * can also be passed. Example:
		 * <code>
		 * load( {
		 * bn: 'i18n/bn.json',
		 * he: 'i18n/he.json',
		 * en: 'i18n/en.json'
		 * } )
		 * </code>
		 *
		 * If the data argument is null/undefined/false,
		 * all cached messages for the i18n instance will get reset.
		 *
		 * @param {string|Object} source
		 * @param {string} locale Language tag
		 * @return {jQuery.Promise}
		 */
		load: function ( source, locale ) {
			var fallbackLocales, locIndex, fallbackLocale, sourceMap = {};
			if ( !source && !locale ) {
				source = 'i18n/' + $.i18n().locale + '.json';
				locale = $.i18n().locale;
			}
			if ( typeof source === 'string' &&
				source.split( '.' ).pop() !== 'json'
			) {
				// Load specified locale then check for fallbacks when directory is specified in load()
				sourceMap[ locale ] = source + '/' + locale + '.json';
				fallbackLocales = ( $.i18n.fallbacks[ locale ] || [] )
					.concat( this.options.fallbackLocale );
				for ( locIndex in fallbackLocales ) {
					fallbackLocale = fallbackLocales[ locIndex ];
					sourceMap[ fallbackLocale ] = source + '/' + fallbackLocale + '.json';
				}
				return this.load( sourceMap );
			} else {
				return this.messageStore.load( source, locale );
			}

		},

		/**
		 * Does parameter and magic word substitution.
		 *
		 * @param {string} key Message key
		 * @param {Array} parameters Message parameters
		 * @return {string}
		 */
		parse: function ( key, parameters ) {
			var message = key.toLocaleString();
			// FIXME: This changes the state of the I18N object,
			// should probably not change the 'this.parser' but just
			// pass it to the parser.
			this.parser.language = $.i18n.languages[ $.i18n().locale ] || $.i18n.languages[ 'default' ];
			if ( message === '' ) {
				message = key;
			}
			return this.parser.parse( message, parameters );
		}
	};

	/**
	 * Process a message from the $.I18N instance
	 * for the current document, stored in jQuery.data(document).
	 *
	 * @param {string} key Key of the message.
	 * @param {string} param1 [param...] Variadic list of parameters for {key}.
	 * @return {string|$.I18N} Parsed message, or if no key was given
	 * the instance of $.I18N is returned.
	 */
	$.i18n = function ( key, param1 ) {
		var parameters,
			i18n = $.data( document, 'i18n' ),
			options = typeof key === 'object' && key;

		// If the locale option for this call is different then the setup so far,
		// update it automatically. This doesn't just change the context for this
		// call but for all future call as well.
		// If there is no i18n setup yet, don't do this. It will be taken care of
		// by the `new I18N` construction below.
		// NOTE: It should only change language for this one call.
		// Then cache instances of I18N somewhere.
		if ( options && options.locale && i18n && i18n.locale !== options.locale ) {
			String.locale = i18n.locale = options.locale;
		}

		if ( !i18n ) {
			i18n = new I18N( options );
			$.data( document, 'i18n', i18n );
		}

		if ( typeof key === 'string' ) {
			if ( param1 !== undefined ) {
				parameters = slice.call( arguments, 1 );
			} else {
				parameters = [];
			}

			return i18n.parse( key, parameters );
		} else {
			// FIXME: remove this feature/bug.
			return i18n;
		}
	};

	$.fn.i18n = function () {
		var i18n = $.data( document, 'i18n' );

		if ( !i18n ) {
			i18n = new I18N();
			$.data( document, 'i18n', i18n );
		}
		String.locale = i18n.locale;
		return this.each( function () {
			var $this = $( this ),
				messageKey = $this.data( 'i18n' ),
				lBracket, rBracket, type, key;

			if ( messageKey ) {
				lBracket = messageKey.indexOf( '[' );
				rBracket = messageKey.indexOf( ']' );
				if ( lBracket !== -1 && rBracket !== -1 && lBracket < rBracket ) {
					type = messageKey.slice( lBracket + 1, rBracket );
					key = messageKey.slice( rBracket + 1 );
					if ( type === 'html' ) {
						$this.html( i18n.parse( key ) );
					} else {
						$this.attr( type, i18n.parse( key ) );
					}
				} else {
					$this.text( i18n.parse( messageKey ) );
				}
			} else {
				$this.find( '[data-i18n]' ).i18n();
			}
		} );
	};

	String.locale = String.locale || $( 'html' ).attr( 'lang' );

	if ( !String.locale ) {
		if ( typeof window.navigator !== undefined ) {
			nav = window.navigator;
			String.locale = nav.language || nav.userLanguage || '';
		} else {
			String.locale = '';
		}
	}

	$.i18n.languages = {};
	$.i18n.messageStore = $.i18n.messageStore || {};
	$.i18n.parser = {
		// The default parser only handles variable substitution
		parse: function ( message, parameters ) {
			return message.replace( /\$(\d+)/g, function ( str, match ) {
				var index = parseInt( match, 10 ) - 1;
				return parameters[ index ] !== undefined ? parameters[ index ] : '$' + match;
			} );
		},
		emitter: {}
	};
	$.i18n.fallbacks = {};
	$.i18n.debug = false;
	$.i18n.log = function ( /* arguments */ ) {
		if ( window.console && $.i18n.debug ) {
			window.console.log.apply( window.console, arguments );
		}
	};
	/* Static members */
	I18N.defaults = {
		locale: String.locale,
		fallbackLocale: 'en',
		parser: $.i18n.parser,
		messageStore: $.i18n.messageStore
	};

	// Expose constructor
	$.i18n.constructor = I18N;
}( jQuery ) );
/*!
 * jQuery Internationalization library - Message Store
 *
 * Copyright (C) 2012 Santhosh Thottingal
 *
 * jquery.i18n is dual licensed GPLv2 or later and MIT. You don't have to do anything special to
 * choose one license or the other and you don't have to notify anyone which license you are using.
 * You are free to use UniversalLanguageSelector in commercial projects as long as the copyright
 * header is left intact. See files GPL-LICENSE and MIT-LICENSE for details.
 *
 * @licence GNU General Public Licence 2.0 or later
 * @licence MIT License
 */

( function ( $, window, undefined ) {
	'use strict';

	var MessageStore = function () {
		this.messages = {};
		this.sources = {};
	};

	/**
	 * See https://github.com/wikimedia/jquery.i18n/wiki/Specification#wiki-Message_File_Loading
	 */
	MessageStore.prototype = {

		/**
		 * General message loading API This can take a URL string for
		 * the json formatted messages.
		 * <code>load('path/to/all_localizations.json');</code>
		 *
		 * This can also load a localization file for a locale <code>
		 * load( 'path/to/de-messages.json', 'de' );
		 * </code>
		 * A data object containing message key- message translation mappings
		 * can also be passed Eg:
		 * <code>
		 * load( { 'hello' : 'Hello' }, optionalLocale );
		 * </code> If the data argument is
		 * null/undefined/false,
		 * all cached messages for the i18n instance will get reset.
		 *
		 * @param {string|Object} source
		 * @param {string} locale Language tag
		 * @return {jQuery.Promise}
		 */
		load: function ( source, locale ) {
			var key = null,
				deferred = null,
				deferreds = [],
				messageStore = this;

			if ( typeof source === 'string' ) {
				// This is a URL to the messages file.
				$.i18n.log( 'Loading messages from: ' + source );
				deferred = jsonMessageLoader( source )
					.done( function ( localization ) {
						messageStore.set( locale, localization );
					} );

				return deferred.promise();
			}

			if ( locale ) {
				// source is an key-value pair of messages for given locale
				messageStore.set( locale, source );

				return $.Deferred().resolve();
			} else {
				// source is a key-value pair of locales and their source
				for ( key in source ) {
					if ( Object.prototype.hasOwnProperty.call( source, key ) ) {
						locale = key;
						// No {locale} given, assume data is a group of languages,
						// call this function again for each language.
						deferreds.push( messageStore.load( source[ key ], locale ) );
					}
				}
				return $.when.apply( $, deferreds );
			}

		},

		/**
		 * Set messages to the given locale.
		 * If locale exists, add messages to the locale.
		 *
		 * @param {string} locale
		 * @param {Object} messages
		 */
		set: function ( locale, messages ) {
			if ( !this.messages[ locale ] ) {
				this.messages[ locale ] = messages;
			} else {
				this.messages[ locale ] = $.extend( this.messages[ locale ], messages );
			}
		},

		/**
		 *
		 * @param {string} locale
		 * @param {string} messageKey
		 * @return {boolean}
		 */
		get: function ( locale, messageKey ) {
			return this.messages[ locale ] && this.messages[ locale ][ messageKey ];
		}
	};

	function jsonMessageLoader( url ) {
		var deferred = $.Deferred();

		$.getJSON( url )
			.done( deferred.resolve )
			.fail( function ( jqxhr, settings, exception ) {
				$.i18n.log( 'Error in loading messages from ' + url + ' Exception: ' + exception );
				// Ignore 404 exception, because we are handling fallabacks explicitly
				deferred.resolve();
			} );

		return deferred.promise();
	}

	$.extend( $.i18n.messageStore, new MessageStore() );
}( jQuery, window ) );
/*!
 * jQuery Internationalization library
 *
 * Copyright (C) 2012 Santhosh Thottingal
 *
 * jquery.i18n is dual licensed GPLv2 or later and MIT. You don't have to do anything special to
 * choose one license or the other and you don't have to notify anyone which license you are using.
 * You are free to use UniversalLanguageSelector in commercial projects as long as the copyright
 * header is left intact. See files GPL-LICENSE and MIT-LICENSE for details.
 *
 * @licence GNU General Public Licence 2.0 or later
 * @licence MIT License
 */
( function ( $, undefined ) {
	'use strict';

	$.i18n = $.i18n || {};
	$.extend( $.i18n.fallbacks, {
		ab: [ 'ru' ],
		ace: [ 'id' ],
		aln: [ 'sq' ],
		// Not so standard - als is supposed to be Tosk Albanian,
		// but in Wikipedia it's used for a Germanic language.
		als: [ 'gsw', 'de' ],
		an: [ 'es' ],
		anp: [ 'hi' ],
		arn: [ 'es' ],
		arz: [ 'ar' ],
		av: [ 'ru' ],
		ay: [ 'es' ],
		ba: [ 'ru' ],
		bar: [ 'de' ],
		'bat-smg': [ 'sgs', 'lt' ],
		bcc: [ 'fa' ],
		'be-x-old': [ 'be-tarask' ],
		bh: [ 'bho' ],
		bjn: [ 'id' ],
		bm: [ 'fr' ],
		bpy: [ 'bn' ],
		bqi: [ 'fa' ],
		bug: [ 'id' ],
		'cbk-zam': [ 'es' ],
		ce: [ 'ru' ],
		crh: [ 'crh-latn' ],
		'crh-cyrl': [ 'ru' ],
		csb: [ 'pl' ],
		cv: [ 'ru' ],
		'de-at': [ 'de' ],
		'de-ch': [ 'de' ],
		'de-formal': [ 'de' ],
		dsb: [ 'de' ],
		dtp: [ 'ms' ],
		egl: [ 'it' ],
		eml: [ 'it' ],
		ff: [ 'fr' ],
		fit: [ 'fi' ],
		'fiu-vro': [ 'vro', 'et' ],
		frc: [ 'fr' ],
		frp: [ 'fr' ],
		frr: [ 'de' ],
		fur: [ 'it' ],
		gag: [ 'tr' ],
		gan: [ 'gan-hant', 'zh-hant', 'zh-hans' ],
		'gan-hans': [ 'zh-hans' ],
		'gan-hant': [ 'zh-hant', 'zh-hans' ],
		gl: [ 'pt' ],
		glk: [ 'fa' ],
		gn: [ 'es' ],
		gsw: [ 'de' ],
		hif: [ 'hif-latn' ],
		hsb: [ 'de' ],
		ht: [ 'fr' ],
		ii: [ 'zh-cn', 'zh-hans' ],
		inh: [ 'ru' ],
		iu: [ 'ike-cans' ],
		jut: [ 'da' ],
		jv: [ 'id' ],
		kaa: [ 'kk-latn', 'kk-cyrl' ],
		kbd: [ 'kbd-cyrl' ],
		khw: [ 'ur' ],
		kiu: [ 'tr' ],
		kk: [ 'kk-cyrl' ],
		'kk-arab': [ 'kk-cyrl' ],
		'kk-latn': [ 'kk-cyrl' ],
		'kk-cn': [ 'kk-arab', 'kk-cyrl' ],
		'kk-kz': [ 'kk-cyrl' ],
		'kk-tr': [ 'kk-latn', 'kk-cyrl' ],
		kl: [ 'da' ],
		'ko-kp': [ 'ko' ],
		koi: [ 'ru' ],
		krc: [ 'ru' ],
		ks: [ 'ks-arab' ],
		ksh: [ 'de' ],
		ku: [ 'ku-latn' ],
		'ku-arab': [ 'ckb' ],
		kv: [ 'ru' ],
		lad: [ 'es' ],
		lb: [ 'de' ],
		lbe: [ 'ru' ],
		lez: [ 'ru' ],
		li: [ 'nl' ],
		lij: [ 'it' ],
		liv: [ 'et' ],
		lmo: [ 'it' ],
		ln: [ 'fr' ],
		ltg: [ 'lv' ],
		lzz: [ 'tr' ],
		mai: [ 'hi' ],
		'map-bms': [ 'jv', 'id' ],
		mg: [ 'fr' ],
		mhr: [ 'ru' ],
		min: [ 'id' ],
		mo: [ 'ro' ],
		mrj: [ 'ru' ],
		mwl: [ 'pt' ],
		myv: [ 'ru' ],
		mzn: [ 'fa' ],
		nah: [ 'es' ],
		nap: [ 'it' ],
		nds: [ 'de' ],
		'nds-nl': [ 'nl' ],
		'nl-informal': [ 'nl' ],
		no: [ 'nb' ],
		os: [ 'ru' ],
		pcd: [ 'fr' ],
		pdc: [ 'de' ],
		pdt: [ 'de' ],
		pfl: [ 'de' ],
		pms: [ 'it' ],
		pt: [ 'pt-br' ],
		'pt-br': [ 'pt' ],
		qu: [ 'es' ],
		qug: [ 'qu', 'es' ],
		rgn: [ 'it' ],
		rmy: [ 'ro' ],
		'roa-rup': [ 'rup' ],
		rue: [ 'uk', 'ru' ],
		ruq: [ 'ruq-latn', 'ro' ],
		'ruq-cyrl': [ 'mk' ],
		'ruq-latn': [ 'ro' ],
		sa: [ 'hi' ],
		sah: [ 'ru' ],
		scn: [ 'it' ],
		sg: [ 'fr' ],
		sgs: [ 'lt' ],
		sli: [ 'de' ],
		sr: [ 'sr-ec' ],
		srn: [ 'nl' ],
		stq: [ 'de' ],
		su: [ 'id' ],
		szl: [ 'pl' ],
		tcy: [ 'kn' ],
		tg: [ 'tg-cyrl' ],
		tt: [ 'tt-cyrl', 'ru' ],
		'tt-cyrl': [ 'ru' ],
		ty: [ 'fr' ],
		udm: [ 'ru' ],
		ug: [ 'ug-arab' ],
		uk: [ 'ru' ],
		vec: [ 'it' ],
		vep: [ 'et' ],
		vls: [ 'nl' ],
		vmf: [ 'de' ],
		vot: [ 'fi' ],
		vro: [ 'et' ],
		wa: [ 'fr' ],
		wo: [ 'fr' ],
		wuu: [ 'zh-hans' ],
		xal: [ 'ru' ],
		xmf: [ 'ka' ],
		yi: [ 'he' ],
		za: [ 'zh-hans' ],
		zea: [ 'nl' ],
		zh: [ 'zh-hans' ],
		'zh-classical': [ 'lzh' ],
		'zh-cn': [ 'zh-hans' ],
		'zh-hant': [ 'zh-hans' ],
		'zh-hk': [ 'zh-hant', 'zh-hans' ],
		'zh-min-nan': [ 'nan' ],
		'zh-mo': [ 'zh-hk', 'zh-hant', 'zh-hans' ],
		'zh-my': [ 'zh-sg', 'zh-hans' ],
		'zh-sg': [ 'zh-hans' ],
		'zh-tw': [ 'zh-hant', 'zh-hans' ],
		'zh-yue': [ 'yue' ]
	} );
}( jQuery ) );
/*!
 * jQuery Internationalization library
 *
 * Copyright (C) 2011-2013 Santhosh Thottingal, Neil Kandalgaonkar
 *
 * jquery.i18n is dual licensed GPLv2 or later and MIT. You don't have to do
 * anything special to choose one license or the other and you don't have to
 * notify anyone which license you are using. You are free to use
 * UniversalLanguageSelector in commercial projects as long as the copyright
 * header is left intact. See files GPL-LICENSE and MIT-LICENSE for details.
 *
 * @licence GNU General Public Licence 2.0 or later
 * @licence MIT License
 */

( function ( $ ) {
	'use strict';

	var MessageParser = function ( options ) {
		this.options = $.extend( {}, $.i18n.parser.defaults, options );
		this.language = $.i18n.languages[ String.locale ] || $.i18n.languages[ 'default' ];
		this.emitter = $.i18n.parser.emitter;
	};

	MessageParser.prototype = {

		constructor: MessageParser,

		simpleParse: function ( message, parameters ) {
			return message.replace( /\$(\d+)/g, function ( str, match ) {
				var index = parseInt( match, 10 ) - 1;

				return parameters[ index ] !== undefined ? parameters[ index ] : '$' + match;
			} );
		},

		parse: function ( message, replacements ) {
			if ( message.indexOf( '{{' ) < 0 ) {
				return this.simpleParse( message, replacements );
			}

			this.emitter.language = $.i18n.languages[ $.i18n().locale ] ||
				$.i18n.languages[ 'default' ];

			return this.emitter.emit( this.ast( message ), replacements );
		},

		ast: function ( message ) {
			var pipe, colon, backslash, anyCharacter, dollar, digits, regularLiteral,
				regularLiteralWithoutBar, regularLiteralWithoutSpace, escapedOrLiteralWithoutBar,
				escapedOrRegularLiteral, templateContents, templateName, openTemplate,
				closeTemplate, expression, paramExpression, result,
				pos = 0;

			// Try parsers until one works, if none work return null
			function choice( parserSyntax ) {
				return function () {
					var i, result;

					for ( i = 0; i < parserSyntax.length; i++ ) {
						result = parserSyntax[ i ]();

						if ( result !== null ) {
							return result;
						}
					}

					return null;
				};
			}

			// Try several parserSyntax-es in a row.
			// All must succeed; otherwise, return null.
			// This is the only eager one.
			function sequence( parserSyntax ) {
				var i, res,
					originalPos = pos,
					result = [];

				for ( i = 0; i < parserSyntax.length; i++ ) {
					res = parserSyntax[ i ]();

					if ( res === null ) {
						pos = originalPos;

						return null;
					}

					result.push( res );
				}

				return result;
			}

			// Run the same parser over and over until it fails.
			// Must succeed a minimum of n times; otherwise, return null.
			function nOrMore( n, p ) {
				return function () {
					var originalPos = pos,
						result = [],
						parsed = p();

					while ( parsed !== null ) {
						result.push( parsed );
						parsed = p();
					}

					if ( result.length < n ) {
						pos = originalPos;

						return null;
					}

					return result;
				};
			}

			// Helpers -- just make parserSyntax out of simpler JS builtin types

			function makeStringParser( s ) {
				var len = s.length;

				return function () {
					var result = null;

					if ( message.slice( pos, pos + len ) === s ) {
						result = s;
						pos += len;
					}

					return result;
				};
			}

			function makeRegexParser( regex ) {
				return function () {
					var matches = message.slice( pos ).match( regex );

					if ( matches === null ) {
						return null;
					}

					pos += matches[ 0 ].length;

					return matches[ 0 ];
				};
			}

			pipe = makeStringParser( '|' );
			colon = makeStringParser( ':' );
			backslash = makeStringParser( '\\' );
			anyCharacter = makeRegexParser( /^./ );
			dollar = makeStringParser( '$' );
			digits = makeRegexParser( /^\d+/ );
			regularLiteral = makeRegexParser( /^[^{}\[\]$\\]/ );
			regularLiteralWithoutBar = makeRegexParser( /^[^{}\[\]$\\|]/ );
			regularLiteralWithoutSpace = makeRegexParser( /^[^{}\[\]$\s]/ );

			// There is a general pattern:
			// parse a thing;
			// if it worked, apply transform,
			// otherwise return null.
			// But using this as a combinator seems to cause problems
			// when combined with nOrMore().
			// May be some scoping issue.
			function transform( p, fn ) {
				return function () {
					var result = p();

					return result === null ? null : fn( result );
				};
			}

			// Used to define "literals" within template parameters. The pipe
			// character is the parameter delimeter, so by default
			// it is not a literal in the parameter
			function literalWithoutBar() {
				var result = nOrMore( 1, escapedOrLiteralWithoutBar )();

				return result === null ? null : result.join( '' );
			}

			function literal() {
				var result = nOrMore( 1, escapedOrRegularLiteral )();

				return result === null ? null : result.join( '' );
			}

			function escapedLiteral() {
				var result = sequence( [ backslash, anyCharacter ] );

				return result === null ? null : result[ 1 ];
			}

			choice( [ escapedLiteral, regularLiteralWithoutSpace ] );
			escapedOrLiteralWithoutBar = choice( [ escapedLiteral, regularLiteralWithoutBar ] );
			escapedOrRegularLiteral = choice( [ escapedLiteral, regularLiteral ] );

			function replacement() {
				var result = sequence( [ dollar, digits ] );

				if ( result === null ) {
					return null;
				}

				return [ 'REPLACE', parseInt( result[ 1 ], 10 ) - 1 ];
			}

			templateName = transform(
				// see $wgLegalTitleChars
				// not allowing : due to the need to catch "PLURAL:$1"
				makeRegexParser( /^[ !"$&'()*,.\/0-9;=?@A-Z\^_`a-z~\x80-\xFF+\-]+/ ),

				function ( result ) {
					return result.toString();
				}
			);

			function templateParam() {
				var expr,
					result = sequence( [ pipe, nOrMore( 0, paramExpression ) ] );

				if ( result === null ) {
					return null;
				}

				expr = result[ 1 ];

				// use a "CONCAT" operator if there are multiple nodes,
				// otherwise return the first node, raw.
				return expr.length > 1 ? [ 'CONCAT' ].concat( expr ) : expr[ 0 ];
			}

			function templateWithReplacement() {
				var result = sequence( [ templateName, colon, replacement ] );

				return result === null ? null : [ result[ 0 ], result[ 2 ] ];
			}

			function templateWithOutReplacement() {
				var result = sequence( [ templateName, colon, paramExpression ] );

				return result === null ? null : [ result[ 0 ], result[ 2 ] ];
			}

			templateContents = choice( [
				function () {
					var res = sequence( [
						// templates can have placeholders for dynamic
						// replacement eg: {{PLURAL:$1|one car|$1 cars}}
						// or no placeholders eg:
						// {{GRAMMAR:genitive|{{SITENAME}}}
						choice( [ templateWithReplacement, templateWithOutReplacement ] ),
						nOrMore( 0, templateParam )
					] );

					return res === null ? null : res[ 0 ].concat( res[ 1 ] );
				},
				function () {
					var res = sequence( [ templateName, nOrMore( 0, templateParam ) ] );

					if ( res === null ) {
						return null;
					}

					return [ res[ 0 ] ].concat( res[ 1 ] );
				}
			] );

			openTemplate = makeStringParser( '{{' );
			closeTemplate = makeStringParser( '}}' );

			function template() {
				var result = sequence( [ openTemplate, templateContents, closeTemplate ] );

				return result === null ? null : result[ 1 ];
			}

			expression = choice( [ template, replacement, literal ] );
			paramExpression = choice( [ template, replacement, literalWithoutBar ] );

			function start() {
				var result = nOrMore( 0, expression )();

				if ( result === null ) {
					return null;
				}

				return [ 'CONCAT' ].concat( result );
			}

			result = start();

			/*
			 * For success, the pos must have gotten to the end of the input
			 * and returned a non-null.
			 * n.b. This is part of language infrastructure, so we do not throw an internationalizable message.
			 */
			if ( result === null || pos !== message.length ) {
				throw new Error( 'Parse error at position ' + pos.toString() + ' in input: ' + message );
			}

			return result;
		}

	};

	$.extend( $.i18n.parser, new MessageParser() );
}( jQuery ) );
/*!
 * jQuery Internationalization library
 *
 * Copyright (C) 2011-2013 Santhosh Thottingal, Neil Kandalgaonkar
 *
 * jquery.i18n is dual licensed GPLv2 or later and MIT. You don't have to do
 * anything special to choose one license or the other and you don't have to
 * notify anyone which license you are using. You are free to use
 * UniversalLanguageSelector in commercial projects as long as the copyright
 * header is left intact. See files GPL-LICENSE and MIT-LICENSE for details.
 *
 * @licence GNU General Public Licence 2.0 or later
 * @licence MIT License
 */

( function ( $ ) {
	'use strict';

	var MessageParserEmitter = function () {
		this.language = $.i18n.languages[ String.locale ] || $.i18n.languages[ 'default' ];
	};

	MessageParserEmitter.prototype = {
		constructor: MessageParserEmitter,

		/**
		 * (We put this method definition here, and not in prototype, to make
		 * sure it's not overwritten by any magic.) Walk entire node structure,
		 * applying replacements and template functions when appropriate
		 *
		 * @param {Mixed} node abstract syntax tree (top node or subnode)
		 * @param {Array} replacements for $1, $2, ... $n
		 * @return {Mixed} single-string node or array of nodes suitable for
		 *  jQuery appending.
		 */
		emit: function ( node, replacements ) {
			var ret, subnodes, operation,
				messageParserEmitter = this;

			switch ( typeof node ) {
			case 'string':
			case 'number':
				ret = node;
				break;
			case 'object':
				// node is an array of nodes
				subnodes = $.map( node.slice( 1 ), function ( n ) {
					return messageParserEmitter.emit( n, replacements );
				} );

				operation = node[ 0 ].toLowerCase();

				if ( typeof messageParserEmitter[ operation ] === 'function' ) {
					ret = messageParserEmitter[ operation ]( subnodes, replacements );
				} else {
					throw new Error( 'unknown operation "' + operation + '"' );
				}

				break;
			case 'undefined':
				// Parsing the empty string (as an entire expression, or as a
				// paramExpression in a template) results in undefined
				// Perhaps a more clever parser can detect this, and return the
				// empty string? Or is that useful information?
				// The logical thing is probably to return the empty string here
				// when we encounter undefined.
				ret = '';
				break;
			default:
				throw new Error( 'unexpected type in AST: ' + typeof node );
			}

			return ret;
		},

		/**
		 * Parsing has been applied depth-first we can assume that all nodes
		 * here are single nodes Must return a single node to parents -- a
		 * jQuery with synthetic span However, unwrap any other synthetic spans
		 * in our children and pass them upwards
		 *
		 * @param {Array} nodes Mixed, some single nodes, some arrays of nodes.
		 * @return {string}
		 */
		concat: function ( nodes ) {
			var result = '';

			$.each( nodes, function ( i, node ) {
				// strings, integers, anything else
				result += node;
			} );

			return result;
		},

		/**
		 * Return escaped replacement of correct index, or string if
		 * unavailable. Note that we expect the parsed parameter to be
		 * zero-based. i.e. $1 should have become [ 0 ]. if the specified
		 * parameter is not found return the same string (e.g. "$99" ->
		 * parameter 98 -> not found -> return "$99" ) TODO throw error if
		 * nodes.length > 1 ?
		 *
		 * @param {Array} nodes One element, integer, n >= 0
		 * @param {Array} replacements for $1, $2, ... $n
		 * @return {string} replacement
		 */
		replace: function ( nodes, replacements ) {
			var index = parseInt( nodes[ 0 ], 10 );

			if ( index < replacements.length ) {
				// replacement is not a string, don't touch!
				return replacements[ index ];
			} else {
				// index not found, fallback to displaying variable
				return '$' + ( index + 1 );
			}
		},

		/**
		 * Transform parsed structure into pluralization n.b. The first node may
		 * be a non-integer (for instance, a string representing an Arabic
		 * number). So convert it back with the current language's
		 * convertNumber.
		 *
		 * @param {Array} nodes List [ {String|Number}, {String}, {String} ... ]
		 * @return {string} selected pluralized form according to current
		 *  language.
		 */
		plural: function ( nodes ) {
			var count = parseFloat( this.language.convertNumber( nodes[ 0 ], 10 ) ),
				forms = nodes.slice( 1 );

			return forms.length ? this.language.convertPlural( count, forms ) : '';
		},

		/**
		 * Transform parsed structure into gender Usage
		 * {{gender:gender|masculine|feminine|neutral}}.
		 *
		 * @param {Array} nodes List [ {String}, {String}, {String} , {String} ]
		 * @return {string} selected gender form according to current language
		 */
		gender: function ( nodes ) {
			var gender = nodes[ 0 ],
				forms = nodes.slice( 1 );

			return this.language.gender( gender, forms );
		},

		/**
		 * Transform parsed structure into grammar conversion. Invoked by
		 * putting {{grammar:form|word}} in a message
		 *
		 * @param {Array} nodes List [{Grammar case eg: genitive}, {String word}]
		 * @return {string} selected grammatical form according to current
		 *  language.
		 */
		grammar: function ( nodes ) {
			var form = nodes[ 0 ],
				word = nodes[ 1 ];

			return word && form && this.language.convertGrammar( word, form );
		}
	};

	$.extend( $.i18n.parser.emitter, new MessageParserEmitter() );
}( jQuery ) );
/*global pluralRuleParser */
( function ( $ ) {
	'use strict';

	// jscs:disable
	var language = {
		// CLDR plural rules generated using
		// libs/CLDRPluralRuleParser/tools/PluralXML2JSON.html
		'pluralRules': {
			'af': {
				'one': 'n = 1'
			},
			'ak': {
				'one': 'n = 0..1'
			},
			'am': {
				'one': 'i = 0 or n = 1'
			},
			'ar': {
				'zero': 'n = 0',
				'one': 'n = 1',
				'two': 'n = 2',
				'few': 'n % 100 = 3..10',
				'many': 'n % 100 = 11..99'
			},
			'ars': {
				'zero': 'n = 0',
				'one': 'n = 1',
				'two': 'n = 2',
				'few': 'n % 100 = 3..10',
				'many': 'n % 100 = 11..99'
			},
			'as': {
				'one': 'i = 0 or n = 1'
			},
			'asa': {
				'one': 'n = 1'
			},
			'ast': {
				'one': 'i = 1 and v = 0'
			},
			'az': {
				'one': 'n = 1'
			},
			'be': {
				'one': 'n % 10 = 1 and n % 100 != 11',
				'few': 'n % 10 = 2..4 and n % 100 != 12..14',
				'many': 'n % 10 = 0 or n % 10 = 5..9 or n % 100 = 11..14'
			},
			'bem': {
				'one': 'n = 1'
			},
			'bez': {
				'one': 'n = 1'
			},
			'bg': {
				'one': 'n = 1'
			},
			'bh': {
				'one': 'n = 0..1'
			},
			'bm': {},
			'bn': {
				'one': 'i = 0 or n = 1'
			},
			'bo': {},
			'br': {
				'one': 'n % 10 = 1 and n % 100 != 11,71,91',
				'two': 'n % 10 = 2 and n % 100 != 12,72,92',
				'few': 'n % 10 = 3..4,9 and n % 100 != 10..19,70..79,90..99',
				'many': 'n != 0 and n % 1000000 = 0'
			},
			'brx': {
				'one': 'n = 1'
			},
			'bs': {
				'one': 'v = 0 and i % 10 = 1 and i % 100 != 11 or f % 10 = 1 and f % 100 != 11',
				'few': 'v = 0 and i % 10 = 2..4 and i % 100 != 12..14 or f % 10 = 2..4 and f % 100 != 12..14'
			},
			'ca': {
				'one': 'i = 1 and v = 0'
			},
			'ce': {
				'one': 'n = 1'
			},
			'cgg': {
				'one': 'n = 1'
			},
			'chr': {
				'one': 'n = 1'
			},
			'ckb': {
				'one': 'n = 1'
			},
			'cs': {
				'one': 'i = 1 and v = 0',
				'few': 'i = 2..4 and v = 0',
				'many': 'v != 0'
			},
			'cy': {
				'zero': 'n = 0',
				'one': 'n = 1',
				'two': 'n = 2',
				'few': 'n = 3',
				'many': 'n = 6'
			},
			'da': {
				'one': 'n = 1 or t != 0 and i = 0,1'
			},
			'de': {
				'one': 'i = 1 and v = 0'
			},
			'dsb': {
				'one': 'v = 0 and i % 100 = 1 or f % 100 = 1',
				'two': 'v = 0 and i % 100 = 2 or f % 100 = 2',
				'few': 'v = 0 and i % 100 = 3..4 or f % 100 = 3..4'
			},
			'dv': {
				'one': 'n = 1'
			},
			'dz': {},
			'ee': {
				'one': 'n = 1'
			},
			'el': {
				'one': 'n = 1'
			},
			'en': {
				'one': 'i = 1 and v = 0'
			},
			'eo': {
				'one': 'n = 1'
			},
			'es': {
				'one': 'n = 1'
			},
			'et': {
				'one': 'i = 1 and v = 0'
			},
			'eu': {
				'one': 'n = 1'
			},
			'fa': {
				'one': 'i = 0 or n = 1'
			},
			'ff': {
				'one': 'i = 0,1'
			},
			'fi': {
				'one': 'i = 1 and v = 0'
			},
			'fil': {
				'one': 'v = 0 and i = 1,2,3 or v = 0 and i % 10 != 4,6,9 or v != 0 and f % 10 != 4,6,9'
			},
			'fo': {
				'one': 'n = 1'
			},
			'fr': {
				'one': 'i = 0,1'
			},
			'fur': {
				'one': 'n = 1'
			},
			'fy': {
				'one': 'i = 1 and v = 0'
			},
			'ga': {
				'one': 'n = 1',
				'two': 'n = 2',
				'few': 'n = 3..6',
				'many': 'n = 7..10'
			},
			'gd': {
				'one': 'n = 1,11',
				'two': 'n = 2,12',
				'few': 'n = 3..10,13..19'
			},
			'gl': {
				'one': 'i = 1 and v = 0'
			},
			'gsw': {
				'one': 'n = 1'
			},
			'gu': {
				'one': 'i = 0 or n = 1'
			},
			'guw': {
				'one': 'n = 0..1'
			},
			'gv': {
				'one': 'v = 0 and i % 10 = 1',
				'two': 'v = 0 and i % 10 = 2',
				'few': 'v = 0 and i % 100 = 0,20,40,60,80',
				'many': 'v != 0'
			},
			'ha': {
				'one': 'n = 1'
			},
			'haw': {
				'one': 'n = 1'
			},
			'he': {
				'one': 'i = 1 and v = 0',
				'two': 'i = 2 and v = 0',
				'many': 'v = 0 and n != 0..10 and n % 10 = 0'
			},
			'hi': {
				'one': 'i = 0 or n = 1'
			},
			'hr': {
				'one': 'v = 0 and i % 10 = 1 and i % 100 != 11 or f % 10 = 1 and f % 100 != 11',
				'few': 'v = 0 and i % 10 = 2..4 and i % 100 != 12..14 or f % 10 = 2..4 and f % 100 != 12..14'
			},
			'hsb': {
				'one': 'v = 0 and i % 100 = 1 or f % 100 = 1',
				'two': 'v = 0 and i % 100 = 2 or f % 100 = 2',
				'few': 'v = 0 and i % 100 = 3..4 or f % 100 = 3..4'
			},
			'hu': {
				'one': 'n = 1'
			},
			'hy': {
				'one': 'i = 0,1'
			},
			'id': {},
			'ig': {},
			'ii': {},
			'in': {},
			'is': {
				'one': 't = 0 and i % 10 = 1 and i % 100 != 11 or t != 0'
			},
			'it': {
				'one': 'i = 1 and v = 0'
			},
			'iu': {
				'one': 'n = 1',
				'two': 'n = 2'
			},
			'iw': {
				'one': 'i = 1 and v = 0',
				'two': 'i = 2 and v = 0',
				'many': 'v = 0 and n != 0..10 and n % 10 = 0'
			},
			'ja': {},
			'jbo': {},
			'jgo': {
				'one': 'n = 1'
			},
			'ji': {
				'one': 'i = 1 and v = 0'
			},
			'jmc': {
				'one': 'n = 1'
			},
			'jv': {},
			'jw': {},
			'ka': {
				'one': 'n = 1'
			},
			'kab': {
				'one': 'i = 0,1'
			},
			'kaj': {
				'one': 'n = 1'
			},
			'kcg': {
				'one': 'n = 1'
			},
			'kde': {},
			'kea': {},
			'kk': {
				'one': 'n = 1'
			},
			'kkj': {
				'one': 'n = 1'
			},
			'kl': {
				'one': 'n = 1'
			},
			'km': {},
			'kn': {
				'one': 'i = 0 or n = 1'
			},
			'ko': {},
			'ks': {
				'one': 'n = 1'
			},
			'ksb': {
				'one': 'n = 1'
			},
			'ksh': {
				'zero': 'n = 0',
				'one': 'n = 1'
			},
			'ku': {
				'one': 'n = 1'
			},
			'kw': {
				'one': 'n = 1',
				'two': 'n = 2'
			},
			'ky': {
				'one': 'n = 1'
			},
			'lag': {
				'zero': 'n = 0',
				'one': 'i = 0,1 and n != 0'
			},
			'lb': {
				'one': 'n = 1'
			},
			'lg': {
				'one': 'n = 1'
			},
			'lkt': {},
			'ln': {
				'one': 'n = 0..1'
			},
			'lo': {},
			'lt': {
				'one': 'n % 10 = 1 and n % 100 != 11..19',
				'few': 'n % 10 = 2..9 and n % 100 != 11..19',
				'many': 'f != 0'
			},
			'lv': {
				'zero': 'n % 10 = 0 or n % 100 = 11..19 or v = 2 and f % 100 = 11..19',
				'one': 'n % 10 = 1 and n % 100 != 11 or v = 2 and f % 10 = 1 and f % 100 != 11 or v != 2 and f % 10 = 1'
			},
			'mas': {
				'one': 'n = 1'
			},
			'mg': {
				'one': 'n = 0..1'
			},
			'mgo': {
				'one': 'n = 1'
			},
			'mk': {
				'one': 'v = 0 and i % 10 = 1 or f % 10 = 1'
			},
			'ml': {
				'one': 'n = 1'
			},
			'mn': {
				'one': 'n = 1'
			},
			'mo': {
				'one': 'i = 1 and v = 0',
				'few': 'v != 0 or n = 0 or n != 1 and n % 100 = 1..19'
			},
			'mr': {
				'one': 'i = 0 or n = 1'
			},
			'ms': {},
			'mt': {
				'one': 'n = 1',
				'few': 'n = 0 or n % 100 = 2..10',
				'many': 'n % 100 = 11..19'
			},
			'my': {},
			'nah': {
				'one': 'n = 1'
			},
			'naq': {
				'one': 'n = 1',
				'two': 'n = 2'
			},
			'nb': {
				'one': 'n = 1'
			},
			'nd': {
				'one': 'n = 1'
			},
			'ne': {
				'one': 'n = 1'
			},
			'nl': {
				'one': 'i = 1 and v = 0'
			},
			'nn': {
				'one': 'n = 1'
			},
			'nnh': {
				'one': 'n = 1'
			},
			'no': {
				'one': 'n = 1'
			},
			'nqo': {},
			'nr': {
				'one': 'n = 1'
			},
			'nso': {
				'one': 'n = 0..1'
			},
			'ny': {
				'one': 'n = 1'
			},
			'nyn': {
				'one': 'n = 1'
			},
			'om': {
				'one': 'n = 1'
			},
			'or': {
				'one': 'n = 1'
			},
			'os': {
				'one': 'n = 1'
			},
			'pa': {
				'one': 'n = 0..1'
			},
			'pap': {
				'one': 'n = 1'
			},
			'pl': {
				'one': 'i = 1 and v = 0',
				'few': 'v = 0 and i % 10 = 2..4 and i % 100 != 12..14',
				'many': 'v = 0 and i != 1 and i % 10 = 0..1 or v = 0 and i % 10 = 5..9 or v = 0 and i % 100 = 12..14'
			},
			'prg': {
				'zero': 'n % 10 = 0 or n % 100 = 11..19 or v = 2 and f % 100 = 11..19',
				'one': 'n % 10 = 1 and n % 100 != 11 or v = 2 and f % 10 = 1 and f % 100 != 11 or v != 2 and f % 10 = 1'
			},
			'ps': {
				'one': 'n = 1'
			},
			'pt': {
				'one': 'n = 0..2 and n != 2'
			},
			'pt-PT': {
				'one': 'n = 1 and v = 0'
			},
			'rm': {
				'one': 'n = 1'
			},
			'ro': {
				'one': 'i = 1 and v = 0',
				'few': 'v != 0 or n = 0 or n != 1 and n % 100 = 1..19'
			},
			'rof': {
				'one': 'n = 1'
			},
			'root': {},
			'ru': {
				'one': 'v = 0 and i % 10 = 1 and i % 100 != 11',
				'few': 'v = 0 and i % 10 = 2..4 and i % 100 != 12..14',
				'many': 'v = 0 and i % 10 = 0 or v = 0 and i % 10 = 5..9 or v = 0 and i % 100 = 11..14'
			},
			'rwk': {
				'one': 'n = 1'
			},
			'sah': {},
			'saq': {
				'one': 'n = 1'
			},
			'sdh': {
				'one': 'n = 1'
			},
			'se': {
				'one': 'n = 1',
				'two': 'n = 2'
			},
			'seh': {
				'one': 'n = 1'
			},
			'ses': {},
			'sg': {},
			'sh': {
				'one': 'v = 0 and i % 10 = 1 and i % 100 != 11 or f % 10 = 1 and f % 100 != 11',
				'few': 'v = 0 and i % 10 = 2..4 and i % 100 != 12..14 or f % 10 = 2..4 and f % 100 != 12..14'
			},
			'shi': {
				'one': 'i = 0 or n = 1',
				'few': 'n = 2..10'
			},
			'si': {
				'one': 'n = 0,1 or i = 0 and f = 1'
			},
			'sk': {
				'one': 'i = 1 and v = 0',
				'few': 'i = 2..4 and v = 0',
				'many': 'v != 0'
			},
			'sl': {
				'one': 'v = 0 and i % 100 = 1',
				'two': 'v = 0 and i % 100 = 2',
				'few': 'v = 0 and i % 100 = 3..4 or v != 0'
			},
			'sma': {
				'one': 'n = 1',
				'two': 'n = 2'
			},
			'smi': {
				'one': 'n = 1',
				'two': 'n = 2'
			},
			'smj': {
				'one': 'n = 1',
				'two': 'n = 2'
			},
			'smn': {
				'one': 'n = 1',
				'two': 'n = 2'
			},
			'sms': {
				'one': 'n = 1',
				'two': 'n = 2'
			},
			'sn': {
				'one': 'n = 1'
			},
			'so': {
				'one': 'n = 1'
			},
			'sq': {
				'one': 'n = 1'
			},
			'sr': {
				'one': 'v = 0 and i % 10 = 1 and i % 100 != 11 or f % 10 = 1 and f % 100 != 11',
				'few': 'v = 0 and i % 10 = 2..4 and i % 100 != 12..14 or f % 10 = 2..4 and f % 100 != 12..14'
			},
			'ss': {
				'one': 'n = 1'
			},
			'ssy': {
				'one': 'n = 1'
			},
			'st': {
				'one': 'n = 1'
			},
			'sv': {
				'one': 'i = 1 and v = 0'
			},
			'sw': {
				'one': 'i = 1 and v = 0'
			},
			'syr': {
				'one': 'n = 1'
			},
			'ta': {
				'one': 'n = 1'
			},
			'te': {
				'one': 'n = 1'
			},
			'teo': {
				'one': 'n = 1'
			},
			'th': {},
			'ti': {
				'one': 'n = 0..1'
			},
			'tig': {
				'one': 'n = 1'
			},
			'tk': {
				'one': 'n = 1'
			},
			'tl': {
				'one': 'v = 0 and i = 1,2,3 or v = 0 and i % 10 != 4,6,9 or v != 0 and f % 10 != 4,6,9'
			},
			'tn': {
				'one': 'n = 1'
			},
			'to': {},
			'tr': {
				'one': 'n = 1'
			},
			'ts': {
				'one': 'n = 1'
			},
			'tzm': {
				'one': 'n = 0..1 or n = 11..99'
			},
			'ug': {
				'one': 'n = 1'
			},
			'uk': {
				'one': 'v = 0 and i % 10 = 1 and i % 100 != 11',
				'few': 'v = 0 and i % 10 = 2..4 and i % 100 != 12..14',
				'many': 'v = 0 and i % 10 = 0 or v = 0 and i % 10 = 5..9 or v = 0 and i % 100 = 11..14'
			},
			'ur': {
				'one': 'i = 1 and v = 0'
			},
			'uz': {
				'one': 'n = 1'
			},
			've': {
				'one': 'n = 1'
			},
			'vi': {},
			'vo': {
				'one': 'n = 1'
			},
			'vun': {
				'one': 'n = 1'
			},
			'wa': {
				'one': 'n = 0..1'
			},
			'wae': {
				'one': 'n = 1'
			},
			'wo': {},
			'xh': {
				'one': 'n = 1'
			},
			'xog': {
				'one': 'n = 1'
			},
			'yi': {
				'one': 'i = 1 and v = 0'
			},
			'yo': {},
			'yue': {},
			'zh': {},
			'zu': {
				'one': 'i = 0 or n = 1'
			}
		},
		// jscs:enable

		/**
		 * Plural form transformations, needed for some languages.
		 *
		 * @param {integer} count
		 *            Non-localized quantifier
		 * @param {Array} forms
		 *            List of plural forms
		 * @return {string} Correct form for quantifier in this language
		 */
		convertPlural: function ( count, forms ) {
			var pluralRules,
				pluralFormIndex,
				index,
				explicitPluralPattern = new RegExp( '\\d+=', 'i' ),
				formCount,
				form;

			if ( !forms || forms.length === 0 ) {
				return '';
			}

			// Handle for Explicit 0= & 1= values
			for ( index = 0; index < forms.length; index++ ) {
				form = forms[ index ];
				if ( explicitPluralPattern.test( form ) ) {
					formCount = parseInt( form.slice( 0, form.indexOf( '=' ) ), 10 );
					if ( formCount === count ) {
						return ( form.slice( form.indexOf( '=' ) + 1 ) );
					}
					forms[ index ] = undefined;
				}
			}

			forms = $.map( forms, function ( form ) {
				if ( form !== undefined ) {
					return form;
				}
			} );

			pluralRules = this.pluralRules[ $.i18n().locale ];

			if ( !pluralRules ) {
				// default fallback.
				return ( count === 1 ) ? forms[ 0 ] : forms[ 1 ];
			}

			pluralFormIndex = this.getPluralForm( count, pluralRules );
			pluralFormIndex = Math.min( pluralFormIndex, forms.length - 1 );

			return forms[ pluralFormIndex ];
		},

		/**
		 * For the number, get the plural for index
		 *
		 * @param {integer} number
		 * @param {Object} pluralRules
		 * @return {integer} plural form index
		 */
		getPluralForm: function ( number, pluralRules ) {
			var i,
				pluralForms = [ 'zero', 'one', 'two', 'few', 'many', 'other' ],
				pluralFormIndex = 0;

			for ( i = 0; i < pluralForms.length; i++ ) {
				if ( pluralRules[ pluralForms[ i ] ] ) {
					if ( pluralRuleParser( pluralRules[ pluralForms[ i ] ], number ) ) {
						return pluralFormIndex;
					}

					pluralFormIndex++;
				}
			}

			return pluralFormIndex;
		},

		/**
		 * Converts a number using digitTransformTable.
		 *
		 * @param {number} num Value to be converted
		 * @param {boolean} integer Convert the return value to an integer
		 */
		convertNumber: function ( num, integer ) {
			var tmp, item, i,
				transformTable, numberString, convertedNumber;

			// Set the target Transform table:
			transformTable = this.digitTransformTable( $.i18n().locale );
			numberString = String( num );
			convertedNumber = '';

			if ( !transformTable ) {
				return num;
			}

			// Check if the restore to Latin number flag is set:
			if ( integer ) {
				if ( parseFloat( num, 10 ) === num ) {
					return num;
				}

				tmp = [];

				for ( item in transformTable ) {
					tmp[ transformTable[ item ] ] = item;
				}

				transformTable = tmp;
			}

			for ( i = 0; i < numberString.length; i++ ) {
				if ( transformTable[ numberString[ i ] ] ) {
					convertedNumber += transformTable[ numberString[ i ] ];
				} else {
					convertedNumber += numberString[ i ];
				}
			}

			return integer ? parseFloat( convertedNumber, 10 ) : convertedNumber;
		},

		/**
		 * Grammatical transformations, needed for inflected languages.
		 * Invoked by putting {{grammar:form|word}} in a message.
		 * Override this method for languages that need special grammar rules
		 * applied dynamically.
		 *
		 * @param {string} word
		 * @param {string} form
		 * @return {string}
		 */
		convertGrammar: function ( word, form ) { /*jshint unused: false */
			return word;
		},

		/**
		 * Provides an alternative text depending on specified gender. Usage
		 * {{gender:[gender|user object]|masculine|feminine|neutral}}. If second
		 * or third parameter are not specified, masculine is used.
		 *
		 * These details may be overriden per language.
		 *
		 * @param {string} gender
		 *      male, female, or anything else for neutral.
		 * @param {Array} forms
		 *      List of gender forms
		 *
		 * @return {string}
		 */
		gender: function ( gender, forms ) {
			if ( !forms || forms.length === 0 ) {
				return '';
			}

			while ( forms.length < 2 ) {
				forms.push( forms[ forms.length - 1 ] );
			}

			if ( gender === 'male' ) {
				return forms[ 0 ];
			}

			if ( gender === 'female' ) {
				return forms[ 1 ];
			}

			return ( forms.length === 3 ) ? forms[ 2 ] : forms[ 0 ];
		},

		/**
		 * Get the digit transform table for the given language
		 * See http://cldr.unicode.org/translation/numbering-systems
		 *
		 * @param {string} language
		 * @return {Array|boolean} List of digits in the passed language or false
		 * representation, or boolean false if there is no information.
		 */
		digitTransformTable: function ( language ) {
			var tables = {
				ar: '٠١٢٣٤٥٦٧٨٩',
				fa: '۰۱۲۳۴۵۶۷۸۹',
				ml: '൦൧൨൩൪൫൬൭൮൯',
				kn: '೦೧೨೩೪೫೬೭೮೯',
				lo: '໐໑໒໓໔໕໖໗໘໙',
				or: '୦୧୨୩୪୫୬୭୮୯',
				kh: '០១២៣៤៥៦៧៨៩',
				pa: '੦੧੨੩੪੫੬੭੮੯',
				gu: '૦૧૨૩૪૫૬૭૮૯',
				hi: '०१२३४५६७८९',
				my: '၀၁၂၃၄၅၆၇၈၉',
				ta: '௦௧௨௩௪௫௬௭௮௯',
				te: '౦౧౨౩౪౫౬౭౮౯',
				th: '๐๑๒๓๔๕๖๗๘๙', // FIXME use iso 639 codes
				bo: '༠༡༢༣༤༥༦༧༨༩' // FIXME use iso 639 codes
			};

			if ( !tables[ language ] ) {
				return false;
			}

			return tables[ language ].split( '' );
		}
	};

	$.extend( $.i18n.languages, {
		'default': language
	} );
}( jQuery ) );
/**
 * cldrpluralparser.js
 * A parser engine for CLDR plural rules.
 *
 * Copyright 2012-2014 Santhosh Thottingal and other contributors
 * Released under the MIT license
 * http://opensource.org/licenses/MIT
 *
 * @version 0.1.0
 * @source https://github.com/santhoshtr/CLDRPluralRuleParser
 * @author Santhosh Thottingal <santhosh.thottingal@gmail.com>
 * @author Timo Tijhof
 * @author Amir Aharoni
 */

/**
 * Evaluates a plural rule in CLDR syntax for a number
 * @param {string} rule
 * @param {integer} number
 * @return {boolean} true if evaluation passed, false if evaluation failed.
 */

// UMD returnExports https://github.com/umdjs/umd/blob/master/returnExports.js
(function(root, factory) {
	if (typeof define === 'function' && define.amd) {
		// AMD. Register as an anonymous module.
		define(factory);
	} else if (typeof exports === 'object') {
		// Node. Does not work with strict CommonJS, but
		// only CommonJS-like environments that support module.exports,
		// like Node.
		module.exports = factory();
	} else {
		// Browser globals (root is window)
		root.pluralRuleParser = factory();
	}
}(this, function() {

function pluralRuleParser(rule, number) {
	'use strict';

	/*
	Syntax: see http://unicode.org/reports/tr35/#Language_Plural_Rules
	-----------------------------------------------------------------
	condition     = and_condition ('or' and_condition)*
		('@integer' samples)?
		('@decimal' samples)?
	and_condition = relation ('and' relation)*
	relation      = is_relation | in_relation | within_relation
	is_relation   = expr 'is' ('not')? value
	in_relation   = expr (('not')? 'in' | '=' | '!=') range_list
	within_relation = expr ('not')? 'within' range_list
	expr          = operand (('mod' | '%') value)?
	operand       = 'n' | 'i' | 'f' | 't' | 'v' | 'w'
	range_list    = (range | value) (',' range_list)*
	value         = digit+
	digit         = 0|1|2|3|4|5|6|7|8|9
	range         = value'..'value
	samples       = sampleRange (',' sampleRange)* (',' ('…'|'...'))?
	sampleRange   = decimalValue '~' decimalValue
	decimalValue  = value ('.' value)?
	*/

	// We don't evaluate the samples section of the rule. Ignore it.
	rule = rule.split('@')[0].replace(/^\s*/, '').replace(/\s*$/, '');

	if (!rule.length) {
		// Empty rule or 'other' rule.
		return true;
	}

	// Indicates the current position in the rule as we parse through it.
	// Shared among all parsing functions below.
	var pos = 0,
		operand,
		expression,
		relation,
		result,
		whitespace = makeRegexParser(/^\s+/),
		value = makeRegexParser(/^\d+/),
		_n_ = makeStringParser('n'),
		_i_ = makeStringParser('i'),
		_f_ = makeStringParser('f'),
		_t_ = makeStringParser('t'),
		_v_ = makeStringParser('v'),
		_w_ = makeStringParser('w'),
		_is_ = makeStringParser('is'),
		_isnot_ = makeStringParser('is not'),
		_isnot_sign_ = makeStringParser('!='),
		_equal_ = makeStringParser('='),
		_mod_ = makeStringParser('mod'),
		_percent_ = makeStringParser('%'),
		_not_ = makeStringParser('not'),
		_in_ = makeStringParser('in'),
		_within_ = makeStringParser('within'),
		_range_ = makeStringParser('..'),
		_comma_ = makeStringParser(','),
		_or_ = makeStringParser('or'),
		_and_ = makeStringParser('and');

	function debug() {
		// console.log.apply(console, arguments);
	}

	debug('pluralRuleParser', rule, number);

	// Try parsers until one works, if none work return null
	function choice(parserSyntax) {
		return function() {
			var i, result;

			for (i = 0; i < parserSyntax.length; i++) {
				result = parserSyntax[i]();

				if (result !== null) {
					return result;
				}
			}

			return null;
		};
	}

	// Try several parserSyntax-es in a row.
	// All must succeed; otherwise, return null.
	// This is the only eager one.
	function sequence(parserSyntax) {
		var i, parserRes,
			originalPos = pos,
			result = [];

		for (i = 0; i < parserSyntax.length; i++) {
			parserRes = parserSyntax[i]();

			if (parserRes === null) {
				pos = originalPos;

				return null;
			}

			result.push(parserRes);
		}

		return result;
	}

	// Run the same parser over and over until it fails.
	// Must succeed a minimum of n times; otherwise, return null.
	function nOrMore(n, p) {
		return function() {
			var originalPos = pos,
				result = [],
				parsed = p();

			while (parsed !== null) {
				result.push(parsed);
				parsed = p();
			}

			if (result.length < n) {
				pos = originalPos;

				return null;
			}

			return result;
		};
	}

	// Helpers - just make parserSyntax out of simpler JS builtin types
	function makeStringParser(s) {
		var len = s.length;

		return function() {
			var result = null;

			if (rule.substr(pos, len) === s) {
				result = s;
				pos += len;
			}

			return result;
		};
	}

	function makeRegexParser(regex) {
		return function() {
			var matches = rule.substr(pos).match(regex);

			if (matches === null) {
				return null;
			}

			pos += matches[0].length;

			return matches[0];
		};
	}

	/**
	 * Integer digits of n.
	 */
	function i() {
		var result = _i_();

		if (result === null) {
			debug(' -- failed i', parseInt(number, 10));

			return result;
		}

		result = parseInt(number, 10);
		debug(' -- passed i ', result);

		return result;
	}

	/**
	 * Absolute value of the source number (integer and decimals).
	 */
	function n() {
		var result = _n_();

		if (result === null) {
			debug(' -- failed n ', number);

			return result;
		}

		result = parseFloat(number, 10);
		debug(' -- passed n ', result);

		return result;
	}

	/**
	 * Visible fractional digits in n, with trailing zeros.
	 */
	function f() {
		var result = _f_();

		if (result === null) {
			debug(' -- failed f ', number);

			return result;
		}

		result = (number + '.').split('.')[1] || 0;
		debug(' -- passed f ', result);

		return result;
	}

	/**
	 * Visible fractional digits in n, without trailing zeros.
	 */
	function t() {
		var result = _t_();

		if (result === null) {
			debug(' -- failed t ', number);

			return result;
		}

		result = (number + '.').split('.')[1].replace(/0$/, '') || 0;
		debug(' -- passed t ', result);

		return result;
	}

	/**
	 * Number of visible fraction digits in n, with trailing zeros.
	 */
	function v() {
		var result = _v_();

		if (result === null) {
			debug(' -- failed v ', number);

			return result;
		}

		result = (number + '.').split('.')[1].length || 0;
		debug(' -- passed v ', result);

		return result;
	}

	/**
	 * Number of visible fraction digits in n, without trailing zeros.
	 */
	function w() {
		var result = _w_();

		if (result === null) {
			debug(' -- failed w ', number);

			return result;
		}

		result = (number + '.').split('.')[1].replace(/0$/, '').length || 0;
		debug(' -- passed w ', result);

		return result;
	}

	// operand       = 'n' | 'i' | 'f' | 't' | 'v' | 'w'
	operand = choice([n, i, f, t, v, w]);

	// expr          = operand (('mod' | '%') value)?
	expression = choice([mod, operand]);

	function mod() {
		var result = sequence(
			[operand, whitespace, choice([_mod_, _percent_]), whitespace, value]
		);

		if (result === null) {
			debug(' -- failed mod');

			return null;
		}

		debug(' -- passed ' + parseInt(result[0], 10) + ' ' + result[2] + ' ' + parseInt(result[4], 10));

		return parseInt(result[0], 10) % parseInt(result[4], 10);
	}

	function not() {
		var result = sequence([whitespace, _not_]);

		if (result === null) {
			debug(' -- failed not');

			return null;
		}

		return result[1];
	}

	// is_relation   = expr 'is' ('not')? value
	function is() {
		var result = sequence([expression, whitespace, choice([_is_]), whitespace, value]);

		if (result !== null) {
			debug(' -- passed is : ' + result[0] + ' == ' + parseInt(result[4], 10));

			return result[0] === parseInt(result[4], 10);
		}

		debug(' -- failed is');

		return null;
	}

	// is_relation   = expr 'is' ('not')? value
	function isnot() {
		var result = sequence(
			[expression, whitespace, choice([_isnot_, _isnot_sign_]), whitespace, value]
		);

		if (result !== null) {
			debug(' -- passed isnot: ' + result[0] + ' != ' + parseInt(result[4], 10));

			return result[0] !== parseInt(result[4], 10);
		}

		debug(' -- failed isnot');

		return null;
	}

	function not_in() {
		var i, range_list,
			result = sequence([expression, whitespace, _isnot_sign_, whitespace, rangeList]);

		if (result !== null) {
			debug(' -- passed not_in: ' + result[0] + ' != ' + result[4]);
			range_list = result[4];

			for (i = 0; i < range_list.length; i++) {
				if (parseInt(range_list[i], 10) === parseInt(result[0], 10)) {
					return false;
				}
			}

			return true;
		}

		debug(' -- failed not_in');

		return null;
	}

	// range_list    = (range | value) (',' range_list)*
	function rangeList() {
		var result = sequence([choice([range, value]), nOrMore(0, rangeTail)]),
			resultList = [];

		if (result !== null) {
			resultList = resultList.concat(result[0]);

			if (result[1][0]) {
				resultList = resultList.concat(result[1][0]);
			}

			return resultList;
		}

		debug(' -- failed rangeList');

		return null;
	}

	function rangeTail() {
		// ',' range_list
		var result = sequence([_comma_, rangeList]);

		if (result !== null) {
			return result[1];
		}

		debug(' -- failed rangeTail');

		return null;
	}

	// range         = value'..'value
	function range() {
		var i, array, left, right,
			result = sequence([value, _range_, value]);

		if (result !== null) {
			debug(' -- passed range');

			array = [];
			left = parseInt(result[0], 10);
			right = parseInt(result[2], 10);

			for (i = left; i <= right; i++) {
				array.push(i);
			}

			return array;
		}

		debug(' -- failed range');

		return null;
	}

	function _in() {
		var result, range_list, i;

		// in_relation   = expr ('not')? 'in' range_list
		result = sequence(
			[expression, nOrMore(0, not), whitespace, choice([_in_, _equal_]), whitespace, rangeList]
		);

		if (result !== null) {
			debug(' -- passed _in:' + result);

			range_list = result[5];

			for (i = 0; i < range_list.length; i++) {
				if (parseInt(range_list[i], 10) === parseInt(result[0], 10)) {
					return (result[1][0] !== 'not');
				}
			}

			return (result[1][0] === 'not');
		}

		debug(' -- failed _in ');

		return null;
	}

	/**
	 * The difference between "in" and "within" is that
	 * "in" only includes integers in the specified range,
	 * while "within" includes all values.
	 */
	function within() {
		var range_list, result;

		// within_relation = expr ('not')? 'within' range_list
		result = sequence(
			[expression, nOrMore(0, not), whitespace, _within_, whitespace, rangeList]
		);

		if (result !== null) {
			debug(' -- passed within');

			range_list = result[5];

			if ((result[0] >= parseInt(range_list[0], 10)) &&
				(result[0] < parseInt(range_list[range_list.length - 1], 10))) {

				return (result[1][0] !== 'not');
			}

			return (result[1][0] === 'not');
		}

		debug(' -- failed within ');

		return null;
	}

	// relation      = is_relation | in_relation | within_relation
	relation = choice([is, not_in, isnot, _in, within]);

	// and_condition = relation ('and' relation)*
	function and() {
		var i,
			result = sequence([relation, nOrMore(0, andTail)]);

		if (result) {
			if (!result[0]) {
				return false;
			}

			for (i = 0; i < result[1].length; i++) {
				if (!result[1][i]) {
					return false;
				}
			}

			return true;
		}

		debug(' -- failed and');

		return null;
	}

	// ('and' relation)*
	function andTail() {
		var result = sequence([whitespace, _and_, whitespace, relation]);

		if (result !== null) {
			debug(' -- passed andTail' + result);

			return result[3];
		}

		debug(' -- failed andTail');

		return null;

	}
	//  ('or' and_condition)*
	function orTail() {
		var result = sequence([whitespace, _or_, whitespace, and]);

		if (result !== null) {
			debug(' -- passed orTail: ' + result[3]);

			return result[3];
		}

		debug(' -- failed orTail');

		return null;
	}

	// condition     = and_condition ('or' and_condition)*
	function condition() {
		var i,
			result = sequence([and, nOrMore(0, orTail)]);

		if (result) {
			for (i = 0; i < result[1].length; i++) {
				if (result[1][i]) {
					return true;
				}
			}

			return result[0];
		}

		return false;
	}

	result = condition();

	/**
	 * For success, the pos must have gotten to the end of the rule
	 * and returned a non-null.
	 * n.b. This is part of language infrastructure,
	 * so we do not throw an internationalizable message.
	 */
	if (result === null) {
		throw new Error('Parse error at position ' + pos.toString() + ' for rule: ' + rule);
	}

	if (pos !== rule.length) {
		debug('Warning: Rule not parsed completely. Parser stopped at ' + rule.substr(0, pos) + ' for rule: ' + rule);
	}

	return result;
}

return pluralRuleParser;

}));
