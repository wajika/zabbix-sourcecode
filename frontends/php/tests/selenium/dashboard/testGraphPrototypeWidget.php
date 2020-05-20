<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * @backup widget
 * @backup profiles
 */
class testGraphPrototypeWidget extends CWebTest {

	/*
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	private $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboardid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_groupid';

	const DASHBOARD_ID = 105;
	const SCREENSHOT_DASHBOARD_ID = 106;

	private static $previous_widget_name = 'Graph prototype for update';

	public static function getWidgetData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Graph prototype',
						'Graph prototype' => [
							'values' => ['testFormGraphPrototype1'],
							'context' => ['Group' => 'Zabbix servers', 'Host' => 'Simple form test host']
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Graph prototype',
						'Source' => 'Simple graph prototype',
						'Item prototype' => [
							'values' => ['testFormItemPrototype1'],
							'context' => ['Group' => 'Zabbix servers', 'Host' => 'Simple form test host']
						]
					],
					'show_header' => false
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Graph prototype',
						'Name' => 'Graph prototype widget with all possible fields filled',
						'Refresh interval' => 'No refresh',
						'Source' => 'Simple graph prototype',
						'Item prototype' => [
							'values' => ['testFormItemPrototype2'],
							'context' => ['Group' => 'Zabbix servers', 'Host' => 'Simple form test host']
						],
						'Show legend' => true,
						'Dynamic item' => true,
						'Columns' => '3',
						'Rows' => '2'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Graph prototype',
						'Source' => 'Graph prototype',
					],
					'error' => ['Invalid parameter "Graph prototype": cannot be empty.']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Graph prototype',
						'Source' => 'Simple graph prototype'
					],
					'error' => ['Invalid parameter "Item prototype": cannot be empty.']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Graph prototype',
						'Source' => 'Graph prototype',
						'Graph prototype' => [
							'values' => ['testFormGraphPrototype1'],
							'context' => ['Group' => 'Zabbix servers', 'Host' => 'Simple form test host']
						],
						'Columns' => '0',
						'Rows' => '0'
					],
					'error' => [
						'Invalid parameter "Columns": value must be one of 1-24.',
						'Invalid parameter "Rows": value must be one of 1-16.'
					]
				]
			],
						[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Graph prototype',
						'Source' => 'Graph prototype',
						'Graph prototype' => [
							'values' => ['testFormGraphPrototype1'],
							'context' => ['Group' => 'Zabbix servers', 'Host' => 'Simple form test host']
						],
						'Columns' => '25',
						'Rows' => '17'
					],
					'error' => [
						'Invalid parameter "Columns": value must be one of 1-24.',
						'Invalid parameter "Rows": value must be one of 1-16.'
					]
				]
			]
		];
	}

	/**
	 * Test for checking new Graph prototype widget creation.
	 *
	 * @dataProvider getWidgetData
	 */
	public function testGraphPrototypeWidget_Create($data) {
		$new_widget_count = 1;
		$this->checkGraphPrototypeWidget($data, $new_widget_count);
	}

	/**
	 * Test for checking existing Graph prototype widget update.
	 *
	 * @dataProvider getWidgetData
	 */
	public function testGraphPrototypeWidget_Update($data) {
		$new_widget_count = 0;
		$update = true;
		$this->checkGraphPrototypeWidget($data, $new_widget_count, $update);
	}

	/**
	 * Test for checking Graph prototype widget update without any changes.
	 */
	public function testGraphPrototypeWidget_SimpleUpdate() {
		$this->checkWidgetSimpleActions('Apply');
	}

	/**
	 * Test for checking Graph prototype edit pressing Cancel button.
	 */
	public function testGraphPrototypeWidget_Cancel() {
		$this->checkWidgetSimpleActions('Cancel');
	}


	public static function getWidgetScreenshotData() {
		return [
			[
				[
					'screenshot_id' => 'default'
				]
			],
			[
				[
					'fields' => [
						'Columns' => '3',
						'Rows' => '1'
					],
					'screenshot_id' => '3x1'
				]
			],
			[
				[
					'fields' => [
						'Columns' => '2',
						'Rows' => '2'
					],
					'screenshot_id' => '2x2'
				]
			],
			[
				[
					'fields' => [
						'Columns' => '16',
						'Rows' => '1'
					],
					'screenshot_id' => '16x1'
				]
			],
			[
				[
					'fields' => [
						'Columns' => '16',
						'Rows' => '2'
					],
					'screenshot_id' => '16x2'
				]
			],
			[
				[
					'fields' => [
						'Columns' => '16',
						'Rows' => '3'
					],
					'screenshot_id' => '16x3'
				]
			]
		];
	}

