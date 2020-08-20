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


/**
 * @var CView $this
 */

$form = (new CForm())
	->cleanItems()
	->setId('preprocessing-test-form');

if ($data['show_prev']) {
	$form
		->addVar('upd_last', '')
		->addVar('upd_prev', '');
}

foreach ($data['inputs'] as $name => $value) {
	if ($name === 'interface') {
		// SNMPv3 additional details about interface.
		if (array_key_exists('useip', $value)) {
			$form->addVar('interface[useip]', $value['useip']);
		}
		if (array_key_exists('interfaceid', $value)) {
			$form->addVar('interface[interfaceid]', $value['interfaceid']);
		}
		continue;
	}
	elseif ($name === 'host' && array_key_exists('hostid', $value)) {
		$form->addVar('hostid', $value['hostid']);
		continue;
	}
	elseif ($name === 'proxy_hostid') {
		continue;
	}
	elseif ($name === 'query_fields' || $name === 'headers') {
		foreach (['name', 'value'] as $key) {
			if (array_key_exists($key, $value)) {
				$form->addVar($name.'['.$key.']', $value[$key]);
			}
		}
		continue;
	}

	$form->addItem((new CInput('hidden', $name, $value))->removeId());
}

// Create macros table.
$macros_table = $data['macros'] ? (new CTable())->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER) : null;

$i = 0;
foreach ($data['macros'] as $macro_name => $macro_value) {
	$macros_table->addRow([
		(new CCol(
			(new CTextAreaFlexible('macro_rows['.$i++.']', $macro_name, ['readonly' => true]))
				->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
				->removeAttribute('name')
				->removeId()
		))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol('&rArr;'))->addStyle('vertical-align: top;'),
		(new CCol(
			(new CTextAreaFlexible('macros['.$macro_name.']', $macro_value))
				->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
				->setAttribute('placeholder', _('value'))
				->removeId()
		))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)
	]);
}

$form_list_top = new CFormList();

