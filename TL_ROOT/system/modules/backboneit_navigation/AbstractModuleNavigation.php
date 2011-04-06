<?php

/**
 * Abstract base class for navigation modules
 * 
 * Navigation item array layout:
 * subitems		=> subnavigation as HTML string or empty string
 * class		=> CSS classes
 * title		=> page name with Insert-Tags stripped and XML specialchars replaced by their entities
 * pageTitle	=> page title with Insert-Tags stripped and XML specialchars replaced by their entities
 * link			=> page name (with Insert-Tags and XML specialchars NOT replaced; as stored in the db)
 * href			=> URL of target page
 * nofollow		=> true, if nofollow should be set on rel attribute
 * target		=> either ' onclick="window.open(this.href); return false;"' or empty string
 * description	=> page description with line breaks (\r and \n) replaced by whitespaces
 * 
 * Additionally all page dataset values from the database are available unter their field name,
 * if the field name does not collide with the listed keys.
 * 
 * For the collisions of the Contao core page dataset fields the following keys are available:
 * 
 * @author Oliver Hoff
 */
abstract class AbstractModuleNavigation extends Module {
	
	protected $strLevelQueryStart;
	protected $strLevelQueryEnd;
	protected $strJumpToFallbackQuery;
	protected $strJumpToQuery;
	protected $strRootConditions; 
	
	protected $arrItems; // compiled page datasets
	protected $arrSubpages; // ordered IDs of subnavigations
	
	protected $arrGroups; // set of groups of the current user
	
	protected $intActive; // the id of the active page
	protected $arrPath; // same as trail but with current page included
	protected $arrTrail; // set of parent pages of the current page
	
