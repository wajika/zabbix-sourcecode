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

package zbxlib

/*
#include "common.h"
#include "sysinfo.h"
#include "comms.h"
#include "perfmon.h"
#include "../src/zabbix_agent/metrics.h"

#cgo LDFLAGS: -Wl,--start-group
#cgo LDFLAGS: ${SRCDIR}/../../../../win64/misc.o
#cgo LDFLAGS: ${SRCDIR}/../../../../win64/str.o
#cgo LDFLAGS: ${SRCDIR}/../../../../win64/file.o
#cgo LDFLAGS: ${SRCDIR}/../../../../win64/alias.o
#cgo LDFLAGS: ${SRCDIR}/../../../../win64/fatal.o
#cgo LDFLAGS: ${SRCDIR}/../../../../win64/threads.o
#cgo LDFLAGS: ${SRCDIR}/../../../../win64/iprange.o
#cgo LDFLAGS: ${SRCDIR}/../../../../win64/md5.o
#cgo LDFLAGS: ${SRCDIR}/../../../../win64/sysinfo.o
#cgo LDFLAGS: ${SRCDIR}/../../../../win64/vector.o
#cgo LDFLAGS: ${SRCDIR}/../../../../win64/zbxregexp.o
#cgo LDFLAGS: ${SRCDIR}/../../../../win64/algodefs.o
#cgo LDFLAGS: ${SRCDIR}/../../../../win64/logfiles.o
#cgo LDFLAGS: ${SRCDIR}/../../../../win64/sysinfo_system.o
#cgo LDFLAGS: -lpcre -lDbghelp -lpsapi -lws2_32
#cgo LDFLAGS: -Wl,--end-group

int CONFIG_TIMEOUT = 3;
int CONFIG_MAX_LINES_PER_SECOND = 20;
char *CONFIG_HOSTNAME = NULL;
int	CONFIG_UNSAFE_USER_PARAMETERS= 0;
int	CONFIG_ENABLE_REMOTE_COMMANDS= 0;
char *CONFIG_SOURCE_IP = NULL;

const char	*progname = NULL;
const char	title_message[] = "agent";
const char	*usage_message[] = {};
const char	*help_message[] = {};

ZBX_METRIC	parameters_common[] = {NULL};

#define ZBX_MESSAGE_BUF_SIZE	1024

char	*strerror_from_system(unsigned long error)
{
	size_t		offset = 0;
	wchar_t		wide_string[ZBX_MESSAGE_BUF_SIZE];
	static __thread char	utf8_string[ZBX_MESSAGE_BUF_SIZE];

	offset += zbx_snprintf(utf8_string, sizeof(utf8_string), "[0x%08lX] ", error);

	if (0 == FormatMessage(FORMAT_MESSAGE_FROM_SYSTEM | FORMAT_MESSAGE_IGNORE_INSERTS, NULL, error,
			MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), wide_string, ZBX_MESSAGE_BUF_SIZE, NULL))
	{
		zbx_snprintf(utf8_string + offset, sizeof(utf8_string) - offset,
				"unable to find message text [0x%08lX]", GetLastError());

		return utf8_string;
	}

	zbx_unicode_to_utf8_static(wide_string, utf8_string + offset, (int)(sizeof(utf8_string) - offset));

	zbx_rtrim(utf8_string, "\r\n ");

	return utf8_string;
}

char	*strerror_from_module(unsigned long error, const wchar_t *module)
{
	size_t		offset = 0;
	wchar_t		wide_string[ZBX_MESSAGE_BUF_SIZE];
	HMODULE		hmodule;
	static __thread char	utf8_string[ZBX_MESSAGE_BUF_SIZE];

	*utf8_string = '\0';
	hmodule = GetModuleHandle(module);

	offset += zbx_snprintf(utf8_string, sizeof(utf8_string), "[0x%08lX] ", error);

	if (0 == FormatMessage(FORMAT_MESSAGE_FROM_HMODULE | FORMAT_MESSAGE_IGNORE_INSERTS, hmodule, error,
			MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), wide_string, sizeof(wide_string), NULL))
	{
		zbx_snprintf(utf8_string + offset, sizeof(utf8_string) - offset,
				"unable to find message text: %s", strerror_from_system(GetLastError()));

		return utf8_string;
	}

	zbx_unicode_to_utf8_static(wide_string, utf8_string + offset, (int)(sizeof(utf8_string) - offset));

	zbx_rtrim(utf8_string, "\r\n ");

	return utf8_string;
}

int	PERF_COUNTER(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Not supported."));
	return SYSINFO_RET_FAIL;
}

DWORD	get_builtin_counter_index(zbx_builtin_counter_ref_t counter_ref)
{
	return 0;
}
*/
import "C"
