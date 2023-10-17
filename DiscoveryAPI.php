<?php

use MediaWiki\Extension\Promoter\Ad;
use MediaWiki\Extension\Promoter\AdCampaign;
use MediaWiki\MediaWikiServices;

class DiscoveryAPI extends ApiBase {

	protected $ads = [];

	/* @param array - URLs to be excluded from further DB queries */
	protected $excludedUrls = [];

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
		$fallbackCampaign = $this->getConfig()->get( 'PromoterFallbackCampaign' );
		$this->config = $this->getConfig()->get( 'DiscoveryConfig' );
		// Allow short caching
		$this->getMain()->setCacheMaxAge( 300 );

		$queryResult  = $this->getResult();
		$params       = $this->extractRequestParams();
		$title        = Title::newFromText( $params['title'] );

		if ( !$title->exists() ) {
			throw new InvalidArgumentException( "$title does not exist" );
		}

		// Get page categories
		$categories = $this->getCategoriesByTitle( $title );

		$shouldPrioritizeCategories = is_array( $this->config['priorityCategories'] );

		if ( $shouldPrioritizeCategories ) {
			$this->config['priorityCategories'] = str_replace( ' ', '_', $this->config['priorityCategories'] );
			foreach ( $categories as $category ) {
				if ( in_array( $category, $this->config[ 'priorityCategories' ] ) ) {
					$categories = [ $category ];
					break;
				}
			}
		}
		// Get campaigns based on page categories
		$campaigns = $this->getCampaignsByCategories( $categories );

		// Do not load ads that link to this very page
		$this->excludedUrls[] = $title->getFullText();

		// Get a fixed number of random ads from current active campaigns
		$this->ads = $this->getCampaignAds( $campaigns, $this->excludedUrls, self::MAX_AD_ITEMS );

		if ( !$shouldPrioritizeCategories && count( $this->ads ) < self::MAX_AD_ITEMS ) {
		// if $this->ads isn't full, fill it with ads from the General campaign
			$this->fillMissingAds( $this->ads, $fallbackCampaign, self::MAX_AD_ITEMS );
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
			[ $fallbackCampaign ], $this->excludedUrls, $limit - count( $adsArray )
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
	 * @param array $excludedUrls array of URLs to ignore (normally $this->urls)
	 * @param int $limit max number of ads to fetch
	 *
	 * @return array
	 */
	public function getCampaignAds( array $campaigns = [], array $excludedUrls = [], $limit = self::MAX_AD_ITEMS ) {
		$adsToMap = AdCampaign::getCampaignAds( $campaigns, $excludedUrls, $limit );
		global $wgServer;

		$ads = array_map( function ( $adItem ) use ( $wgServer ) {
			$ad = Ad::fromId( $adItem->ad_id );

			$url = $this->excludedUrls[] = $ad->getMainLink();
			$url = empty( $url ) ? null : Skin::makeInternalOrExternalUrl( $url );
			$url = wfExpandUrl( $url );

			$urlType = 'internal'; // The default URL type, unless... ->
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
		$campaigns = AdCampaign::getCampaignNames();

		// Make sure the names of categories and campaigns are formatted as db_keys
		$campaigns = str_replace( ' ', '_', $campaigns );
		$categories = str_replace( ' ', '_', $categories );
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
			return $titleParser->parseTitle( $item )->getDBkey();
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