	public function __construct(Database_Result $objModule, $strColumn = 'main') {
		parent::__construct($objModule, $strColumn);
		$this->import('Database');
		
		global $objPage;
		$this->intActive = $this->backboneit_navigation_isSitemap || $this->Input->get('articles') ? false : $objPage->id;
		$this->arrPath = array_flip($objPage->trail);
		$this->arrTrail = $this->arrPath;
		unset($this->arrTrail[$objPage->id]); // trail has a slightly different meaning here (current page excluded, same as in the templates)
		
		if(FE_USER_LOGGED_IN) {
			$this->import('FrontendUser', 'User');
			$this->arrGroups = array_flip($this->User->groups);
		}
		
		if(!strlen($this->navigationTpl))
			$this->navigationTpl = 'nav_default';
			
			
		if($this->backboneit_navigation_showHidden) {
			$strHidden = '';
		} elseif($this->backboneit_navigation_isSitemap) {
			$strHidden = ' AND (sitemap = \'map_always\' OR (hide != 1 AND sitemap != \'map_never\'))';
		} else {
			$strHidden = ' AND hide != 1';
		}
		
		if(FE_USER_LOGGED_IN && !BE_USER_LOGGED_IN) {
			$strGuests = ' AND guests != 1';
		}
	
		if(BE_USER_LOGGED_IN) {
			$strPublish = '';
		} else {
			$intTime = time();
			$strPublish = ' AND (start = \'\' OR start < ' . $intTime . ') AND (stop = \'\' OR stop > ' . $intTime . ') AND published = 1';
		}
		
		$this->strRootConditions = $strPublish;
		!$this->backboneit_navigation_ignoreHidden && $this->strRootConditions .= $strHidden;
		!$this->backboneit_navigation_ignoreGuests && $this->strRootConditions .= $strGuests;
		
		$arrFields = deserialize($this->backboneit_navigation_addFields, true);
		$strFields = 'id,pid,type,alias,title,protected,groups,jumpTo,pageTitle,
				target,description,url,robots,cssClass,accesskey,tabindex';
		
		if(count($arrFields) > 10) {
			$strFields = '*';
			
		} elseif(count($arrFields) > 0) {
			$arrMiss = array_flip($arrFields);
			foreach($this->Database->listFields('tl_page') as $arrField)
				unset($arrMiss[$arrField['name']]);

			foreach($arrFields as $strField)
				if(!isset($arrMiss[$strField]))
					$strFields = ',' . $strField;
		}
		
		$this->strLevelQueryStart =
			'SELECT	' . $strFields . '
			FROM	tl_page
			WHERE	pid IN (';
		$this->strLevelQueryEnd = ')
			AND		type != \'root\'
			AND		type != \'error_403\'
			AND		type != \'error_404\'
			' . $strHidden . $strGuests . $strPublish . '
			ORDER BY sorting';
		
		$this->strJumpToQuery =
			'SELECT	id, alias, type
			FROM	tl_page
			WHERE	id = ?
			' . $strGuests . $strPublish . '
			LIMIT	0, 1';
		
		$this->strJumpToFallbackQuery =
			'SELECT	id, alias
			FROM	tl_page
			WHERE	pid = ?
			AND		type = \'regular\'
			' . $strGuests . $strPublish . '
			ORDER BY sorting
			LIMIT	0, 1';
	}
	
	/**
	 * Filters the given array of page IDs in regard of publish state,
	 * required permissions (protected and guests only) and hidden state, according to
	 * this navigations settings.
	 * Maintains relative order of the input array.
	 * 
	 * @param array $arrPages An array of page IDs to filter
	 * @return array Filtered array of page IDs
	 */
	protected function filterPages(array $arrPages) {
		if(!$arrPages)
			return $arrPages;
			
		$objPages = $this->Database->execute(
			'SELECT	id, protected, groups
			FROM	tl_page
			WHERE	id IN (' . implode(',', array_keys(array_flip($arrPages))) . ')
			' . $this->strRootConditions);
		
		$arrValid = array();
		while($objPages->next())
			if($this->checkProtected($objPages))
				$arrValid[$objPages->id] = true;
		
		$arrFiltered = array();
		foreach($arrPages as $intID)
			if(isset($arrValid[$intID]))
				$arrFiltered[] = $intID;
		
		return $arrFiltered;
	}
	
	protected function getNextLevel(array $arrPages) {
		if(!$arrPages)
			return $arrPages;
			
		$objNext = $this->Database->execute(
			'SELECT	id, pid, protected, groups
			FROM	tl_page
			WHERE	pid IN (' . implode(',', array_keys(array_flip($arrPages))) . ')
			' . $this->strRootConditions . '
			ORDER BY sorting');
		
		$arrNext = array();
		while($objNext->next())
			if($this->checkProtected($objNext))
				$arrNext[$objNext->pid][] = $objNext->id;
		
		$arrNextLevel = array();
		foreach($arrPages as $intID)
			if(isset($arrNext[$intID]))
				$arrNextLevel = array_merge($arrNextLevel, $arrNext[$intID]);
		
		return $arrNextLevel;
	}
	
	protected function getPrevLevel(array $arrPages) {
		if(!$arrPages)
			return $arrPages;
			
		$objPrev = $this->Database->execute(
			'SELECT	id, pid
			FROM	tl_page
			WHERE	id IN (' . implode(',', array_keys(array_flip($arrPages))) . ')');
		
		$arrPrev = array();
		while($objPrev->next())
			$arrPrev[$objPrev->id] = $objPrev->pid;
		
		$arrPrevLevel = array();
		foreach($arrPages as $intID)
			if(isset($arrPrev[$intID]))
				$arrPrevLevel[] = $arrPrev[$intID];
		
		return $arrPrevLevel;
	}
	
	
	/**
	 * Renders the navigation of the given IDs into the navigation template.
	 * Adds CSS classes "first" and "last" to the appropriate navigation item arrays.
	 * If the given array is empty, the empty string is returned.
	 * 
	 * @param array $arrRoots The navigation items arrays
	 * @param integer $intLevel (optional, defaults to 1) The current level of this navigation layer
	 * @return string The parsed navigation template, could be empty string.
	 */
	protected function renderNaviTree(array $arrIDs, $intLevel = 1) {
		if(!$arrIDs)
			return '';
			
		$arrItems = array();
		
		foreach($arrIDs as $intID) {
			if(!isset($this->arrItems[$intID]))
				continue;
				
			$arrItem = $this->arrItems[$intID];
			
			if(isset($this->arrSubpages[$intID]))
				$arrItem['subitems'] = $this->renderNaviTree($this->arrSubpages[$intID], $intLevel + 1);
			
			$arrItems[] = $arrItem;
		}
		
		$intLast = count($arrItems) - 1;
		$arrItems[0]['class'] = trim($arrItems[0]['class'] . ' first');
		$arrItems[$intLast]['class'] = trim($arrItems[$intLast]['class'] . ' last');
		
		$objTemplate = new FrontendTemplate($this->navigationTpl);
		$objTemplate->setData(array(
			'level' => 'level_' . $intLevel,
			'items' => $arrItems,
			'type' => get_class($this)
		));
		
		return $objTemplate->parse();
	}
	
	/**
	 * Compiles a navigation item array from a page dataset with the given subnavi
	 * 
	 * @param array $arrPage The page dataset as an array
	 * @param string $strSubnavi (optional) HTML string of subnavi
	 * @return array The compiled navigation item array
	 */
	protected function compileNavigationItem(array $arrPage) {
		// fallback for dataset field collisions
		$arrPage['_pageTitle']		= $arrPage['pageTitle'];
		$arrPage['_target']			= $arrPage['target'];
		$arrPage['_description']	= $arrPage['description'];
		
		switch($arrPage['type']) {
			case 'forward':
				if($arrPage['jumpTo']) {
					$intFallbackSearchID = $arrPage['id'];
					$intJumpToID = $arrPage['jumpTo'];
					do {
						$objNext = $this->Database->prepare(
							$this->strJumpToQuery
						)->execute($intJumpToID);
						
						if(!$objNext->numRows) {
							$objNext = $this->Database->prepare(
								$this->strJumpToFallbackQuery
							)->execute($intFallbackSearchID);
							break;
						}
						
						$intFallbackSearchID = $intJumpToID;
						$intJumpToID = $objNext->jumpTo;
						
					} while($objNext->type == 'forward');
				} else {
					$objNext = $this->Database->prepare(
						$this->strJumpToFallbackQuery
					)->execute($arrPage['id']);
				}
				
				if(!$objNext->numRows) {
					$arrPage['href'] = $this->generateFrontendUrl($arrPage);
				} elseif($objNext->type == 'redirect') {
					$arrPage['href'] = $this->encodeEmailURL($objNext->url);
				} else {
					$intForwardID = $objNext->id;
					$arrPage['href'] = $this->generateFrontendUrl($objNext->row());
				}
				break;
				
			case 'redirect':
				$arrPage['href'] = $this->encodeEmailURL($arrPage['url']);
				break;
				
			default:
				$arrPage['href'] = $this->generateFrontendUrl($arrPage);
				break;
		}
		
		$arrPage['link']			= $arrPage['title'];
		$arrPage['title']			= specialchars($arrPage['title'], true);
		$arrPage['pageTitle']		= specialchars($arrPage['_pageTitle'], true);
		$arrPage['nofollow']		= strncmp($arrPage['robots'], 'noindex', 7) === 0;
		$arrPage['target']			= $arrPage['type'] == 'redirect' && $arrPage['_target'] ? LINK_NEW_WINDOW : '';
		$arrPage['description']		= str_replace(array("\n", "\r"), array(' ' , ''), $arrPage['_description']);
		
		$arrPage['isActive'] = $this->intActive === $arrPage['id'] || $this->intActive === $intForwardID;
		
		$strClass = '';
		if(strlen($strSubnavi))
			$strClass .= 'submenu';
		if(strlen($arrPage['cssClass']))
			$strClass .= ' ' . $arrPage['cssClass'];
		if($arrPage['pid'] == $objPage->pid) {
			$strClass .= ' sibling';
		} elseif(isset($this->arrTrail[$arrPage['id']])) {
			$strClass .= ' trail';
		}
		$arrPage['class'] = trim($strClass);
		
		return $arrPage;
	}
	
	/**
	 * Utility method of compileNavigationItem.
	 * 
	 * If the given URL starts with "mailto:", the E-Mail is encoded,
	 * otherwise nothing is done.
	 * 
	 * @param string $strHref The URL to check and possibly encode
	 * @return string The modified URL
	 */
	protected function encodeEmailURL($strHref) {
		if(strncasecmp($strHref, 'mailto:', 7) !== 0)
			return $strHref;

		$this->import('String');
		return $this->String->encodeEmail($strHref);
	}
	
	/**
	 * Utility method.
	 * Checks if the page of the given page dataset is visible to the current user,
	 * in regards to the current navigation settings and the permission requirements of the page.
	 * 
	 * @param $objPage
	 * @return unknown_type
	 */
	protected function checkProtected($objPage) {
		if(BE_USER_LOGGED_IN)
			return true;
			
		if(!$objSubpages->protected)
			return true;
			
		if($this->backboneit_navigation_showProtected)
			return true;
			
		if(!$this->arrGroups)
			return false;
			
		if(array_intersect_key($this->arrGroups, array_flip(deserialize($objSubpages->groups, true))))
			return true;
			
		return false;
	}
	
	/**
	 * A helper method to generate BE wildcard.
	 * 
	 * @param string $strBEType (optional, defaults to "NAVIGATION") The type to be displayed in the wildcard
	 * @return string The wildcard HTML string
	 */
	protected function generateBE($strBEType = 'NAVIGATION') {
		$objTemplate = new BackendTemplate('be_wildcard');

		$objTemplate->wildcard = '### ' . $strBEType . ' ###';
		$objTemplate->title = $this->headline;
		$objTemplate->id = $this->id;
		$objTemplate->link = $this->name;
		$objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

		return $objTemplate->parse();
	}
	
}