if ($data['is_item_testable']) {
	$host_port_row = (new CHorList())
		->addItem(
			$data['interface_address_enabled']
				? (new CTextBox('interface[address]', $data['inputs']['interface']['address']))
					->setWidth(300)
				: (new CTextBox('interface[address]'))
					->setWidth(300)
					->setEnabled(false)
		)
		->addItem((new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN))
		->addItem(new CLabel(_('Port')))
		->addItem(
			$data['interface_port_enabled']
				? (new CTextBox('interface[port]', $data['inputs']['interface']['port'], '', 64))
					->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
				: (new CTextBox('interface[port]'))
					->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
					->setEnabled(false)
		);

	$interface_list = (new CFormList('snmp_details'))
		->cleanItems()
		->addRow((new CLabel(_('Host address'), 'host_address')),
			$host_port_row
		);

	if ($data['inputs']['interface']['interfaceid'] == INTERFACE_TYPE_SNMP) {
		$interface_list
			->addRow((new CLabel(_('SNMP version'), 'interface[details][version]'))->setAsteriskMark(),
				new CComboBox('interface[details][version]', $data['inputs']['interface']['details']['version'], null, [SNMP_V1 => _('SNMPv1'), SNMP_V2C => _('SNMPv2'), SNMP_V3 => _('SNMPv3')]),
				'row_snmp_version'
			)
			->addRow((new CLabel(_('SNMP community'), 'interface[details][community]'))->setAsteriskMark(),
				(new CTextBox('interface[details][community]', $data['inputs']['interface']['details']['community'], false, DB::getFieldLength('interface_snmp', 'community')))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired(),
				'row_snmp_community', 'test'
			)
			->addRow(new CLabel(_('Context name'), 'interface[details][contextname]'),
				(new CTextBox('interface[details][contextname]', $data['inputs']['interface']['details']['contextname'], false, DB::getFieldLength('interface_snmp', 'contextname')))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
				'row_snmpv3_contextname'
			)
			->addRow(new CLabel(_('Security name'), 'interface[details][securityname]'),
				(new CTextBox('interface[details][securityname]', $data['inputs']['interface']['details']['securityname'], false, DB::getFieldLength('interface_snmp', 'securityname')))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
				'row_snmpv3_securityname'
			)
			->addRow(new CLabel(_('Security level'), 'interface[details][securitylevel]'),
				new CComboBox('interface[details][securitylevel]', $data['inputs']['interface']['details']['securitylevel'], null, [
					ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
					ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
					ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
				]),
				'row_snmpv3_securitylevel'
			)
			->addRow(new CLabel(_('Authentication protocol'), 'interface[details][authprotocol]'),
				(new CRadioButtonList('interface[details][authprotocol]', (int) $data['inputs']['interface']['details']['authprotocol']))
					->addValue(_('MD5'), ITEM_AUTHPROTOCOL_MD5, 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_MD5)
					->addValue(_('SHA'), ITEM_AUTHPROTOCOL_SHA, 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_SHA)
					->setModern(true),
				'row_snmpv3_authprotocol'
			)
			->addRow(new CLabel(_('Authentication passphrase'), 'interface[details][authpassphrase]'),
				(new CTextBox('interface[details][authpassphrase]', $data['inputs']['interface']['details']['authpassphrase'], false, DB::getFieldLength('interface_snmp', 'authpassphrase')))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
				'row_snmpv3_authpassphrase'
			)
			->addRow(new CLabel(_('Privacy protocol'), 'interface[details][privprotocol]'),
				(new CRadioButtonList('interface[details][privprotocol]', (int) $data['inputs']['interface']['details']['privprotocol']))
					->addValue(_('DES'), ITEM_PRIVPROTOCOL_DES, 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_DES)
					->addValue(_('AES'), ITEM_PRIVPROTOCOL_AES, 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_AES)
					->setModern(true),
				'row_snmpv3_privprotocol'
			)
			->addRow(new CLabel(_('Privacy passphrase'), 'interface[details][privpassphrase]'),
				(new CTextBox('interface[details][privpassphrase]', $data['inputs']['interface']['details']['privpassphrase'], false, DB::getFieldLength('interface_snmp', 'privpassphrase')))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
				'row_snmpv3_privpassphrase'
			);
	}

	$form_list_top
		->addRow(
			new CLabel(_('Get value from host'), 'get_value'),
			(new CCheckBox('get_value', 1))->setChecked($data['get_value'])
		)
		->addRow(
			(new CDiv())
				->addItem(
					(new CSpan())
						->addClass(ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE)
						->addClass('closed')
						->addClass($data['inputs']['interface']['interfaceid'] != INTERFACE_TYPE_SNMP ? 'hidden' : '')
				)
				->addItem(new CSpan(_('Interface')))
				->addClass(ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE_WRAPPER),
			$interface_list
		)
		->addRow(
			new CLabel(_('Proxy'), 'proxy_hostid'),
			$data['proxies_enabled']
				? (new CComboBox('proxy_hostid',
						array_key_exists('proxy_hostid', $data['inputs']) ? $data['inputs']['proxy_hostid'] : 0, null,
						[0 => _('(no proxy)')] + $data['proxies']))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				: (new CTextBox(null, _('(no proxy)'), true))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->setId('proxy_hostid') // Automated tests need this.
		)
		->addRow(
			null,
			(new CSimpleButton(_('Get value')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('get_value_btn')
				->addStyle('float: right')
		);
}

$form_list_left = new CFormList();
$form_list_right = new CFormList();

$form_list_left
	->addRow(
		new CLabel(_('Value'), 'value'),
		(new CMultilineInput('value', '', [
			'disabled' => false,
			'readonly' => false
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'preproc-test-popup-value-row'
	)
	->addRow(
		new CLabel(_('Previous value'), 'prev_item_value'),
			(new CMultilineInput('prev_value', '', [
				'disabled' => !$data['show_prev']
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'preproc-test-popup-prev-value-row'
	)
	->addRow(
		new CLabel(_('End of line sequence'), 'eol'),
		(new CRadioButtonList('eol', $data['eol']))
			->addValue(_('LF'), ZBX_EOL_LF)
			->addValue(_('CRLF'), ZBX_EOL_CRLF)
			->setModern(true)
	);

$form_list_right
	->addRow(
		new CLabel(_('Time'), 'time'),
		(new CTextBox(null, 'now', true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setId('time')
	)
	->addRow(
		new CLabel(_('Prev. time'), 'prev_time'),
		(new CTextBox('prev_time', $data['prev_time']))
			->setEnabled($data['show_prev'])
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);

$form_list = new CFormList();

if ($macros_table) {
	$form_list->addRow(
		_('Macros'),
		(new CDiv($macros_table))
			->addStyle('width: 675px;')
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	);
}

if (count($data['steps']) > 0) {
	// Create results table.
	$result_table = (new CTable())
		->setId('preprocessing-steps')
		->addClass('preprocessing-test-results')
		->addStyle('width: 100%;')
		->setHeader([
			'',
			(new CColHeader(_('Name')))->addStyle('width: 100%;'),
			(new CColHeader(_('Result')))->addClass(ZBX_STYLE_RIGHT)
		]);

	foreach ($data['steps'] as $i => $step) {
		$form
			->addVar('steps['.$i.'][type]', $step['type'])
			->addVar('steps['.$i.'][error_handler]', $step['error_handler'])
			->addVar('steps['.$i.'][error_handler_params]', $step['error_handler_params']);

		// Temporary solution to fix "\n\n1" conversion to "\n1" in the hidden textarea field after jQuery.append().
		if ($step['type'] == ZBX_PREPROC_CSV_TO_JSON) {
			$form->addItem(new CInput('hidden', 'steps['.$i.'][params]', $step['params']));
		}
		else {
			$form->addVar('steps['.$i.'][params]', $step['params']);
		}

		$result_table->addRow([
			$step['num'].':',
			(new CCol($step['name']))->setId('preproc-test-step-'.$i.'-name'),
			(new CCol())
				->addClass(ZBX_STYLE_RIGHT)
				->setId('preproc-test-step-'.$i.'-result')
		]);
	}

	$form_list->addRow(
		_('Preprocessing steps'),
		(new CDiv($result_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('width: 675px;')
	);
}

if ($data['show_final_result']) {
	$form_list->addRow(_('Result'), false, 'final-result');
}

$container = (new CDiv())
	->addClass(ZBX_STYLE_ROW)
	->addItem([
		(new CDiv($form_list_left))->addClass(ZBX_STYLE_CELL),
		(new CDiv($form_list_right))->addClass(ZBX_STYLE_CELL)
	]);

$form
	->addItem($form_list_top)
	->addItem($container)
	->addItem($form_list)
	->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'));

$templates = [
	(new CTag('script', true))
		->setAttribute('type', 'text/x-jquery-tmpl')
		->setId('preprocessing-step-error-icon')
		->addItem(makeErrorIcon('#{error}')),
	(new CTag('script', true))
		->setAttribute('type', 'text/x-jquery-tmpl')
		->setId('preprocessing-gray-label')
		->addItem(
			(new CDiv('#{label}'))
				->addStyle('margin-top: 5px;')
				->addClass(ZBX_STYLE_GREY)
		),
	(new CTag('script', true))
		->setAttribute('type', 'text/x-jquery-tmpl')
		->setId('preprocessing-step-result')
		->addItem(
			(new CDiv(
				(new CSpan('#{result}'))
					->addClass(ZBX_STYLE_LINK_ACTION)
					->setHint('#{result}', 'hintbox-scrollable', true, 'max-width:'.ZBX_ACTIONS_POPUP_MAX_WIDTH.'px;')
			))
				->addStyle('max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
		),
	(new CTag('script', true))
		->setAttribute('type', 'text/x-jquery-tmpl')
		->setId('preprocessing-step-action-done')
		->addItem(
			(new CDiv([
				'#{action_name} ',
				(new CDiv(
					(new CSpan('#{failed}'))
						->addClass(ZBX_STYLE_LINK_ACTION)
						->setHint('#{failed}', '', true, 'max-width:'.ZBX_ACTIONS_POPUP_MAX_WIDTH.'px; ')
				))
					->addStyle('max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
					->addClass(ZBX_STYLE_REL_CONTAINER)
			]))
				->addStyle('margin-top: 1px;')
				->addClass(ZBX_STYLE_GREY)
		)
];

$output = [
	'header' => $data['title'],
	'script_inline' => $this->readJsFile('popup.itemtestedit.view.js.php'),
	'body' => (new CDiv([$form, $templates]))->toString(),
	'cancel_action' => 'return saveItemTestInputs();',
	'buttons' => [
		[
			'title' => ($data['is_item_testable'] && $data['get_value']) ? _('Get value and test') : _('Test'),
			'keepOpen' => true,
			'enabled' => true,
			'isSubmit' => true,
			'action' => 'return itemCompleteTest(overlay);'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
