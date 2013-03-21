<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2013 Leo Feyer
 * 
 * @package Backboneit_navigation
 * @link    http://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	'AbstractModuleNavigation'      => 'system/modules/backboneit_navigation/AbstractModuleNavigation.php',
	'ModuleNavigationChainPreorder' => 'system/modules/backboneit_navigation/ModuleNavigationChainPreorder.php',
	'ModuleNavigationMenu'          => 'system/modules/backboneit_navigation/ModuleNavigationMenu.php',
	'NavigationDCA'                 => 'system/modules/backboneit_navigation/NavigationDCA.php',
));


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	'mod_backboneit_navigation_menu' => 'system/modules/backboneit_navigation/templates',
));
