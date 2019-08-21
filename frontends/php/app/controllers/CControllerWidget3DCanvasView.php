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

		// Test data generation
		$max_childs_count = 25;
		$tree_levels = ['Proxy ', 'Node '];

		$elements = [
			['id' => 'h100', 'geometry' => 'sphere', 'deails' => 'Zabbix Server']
		];
		$connections = [];

		$id = 200;
		$parents = ['h100'];
		while ($tree_levels) {
			$label = array_shift($tree_levels);
			$new_parents = [];

			foreach ($parents as $parentid) {
				for ($i = rand(1, $max_childs_count); $i > 0; $i--) {
					$elements[] = [
						'id' => 'h'.$id, 'geometry' => 'icosahedron', 'details' => $label.$id
					];
					$connections[] = [
						'parent' => $parentid, 'child' => 'h'.$id
					];
					$new_parents[] = 'h'.$id;

					++$id;
				}
			}

			$parents = $new_parents;
		}

		// $elements = [$elements[0]];
		// $connections = [];

		$event_data = [
			'type' => 'init.widget.3dcanvas',
			'widgetid' => 'TODO-widget-container-uniqueid',
			'scene' => [
				'elements' => $elements,
				'connections' => $connections
				// 'elements' => [
				// 	['id' => 'h100', 'geometry' => 'sphere', 'deails' => 'Zabbix Server'],
				// 	['id' => 'h200', 'geometry' => 'sphere', 'deails' => 'Proxy alpha'],
				// 	['id' => 'h201', 'geometry' => 'sphere', 'deails' => 'Proxy beta'],
				// 	['id' => 'h202', 'geometry' => 'sphere', 'deails' => 'Proxy gamma'],
				// 	['id' => 'h203', 'geometry' => 'icosahedron', 'deails' => 'node №1'],
				// 	['id' => 'h204', 'geometry' => 'icosahedron', 'deails' => 'node №2'],
				// 	['id' => 'h205', 'geometry' => 'icosahedron', 'deails' => 'node №3'],
				// 	['id' => 'h206', 'geometry' => 'icosahedron', 'deails' => 'node №4'],
				// 	['id' => 'h207', 'geometry' => 'icosahedron', 'deails' => 'node №5'],
				// 	['id' => 'h208', 'geometry' => 'icosahedron', 'deails' => 'node №6'],
				// ],
				// 'connections' => [
				// 	['parent' => 'h100', 'child' => 'h200'],
				// 	['parent' => 'h100', 'child' => 'h201'],
				// 	['parent' => 'h100', 'child' => 'h202'],
				// 	['parent' => 'h100', 'child' => 'h203'],
				// 	['parent' => 'h100', 'child' => 'h204'],
				// 	['parent' => 'h204', 'child' => 'h205'],
				// 	['parent' => 'h204', 'child' => 'h206'],
				// 	['parent' => 'h204', 'child' => 'h207'],
				// 	['parent' => 'h204', 'child' => 'h208'],
				// ]
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
