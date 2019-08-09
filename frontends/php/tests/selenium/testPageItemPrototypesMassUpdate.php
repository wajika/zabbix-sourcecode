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

	public function getChangeItemTypeData() {
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
								['type' => 'Flexible', 'interval' => '60s', 'period' => '2-5,3:00-17:00'],
								['type' => 'Scheduling', 'interval' => 'wd3-4h1-15']
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
						'Prototype for mass update 3  (SNMPv1 => SNMPv3)',
						'Prototype for mass update 4  (SNMPv1 => SNMPv3)'
					],
					'change' => [
						'Context name' => 'New Context name',
						'Security name' => ' New Security name',
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
						'Prototype for mass update 5  (SNMPv2 => SNMPv1)',
						'Prototype for mass update 6  (SNMPv2 => SNMPv1)'
					],
					'change' => [
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
						'JMX endpoint' => ['service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi_new']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getChangeItemTypeData
	 */
	public function testPageItemPrototypesMassUpdate_ChangeItemType($data) {
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
			if ($field === 'Host interface'){
				$form->query('id:interfaceid')->asDropdown()->one()->select($value);
			}
			elseif ($field === 'Update interval'){
				$container_table = $form->query('id:update_interval')->asTable()->one();
				$delay = $container_table->getRow(0)->getColumn(1);
				$delay->query('id:delay')->one()->fill($value['Delay']);

				$intervals_table= $container_table->getRow(1)->getColumn(1)->query('id:custom_intervals')
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
							],
						]
					])->one();
				$intervals_table->fill([
					[
						'action' => USER_ACTION_UPDATE,
						'index' => 0,
						'Type' => $value['Custom intervals'][0]['type'],
						'Interval' => $value['Custom intervals'][0]['interval'],
						'Period' => $value['Custom intervals'][0]['period'],
					],
					[
						'Type' => $value['Custom intervals'][1]['type'],
						'Interval' => $value['Custom intervals'][1]['interval'],
					],
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
			elseif ($field === 'Show value'){
				$form->query('id:valuemapid')->asDropdown()->one()->select($value);
			}
			// Rewrite after DEV-1257 is done.
			elseif ($field === 'Master item'){
				$container = $form->query('id:master_item')->one();
				$container->query('button:'.$value['button'])->one()->click();
				$dialog = COverlayDialogElement::find()->one();
				$dialog->query('link:'.$value['item'])->waitUntilVisible()->one()->click();
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
	}
}
