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

#ifndef ZABBIX_USER_MACROS_H
#define ZABBIX_USER_MACROS_H

#define ZBX_DBCONFIG_IMPL

#include "common.h"
#include "dbconfig.h"

typedef struct
{
	zbx_uint64_t	macroid;
	zbx_uint64_t	hostid;
	const char	*name;
	const char	*context;
	const char	*value;
	unsigned char	type;
	unsigned int	refcount;
}
zbx_um_macro_t;

zbx_um_manager_t	*zbx_dc_um_sync(zbx_um_manager_t *manager, zbx_dbsync_t *gmacros, zbx_dbsync_t *hmacros,
		zbx_dbsync_t *htmpls);


#endif

