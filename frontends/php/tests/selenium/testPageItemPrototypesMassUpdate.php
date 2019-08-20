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

require_once dirname(__FILE__) . '/../include/CWebTest.php';

/**
 * Test the mass update of item prototypes.
 *
 * @backup items
 */
class testPageItemPrototypesMassUpdate extends CWebTest{

	const DISCOVERY_RULE_ID = 33800;

	public $host_name = 'Simple form test host';

	public function getChangeItemProtoypeData() {
		return [
			[
				[
					'names'=> [
						'Prototype for mass update 1 (active agent => agent)',
						'Prototype for mass update 2 (active agent => agent)'
					],
					'change' => [
						'Type' => 'Zabbix agent',
						'Host interface' => '127.0.0.2 : 10099',
						'Type of information'=> 'Numeric (float)',
						'Units'=> '$',
						'Update interval' => [
							'Delay' => '90s',
							'Custom intervals' => [
								['Type' => 'Flexible', 'Interval' => '60s', 'Period' => '2-5,3:00-17:00'],
								['Type' => 'Scheduling', 'Interval' => 'wd3-4h1-15']
							]
						],
						'History storage period' => [
							'type' => 'Do not keep history',
							'case' => 'history'
						],
						'Trend storage period' => [
							'type' => 'Storage period',
							'period' => '400d',
							'case' => 'trends'
						],
						'Show value' => 'TruthValue',
						'Applications' => [
							'action' => 'Add',
							'query' => 'applications',
							'radio'=> 'app',
							'application' => 'New application'
						],
						'Application prototypes' => [
							'action' => 'Add',
							'query' => 'application_prototypes',
							'radio' => 'app_prot',
							'application' => 'New application prototype'
						],
						'Description' => 'New test description',
						'Create enabled' => 'Disabled'
					]
				]
			],
			[
				[
					'names'=> [
						'Prototype for mass update 3 (SNMPv1 => SNMPv3)',
						'Prototype for mass update 4 (SNMPv1 => SNMPv3)'
					],
					'change' => [
						'Type' => 'SNMPv3 agent',
						'Context name' => 'New Context name',
						'Security name' => 'New Security name',
						'Security level'=> 'authPriv',
						'Authentication protocol'=> 'SHA',
						'Authentication passphrase' => 'New_passphrase',
						'Privacy protocol' => 'AES',
						'Privacy passphrase' => 'New_privacy_passphrase',
						'Port' => '999',
						'Type of information' => 'Log',
						'Log time format' => 'pppppp:yyyyMMdd:hhmmss'
					]
				]
			],
			[
				[
					'names'=> [
						'Prototype for mass update 5 (SNMPv2 => SNMPv1)',
						'Prototype for mass update 6 (SNMPv2 => SNMPv1)'
					],
					'change' => [
						'Type' => 'SNMPv1 agent',
						'SNMP community' => 'New Test Community',
					]
				]
			],
			[
				[
					'names'=> [
						'testFormItemPrototype1',
						'testFormItemPrototype2'
					],
					'change' => [
						'Type' => 'Zabbix trapper',
						'Allowed hosts' => '127.0.0.1, ::127.0.0.2, ::ffff:127.0.0.3, {HOST.HOST}'
					]
				]
			],
			[
				[
					'names'=> [
						'testFormItemPrototype1',
						'testFormItemPrototype2'
					],
					'change' => [
						'Type' => 'Dependent item',
						'Master item' => [
							'button' => 'Select',
							'item' => 'Download speed for scenario "testFormWeb1".'
						]
					]
				]
			],
			[
				[
					'names'=> [
						'testFormItemPrototype3',
						'testFormItemPrototype4'
					],
					'change' => [
						'Type' => 'Dependent item',
						'Master item' => [
							'button' => 'Select prototype',
							'item' => 'testFormItemReuse'
						]
					]
				]
			],
			[
				[
					'names'=> [
						'Prototype for mass update 7 (JMX)',
						'Prototype for mass update 8 (JMX)'
					],
					'change' => [
						'JMX endpoint' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi_new'
					]
				]
			],
			[
				[
					'names'=> [
						'Prototype for mass update 9 (DB monitor)',
						'Prototype for mass update 10 (DB monitor)'
					],
					'change' => [
						'User name' => 'test_username',
						'Password' => 'test_password'
					]
				]
			],
			[
				[
					'names'=> [
						'Prototype for mass update 11 (HTTP agent)',
						'Prototype for mass update 12 (HTTP agent)'
					],
					'change' => [
						'URL' => 'https://www.zabbix.com/network_monitoring',
						'Request body type' => 'JSON data',
						'Request body' => '{"html":"<body>1</body>"}',
						'Headers' => [
							['Name' => 'header1', 'Value' => '1'],
							['Name' => 'header2', 'Value' => '2']
						],
						'Enable trapping' => true
					]
				]
			],
			[
				[
					'names'=> [
						'Prototype for mass update 13 (SSH)',
						'Prototype for mass update 14 (SSH)'
					],
					'change' => [
						'Authentication method' => 'Public key',
						'Public key file' => '/tmp/path/public_file.fl',
						'Private key file' => 'tmp/path/private_file.fl'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getChangeItemProtoypeData
	 */
	public function testPageItemPrototypesMassUpdate_ChangeItemProtoype($data) {
		$this->page->login()->open('disc_prototypes.php?parent_discoveryid='.self::DISCOVERY_RULE_ID);
		// Get item table.
		$table = $this->query('xpath://form[@name="items"]/table[@class="list-table"]')->asTable()->one();
		foreach ($data['names'] as $name) {
			$table->findRow('Name', $name)->select();
		}
		// Open mass update form.
		$this->query('button:Mass update')->one()->click();
		$form = $this->query('name:item_prototype_form')->waitUntilPresent()->asForm()->one();

		foreach ($data['change'] as $field => $value) {
			// Click on a label to show input control.
			$form->getLabel($field)->click();
			// Set field value.
			if ($field === 'Host interface') {
				$form->query('id:interfaceid')->asDropdown()->one()->select($value);
			}
			elseif ($field === 'Update interval'){
				$container_table = $form->query('id:update_interval')->asTable()->one();
				$delay = $container_table->getRow(0)->getColumn(1);
				$delay->query('id:delay')->one()->fill($value['Delay']);

				$intervals_table = $container_table->getRow(1)->getColumn(1)->query('id:custom_intervals')
					->asMultifieldTable([
						'mapping' => [
							[
								'name' => 'Type',
								'selector' => 'class:radio-list-control',
								'class' => 'CSegmentedRadioElement'
							],
							[
								'name' => 'Interval',
								'selector' => 'xpath:./input',
								'class' => 'CElement'
							],
							[
								'name' => 'Period',
								'selector' => 'xpath:./input',
								'class' => 'CElement'
							]
						]
					])->one();
				$intervals_table->fill([
					[
						'action' => USER_ACTION_UPDATE,
						'index' => 0,
						'Type' => $value['Custom intervals'][0]['Type'],
						'Interval' => $value['Custom intervals'][0]['Interval'],
						'Period' => $value['Custom intervals'][0]['Period']
					],
					[
						'Type' => $value['Custom intervals'][1]['Type'],
						'Interval' => $value['Custom intervals'][1]['Interval']
					]
				]);
			}
			elseif ($field === 'History storage period'|| $field === 'Trend storage period'){
				$storage = $form->query('id:'.$value['case'].'_div')->one();
				$storage->query('id:'.$value['case'].'_mode')->one()->asSegmentedRadio()->select($value['type']);
				if(array_key_exists('period', $value)){
					$storage->query('xpath:.//input[@type="text"]')->one()->fill($value['period']);
				}
			}
			elseif ($field === 'Applications'||$field === 'Application prototypes'){
				$app_container = $form->query('id:'.$value['query'].'_div')->one();
				$app_container->query('id:massupdate_'.$value['radio'].'_action')->one()->asSegmentedRadio()->select($value['action']);
				$app_container->query('class:input')->one()->fill($value['application']);
				$app_container->query('class:multiselect-suggest')->waitUntilVisible()->one()->click();
			}
			elseif ($field === 'Show value') {
				$form->query('id:valuemapid')->asDropdown()->one()->select($value);
			}
			// Rewrite after DEV-1257 is done.
			elseif ($field === 'Master item') {
				$container = $form->query('id:master_item')->one();
				$container->query('button:'.$value['button'])->one()->click();
				$dialog = COverlayDialogElement::find()->one();
				$dialog->query('link:'.$value['item'])->waitUntilVisible()->one()->click();
			}
			elseif ($field === 'Headers') {
				$headers_table = $form->query('id:headers_pairs')->asMultifieldTable([
						'mapping' => [
							[
								'name' => 'Name',
								'selector' => 'xpath:./input',
								'class' => 'CElement'
							],
							[
								'name' => 'Value',
								'selector' => 'xpath:./input',
								'class' => 'CElement'
							]
						]
					])->one();
				$headers_table->fill([
					[
						'action' => USER_ACTION_UPDATE,
						'index' => 0,
						'Name' => $value[0]['Name'],
						'Value' => $value[0]['Value']
					],
					[
						'Name' => $value[1]['Name'],
						'Value' => $value[1]['Value']
					]
				]);
			}
			else{
				$form->getField($field)->fill($value);
			}
		}
		$form->submit();
		$this->page->waitUntilReady();
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Item prototypes updated', $message->getTitle());

		// Check that fields are saved for each item.
		foreach ($data['names'] as $name) {
			$this->page->open('disc_prototypes.php?parent_discoveryid='.self::DISCOVERY_RULE_ID);
			// Open modified items.
			if(array_key_exists('Type', $data['change']) && $data['change']['Type'] === 'Dependent item'){
				$cell_name = $data['change']['Master item']['item'].': '.$name;
				$table->findRow('Name', $cell_name)->query('link:'.$name)->one()->click();
			}
			else{
				$table->findRow('Name', $name)->query('link:'.$name)->one()->click();
			}

			$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();


			foreach ($data['change'] as $field => $value) {
				switch ($field) {
					case 'Type':
					case 'Host interface':
					case 'Type of information':
					case 'Units':
					case 'Description':
					case 'Context name':
					case 'Security name':
					case 'Security level':
					case 'Authentication protocol':
					case 'Authentication passphrase':
					case 'Privacy protocol':
					case 'Privacy passphrase':
					case 'Port':
					case 'Type of information':
					case 'Log time format':
					case 'SNMP community':
					case 'Allowed hosts':
					case 'JMX endpoint':
					case 'User name':
					case 'Password':
					case 'Authentication method':
					case 'Public key file':
					case 'Private key file':
					case 'URL':
					case 'Request body type':
					case 'Request body':
					case 'Enable trapping':
						$this->assertEquals($value, $form->getField($field)->getValue());
						break;
					case 'Create enabled':
						$checkbox = $form->getField($field)->getValue();
						if ($checkbox === true) {
							$checkbox = 'Enabled';
						}
						else {
							$checkbox = 'Disabled';
						}
						$this->assertEquals($value, $checkbox);
						break;
					case 'Update interval':
						$update_interval = $form->getField($field)->getValue();
						$this->assertEquals($value['Delay'], $update_interval);

						$custom_intervals_table = $form->query('id:delayFlexTable')
							->asMultifieldTable([
								'mapping' => [
									[
										'name' => 'Type',
										'selector' => 'class:radio-list-control',
										'class' => 'CSegmentedRadioElement'
									],
									[
										'name' => 'Interval',
										'selector' => 'xpath:./input',
										'class' => 'CElement'
									],
									[
										'name' => 'Period',
										'selector' => 'xpath:./input',
										'class' => 'CElement'
									]
								]
							])->one();
						$this->assertEquals($value['Custom intervals'], $custom_intervals_table->getValue());
						break;
					case 'History storage period':
					case 'Trend storage period':
						$storage_container = $form->getFieldContainer($field);
						$storage_radio_value = $storage_container->query('id:'.$value['case'].'_mode')->one()->asSegmentedRadio()->getValue();
						$this->assertEquals($value['type'], $storage_radio_value);
						if(array_key_exists('period', $value)){
							$storage_period_value = $storage_container->query('xpath:.//input[@type="text"]')->one()->getValue();
							$this->assertEquals($value['period'], $storage_period_value);
						}
						break;
					case 'Show value':
							$show_value = $form->query('id:valuemapid')->asDropdown()->one()->getValue();
							$this->assertEquals($value, $show_value);
						break;
					case 'Applications':
					case 'Application prototypes':
						$this->assertEquals($value['application'], $form->getField($field)->getValue());
						break;
					case 'Master item':
						$master_item_container = $form->getFieldContainer($field);
						$master_item_value = $master_item_container->query('id:master_itemname')->one()->getValue();
						$this->assertEquals($this->host_name.': '.$value['item'], $master_item_value);
						break;
					case 'Headers':
						$headers_table = $form->query('id:headers_pairs')
							->asMultifieldTable([
								'mapping' => [
									[
										'name' => 'Name',
										'selector' => 'xpath:./input',
										'class' => 'CElement'
									],
									[
										'name' => 'Value',
										'selector' => 'xpath:./input',
										'class' => 'CElement'
									]
								]
							])->one();
						$this->assertEquals($value, $headers_table->getValue());
						break;
				}
			}
		}
	}


		public function ChangePreprocessing() {
		return [
			[
				[
					'names'=> [
						'Prototype for mass update 1 (active agent => agent)',
						'Prototype for mass update 2 (active agent => agent)'
					],
					'change' => [
						'Type' => 'Zabbix agent',
						'Host interface' => '127.0.0.2 : 10099',
						'Type of information'=> 'Numeric (float)',
						'Units'=> '$',
						'Update interval' => [
							'Delay' => '90s',
							'Custom intervals' => [
								['Type' => 'Flexible', 'Interval' => '60s', 'Period' => '2-5,3:00-17:00'],
								['Type' => 'Scheduling', 'Interval' => 'wd3-4h1-15']
							]
						],
						'History storage period' => [
							'type' => 'Do not keep history',
							'case' => 'history'
						],
						'Trend storage period' => [
							'type' => 'Storage period',
							'period' => '400d',
							'case' => 'trends'
						],
						'Show value' => 'TruthValue',
						'Applications' => [
							'action' => 'Add',
							'query' => 'applications',
							'radio'=> 'app',
							'application' => 'New application'
						],
						'Application prototypes' => [
							'action' => 'Add',
							'query' => 'application_prototypes',
							'radio' => 'app_prot',
							'application' => 'New application prototype'
						],
						'Description' => 'New test description',
						'Create enabled' => 'Disabled'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getChangePreprocessingData
	 */
	public function testPageItemPrototypesMassUpdate_ChangePreprocessing($data) {

	}
}
