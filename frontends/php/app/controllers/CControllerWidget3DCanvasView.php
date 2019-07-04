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


class CControllerWidget3DCanvasView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_3D_CANVAS);
		$this->setValidationRules([
			'name' => 'string',
			'uniqueid' => 'required|string',
			'initial_load' => 'in 0,1',
			'edit_mode' => 'in 0,1',
			'dashboardid' => 'db dashboard.dashboardid',
			'fields' => 'json',
			'dynamic_hostid' => 'db hosts.hostid',
			'content_width' => 'int32',
			'content_height' => 'int32'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();
		$error = null;
		$dynamic_hostid = $this->getInput('dynamic_hostid', '0');

		$event_data = [
			'type' => 'init.widget.3dcanvas',
			'widgetid' => 'TODO-widget-container-uniqueid',
			'scene' => [
				'elements' => [
					['id' => 'h100', 'type' => 'server'],
					['id' => 'h200', 'type' => 'host'],
					['id' => 'h201', 'type' => 'host'],
					['id' => 'h202', 'type' => 'host'],
					['id' => 'h203', 'type' => 'host'],
					['id' => 'h204', 'type' => 'host'],
					['id' => 'h205', 'type' => 'host'],
					['id' => 'h206', 'type' => 'host'],
					['id' => 'h207', 'type' => 'host'],
					['id' => 'h208', 'type' => 'host'],
				],
				'connections' => [
					['parent' => 'h100', 'child' => '200'],
					['parent' => 'h100', 'child' => '201'],
					['parent' => 'h100', 'child' => '202'],
					['parent' => 'h200', 'child' => '203'],
					['parent' => 'h200', 'child' => '204'],
					['parent' => 'h200', 'child' => '205'],
					['parent' => 'h200', 'child' => '206'],
					['parent' => 'h200', 'child' => '207'],
				]
			],
			'fields' => $fields
		];

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'event_data' => $event_data,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
