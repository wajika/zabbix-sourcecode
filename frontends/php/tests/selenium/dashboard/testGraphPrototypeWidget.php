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

	public static function getCreateWidgetData() {
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
						'Name' => 'Graph prototype widget with all possible fields filled',
						'Refresh interval' => 'No refresh',
						'Source' => 'Simple graph prototype',
						'Item prototype' => [
							'values' => ['testFormItemPrototype1'],
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
						'Type' => 'Graph prototype'
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
	 * @dataProvider getCreateWidgetData
	 */
	public function testGraphPrototypeWidget_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=105');
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		// Add a widget.
		$form = $dashboard->edit()->addWidget()->asForm();
		$this->assertTrue($form->query('xpath:.//label[@for="show_header"]')->one()->isClickable());
		$form->fill($data['fields']);
		$form->submit();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->page->waitUntilReady();

				// Make sure that the widget is present before saving the dashboard.
				$type = CTestArrayHelper::get($data['fields'], 'Source') === 'Simple graph prototype'
					? 'Item prototype' : 'Graph prototype';

				$default_header = $data['fields'][$type]['context']['Host'].': '.$data['fields'][$type]['values'][0];

				$header = CTestArrayHelper::get($data['fields'], 'Name', $default_header);
				$dashboard->getWidget($header);
				$dashboard->save();

				// Check that Dashboard has been saved and that widget has been added.
				$this->checkDashboardUpdateMessage();
				$this->assertEquals($old_widget_count + 1, $dashboard->getWidgets()->count());
				// Verify widget content
				$widget = $dashboard->getWidget($header);
				$this->assertTrue($widget->query('class:dashbrd-grid-iterator-content')->one()->isPresent());

				// Compare placeholders count in data and created widget.
				$expected_placeholders_count =
					CTestArrayHelper::get($data['fields'], 'Columns')
					? $data['fields']['Columns'] * $data['fields']['Rows']
					: 2;
				$placeholders_count = $widget->query('class:dashbrd-grid-iterator-placeholder')->count();

				$this->assertEquals($expected_placeholders_count, $placeholders_count);
				// Check Dynamic item setting on Dashboard.
				if (CTestArrayHelper::get($data['fields'], 'Dynamic item')){
					$this->assertTrue($dashboard->query('xpath://form[@aria-label="Main filter"]')
						->one()->isPresent());
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

	/*
	 * Check dashboard update message.
	 */
	private function checkDashboardUpdateMessage() {
		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());
	}
}
