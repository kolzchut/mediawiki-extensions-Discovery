<?php

class DiscoveryAPI extends ApiBase {

    protected $currentUrl = '';

    protected $campaigns = [];

    protected $ads = [];

    protected $seeAlso = [];

    const MAX_SEE_ALSO_ITEMS = 2;

    const MAX_AD_ITEMS = 2;

    public function __construct($main, $moduleName) {
        parent::__construct($main, $moduleName);
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
        $queryResult = $this->getResult();
        $params = $this->extractRequestParams();
        $title = $params['title'];
        $this->currentUrl = Title::newFromText($title)->getFullURL();

        // Get 'see also' item IDs
        $seeAlsoTitleKeys = $this->getRelevantArticles($params['title'], 2);
        if(empty($seeAlsoTitleKeys)) {
            $this->seeAlso = [];
        }
        else {
            // Get 'see also' items by given IDs
            $seeAlsoTitles = $this->getTitlesFromTitleStrings($seeAlsoTitleKeys);

            // Parse 'see also' items to key->value objects
            $this->seeAlso = $this->getSeeAlsoItemData($seeAlsoTitles);

            // Get page categories
            $categories = $this->getCategoriesByTitleString($title);

            // Get campaigns based on page categories
            $this->campaigns = $this->getCampaignsByCategories($categories);
        }

        // Populate 'see also' array recursively
        $this->populateSeeAlso();

        // Populate ads array recursively
        $this->populateAds();

        $result = [
            'seeAlso' => $this->seeAlso,
            'ads'     => $this->ads
        ];

        $queryResult->addValue(null, 'discovery', $result);
    }

    protected function populateAds($timesRun = 0) {
        $adCount = count($this->ads);
        $totalToFetch = $this::MAX_AD_ITEMS - $adCount;

        $ads = $this->getRandomAdsFromCampaigns($this->campaigns, $totalToFetch);

        if ($timesRun < 3 && !empty($ads)) {
            foreach ($ads as $key => $value) {
                if ($value['url'] !== $this->currentUrl 
                    && $this->isUniqueInArray($this->ads, 'url', $value['url']) 
                    && $this->isUniqueInArray($this->seeAlso, 'url', $value['url'])) {
                    $this->ads[] = $value;
                } else {
                    $this->populateAds($timesRun + 1);
                }
            }
        }

        if (count($this->ads) >= $this::MAX_AD_ITEMS) {
            return true;
        }

        $totalToFetch = $this::MAX_AD_ITEMS - $adCount;
        $ads = $this->getRandomAdsFromCampaigns(['כללי'], $totalToFetch);

        if (!empty($ads)) {
            foreach ($ads as $key => $value) {
                if ($value['url'] !== $this->currentUrl 
                    && $this->isUniqueInArray($this->ads, 'url', $value['url']) 
                    && $this->isUniqueInArray($this->seeAlso, 'url', $value['url'])) {
                    $this->ads[] = $value;
                } else {
                    $this->populateAds($timesRun + 1);
                }
            }
        }

        return !!(count($this->ads) >= $this::MAX_AD_ITEMS);
    }

    protected function populateSeeAlso($timesRun = 0) {
        $seeAlsoCount = count($this->seeAlso);
        $totalToFetch = $this::MAX_SEE_ALSO_ITEMS - $seeAlsoCount;

        $ads = $this->getRandomAdsFromCampaigns($this->campaigns, $totalToFetch);

        if($timesRun < 3 && !empty($ads)) {
            foreach ($ads as $key => $value) {
                if($value['url'] !== $this->currentUrl 
                    && $this->isUniqueInArray($this->seeAlso, 'url', $value['url']) 
                    && $this->isUniqueInArray($this->ads, 'url', $value['url'])) {
                    $this->seeAlso[] = $value;
                } else {
                    $this->populateSeeAlso($timesRun + 1);
                }
            }
        }

        if(count($this->seeAlso) >= $this::MAX_SEE_ALSO_ITEMS) {
            return true;
        }

        $totalToFetch = $this::MAX_SEE_ALSO_ITEMS - $seeAlsoCount;
        $ads = $this->getRandomAdsFromCampaigns(['כללי'], $totalToFetch);

        if(!empty($ads)) {
            foreach ($ads as $key => $value) {
                if ($value['url'] !== $this->currentUrl 
                    && $this->isUniqueInArray($this->seeAlso, 'url', $value['url']) 
                    && $this->isUniqueInArray($this->ads, 'url', $value['url'])) {
                    $this->seeAlso[] = $value;
                } else {
                    $this->populateSeeAlso($timesRun + 1);
                }
            }
        }

        return !!(count($this->seeAlso) >= $this::MAX_SEE_ALSO_ITEMS);
    }

