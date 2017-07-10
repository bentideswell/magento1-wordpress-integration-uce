<?php
/**
 * @category Fishpig
 * @package Fishpig_Wordpress
 * @license http://fishpig.co.uk/license.txt
 * @author Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Addon_UCE_Model_Observer
{
	/**
	 * Option name used to load plugin data
	 *
	 * @const string
	 */
	const WP_OPTION_NAME = 'ksuceExcludes';
	
	/**
	 * Cache for UCE's exclusion data
	 *
	 * @var array
	 */
	protected $_exclusionData = false;
	
	/**
	 * Cache to determine whether extension is enabled
	 *
	 * @var bool
	 */
	protected $_isEnabled = null;
	
	/**
	 * Initiliase the plugin data from WP
	 *
	 */
	public function __construct()
	{
		if ($this->isEnabled()) {
			$data = trim(Mage::helper('wordpress')->getWpOption(self::WP_OPTION_NAME));
			
			if ($data !== '') {
				$data = unserialize($data);
				
				foreach($data as $section => $categoryIds) {
					foreach($categoryIds as $it => $categoryId) {
						$data[$section][$it] = ltrim($categoryId, '-');	
					}
				}
				
				$this->_exclusionData = $data;
			}
		}
	}
	
	/**
	 * Apply the filter to the blog homepage post list
	 *
	 * @param Varien_Event_Observer $observer
	 * @return void
	 */
	public function applyMainExclusionObserver(Varien_Event_Observer $observer)
	{
		if (!$this->isEnabled()) {
			return false;
		}

		if (Mage::app()->getRequest()->getActionName() !== 'feed') {
			if (($categoryIds = $this->getExclusionData('exclude_main')) !== false) {
				$this->excludeCategoryIds($observer->getEvent()->getCollection(), $categoryIds);
			}
		}
	}
	
	/**
	 * Apply the filter to the blog archive pages
	 *
	 * @param Varien_Event_Observer $observer
	 * @return void
	 */
	public function applyArchiveViewExclusionObserver(Varien_Event_Observer $observer)
	{
		if (!$this->isEnabled()) {
			return false;
		}
		
		if (($categoryIds = $this->getExclusionData('exclude_archives')) !== false) {
			$this->excludeCategoryIds($observer->getEvent()->getCollection(), $categoryIds);
		}
	}
	
	/**
	 * Apply the filter to the blog feeds
	 *
	 * @param Varien_Event_Observer $observer
	 * @return void
	 */
	public function applyFeedHomeExclusionObserver(Varien_Event_Observer $observer)
	{
		if (!$this->isEnabled()) {
			return false;
		}
		
		if (Mage::app()->getRequest()->getActionName() === 'feed') {
			if (($categoryIds = $this->getExclusionData('exclude_feed')) !== false) {
				$this->excludeCategoryIds($observer->getEvent()->getCollection(), $categoryIds);
			}
		}
	}
	
	/**
	 * Apply the filter to the blog search
	 *
	 * @param Varien_Event_Observer $observer
	 * @return void
	 */
	public function applySearchExclusionObserver(Varien_Event_Observer $observer)
	{
		if (!$this->isEnabled()) {
			return false;
		}
		
		if (($categoryIds = $this->getExclusionData('exclude_search')) !== false) {
			$this->excludeCategoryIds($observer->getEvent()->getCollection(), $categoryIds);
		}
	}
	
	/**
	 * Apply an exclusion filter to $collection so that all products that are part of
	 * the categories listed in $categoryIds are not returned in the result
	 *
	 * @param Fishpig_Wordpress_Model_Resource_Post_Collection
	 * @param array $categoryIds
	 * @return void
	 */
	public function excludeCategoryIds(Fishpig_Wordpress_Model_Resource_Post_Collection $collection, array $categoryIds)
	{
		$db = Mage::helper('wordpress/app')->getDbConnection();
		$select = $db->select()
			->from(Mage::helper('wordpress/app')->getTableName('wordpress/term_taxonomy'), 'term_taxonomy_id')
			->distinct(true)
			->where('term_id IN (?)', $categoryIds);;

		if ($allCategoryIds = $db->fetchCol($select)) {
			$select = $db->select()
				->distinct(true)
				->from(Mage::helper('wordpress/app')->getTableName('wordpress/term_relationship'), 'object_id')
				->where('term_taxonomy_id  IN (?)', $allCategoryIds);

			if ($postIds = $db->fetchCol($select)) {
				$collection->getSelect()->where('main_table.ID NOT IN (?)', $postIds);
			}
		}
	}	

	/**
	 * Retrieve exclusion data
	 * If $area is set, data from that section will be returned
	 * Else the whole array will be returned
	 *
	 * @param string $area = null
	 * @return false|array
	 */
	public function getExclusionData($area = null)
	{
		if ($this->_exclusionData !== false) {
			if (is_null($area)) {
				return $this->_exclusionData;
			}
			else if (isset($this->_exclusionData[$area])) {
				return $this->_exclusionData[$area];
			}
		}
		
		return false;
	}
	
	/**
	 * Determine whether the extension is enabled
	 * And whether the plugin is installed and enabled in WordPress
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		return Mage::helper('wordpress/plugin')->isEnabled('ultimate-category-excluder/ultimate-category-excluder.php');
	}
}
