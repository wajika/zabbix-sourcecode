<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class API {

	/**
	 * API wrapper that all of the calls will go through.
	 *
	 * @var CApiWrapper
	 */
	private static $wrapper;

	/**
	 * Factory for creating API services.
	 *
	 * @var CRegistryFactory
	 */
	private static $apiServiceFactory;

	/**
	 * API execution call stack.
	 *
	 * @var array
	 */
	public static $stack = [];

	/**
	 * Sets the API wrapper.
	 *
	 * @param CApiWrapper $wrapper
	 */
	public static function setWrapper(CApiWrapper $wrapper = null) {
		self::$wrapper = $wrapper;

		// Set new API wrapper to a proper name in case there are more methods to call.
		if ($wrapper !== null && self::$stack) {
			$api = end(self::$stack);
			self::getApi($api);
		}
	}

	/**
	 * Set the service factory.
	 *
	 * @param CRegistryFactory $factory
	 */
	public static function setApiServiceFactory(CRegistryFactory $factory) {
		self::$apiServiceFactory = $factory;
	}

	/**
	 * Returns the API wrapper.
	 *
	 * @return CApiWrapper
	 */
	public static function getWrapper() {
		return self::$wrapper;
	}

	/**
	 * Returns an object that can be used for making API calls.  If a wrapper is used, returns a CApiWrapper,
	 * otherwise - returns a CApiService object.
	 *
	 * @param $name
	 *
	 * @return CApiWrapper|CApiService
	 */
	public static function getApi($name) {
		if (self::$wrapper) {
			self::$wrapper->api = $name;

			return self::$wrapper;
		}
		else {
			return self::getApiService($name);
		}
	}

	/**
	 * Returns the CApiInstance object for the requested API.
	 *
	 * NOTE: This method must only be called from other CApiService objects.
	 *
	 * @param string $name
	 *
	 * @return CApiService
	 */
	public static function getApiService($name = null) {
		return self::$apiServiceFactory->getObject($name ? $name : 'api');
	}

	/**
	 * @return CAction
	 */
	public static function Action() {
		self::$stack[] = 'action';
		return self::getApi('action');
	}

	/**
	 * @return CAlert
	 */
	public static function Alert() {
		self::$stack[] = 'alert';
		return self::getApi('alert');
	}

	/**
	 * @return CAPIInfo
	 */
	public static function APIInfo() {
		self::$stack[] = 'apiinfo';
		return self::getApi('apiinfo');
	}

	/**
	 * @return CApplication
	 */
	public static function Application() {
		self::$stack[] = 'application';
		return self::getApi('application');
	}

	/**
	 * @return CConfiguration
	 */
	public static function Configuration() {
		self::$stack[] = 'configuration';
		return self::getApi('configuration');
	}

	/**
	 * @return CCorrelation
	 */
	public static function Correlation() {
		self::$stack[] = 'correlation';
		return self::getApi('correlation');
	}

	/**
	 * @return CDashboard
	 */
	public static function Dashboard() {
		self::$stack[] = 'dashboard';
		return self::getApi('dashboard');
	}

	/**
	 * @return CDCheck
	 */
	public static function DCheck() {
		self::$stack[] = 'dcheck';
		return self::getApi('dcheck');
	}

	/**
	 * @return CDHost
	 */
	public static function DHost() {
		self::$stack[] = 'dhost';
		return self::getApi('dhost');
	}

	/**
	 * @return CDiscoveryRule
	 */
	public static function DiscoveryRule() {
		self::$stack[] = 'discoveryrule';
		return self::getApi('discoveryrule');
	}

	/**
	 * @return CDRule
	 */
	public static function DRule() {
		self::$stack[] = 'drule';
		return self::getApi('drule');
	}

	/**
	 * @return CDService
	 */
	public static function DService() {
		self::$stack[] = 'dservice';
		return self::getApi('dservice');
	}

	/**
	 * @return CEvent
	 */
	public static function Event() {
		self::$stack[] = 'event';
		return self::getApi('event');
	}

	/**
	 * @return CGraph
	 */
	public static function Graph() {
		self::$stack[] = 'graph';
		return self::getApi('graph');
	}

	/**
	 * @return CGraphItem
	 */
	public static function GraphItem() {
		self::$stack[] = 'graphitem';
		return self::getApi('graphitem');
	}

	/**
	 * @return CGraphPrototype
	 */
	public static function GraphPrototype() {
		self::$stack[] = 'graphprototype';
		return self::getApi('graphprototype');
	}

	/**
	 * @return CHistory
	 */
	public static function History() {
		self::$stack[] = 'history';
		return self::getApi('history');
	}

	/**
	 * @return CHost
	 */
	public static function Host() {
		self::$stack[] = 'host';
		return self::getApi('host');
	}

	/**
	 * @return CHostPrototype
	 */
	public static function HostPrototype() {
		self::$stack[] = 'hostprototype';
		return self::getApi('hostprototype');
	}

	/**
	 * @return CHostGroup
	 */
	public static function HostGroup() {
		self::$stack[] = 'hostgroup';
		return self::getApi('hostgroup');
	}

	/**
	 * @return CHostInterface
	 */
	public static function HostInterface() {
		self::$stack[] = 'hostinterface';
		return self::getApi('hostinterface');
	}

	/**
	 * @return CImage
	 */
	public static function Image() {
		self::$stack[] = 'image';
		return self::getApi('image');
	}

	/**
	 * @return CIconMap
	 */
	public static function IconMap() {
		self::$stack[] = 'iconmap';
		return self::getApi('iconmap');
	}

	/**
	 * @return CItem
	 */
	public static function Item() {
		self::$stack[] = 'item';
		return self::getApi('item');
	}

	/**
	 * @return CItemPrototype
	 */
	public static function ItemPrototype() {
		self::$stack[] = 'itemprototype';
		return self::getApi('itemprototype');
	}

	/**
	 * @return CMaintenance
	 */
	public static function Maintenance() {
		self::$stack[] = 'maintenance';
		return self::getApi('maintenance');
	}

	/**
	 * @return CMap
	 */
	public static function Map() {
		self::$stack[] = 'map';
		return self::getApi('map');
	}

	/**
	 * @return CMediaType
	 */
	public static function MediaType() {
		self::$stack[] = 'mediatype';
		return self::getApi('mediatype');
	}

	/**
	 * @return CProblem
	 */
	public static function Problem() {
		self::$stack[] = 'problem';
		return self::getApi('problem');
	}

	/**
	 * @return CProxy
	 */
	public static function Proxy() {
		self::$stack[] = 'proxy';
		return self::getApi('proxy');
	}

	/**
	 * @return CService
	 */
	public static function Service() {
		self::$stack[] = 'service';
		return self::getApi('service');
	}

	/**
	 * @return CScreen
	 */
	public static function Screen() {
		self::$stack[] = 'screen';
		return self::getApi('screen');
	}

	/**
	 * @return CScreenItem
	 */
	public static function ScreenItem() {
		self::$stack[] = 'screenitem';
		return self::getApi('screenitem');
	}

	/**
	 * @return CScript
	 */
	public static function Script() {
		self::$stack[] = 'script';
		return self::getApi('script');
	}

	/**
	 * @return CTask
	 */
	public static function Task() {
		self::$stack[] = 'task';
		return self::getApi('task');
	}

	/**
	 * @return CTemplate
	 */
	public static function Template() {
		self::$stack[] = 'template';
		return self::getApi('template');
	}

	/**
	 * @return CTemplateScreen
	 */
	public static function TemplateScreen() {
		self::$stack[] = 'templatescreen';
		return self::getApi('templatescreen');
	}

	/**
	 * @return CTemplateScreenItem
	 */
	public static function TemplateScreenItem() {
		self::$stack[] = 'templatescreenitem';
		return self::getApi('templatescreenitem');
	}

	/**
	 * @return CTrend
	 */
	public static function Trend() {
		self::$stack[] = 'trend';
		return self::getApi('trend');
	}

	/**
	 * @return CTrigger
	 */
	public static function Trigger() {
		self::$stack[] = 'trigger';
		return self::getApi('trigger');
	}

	/**
	 * @return CTriggerPrototype
	 */
	public static function TriggerPrototype() {
		self::$stack[] = 'triggerprototype';
		return self::getApi('triggerprototype');
	}

	/**
	 * @return CUser
	 */
	public static function User() {
		self::$stack[] = 'user';
		return self::getApi('user');
	}

	/**
	 * @return CUserGroup
	 */
	public static function UserGroup() {
		self::$stack[] = 'usergroup';
		return self::getApi('usergroup');
	}

	/**
	 * @return CUserMacro
	 */
	public static function UserMacro() {
		self::$stack[] = 'usermacro';
		return self::getApi('usermacro');
	}

	/**
	 * @return CValueMap
	 */
	public static function ValueMap() {
		self::$stack[] = 'valuemap';
		return self::getApi('valuemap');
	}

	/**
	 * @return CHttpTest
	 */
	public static function HttpTest() {
		self::$stack[] = 'httptest';
		return self::getApi('httptest');
	}
}