	/**
	 * Test for comparing widgets form screenshot.
	 */
	public function testGraphPrototypeWidget_FormScreenshot() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::SCREENSHOT_DASHBOARD_ID);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		if ($form->getField('Type')->getText() !== 'Graph prototype') {
			$form->fill(['Type' => 'Graph prototype']);
			$form->waitUntilReloaded();
		}
		$this->page->removeFocus();
		$dialog = $this->query('id:overlay_dialogue')->one();
		$this->assertScreenshot($dialog);
	}

	/**
	 * Test for comparing widgets grid screenshots.
	 *
	 * @dataProvider getWidgetScreenshotData
	 */
	public function testGraphPrototypeWidget_GridScreenshots($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::SCREENSHOT_DASHBOARD_ID);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$widget = [
			'Name' => 'Screenshot Widget',
			'Graph prototype' => [
				'values' => ['testFormGraphPrototype1'],
				'context' => ['Group' => 'Zabbix servers', 'Host' => 'Simple form test host']
			]
		];
		if ($form->getField('Type')->getText() !== 'Graph prototype') {
			$form->fill(['Type' => 'Graph prototype']);
			$form->waitUntilReloaded();
		}
		$form->fill($widget);
		if (array_key_exists('fields', $data)){
			$form->fill($data['fields']);
		}
		$form->submit();
		$dashboard->getWidget($widget['Name']);
		$dashboard->save();
		$screenshot_area = $this->query('class:dashbrd-grid-container')->one();
		$this->assertScreenshot($screenshot_area, $data['screenshot_id']);
		$dashboard->edit();
		$this->query('class:btn-widget-delete')->one()->click(true);
		$dashboard->save();
		$this->page->waitUntilReady();
	}

	public function checkGraphPrototypeWidget($data, $new_widget_count, $update = false) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::DASHBOARD_ID);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $update
			? $dashboard->getWidget(self::$previous_widget_name)->edit()->asForm()
			: $dashboard->edit()->addWidget()->asForm();

		if (array_key_exists('show_header', $data)) {
			$form->query('xpath:.//input[@id="show_header"]')->asCheckbox()->one()->fill($data['show_header']);
		}
		$form->fill($data['fields']);
		if (!array_key_exists('Graph prototype', $data['fields']) &&
			!array_key_exists('Item prototype', $data['fields'])) {
			$form->query('xpath:.//div[@id="graphid" | @id="itemid"]')->asMultiselect()->one()->clear();
		}
		$form->submit();

		switch ($data['expected']) {
			case TEST_GOOD:
				// Make sure that the widget is present before saving the dashboard.
				$type = CTestArrayHelper::get($data['fields'], 'Source') === 'Simple graph prototype'
					? 'Item prototype' : 'Graph prototype';

				$default_header = $update ? 'Graph prototype for update'
					: $data['fields'][$type]['context']['Host'].': '.$data['fields'][$type]['values'][0];

				$header = CTestArrayHelper::get($data['fields'], 'Name', $default_header);
				$dashboard->getWidget($header);
				$dashboard->save();

				// Check that Dashboard has been saved and that widget has been added.
				$this->checkDashboardUpdateMessage();
				$this->assertEquals($old_widget_count + $new_widget_count, $dashboard->getWidgets()->count());
				// Verify widget content
				$widget = $dashboard->getWidget($header);
				$this->assertTrue($widget->query('class:dashbrd-grid-iterator-content')->one()->isPresent());

				// Compare placeholders count in data and created widget.
				$expected_placeholders_count =
					CTestArrayHelper::get($data['fields'], 'Columns') && CTestArrayHelper::get($data['fields'], 'Rows')
					? $data['fields']['Columns'] * $data['fields']['Rows']
					: 2;
				$placeholders_count = $widget->query('class:dashbrd-grid-iterator-placeholder')->count();
				$this->assertEquals($expected_placeholders_count, $placeholders_count);
				// Check Dynamic item setting on Dashboard.
				if (CTestArrayHelper::get($data['fields'], 'Dynamic item')){
					$this->assertTrue($dashboard->query('xpath://form[@aria-label="Main filter"]')
						->one()->isPresent());
				}
				// Write widget name to variable to use it in next Update test case.
				if($update){
					self::$previous_widget_name = array_key_exists('Name', $data['fields'])
						? $data['fields']['Name']
						: 'Graph prototype for update';
				}
				break;
			case TEST_BAD:
				$message = $form->getOverlayMessage();
				$this->assertTrue($message->isBad());
				$count = count($data['error']);
				$message->query('xpath:./div[@class="msg-details"]/ul/li['.$count.']')->waitUntilPresent();
				$this->assertEquals($count, $message->getLines()->count());

				foreach ($data['error'] as $error) {
					$this->assertTrue($message->hasLine($error));
				}
				break;
		}
	}

	public function checkWidgetSimpleActions($action) {
		$initial_values = CDBHelper::getHash($this->sql);
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::DASHBOARD_ID);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->getWidget(self::$previous_widget_name)->edit();
		$dialog = $this->query('id:overlay_dialogue')->one();
		$dialog->query('button:'.$action)->one()->click();
		$this->page->waitUntilReady();

		$dashboard->getWidget(self::$previous_widget_name);
		$dashboard->save();
		// Check that Dashboard has been saved and that there are no changes made to the widgets.
		$this->checkDashboardUpdateMessage();
		$this->assertEquals($initial_values, CDBHelper::getHash($this->sql));
	}

	/*
	 * Check dashboard update message.
	 */
	private function checkDashboardUpdateMessage() {
		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());
	}
}
