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


// Get language translations.
require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/js.inc.php';
require_once dirname(__FILE__).'/../../include/locales.inc.php';
require_once dirname(__FILE__).'/../../include/translateDefines.inc.php';

class CControllerJsLoader extends CController {
	private $available_js_cripts = [];

	public function __construct() {
		parent::__construct();

		// Available scripts 'scriptFileName' => 'path relative to js/'
		$this->available_js_cripts = [
			'common.js' => '',
			'dashboard.grid.js' => '',
			'menupopup.js' => '',
			'gtlc.js' => '',
			'functions.js' => '',
			'main.js' => '',
			'dom.js' => '',
			'servercheck.js' => '',
			'flickerfreescreen.js' => '',
			'multilineinput.js' => '',
			'multiselect.js' => '',
			'colorpicker.js' => '',
			'chkbxrange.js' => '',
			'csvggraphwidget.js' => '',
			'layout.mode.js' => '',
			'textareaflexible.js' => '',
			// vendors
			'prototype.js' => 'vendors/',
			'jquery.js' => 'vendors/',
			'jquery-ui.js' => 'vendors/',
			// classes
			'class.bbcode.js' => '',
			'class.calendar.js' => '',
			'class.cclock.js' => '',
			'class.cdate.js' => '',
			'class.cdebug.js' => '',
			'class.cmap.js' => '',
			'class.promise.js' => '',
			'class.localstorage.js' => '',
			'class.notifications.js' => '',
			'class.notification.js' => '',
			'class.notification.collection.js' => '',
			'class.notifications.audio.js' => '',
			'class.browsertab.js' => '',
			'class.cnavtree.js' => '',
			'class.cookie.js' => '',
			'class.coverride.js' => '',
			'class.crangecontrol.js' => '',
			'class.cscreen.js' => '',
			'class.csuggest.js' => '',
			'class.csvggraph.js' => '',
			'class.ctree.js' => '',
			'class.curl.js' => '',
			'class.overlaycollection.js' => '',
			'class.cverticalaccordion.js' => '',
			'class.mapWidget.js' => '',
			'class.svg.canvas.js' => 'vector/',
			'class.svg.map.js' => 'vector/',
			'class.cviewswitcher.js' => '',
			'class.pmaster.js' => '',
			'class.rpc.js' => '',
			'init.js' => '',
			// templates
			'sysmap.tpl.js' => 'templates/',
			// page-specific scripts
			'items.js' => 'pages/',
			'popup.condition.common.js' => 'pages/',
			'popup.operation.common.js' => 'pages/'
		];
	}

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$supported_locales = array_keys(getLocales());