    /**
     * getRandomAdsFromCampaigns
     *
     * @param Array $campaigns
     * @param int $amount
     * @return Array
     */
    protected function getRandomAdsFromCampaigns(Array $campaigns, int $amount) {
        $ads = $this->getCampaignAds($campaigns);

        if(empty($ads)) return false;

        $result = [];
        for ($i=0; $i < $amount; $i++) {
            $randomIndex = array_rand($ads, 1);
            $result[] = $ads[$randomIndex];
        }

        return $result;
    }

    protected function isUniqueInArray($array, $keyName, $newValue) {
        foreach($array as $key => $value) {
            if($value[$keyName] === $newValue) return false;
        }

        return true;
    }

    /**
     * getSeeAlsoItemData
     *
     * @param Array $titles
     * @return Array|Boolean
     */
    public function getSeeAlsoItemData(Array $titles) {
        if(!$titles || empty($titles)) {
            return false;
        }

        $seeAlso = [];
        foreach ($titles as $key => $value) {
            $seeAlso[] = [
                'content' => $value->mTextform,
                'url' => $value->getFullURL()
            ];
        }

        return $seeAlso;
    }

    /**
     * getCampaignAds
     *
     * @param Array $campaigns
     * @return Array|Boolean
     */
    public function getCampaignAds(Array $campaigns) {
        if(!$campaigns || empty($campaigns)) {
            return false;
        }

        $campaigns = str_replace('_', ' ', $campaigns);

        $ads = [];
        foreach ($campaigns as $campaign) {
            $campaign = new AdCampaign($campaign);
            $campaign_ads = $campaign->getAds();

            foreach ($campaign_ads as $ad) {
                $ad = Ad::fromName($ad['name']);
                $ads[] = $this->getAdData($ad);
            }
        }

        return empty($ads) ? false : $ads;
    }

    /**
     * getCampaignsByCategories
     *
     * @param mixed $categories
     * @return void
     */
    public function getCampaignsByCategories($categories) {
        $campaigns = AdCampaign::getAllCampaignNames();
        $campaigns = str_replace(' ', '_', $campaigns);
        $campaigns = array_intersect($categories, $campaigns);
        $result = array_values($campaigns);

        return !empty($result) ? $result : false;
    }

    /**
     * getAdData
     *
     * @param Ad $ad
     * @return Array|Boolean
     */
    public function getAdData(Ad $ad) {
        global $wgServer;

        if(!$ad) return false;

        $url = $ad->getMainLink();
        $url = empty($url) ? null : Skin::makeInternalOrExternalUrl($ad->getMainLink());
        $url = strpos($url, '/') === 0 ? $wgServer . $url : $url;

        return [
            'content'    => $ad->getBodyContent(),
            'url'        => $url,
            'indicators' => [
                'new' => (strpos($ad->getCaption(), 'חדש') > -1) ? 1 : 0
            ]
        ];
    }

    /**
     * getCategoriesByTitleString
     *
     * @param String $title
     * @return Array
     */
    public function getCategoriesByTitleString(String $title) {
        $categories = $this->getSemanticData($title);

        if(empty($categories) || $categories[''] === NULL)  {
            return [];
        }

        $categories = $categories[''];
        unset($categories[0]);
        unset($categories[count($categories)]);
        $categories = $this->removeHashes($categories);

        return $categories;
    }

    /**
     * Get relevant articles according to current page's ($title) 'read also' list
     *
     * @param String $title
     * @param Int $limit
     * @return Array
     */
    public static function getRelevantArticles(String $title, Int $limit = 2) {
        $semanticData = self::getSemanticData($title) ;

        if(!key_exists('ראו גם', $semanticData) || empty($semanticData['ראו גם'])) {
            return [];
        }

        $limitedKeys = array_rand($semanticData['ראו גם'], $limit);
        $data = [];

        foreach ($limitedKeys as $key => $value) {
            $data[] = $semanticData['ראו גם'][$value];
        }

        $filteredData = self::removeHashes($data);
        return $filteredData;
    }

    /**
     * Remove hashes from each element in an array of strings, return new array
     *
     * @param Array $arr
     * @return Array
     */
    public static function removeHashes(Array $arr) {
        return array_map(function($item){
            return preg_replace('/#\d+#/', '', $item);
        }, $arr);
    }
    /**
     * @param Array $strings
     * @return Array
     */
    public static function getTitlesFromTitleStrings(Array $strings) {
        $titlesArr = [];

        foreach ($strings as $value) {
            $titlesArr[] = Title::newFromDBkey($value);
        }

        return $titlesArr;
    }

    /**
     * getSemanticData
     *
     * @param String $title
     * @return Array
     */
    public static function getSemanticData(String $title) {
        $store = SMW\StoreFactory::getStore()->getSemanticData(\SMW\DIWikiPage::newFromText($title));

        $arrSMWProps = $store->getProperties();
        $arrValues   = [];
        foreach ($arrSMWProps as $smwProp) {
            $arrSMWPropValues = $store->getPropertyValues($smwProp);
            foreach ($arrSMWPropValues as $smwPropValue) {
                $arrValues[$smwProp->getLabel()][] = $smwPropValue->getSerialization();
            }
        }

        return $arrValues;
    }

}