/**
 * Compact the interlanguage links in the sidebar
 *
 * Copyright (C) 2012-2014 Alolita Sharma, Amir Aharoni, Arun Ganesh, Brandon Harris,
 * Niklas Laxström, Pau Giner, Santhosh Thottingal, Siebrand Mazeland, Niharika Kohli
 * and other contributors. See CREDITS for a list.
 *
 * UniversalLanguageSelector is dual licensed GPLv2 or later and MIT. You don't
 * have to do anything special to choose one license or the other and you don't
 * have to notify anyone which license you are using. You are free to use
 * UniversalLanguageSelector in commercial projects as long as the copyright
 * header is left intact. See files GPL-LICENSE and MIT-LICENSE for details.
 *
 * @file
 * @ingroup Extensions
 * @licence GNU GPL-2.0+
 * @licence MIT License
 */

( function ( $, mw ) {
	'use strict';

	/**
	 * Add a language to the interlanguage list
	 * @param {string} name Name of language in Autonym font
	 * @param {string} url Link of the article in the respective language wiki
	 */
	function addLanguage( name, url ) {
		var $linkNode,
			$listNode,
			$interlanguageList;

		$linkNode = $( '<a>' )
			.addClass( 'active' )
			.attr( 'href', url )
			.text( name );

		$listNode = $( '<li>' )
			.append( $linkNode );

		$interlanguageList = $( '#p-lang > div > ul' );
		$interlanguageList.append( $listNode );
	}

	/**
	 * Find out the existing languages supported
	 * by article and fetch their href
	 * @return {Object} List of exiting language codes and their hrefs
	 */
	function getInterlanguageList() {
		var interlangList = {},
			selectedElement;
		$( '#p-lang > div > ul > li > a' ).each( function() {
			selectedElement = $( this );
			interlangList[ selectedElement.attr( 'lang' ) ] = selectedElement.attr( 'href' );
		} );
		return interlangList;
	}

	/**
	 * Fetch list of language names(in Autonym) which are supported by the article
	 * @return {Object} List of Language names in Autonym supported by article
	 */
	function getCurrentLanguages() {
		var acceptedLanglist = {},
			interlangList = getInterlanguageList(), i;
		for ( i in interlangList ) {
			if( interlangList.hasOwnProperty( i ) ) {
				acceptedLanglist[ i ] = $.uls.data.getAutonym(i);
			}
		}
		return acceptedLanglist;
	}

	/**
	 * Frequently spoken languages which are supported by the article for
	 * the Common Languages section of the ULS
	 * @return {Array} List of those language codes which are supported by article and appears
	 * in the Common Languages section of ULS
	 */
	function getCommonLanguages() {
		// From ext.uls.init.js
		var frequentLang = mw.uls.getFrequentLanguageList(),
			$acceptedLang = $.map( getCurrentLanguages(), function ( element, index ) {
				return index;
			} ),
			commonLanguages = [], i;
		for ( i = 0; i < frequentLang.length; i++ ) {
			if ( $.inArray( frequentLang[i], $acceptedLang ) >= 0 ) {
				commonLanguages.push( frequentLang[i] );
			}
		}
		return commonLanguages;
	}

	/**
	 * Add a ULS trigger beneath the interlanguage links
	 */
	function addULSlink() {
		var $newLinknode,
			$interlanguageList,
			supportedLangs,
			posOfTrigger;

		$newLinknode = $( '<div>' )
			.addClass( 'mw-interlanguage-selector mw-ui-button active' )
			.html( '&#8230' )
			.append( $newLinknode );

		$interlanguageList = $( '#p-lang > div > ul' );
		$interlanguageList.append( $newLinknode );
		posOfTrigger = $newLinknode.offset();

		$( '.mw-interlanguage-selector' ).uls( {
			onReady: function() {
				this.$menu.addClass( 'interlanguage-uls-menu' );
			},

			onSelect: function( language ) {
				supportedLangs = getInterlanguageList();
				window.location.href = supportedLangs[language];
			},

			compact: true,
			left: posOfTrigger.left + $newLinknode.width() + 50 + 'px',
			top: posOfTrigger.top - $newLinknode.height()/2 - 250 + 'px',
			languages: getCurrentLanguages(),
			quickList: getCommonLanguages()
		} );
	}

	/**
	 * Hide existing languages displayed on the page
	 */
	function hideLanguages() {
		var $languages = $( '.interlanguage-link' );
		$languages.hide();
	}

	/**
	 * Returns all languages returned by the commonLanguages function
	 * and randomly some more if the number falls short of numberOfLanguagesToShow parameter
	 * @param {number} numberOfLanguagesToShow Number of languages to be shown in sidebar
	 * @return {Array} Language codes of the final list to be displayed
	 */
	function displayLanguages( numberOfLanguagesToShow ) {
		var commonLang = getCommonLanguages(),
			currentLangs = getInterlanguageList(), i,
			count,
			finalList = [];

		// Check existing languages for ones in common, and add them to final list
		for ( i = 0; i < commonLang.length; i++ ) {
			finalList.push( commonLang[i] );
		}

		count = commonLang.length;
		if ( count < numberOfLanguagesToShow ) {
			for ( i in currentLangs ) {
				if ( $.inArray( i, commonLang ) === -1 ) {
					finalList.push( i );
					count++;
					if ( count === numberOfLanguagesToShow ) {
						break;
					}
				}
			}
		}

		// Sorting the language list in alphabetical order of ISO codes
		finalList = finalList.sort();
		return finalList;
	}

	/*
	 * Adds a label stating the number of more languages
	 * beneath the ULS link
	 * @param {Number} numberOfLanguagesSupported Number of languages supported by article
	 * @param {Number} numberOfLanguagesToShow Number of languages to be shown in the sidebar
	 */
	function addLabel( numberOfLanguagesSupported, numberOfLanguagesToShow ) {
		var $interlanguageList = $( '#p-lang > div > ul' ),
			newLabel = $( '<label>' )
				.attr( 'id', 'more-lang-label' ),
			numberOfLanguagesHidden = numberOfLanguagesSupported - numberOfLanguagesToShow;
		newLabel.text( $.i18n( 'ext-uls-compact-link-count', numberOfLanguagesHidden ) );
		$interlanguageList.append( newLabel );
	}

	/*
	 * Driver function to manipulate interlanguage list
	 * Computes number of languages to be shown
	 * and passes appropriate parameters to displayLanguages
	 * and addLabel functions
	 */
	function manageInterlaguageList() {
		var $numOfLangCurrently = $( '.interlanguage-link' ).length,
			currentLangs = getInterlanguageList(),
			numLanguages = 9,
			minLanguages = 7,
			i,
			finalList; //Final list of languages to be displayed on page

		if ( $numOfLangCurrently > 9) {
			hideLanguages();
			if ( $numOfLangCurrently > 9 && $numOfLangCurrently <= 12 ) {
				finalList = displayLanguages( minLanguages );
			} else {
				finalList = displayLanguages( numLanguages );
			}

			for ( i in finalList ) {
				addLanguage( $.uls.data.getAutonym( finalList[i] ), currentLangs[ finalList[i] ] );
			}

			addULSlink();
			addLabel( $numOfLangCurrently, finalList.length );
		}
	}

	$( document ).ready( manageInterlaguageList() );

}( jQuery, mediaWiki ) );
