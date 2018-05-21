<?php

class DiscoveryAPI extends ApiBase {

	protected $currentUrl = '';

	protected $campaigns = [];

	protected $ads = [];

	private $allCampaignAds = [];

	protected $seeAlso = [];

	protected $seeAlsoTitleKeys = [];

	/* @var Title */
	protected $title;

	protected $generalCampaign = '';

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
		$this->generalCampaign = $wgPromoterFallbackCampaign;

		$queryResult      = $this->getResult();
		$params           = $this->extractRequestParams();
		$this->title      = Title::newFromText( $params['title'] );
		$this->currentUrl = $this->title->getFullURL();

		// Get 'see also' item IDs
		$this->seeAlsoTitleKeys = $this->getRelevantArticles( $this->title, 2 );
		if ( empty( $this->seeAlsoTitleKeys ) ) {
			$this->seeAlso = [];
		} else {
			// Get 'see also' items by given IDs
			$seeAlsoTitles = $this->getTitlesFromTitleStrings( $this->seeAlsoTitleKeys );

			// Parse 'see also' items to key->value objects
			$this->seeAlso = $this->getSeeAlsoItemData( $seeAlsoTitles );

			// Get page categories
			$categories = $this->getCategoriesByTitleString( $this->title );

			// Get campaigns based on page categories
			$this->campaigns = $this->getCampaignsByCategories( $categories );

			$this->allCampaignAds = $this->getCampaignAds( $this->campaigns );
			shuffle( $this->allCampaignAds );
		}

		// Populate 'see also' array
		if ( count( $this->seeAlso ) < self::MAX_SEE_ALSO_ITEMS ) {
			$this->populateSeeAlso();
		}

		$this->populateAds( $this->allCampaignAds );

		$result = [
			'seeAlso' => $this->seeAlso,
			'ads'     => $this->ads
		];

		$queryResult->addValue( null, 'discovery', $result );
	}

	/**
	 * Take given ads, shuffle and push them to $this->ads array
	 *
	 * @param array $adsArray Array of ads (obviously)
	 * @return void
	 */
	protected function populateAds( array $adsArray = [], $limit = self::MAX_AD_ITEMS ) {
		if ( !empty( $adsArray ) ) {
			shuffle( $adsArray );

			foreach ( $adsArray as $key => $value ) {
				if (
				// if current ad url isn't similar to the current page url
				$value['url'] !== $this->currentUrl
				// if current ad url doesn't exist in $this->ads
				&& $this->isUniqueInArray( $this->ads, 'url', $value['url'] )
				// if current ad url doesn't exist in $this->seeAlso
				&& $this->isUniqueInArray( $this->seeAlso, 'url', $value['url'] )
				// Make sure only the first <MAX_AD_ITEMS> keys are evaluated (they are random anyway)
				&& $key < $limit ) {
					$this->ads[] = $value;
				}
			}

			if ( count( $this->ads ) < self::MAX_AD_ITEMS ) {
				$generalAds = $this->getCampaignAds( [ $this->generalCampaign ] );
				$this->populateAds( $generalAds, self::MAX_AD_ITEMS - count( $this->ads ) );
			}
		}
	}

	/**
	 * Generate 'see also' data, shuffle it and then push to $this->seeAlso array
	 *
	 * @return void
	 */
	protected function populateSeeAlso() {
		$itemsData     = $this->seeAlsoTitleKeys;
		$seeAlsoTitles = $this->getTitlesFromTitleStrings( $itemsData );
		$itemsData     = $this->getSeeAlsoItemData( $seeAlsoTitles );

		shuffle( $itemsData );

		if ( !empty( $this->seeAlso ) ) {
			foreach ( $ads as $key => $value ) {
				if ( $value['url']
					&& $value['url'] !== $this->currentUrl
					&& $this->isUniqueInArray( $this->seeAlso, 'url', $value['url'] )
					&& $this->isUniqueInArray( $this->ads, 'url', $value['url'] )
					&& $key < self::MAX_SEE_ALSO_ITEMS
				) {
					$this->seeAlso[] = $value;
				}
			}
		}
	}

	protected function isUniqueInArray( $array, $keyName, $newValue ) {
		foreach ( $array as $key => $value ) {
			if ( $value[$keyName] === $newValue ) {
				return false;
			}
		}

		return true;
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
			$seeAlso[] = [
				'content' => $value->getText(),
				'url' => $value->getFullURL()
			];
		}

		return $seeAlso;
	}

	/**
	 * getCampaignAds
	 *
	 * @param array $campaigns
	 * @return array
	 */
	public function getCampaignAds( array $campaigns = [] ) {
		if ( empty( $campaigns ) ) {
			return false;
		}

		$campaigns = str_replace( '_', ' ', $campaigns );

		$ads = [];
		foreach ( $campaigns as $campaign ) {
			$campaign = new AdCampaign( $campaign );

			if ( $campaign->isEnabled() ) {
				$campaign_ads = $campaign->getAds();

				foreach ( $campaign_ads as $ad ) {
					$ad = Ad::fromName( $ad['name'] );
					$ads[] = $this->getAdData( $ad );
				}
			}
		}

		return empty( $ads ) ? [] : $ads;
	}

	/**
	 * getCampaignsByCategories
	 *
	 * @param mixed $categories
	 * @return array
	 */
	public function getCampaignsByCategories( $categories ) {
		$campaigns = AdCampaign::getAllCampaignNames();
		$campaigns = str_replace( ' ', '_', $campaigns );
		$campaigns = array_intersect( $categories, $campaigns );
		$result = array_values( $campaigns );

		return !empty( $result ) ? $result : [];
	}

	/**
	 * getAdData
	 *
	 * @param Ad $ad
	 * @return array|Boolean
	 */
	public function getAdData( Ad $ad ) {
		global $wgServer;

		if ( !$ad || !$ad->isNotExpired() ) {
			return false;
		}

		$url = $ad->getMainLink();
		$url = empty( $url ) ? null : Skin::makeInternalOrExternalUrl( $ad->getMainLink() );
		$url = strpos( $url, '/' ) === 0 ? $wgServer . $url : $url;

		return [
			'content'    => $ad->getBodyContent(),
			'url'        => $url,
			'indicators' => [
				'new' => (int)$ad->isNew()
			]
		];
	}

	/**
	 * getCategoriesByTitleString
	 *
	 * @param String $title
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
