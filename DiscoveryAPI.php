<?php

use MediaWiki\MediaWikiServices;

class DiscoveryAPI extends ApiBase {

	protected $ads = [];

	protected $urls = [];

	protected $config = [];

	const MAX_AD_ITEMS = 4;

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
		global $wgDiscoveryConfig;

		$this->config = $wgDiscoveryConfig;
		$queryResult  = $this->getResult();
		$params       = $this->extractRequestParams();
		$title        = Title::newFromText( $params['title'] );

		if ( !$title->exists() ) {
			throw new InvalidArgumentException( "$title does not exist" );
		}

		// Get page categories
		$categories = $this->getCategoriesByTitle( $title );

		// Get campaigns based on page categories
		$campaigns = $this->getCampaignsByCategories( $categories );

		// Get a fixed number of random ads from current active campaigns
		$this->ads = $this->getCampaignAds( $campaigns, $this->urls, self::MAX_AD_ITEMS );

		// if $this->ads isn't full, fill it with ads from the General campaign
		if ( count( $this->ads ) < self::MAX_AD_ITEMS ) {
			$this->fillMissingAds( $this->ads, $wgPromoterFallbackCampaign, self::MAX_AD_ITEMS );
		}

		$result = [
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
		// For potential future analytics
		foreach ( $generalCampaign as &$ad ) {
			$ad['type'] = 'fallback';
		}

		$adsArray = array_merge( $adsArray, $generalCampaign );
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
		global $wgServer;

		$ads = array_map( function ( $adItem ) use ( $wgServer ) {
			$ad = Ad::fromId( $adItem->ad_id );

			$url = $ad->getMainLink();
			$url = empty( $url ) ? null : Skin::makeInternalOrExternalUrl( $ad->getMainLink() );
			$url = wfExpandUrl( $url );
			$this->urls[] = $url;

			$urlType = 'internal';
			$blogUrl = $this->config['blogUrl'];
			if ( strpos( $url, $blogUrl ) !== false ) {
				$urlType = 'blog';
			} elseif ( strpos( $url, $wgServer ) === false ) {
				$urlType = 'external';
			}

			return [
				'name'       => $ad->getName(),
				'content'    => $ad->getBodyContent(),
				'url'        => $url,
				'urlType'    => $urlType,
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
	 * getCategoriesByTitle
	 *
	 * @param Title $title
	 * @return array
	 */
	public function getCategoriesByTitle( Title $title ) {
		$categories = $title->getParentCategories();
		$titleParser = MediaWikiServices::getInstance()->getTitleParser();

		$categories = array_keys( $categories );
		$categories = array_map( function ( $item ) use ( $titleParser ) {
			return $titleParser->parseTitle( $item, NS_MAIN )->getDBkey();
		}, $categories );

		return empty( $categories ) ? [] : $categories;
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

}