		$fields = [
			'lang' =>				'db users.lang|in '.implode(',', $supported_locales),
			'files' =>				'array',
			'showGuiMessaging' =>	'int32|in 1',
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->getInput('files', [])) {
			foreach ($this->getInput('files') as $file) {
				if (!array_key_exists($file, $this->available_js_cripts)) {
					$ret = false;
					error(_s('Incorrect value "%1$s" for "%2$s" field.', $file, 'files'));
					break;
				}
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		// If we must provide language constants on language different from English.
		if ($this->hasInput('lang')) {
			if (function_exists('bindtextdomain')) {
				// Initializing gettext translations depending on language selected by user.
				$locales = zbx_locale_variants($this->getInput('lang'));

				foreach ($locales as $locale) {
					putenv('LC_ALL='.$locale);
					putenv('LANG='.$locale);
					putenv('LANGUAGE='.$locale);

					if (setlocale(LC_ALL, $locale)) {
						break;
					}
				}

				bindtextdomain('frontend', 'locale');
				bind_textdomain_codeset('frontend', 'UTF-8');
				textdomain('frontend');
			}

			// Numeric Locale to default.
			setlocale(LC_NUMERIC,
				['C', 'POSIX', 'en', 'en_US', 'en_US.UTF-8', 'English_United States.1252', 'en_GB', 'en_GB.UTF-8']
			);
		}

		$js = 'if (typeof(locale) == "undefined") { var locale = {}; }'."\n";
		if ($this->getInput('files', [])) {
			$files = $this->getInput('files');
		}
		else {
			$files = [
				'prototype.js',
				'jquery.js',
				'jquery-ui.js',
				'common.js',
				'class.cdebug.js',
				'class.overlaycollection.js',
				'class.cdate.js',
				'class.cookie.js',
				'class.curl.js',
				'class.rpc.js',
				'class.bbcode.js',
				'class.csuggest.js',
				'main.js',
				'chkbxrange.js',
				'functions.js',
				'menupopup.js',
				'init.js'
			];

			// Load frontend messaging only for some pages.
			if ($this->hasInput('showGuiMessaging')) {

				$cookie = CWebUser::getSessionCookie();
				if ($cookie !== null) {
					$js .= 'window.ZBX_SESSION_NAME = "'.crc32($cookie).'";';
				}

				$files[] = 'class.promise.js';
				$files[] = 'class.localstorage.js';
				$files[] = 'class.browsertab.js';
				$files[] = 'class.notification.collection.js';
				$files[] = 'class.notifications.audio.js';
				$files[] = 'class.notification.js';
				$files[] = 'class.notifications.js';
			}
		}

		$translation_strings = [
			'gtlc.js' => [
				'S_MINUTE_SHORT' => _x('m', 'minute short')
			],
			'dashboard.grid.js' => [
				'Edit widget' => _('Edit widget'),
				'Add widget' => _('Add widget'),
				'Apply' => _('Apply'),
				'Add' => _('Add'),
				'Edit' => _('Edit'),
				'Cancel' => _('Cancel'),
				'Delete' => _('Delete'),
				'You have unsaved changes.' => _('You have unsaved changes.'),
				'Are you sure, you want to leave this page?' => _('Are you sure, you want to leave this page?'),
				'Cannot add widgets in kiosk mode' => _('Cannot add widgets in kiosk mode'),
				'You do not have permissions to edit dashboard' => _('You do not have permissions to edit dashboard'),
				'Add a new widget' => _('Add a new widget'),
				'Release to create a new widget.' => _('Release to create a new widget.'),
				'Click and drag to desired size.' => _('Click and drag to desired size.'),
				'Adjust widget refresh interval' => _('Adjust widget refresh interval'),
				'Previous page' => _('Previous page'),
				'Next page' => _('Next page'),
				'Widget is too small for the specified number of columns and rows.' =>
					_('Widget is too small for the specified number of columns and rows.'),
				'Cannot add widget: not enough free space on the dashboard.' =>
					_('Cannot add widget: not enough free space on the dashboard.')
			],
			'functions.js' => [
				'Cancel' => _('Cancel'),
				'S_CLOSE' => _('Close'),
				'Execute' => _('Execute'),
				'Execution confirmation' => _('Execution confirmation'),
				'S_YEAR_SHORT' => _x('y', 'year short'),
				'S_MONTH_SHORT' => _x('m', 'month short'),
				'S_DAY_SHORT' => _x('d', 'day short'),
				'S_HOUR_SHORT' => _x('h', 'hour short'),
				'S_MINUTE_SHORT' => _x('m', 'minute short'),
				'Do you wish to replace the conditional expression?' => _('Do you wish to replace the conditional expression?'),
				'Success message' => _('Success message'),
				'Error message' => _('Error message'),
				'Warning message' => _('Warning message')
			],
			'class.calendar.js' => [
				'S_CALENDAR' => _('Calendar'),
				'S_JANUARY' => _('January'),
				'S_FEBRUARY' => _('February'),
				'S_MARCH' => _('March'),
				'S_APRIL' => _('April'),
				'S_MAY' => _('May'),
				'S_JUNE' => _('June'),
				'S_JULY' => _('July'),
				'S_AUGUST' => _('August'),
				'S_SEPTEMBER' => _('September'),
				'S_OCTOBER' => _('October'),
				'S_NOVEMBER' => _('November'),
				'S_DECEMBER' => _('December'),
				'S_MONDAY' => _('Monday'),
				'S_TUESDAY' => _('Tuesday'),
				'S_WEDNESDAY' => _('Wednesday'),
				'S_THURSDAY' => _('Thursday'),
				'S_FRIDAY' => _('Friday'),
				'S_SATURDAY' => _('Saturday'),
				'S_SUNDAY' => _('Sunday'),
				'S_MONDAY_SHORT_BIG' => _x('M', 'Monday short'),
				'S_TUESDAY_SHORT_BIG' => _x('T', 'Tuesday short'),
				'S_WEDNESDAY_SHORT_BIG' => _x('W', 'Wednesday short'),
				'S_THURSDAY_SHORT_BIG' => _x('T', 'Thursday short'),
				'S_FRIDAY_SHORT_BIG' => _x('F', 'Friday short'),
				'S_SATURDAY_SHORT_BIG' => _x('S', 'Saturday short'),
				'S_SUNDAY_SHORT_BIG' => _x('S', 'Sunday short')
			],
			'class.cmap.js' => [
				'S_ON' => _('On'),
				'S_OFF' => _('Off'),
				'S_HIDDEN' => _('Hidden'),
				'S_SHOWN' => _('Shown'),
				'S_HOST' => _('Host'),
				'S_MAP' => _('Map'),
				'S_TRIGGER' => _('Trigger'),
				'S_HOST_GROUP' => _('Host group'),
				'S_IMAGE' => _('Image'),
				'S_DEFAULT' => _('Default'),
				'S_PLEASE_SELECT_TWO_ELEMENTS' => _('Please select two elements'),
				'S_TWO_MAP_ELEMENTS_SHOULD_BE_SELECTED' => _('Two map elements should be selected'),
				'S_DELETE_SELECTED_ELEMENTS_Q' => _('Delete selected elements?'),
				'S_DELETE_SELECTED_SHAPES_Q' => _('Delete selected shapes?'),
				'S_BRING_TO_FRONT' => _('Bring to front'),
				'S_BRING_FORWARD' => _('Bring forward'),
				'S_SEND_BACKWARD' => _('Send backward'),
				'S_SEND_TO_BACK' => _('Send to back'),
				'S_REMOVE' => _('Remove'),
				'S_NEW_ELEMENT' => _('New element'),
				'S_COPY' => _('Copy'),
				'S_PASTE' => _('Paste'),
				'S_PASTE_SIMPLE' => _('Paste without external links'),
				'S_INCORRECT_ELEMENT_MAP_LINK' => _('All links should have "Name" and "URL" specified'),
				'S_EACH_URL_SHOULD_HAVE_UNIQUE' => _('Each URL should have a unique name. Please make sure there is only one URL named'),
				'S_DELETE_LINKS_BETWEEN_SELECTED_ELEMENTS_Q' => _('Delete links between selected elements?'),
				'S_MACRO_EXPAND_ERROR' => _('Cannot expand macros.'),
				'S_NO_IMAGES' => 'You need to have at least one image uploaded to create map element. Images can be uploaded in Administration->General->Images section.',
				'S_COLOR_IS_NOT_CORRECT' => _('Colour "%1$s" is not correct: expecting hexadecimal colour code (6 symbols).')
			],
			'class.notifications.js' => [
				'S_PROBLEM_ON' => _('Problem on'),
				'S_RESOLVED' => _('Resolved'),
				'S_MUTE' => _('Mute'),
				'S_UNMUTE' => _('Unmute'),
				'S_CLEAR' => _('Clear'),
				'S_SNOOZE' => _('Snooze')
			],
			'class.cookie.js' => [
				'S_MAX_COOKIE_SIZE_REACHED' => _('We are sorry, the maximum possible number of elements to remember has been reached.')
			],
			'class.coverride.js' => [
				'S_COLOR' => _('colour'),
				'S_TIME_SHIFT' => _('time shift')
			],
			'class.cverticalaccordion.js' => [
				'S_COLLAPSE' => _('Collapse'),
				'S_EXPAND' => _('Expand')
			],
			'main.js' => [
				'S_EXPAND' => _('Expand'),
				'S_COLLAPSE' => _('Collapse'),
				'S_CLOSE' => _('Close')
			],
			'multilineinput.js' => [
				'S_N_SYMBOLS_REMAINING' => _('%1$s symbols remaining'),
				'S_CLICK_TO_VIEW_OR_EDIT' => _('Click to view or edit'),
				'S_APPLY' => _('Apply'),
				'S_CANCEL' => _('Cancel')
			],
			'multiselect.js' => [
				'No matches found' => _('No matches found'),
				'More matches found...' => _('More matches found...'),
				'type here to search' => _('type here to search'),
				'new' => _('new'),
				'Select' => _('Select'),
				'Added, %1$s' => _x('Added, %1$s', 'screen reader'),
				'Removed, %1$s' => _x('Removed, %1$s', 'screen reader'),
				'%1$s, read only' => _x('%1$s, read only', 'screen reader'),
				'Can not be removed' => _x('Can not be removed', 'screen reader'),
				'Selected, %1$s in position %2$d of %3$d' => _x('Selected, %1$s in position %2$d of %3$d', 'screen reader'),
				'Selected, %1$s, read only, in position %2$d of %3$d' => _x('Selected, %1$s, read only, in position %2$d of %3$d', 'screen reader'),
				'More than %1$d matches for %2$s found' => _x('More than %1$d matches for %2$s found', 'screen reader'),
				'%1$d matches for %2$s found' => _x('%1$d matches for %2$s found', 'screen reader'),
				'%1$s preselected, use down,up arrow keys and enter to select' => _x('%1$s preselected, use down,up arrow keys and enter to select', 'screen reader')
			],
			'menupopup.js' => [
				'Actions' => _('Actions'),
				'Acknowledge' => _('Acknowledge'),
				'Configuration' => _('Configuration'),
				'Clone' => _('Clone'),
				'Create new' => _('Create new'),
				'Create trigger' => _('Create trigger'),
				'Create dependent item' => _('Create dependent item'),
				'Create dependent discovery rule' => _('Create dependent discovery rule'),
				'Delete' => _('Delete'),
				'Delete dashboard?' => _('Delete dashboard?'),
				'Do you wish to replace the conditional expression?' => _('Do you wish to replace the conditional expression?'),
				'Edit trigger' => _('Edit trigger'),
				'Insert expression' => _('Insert expression'),
				'Sharing' => _('Sharing'),
				'Trigger status "OK"' => _('Trigger status "OK"'),
				'Trigger status "Problem"' => _('Trigger status "Problem"'),
				'Go to' => _('Go to'),
				'Graphs' => _('Graphs'),
				'History' => _('History'),
				'Host inventory' => _('Host inventory'),
				'Host screens' => _('Host screens'),
				'Latest data' => _('Latest data'),
				'Latest values' => _('Latest values'),
				'Last hour graph' => _('Last hour graph'),
				'Last month graph' => _('Last month graph'),
				'Last week graph' => _('Last week graph'),
				'Problems' => _('Problems'),
				'Refresh interval' => _('Refresh interval'),
				'Refresh interval multiplier' => _('Refresh interval multiplier'),
				'Scripts' => _('Scripts'),
				'Submap' => _('Submap'),
				'S_TRIGGER' => _('Trigger'),
				'URL' => _('URL'),
				'URLs' => _('URLs'),
				'No refresh' => _('No refresh'),
				'10 seconds' => _n('%1$s second', '%1$s seconds', 10),
				'30 seconds' => _n('%1$s second', '%1$s seconds', 30),
				'1 minute' => _n('%1$s minute', '%1$s minutes', 1),
				'2 minutes' => _n('%1$s minute', '%1$s minutes', 2),
				'10 minutes' => _n('%1$s minute', '%1$s minutes', 10),
				'15 minutes' => _n('%1$s minute', '%1$s minutes', 15),
				'S_SELECTED_SR' => _x('%1$s, selected', 'screen reader')
			],
			'items.js' => [
				'To set a host interface select a single item type for all items' => _('To set a host interface select a single item type for all items'),
				'No interface found' => _('No interface found')
			],
			'class.cnavtree.js' => [
				'Edit' => _('Edit'),
				'Remove' => _('Remove'),
				'root' => _('root'),
				'Edit tree element' => _('Edit tree element'),
				'Apply' => _('Apply'),
				'Add' => _('Add'),
				'Cancel' => _('Cancel'),
				'Add child element' => _('Add child element'),
				'Add multiple maps' => _('Add multiple maps')
			],
			'colorpicker.js' => [
				'S_CLOSE' => _('Close')
			],
			'class.csvggraph.js' => [
				'S_DISPLAYING_FOUND' => _('Displaying %1$s of %2$s found'),
				'S_MINUTE_SHORT' => _x('m', 'minute short')
			],
			'common.js' => [
				'Cancel' => _('Cancel')
			]
		];

		foreach ($files as $file) {
			if (array_key_exists($file, $translation_strings)) {
				foreach ($translation_strings[$file] as $original_string => $string) {
					$js .= "locale['".$original_string."'] = ".zbx_jsvalue($string).";";
				}
			}

			if (array_key_exists($file, $this->available_js_cripts)) {
				$js .= file_get_contents(dirname(__FILE__).'/../../js/'.$this->available_js_cripts[$file].$file)."\n";
			}
		}

		$data = [
			'files' => $files,
			'js' => $js,
			'etag' => md5($js)
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
