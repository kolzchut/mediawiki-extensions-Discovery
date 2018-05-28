<?php

class DiscoveryAPI extends ApiBase {

	protected $ads = [];

	protected $seeAlso = [];

	protected $urls = [];

	const MAX_SEE_ALSO_ITEMS = 2;

	const MAX_AD_ITEMS = 2;

	public function __construct( $main, $moduleName ) {
		parent::__construct( $main, $moduleName );
	}

	protected function getAllowedParams() {
		return [
			'title' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			]
		];
	}

	public function execute() {
		global $wgPromoterFallbackCampaign;

		$queryResult = $this->getResult();
		$params      = $this->extractRequestParams();
		$title       = Title::newFromText( $params['title'] );

		// Get 'see also' item IDs
		$seeAlsoTitleKeys = $this->getRelevantArticles( $title, self::MAX_SEE_ALSO_ITEMS );
		if ( empty( $seeAlsoTitleKeys ) ) {
			$this->seeAlso = [];
		} else {
			// Get 'see also' items by given IDs
			$seeAlsoTitles = $this->getTitlesFromTitleStrings( $seeAlsoTitleKeys );

			// Parse 'see also' items to key->value objects
			$this->seeAlso = $this->getSeeAlsoItemData( $seeAlsoTitles );
		}

		// Get page categories
		$categories = $this->getCategoriesByTitleString( $title );

		// Get campaigns based on page categories
		$campaigns = $this->getCampaignsByCategories( $categories );

		// Get a fixed number of random ads from current active campaigns
		$this->ads = $this->getCampaignAds( $campaigns, $this->urls, self::MAX_AD_ITEMS );

		// if $this->ads isn't full, fill it with ads from the General campaign
		if ( count( $this->ads ) < self::MAX_AD_ITEMS ) {
			$this->fillMissingAds( $this->ads, $wgPromoterFallbackCampaign, self::MAX_AD_ITEMS );
		}

		// if $this->seeAlso isn't full, fill it with ads from the General campaign
		if ( count( $this->seeAlso ) < self::MAX_SEE_ALSO_ITEMS ) {
			$this->fillMissingAds( $this->seeAlso, $wgPromoterFallbackCampaign, self::MAX_SEE_ALSO_ITEMS );
		}

		$result = [
			'seeAlso' => $this->seeAlso,
			'ads'     => $this->ads
		];

		$queryResult->addValue( null, 'discovery', $result );
	}

	/**
	 * Fill an array of ads to match its max value
	 *
	 * @param array &$adsArray The array to add ads to
	 * @param string $fallbackCampaign The name of the campaign to fetch ads from
	 * @param int $limit Ad limit
	 * @return void
	 */
	private function fillMissingAds( &$adsArray, $fallbackCampaign, $limit ) {
		$generalCampaign = $this->getCampaignAds(
			[ $fallbackCampaign ], $this->urls, $limit - count( $adsArray )
		);

		$adsArray = array_merge( $adsArray, $generalCampaign );
	}

	/**
	 * getSeeAlsoItemData
	 *
	 * @param array $titles
	 * @return array|bool
	 */
	public function getSeeAlsoItemData( array $titles ) {
		if ( !$titles || empty( $titles ) ) {
			return false;
		}

		$seeAlso = [];
		foreach ( $titles as $key => $value ) {
			$this->urls[] = $value->getFullURL();

			$seeAlso[] = [
				'content' => $value->getText(),
				'url'     => $value->getFullURL()
			];
		}

		return $seeAlso;
	}

	/**
	 * Get active ads from active campaigns, initialize them and return a normalized structure
	 *
	 * @param array $campaigns array of campaign names to include
	 * @param array $ignoredUrls array of URLs to ignore (normally $this->urls)
	 * @param int $limit max number of ads to fetch
	 * @return array
	 */
	public function getCampaignAds( array $campaigns = [], array $ignoredUrls = [], $limit ) {
		$adsToMap = AdCampaign::getCampaignAds( $campaigns, $ignoredUrls, $limit );

		$ads = array_map( function ( $adItem ) {
			global $wgServer;

			$ad = Ad::fromId( $adItem->ad_id );

			$url = $ad->getMainLink();
			$url = empty( $url ) ? null : Skin::makeInternalOrExternalUrl( $ad->getMainLink() );
			$url = wfExpandUrl( $url );
			$this->urls[] = $url;

			return [
				'content'    => $ad->getBodyContent(),
				'url'        => $url,
				'indicators' => [
					'new' => (int)$ad->isNew()
				]
			];
		}, $adsToMap );

		return $ads;
	}

	/**
	 * getCampaignsByCategories
	 *
	 * @param array $categories
	 * @return array
	 */
	public function getCampaignsByCategories( $categories ) {
		$campaigns = AdCampaign::getAllCampaignNames();
		$campaigns = str_replace( ' ', '_', $campaigns );
		$campaigns = array_intersect( $categories, $campaigns );
		$result    = array_values( $campaigns );

		return !empty( $result ) ? $result : [];
	}

	/**
	 * getCategoriesByTitleString
	 *
	 * @param Title $title
	 * @return array
	 */
	public function getCategoriesByTitleString( Title $title ) {
		$categories = $title->getParentCategories();

		$categories = array_keys( $categories );
		$categories = array_map( function ( $item ) {
			return substr( $item, strpos( $item, ':' ) + 1, strlen( $item ) );
		}, $categories );

		return empty( $categories ) ? [] : $categories;
	}

	/**
	 * Get relevant articles according to current page's ($title) 'read also' list
	 *
	 * @param Title $title
	 * @param Int $limit
	 * @return array
	 */
	public static function getRelevantArticles( Title $title, Int $limit = 2 ) {
		$results = self::getSemanticData( $title, 'ראו גם' );

		shuffle( $results );

		$limit = ( count( $results ) < $limit ) ? count( $results ) : $limit;
		$limitedKeys = array_rand( $results, $limit );
		$data = [];

		if ( is_array( $limitedKeys ) ) {
			foreach ( $limitedKeys as $key => $value ) {
				$data[] = $results[$key];
			}
		} else {
			$data[] = $results[$limitedKeys];
		}

		$filteredData = self::removeHashes( $data );

		return $filteredData;
	}

	/**
	 * Remove hashes from each element in an array of strings, return new array
	 *
	 * @param array $arr
	 * @return array
	 */
	public static function removeHashes( array $arr ) {
		return array_map( function ( $item ) {
			return preg_replace( '/#\d+#/', '', $item );
		}, $arr );
	}
	/**
	 * @param array $strings
	 * @return array
	 */
	public static function getTitlesFromTitleStrings( array $strings ) {
		$titlesArr = [];

		foreach ( $strings as $value ) {
			$titlesArr[] = Title::newFromDBkey( $value );
		}

		return $titlesArr;
	}

	/**
	 * getSemanticData
	 *
	 * @param Title $title
	 * @param String $property
	 * @return array
	 */
	public static function getSemanticData( Title $title, $property = null ) {
		$store = SMW\StoreFactory::getStore()->getSemanticData( \SMW\DIWikiPage::newFromText( $title->getText() ) );

		$arrSMWProps = $store->getProperties();
		$arrValues   = [];

		foreach ( $arrSMWProps as $smwProp ) {
			$arrSMWPropValues = $store->getPropertyValues( $smwProp );
			foreach ( $arrSMWPropValues as $smwPropValue ) {
				$arrValues[$smwProp->getLabel()][] = $smwPropValue->getSerialization();
			}
		}

		return ( $property !== null ) ? $arrValues[$property] : $arrValues;
	}

}
