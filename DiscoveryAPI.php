<?php

class DiscoveryAPI extends ApiBase {

	protected $currentUrl = '';

	protected $campaigns = [];

	protected $ads = [];

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
		}

		// Populate 'see also' array recursively
		if ( count( $this->seeAlso ) < $this::MAX_SEE_ALSO_ITEMS ) {
			$this->populateSeeAlso();
		}

		// Populate ads array recursively
		$this->populateAds();

		$result = [
			'seeAlso' => $this->seeAlso,
			'ads'     => $this->ads
		];

		$queryResult->addValue( null, 'discovery', $result );
	}

	

	/**
	 * Get ads recursively according to active campaigns & categories, and push them to $this->ads array
	 *
	 * @param mixed $timesRun
	 * @return Boolean
	 */
	protected function populateAds( $timesRun = 0 ) {
		$adCount = count( $this->ads );
		$totalToFetch = ( $this::MAX_SEE_ALSO_ITEMS - count( $this->seeAlso ) ) + $this::MAX_AD_ITEMS - $adCount;

        $ads = $this->getRandomAdsFromCampaigns( $this->campaigns, $totalToFetch );

		if ( $timesRun < 10 && !empty( $ads ) ) {
			foreach ( $ads as $key => $value ) {
				if (
					// if current ad url isn't similar to the current page url
					$value['url'] !== $this->currentUrl
					// if current ad url doesn't exist in $this->ads
					&& $this->isUniqueInArray( $this->ads, 'url', $value['url'] )
					// if current ad url doesn't exist in $this->seeAlso
					&& $this->isUniqueInArray( $this->seeAlso, 'url', $value['url']) ) {
					$this->ads[] = $value;
				} else {
					$this->populateAds( $timesRun + 1 );
				}
			}
		}

		// if $this->ads is filled, return
		if ( count( $this->ads ) >= $this::MAX_AD_ITEMS ) {
			return true;
		}

		// if $this->ads isn't filled, continue populating with ads from 'general' campaign
		$adCount = count( $this->ads );
		$totalToFetch = ( $this::MAX_SEE_ALSO_ITEMS - count( $this->seeAlso ) ) + $this::MAX_AD_ITEMS - $adCount;
		$ads = $this->getRandomAdsFromCampaigns( [$this->generalCampaign], $totalToFetch );

		if ( !empty( $ads ) ) {
			foreach ( $ads as $key => $value ) {
				if ( $value['url'] !== $this->currentUrl
					&& $this->isUniqueInArray( $this->ads, 'url', $value['url'] )
					&& $this->isUniqueInArray( $this->seeAlso, 'url', $value['url'] ) ) {
					$this->ads[] = $value;
				} else {
					$this->populateAds( $timesRun + 1 );
				}
			}
		}

		return !!( count( $this->ads ) >= $this::MAX_AD_ITEMS );
	}

	protected function populateSeeAlso( $timesRun = 0 ) {
		$seeAlsoCount = count( $this->seeAlso );
		$totalToFetch = $this::MAX_SEE_ALSO_ITEMS - $seeAlsoCount;
		$ads = $this->seeAlsoTitleKeys;
		$seeAlsoTitles = $this->getTitlesFromTitleStrings( $ads );
		$ads = $this->getSeeAlsoItemData( $seeAlsoTitles );

		if ( $timesRun < 10 && $ads ) {
			foreach ( $ads as $key => $value ) {
				if ( $value['url']
					&& $value['url'] !== $this->currentUrl
					&& $this->isUniqueInArray( $this->seeAlso, 'url', $value['url'] )
					&& $this->isUniqueInArray( $this->ads, 'url', $value['url'] )
				) {
					$this->seeAlso[] = $value;
				} else {
					$this->populateSeeAlso( $timesRun + 1 );
				}
			}
		}

		return count( $this->seeAlso ) >= $this::MAX_SEE_ALSO_ITEMS;
	}

	/**
	 * getRandomAdsFromCampaigns
	 *
	 * @param array $campaigns
	 * @param int $amount
	 * @return array|bool
	 */
	protected function getRandomAdsFromCampaigns( array $campaigns, int $amount ) {
		$ads = $this->getCampaignAds( $campaigns );

		if ( empty( $ads ) ) {
			return false;
		}

		$result = [];
		for ( $i=0; $i < $amount; $i++ ) {
			$randomIndex = array_rand( $ads, 1 );
			$result[] = $ads[$randomIndex];
		}

		return $result;
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
	 * @return array|bool
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

		return empty( $ads ) ? false : $ads;
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

        $categories = array_keys($categories);
        $categories = array_map(function ($item) {
            return substr($item, strpos($item, ':') + 1, strlen($item));
        }, $categories);

        return empty($categories) ? [] : $categories;
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
		return array_map( function( $item ) {
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
