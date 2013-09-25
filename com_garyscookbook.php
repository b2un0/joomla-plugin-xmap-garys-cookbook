<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://wwr.z-index.net
 * @copyright  (c) 2013 Branko Wilhelm
 * @license    GNU/GPLv3 http://wwr.gnu.org/licenses/gpl-3.0.html
 */
 
defined('_JEXEC') or die;

final class xmap_com_garyscookbook {
	
	private static $views = array('category', 'categories');
	
	private static $enabled = false;
	
	public function __construct() {
		self::$enabled = JComponentHelper::isEnabled('com_garyscookbook');
		
		if(self::$enabled) {
			require_once JPATH_SITE . '/components/com_garyscookbook/helpers/route.php';
		}
	}
	
	public static function getTree(XmapDisplayer &$xmap, stdClass &$parent, array &$params) {
		$uri = new JUri($parent->link);
		
		if(!self::$enabled || !in_array($uri->getVar('view'), self::$views)) {
			return;
		}

		$params['groups'] = implode(',', JFactory::getUser()->getAuthorisedViewLevels());

		$params['language_filter'] = JFactory::getApplication()->getLanguageFilter();
		
		$params['include_recipes'] = JArrayHelper::getValue($params, 'include_recipes', 1);
		$params['include_recipes'] = ($params['include_recipes'] == 1 || ($params['include_recipes'] == 2 && $xmap->view == 'xml') || ($params['include_recipes'] == 3 && $xmap->view == 'html'));
		
		$params['show_unauth'] = JArrayHelper::getValue($params, 'show_unauth', 0);
		$params['show_unauth'] = ($params['show_unauth'] == 1 || ( $params['show_unauth'] == 2 && $xmap->view == 'xml') || ( $params['show_unauth'] == 3 && $xmap->view == 'html'));
		
		$params['category_priority'] = JArrayHelper::getValue($params, 'category_priority', $parent->priority);
		$params['category_changefreq'] = JArrayHelper::getValue($params, 'category_changefreq', $parent->changefreq);
		
		if($params['category_priority'] == -1) {
			$params['category_priority'] = $parent->priority;
		}
		
		if($params['category_changefreq'] == -1) {
			$params['category_changefreq'] = $parent->changefreq;
		}
		
		$params['recipe_priority'] = JArrayHelper::getValue($params, 'recipe_priority', $parent->priority);
		$params['recipe_changefreq'] = JArrayHelper::getValue($params, 'recipe_changefreq', $parent->changefreq);
		
		if($params['recipe_priority'] == -1) {
			$params['recipe_priority'] = $parent->priority;
		}
		
		if($params['recipe_changefreq'] == -1) {
			$params['recipe_changefreq'] = $parent->changefreq;
		}
		
		switch($uri->getVar('view')) {
			case 'categories':
				self::getCategoryTree($xmap, $parent, $params, $uri->getVar('id'));
			break;
					
			case 'category':
				self::getRecipes($xmap, $parent, $params, $uri->getVar('id'));
			break;
		}
	}
	
	private static function getCategoryTree(XmapDisplayer &$xmap, stdClass &$parent, array &$params, $parent_id) {
		$db = JFactory::getDbo();
		
		$query = $db->getQuery(true)
				->select(array('c.id', 'c.alias', 'c.title', 'c.parent_id'))
				->from('#__categories AS c')
				->where('c.parent_id = ' . $db->quote($parent_id ? $parent_id : 1))
				->where('c.extension = ' . $db->quote('com_garyscookbook'))
				->where('c.published = 1')
				->order('c.lft');
		
		if (!$params['show_unauth']) {
			$query->where('c.access IN(' . $params['groups'] . ')');
		}
		
		if($params['language_filter']) {
		    $query->where('c.language IN(' . $db->quote(JFactory::getLanguage()->getTag()) . ', ' . $db->quote('*') . ')');
		}
		
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		
		if(empty($rows)) {
			return;
		}
		
		$xmap->changeLevel(1);
		
		foreach($rows as $row) {
			$node = new stdclass;
			$node->id = $parent->id;
			$node->name = $row->title;
			$node->uid = $parent->uid . '_cid_' . $row->id;
			$node->browserNav = $parent->browserNav;
			$node->priority = $params['category_priority'];
			$node->changefreq = $params['category_changefreq'];
			$node->pid = $row->parent_id;
			$node->link = GaryscookbookHelperRoute::getCategoryRoute($row->id);
			
			if ($xmap->printNode($node) !== false) {
				self::getCategoryTree($xmap, $parent, $params, $row->id);
				if ($params['include_recipes']) {
					self::getRecipes($xmap, $parent, $params, $row->id);
				}
			}
		}
		
		$xmap->changeLevel(-1);
	}
	
	private static function getRecipes(XmapDisplayer &$xmap, stdClass &$parent, array &$params, $catid) {
		$db = JFactory::getDbo();
		$now = JFactory::getDate('now', 'UTC')->toSql();
		
		$query = $db->getQuery(true)
				->select(array('r.id', 'r.alias', 'r.imgtitle'))
				->from('#__garyscookbook AS r')
				->where('(r.catid = ' . $db->Quote($catid) . ' OR r.catid2 = ' . $db->Quote($catid) . ' OR r.catid3 = ' . $db->Quote($catid). ' OR r.catid4 = ' . $db->Quote($catid). ' OR r.catid5 = ' . $db->Quote($catid) . ')')
				->where('r.published = 1')
				->where('(r.publish_up = ' . $db->quote($db->getNullDate()) . ' OR r.publish_up <= ' . $db->quote($now) . ')')
				->where('(r.publish_down = ' . $db->quote($db->getNullDate()) . ' OR r.publish_down >= ' . $db->quote($now) . ')')
				->order('r.ordering');
		
		if (!$params['show_unauth']) {
			$query->where('r.access IN(' . $params['groups'] . ')');
		}
		
		if($params['language_filter']) {
		    $query->where('r.language IN(' . $db->quote(JFactory::getLanguage()->getTag()) . ', ' . $db->quote('*') . ')');
		}
		
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		
		if(empty($rows)) {
			return;
		}
		
		$xmap->changeLevel(1);
		
		foreach($rows as $row) {
			$node = new stdclass;
			$node->id = $parent->id;
			$node->name = $row->imgtitle;
			$node->uid = $parent->uid . '_' . $row->id;
			$node->browserNav = $parent->browserNav;
			$node->priority = $params['recipe_priority'];
			$node->changefreq = $params['recipe_changefreq'];
			$node->link = GaryscookbookHelperRoute::getGaryscookbookRoute($row->id . ':' . $row->alias, $catid);
			
			$xmap->printNode($node);
		}
		
		$xmap->changeLevel(-1);
	}
}