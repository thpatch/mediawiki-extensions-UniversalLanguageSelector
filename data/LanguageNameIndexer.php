<?php
/**
 * Script to create language names index.
 *
 * Copyright (C) 2012 Alolita Sharma, Amir Aharoni, Arun Ganesh, Brandon Harris,
 * Niklas Laxström, Pau Giner, Santhosh Thottingal, Siebrand Mazeland and other
 * contributors. See CREDITS for a list.
 *
 * UniversalLanguageSelector is dual licensed GPLv2 or later and MIT. You don't
 * have to do anything special to choose one license or the other and you don't
 * have to notify anyone which license you are using. You are free to use
 * UniversalLanguageSelector in commercial projects as long as the copyright
 * header is left intact. See files GPL-LICENSE and MIT-LICENSE for details.
 *
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 * @license MIT
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MediaWikiServices;

class LanguageNameIndexer extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Script to create language names index.' );
		$this->requireExtension( 'UniversalLanguageSelector' );
		$this->requireExtension( 'CLDR' );
	}

	public function execute() {
		$languageNames = [];
		// Add languages from language-data
		$ulsLanguages = $this->getLanguageData()[ 'languages' ];
		foreach ( $ulsLanguages as $languageCode => $languageEntry ) {
			// Redirect have only one item
			if ( isset( $languageEntry[ 2 ] ) ) {
				$languageNames[ 'autonyms' ][ $languageCode ] = $languageEntry[ 2 ];
			}
		}

		// Languages and their names in different languages from Names.php and the cldr extension
		// This comes after $ulsLanguages so that for example the als/gsw mixup is using the code
		// used in the Wikimedia world.
		$mwLanguages = MediaWikiServices::getInstance()->getLanguageNameUtils()
			->getLanguageNames( LanguageNameUtils::AUTONYMS, LanguageNameUtils::ALL );
		foreach ( array_keys( $mwLanguages ) as $languageCode ) {
			$languageNames[ $languageCode ] = LanguageNames::getNames( $languageCode, 0, 2 );
		}

		$buckets = [];
		foreach ( $languageNames as $translations ) {
			foreach ( $translations as $targetLanguage => $translation ) {
				// Remove directionality markers used in Names.php: users are not
				// going to type these.
				$translation = str_replace( "\xE2\x80\x8E", '', $translation );
				$translation = mb_strtolower( $translation );
				$translation = trim( $translation );

				// Clean up "gjermanishte zvicerane (dialekti i alpeve)" to "gjermanishte zvicerane".
				// The original name is still shown, but avoid us creating entries such as
				// "(dialekti" or "alpeve)".
				$basicForm = preg_replace( '/\(.+\)$/', '', $translation );
				$words = preg_split( '/[\s]+/u', $basicForm, -1, PREG_SPLIT_NO_EMPTY );

				foreach ( $words as $index => $word ) {
					$bucket = LanguageNameSearch::getIndex( $word );

					$type = 'prefix';
					$display = $translation;
					if ( $index > 0 && count( $words ) > 1 ) {
						$type = 'infix';
						$display = "$word — $translation";
					}
					$buckets[$bucket][$type][$display] = $targetLanguage;
				}
			}
		}

		// Some languages don't have a conveniently searchable name in CLDR.
		// For example, the name of Western Punjabi doesn't start with
		// the string "punjabi" in any language, so it cannot be found
		// by people who search in English.
		// To resolve this, some languages are added here locally.
		$specialLanguages = [
			// Catalan, sometimes searched as "Valencià"
			'ca' => [ 'valencia' ],
			// Spanish, the transliteration of the autonym is often used for searching
			'es' => [ 'castellano' ],
			// Armenian, the transliteration of the autonym is often used for searching
			'hy' => [ 'hayeren' ],
			// Georgian, the transliteration of the autonym is often used for searching
			'ka' => [ 'kartuli', 'qartuli' ],
			// Japanese, the transliteration of the autonym is often used for searching
			'ja' => [ 'nihongo', 'にほんご' ],
			// Western Punjabi, doesn't start with the word "Punjabi" in any language
			'pnb' => [ 'punjabi western' ],
			// Simplified and Traditional Chinese, because zh-hans and zh-hant
			// are not mapped to any English name
			'zh-hans' => [ 'chinese simplified' ],
			'zh-hant' => [ 'chinese traditional' ],
		];

		foreach ( $specialLanguages as $targetLanguage => $translations ) {
			foreach ( $translations as $translation ) {
				$bucket = LanguageNameSearch::getIndex( $translation );
				$buckets[$bucket]['prefix'][$translation] = $targetLanguage;
			}
		}

		$lengths = [];
		// Sorting the bucket contents gives two benefits:
		// - more consistent output across environments
		// - shortest matches appear first, especially exact matches
		// Sort buckets by index
		ksort( $buckets );
		foreach ( $buckets as &$bucketTypes ) {
			$lengths[] = array_sum( array_map( 'count', $bucketTypes ) );
			// Ensure 'prefix' is before 'infix';
			krsort( $bucketTypes );
			// Ensure each bucket has entries sorted
			foreach ( $bucketTypes as &$bucket ) {
				ksort( $bucket );
			}
		}

		$count = count( $buckets );
		$min = min( $lengths );
		$max = max( $lengths );
		$median = $lengths[ceil( $count / 2 )];
		$avg = array_sum( $lengths ) / $count;
		$this->output( "Bucket stats:\n - $count buckets\n - smallest has $min entries\n" );
		$this->output( " - largest has $max entries\n - median size is $median entries\n" );
		$this->output( " - average size is $avg entries\n" );

		$this->generateFile( $buckets );
	}

	/**
	 * @return array
	 */
	private function getLanguageData() {
		$file = __DIR__ . '/../lib/jquery.uls/src/jquery.uls.data.js';
		$contents = file_get_contents( $file );
		if ( !preg_match( '/.*\$\.uls\.data\s*=\s*(.*?)\s*}\s*\(\s*jQuery\s*\)/s', $contents, $matches ) ) {
			throw new LogicException( 'Syntax error in jquery.uls.data.js?' );
		}
		$json = $matches[ 1 ];
		$data = json_decode( $json, true );
		if ( !$data ) {
			throw new LogicException( 'json_decode failed. Syntax error in jquery.uls.data.js?' );
		}
		return $data;
	}

	/**
	 * @param array $buckets
	 */
	private function generateFile( array $buckets ) {
		$template = <<<'PHP'
<?php
// This file is generated by a script!
class LanguageNameSearchData {
	public static $buckets = ___;
}

PHP;

		// Format for short array format
		$data = var_export( $buckets, true );
		$data = str_replace( "array (", '[', $data );
		$data = str_replace( "),", '],', $data );
		// Closing of the array, add correct indentation
		$data = preg_replace( "/\)$/", "\t]", $data );
		// Remove newlines after =>s
		$data = preg_replace( '/(=>)\s+(\[)/m', '\1 \2', $data );
		// Convert spaces to tabs. Since we are not top-level need more tabs.
		$data = preg_replace( '/^      /m', "\t\t\t\t", $data );
		$data = preg_replace( '/^    /m', "\t\t\t", $data );
		$data = preg_replace( '/^  /m', "\t\t", $data );

		$template = str_replace( '___', $data, $template );

		file_put_contents( __DIR__ . '/LanguageNameSearchData.php', $template );
	}
}

$maintClass = LanguageNameIndexer::class;
require_once RUN_MAINTENANCE_IF_MAIN;
