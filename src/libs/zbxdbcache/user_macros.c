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

#define ZBX_DBCONFIG_IMPL

#include "common.h"
#include "log.h"
#include "db.h"
#include "zbxalgo.h"
#include "vectorimpl.h"
#include "memalloc.h"
#include "dbcache.h"
#include "dbconfig.h"
#include "dbsync.h"
#include "user_macros.h"

// WDN debug
static void     snapshot_start(struct timeval *s1)
{
	gettimeofday(s1, NULL);
}

static int      snapshot_end(struct timeval *s1)
{
	struct timeval  s2;
	int             diff;

	gettimeofday(&s2, NULL);
	diff = s2.tv_sec - s1->tv_sec;
	if (s2.tv_usec < s1->tv_usec)
	{
		s2.tv_usec += 1000000;
		diff--;
	}
	diff = diff * 1000 + (s2.tv_usec - s1->tv_usec) / 1000;

	return diff;
}

extern zbx_mem_info_t	*config_mem;
ZBX_MEM_FUNC_IMPL(__config, config_mem);

ZBX_PTR_VECTOR_DECL(um_macro, zbx_um_macro_t*)
ZBX_PTR_VECTOR_IMPL(um_macro, zbx_um_macro_t*)

typedef struct
{
	zbx_uint64_t		hostid;
	zbx_vector_um_macro_t	macros;
	zbx_vector_uint64_t	parentids;
	unsigned int		refcount;
}
zbx_um_host_t;

typedef struct
{
	zbx_uint64_t	hostid;
	zbx_um_host_t	*host;
}
zbx_um_host_update_t;

static void	dc_um_macro_release(zbx_um_macro_t *macro)
{
	if (0 == --macro->refcount)
	{
		zbx_strpool_release(macro->name);
		if (NULL != macro->context)
			zbx_strpool_release(macro->context);
		zbx_strpool_release(macro->value);
		zbx_strpool_release(macro->value);
		__config_mem_free_func(macro);
	}
}

static void	dc_um_host_release(zbx_um_host_t *host)
{
	int	i;

	for (i = 0; i < host->macros.values_num; i++)
		dc_um_macro_release(host->macros.values[i]);

	if (0 == --host->refcount)
	{
		zbx_vector_um_macro_clear(&host->macros);
		zbx_vector_um_macro_destroy(&host->macros);
		zbx_vector_uint64_destroy(&host->parentids);
		__config_mem_free_func(host);
	}
}

static void	dc_um_manager_release(zbx_um_manager_t *manager)
{
	if (0 == --manager->refcount)
	{
		zbx_hashset_iter_t	iter;
		zbx_um_host_t		**phost;

		zbx_hashset_iter_reset(&manager->hosts, &iter);
		while (NULL != (phost = (zbx_um_host_t **)zbx_hashset_iter_next(&iter)))
			dc_um_host_release(*phost);

		zbx_hashset_destroy(&manager->hosts);
		__config_mem_free_func(manager);
	}
}

static	zbx_hash_t	dc_um_host_hash(const void *data)
{
	const zbx_um_host_t	*host = *(const zbx_um_host_t **)data;
	return ZBX_DEFAULT_UINT64_HASH_FUNC(&host->hostid);
}

static int	dc_um_host_compare(const void *d1, const void *d2)
{
	const zbx_um_host_t	*h1 = *(const zbx_um_host_t **)d1;
	const zbx_um_host_t	*h2 = *(const zbx_um_host_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(h1->hostid, h2->hostid);
	return 0;
}

zbx_um_manager_t	*dc_um_manager_create(int hosts_num)
{
	zbx_um_manager_t	*manager;

	manager = (zbx_um_manager_t *)__config_mem_malloc_func(NULL, sizeof(zbx_um_manager_t));
	manager->refcount = 1;
	zbx_hashset_create_ext(&manager->hosts, hosts_num, dc_um_host_hash, dc_um_host_compare, NULL,
			__config_mem_malloc_func, __config_mem_realloc_func, __config_mem_free_func);

	return manager;
}

zbx_um_host_t	*dc_um_get_host(zbx_um_manager_t *manager, zbx_uint64_t hostid)
{
	zbx_uint64_t	*phostid = &hostid;
	zbx_um_host_t	**phost;

	if (NULL == (phost = (zbx_um_host_t **)zbx_hashset_search(&manager->hosts, &phostid)))
		return NULL;

	return *phost;
}

zbx_um_host_t	*dc_um_host_create(zbx_uint64_t hostid)
{
	zbx_um_host_t	*host;

	host = (zbx_um_host_t *)__config_mem_malloc_func(NULL, sizeof(zbx_um_host_t));
	host->hostid = hostid;
	host->refcount = 1;
	zbx_vector_um_macro_create_ext(&host->macros, __config_mem_malloc_func, __config_mem_realloc_func,
			__config_mem_free_func);
	zbx_vector_uint64_create_ext(&host->parentids, __config_mem_malloc_func, __config_mem_realloc_func,
			__config_mem_free_func);
	return host;
}

zbx_um_macro_t	*dc_um_macro_create(zbx_uint64_t macroid, zbx_uint64_t hostid, const char *macro, const char *value,
		unsigned char type)
{
	char		*name = NULL, *context = NULL;
	zbx_um_macro_t	*um_macro;

	if (SUCCEED != zbx_user_macro_parse_dyn(macro, &name, &context, NULL))
		return NULL;

	um_macro = (zbx_um_macro_t *)__config_mem_malloc_func(NULL, sizeof(zbx_um_macro_t));
	um_macro->macroid = macroid;
	um_macro->hostid = hostid;
	um_macro->name = zbx_strpool_intern(name);
	um_macro->context = (NULL != context ? zbx_strpool_intern(context) : NULL);
	um_macro->value = zbx_strpool_intern(value);
	um_macro->type = type;
	um_macro->refcount = 1;

	zbx_free(name);
	zbx_free(context);

	return um_macro;
}

zbx_um_manager_t	*dc_um_manager_copy(zbx_um_manager_t *src)
{
	zbx_um_manager_t	*dst;
	zbx_hashset_iter_t	iter;
	zbx_um_host_t		**phost;

	dst = dc_um_manager_create(src->hosts.num_data);

	zbx_hashset_iter_reset(&src->hosts, &iter);
	while (NULL != (phost = (zbx_um_host_t **)zbx_hashset_iter_next(&iter)))
	{
		int		i;
		zbx_um_host_t	*host = *phost;

		host->refcount++;
		for (i = 0; i < host->macros.values_num; i++)
			host->macros.values[i]->refcount++;
		zbx_hashset_insert(&dst->hosts, &host, sizeof(host));
	}

	dc_um_manager_release(src);

	return dst;
}

static int	um_macro_compare(const void *d1, const void *d2)
{
	const zbx_um_macro_t	*m1 = *(const zbx_um_macro_t **)d1;
	const zbx_um_macro_t	*m2 = *(const zbx_um_macro_t **)d2;
	int			ret;

	ret = strcmp(m1->name, m2->name);
	if (0 != ret)
		return ret;

	if (NULL == m1->context)
		return (NULL == m2->context ? 0 : -1);
	if (NULL == m2->context)
		return 1;

	return strcmp(m1->context, m2->context);
}

static zbx_um_host_t	*dc_um_update_add_host(zbx_hashset_t *updates, zbx_uint64_t hostid)
{
	zbx_um_host_update_t	update_local;
	zbx_um_host_t		*host;

	host = dc_um_host_create(hostid);
	update_local.hostid = hostid;
	update_local.host = host;
	zbx_hashset_insert(updates, &update_local, sizeof(update_local));

	return host;
}

static void	dc_um_sync_prepare(zbx_dbsync_t *sync, zbx_hashset_t *index, zbx_hashset_t *updates,
		zbx_vector_uint64_t *skip)
{
	char			**row;
	zbx_uint64_t		rowid, hostid;
	unsigned char		tag;
	int			ret, i;
	zbx_um_host_t		*host = NULL;
	zbx_um_macro_t		*macro, **pmacro;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* Set the correct macro field index in result set for global and host macros. */
	/* Global macros does not have hostid field, so the offset is less by one and  */
	/* hostid is always 0.                                                         */
	if (5 != sync->columns_num)
	{
		hostid = 0;
		i = 1;
	}
	else
		i = 2;

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		if (2 == i)
			ZBX_STR2UINT64(hostid, row[1]);

		if (NULL == (macro = dc_um_macro_create(rowid, hostid, row[i], row[i + 1], atoi(row[i + 2]))))
			continue;

		if (NULL == host || hostid != host->hostid)
			host = dc_um_update_add_host(updates, hostid);

		zbx_vector_um_macro_append(&host->macros, macro);
		pmacro = (zbx_um_macro_t **)zbx_hashset_insert(index, &macro, sizeof(macro));
		*pmacro = macro;

		if (ZBX_DBSYNC_ROW_UPDATE == tag)
			zbx_vector_uint64_append(skip, rowid);
	}

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		zbx_uint64_t	*prowid = &rowid;

		zbx_vector_uint64_append(skip, rowid);
		if (NULL == (pmacro = (zbx_um_macro_t **)zbx_hashset_search(index, &prowid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		if (NULL == (zbx_um_host_update_t *)zbx_hashset_search(updates, &(*pmacro)->hostid))
			dc_um_update_add_host(updates, hostid);

		zbx_hashset_remove_direct(index, pmacro);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	dc_um_sync_update(zbx_um_manager_t *manager, zbx_hashset_t *updates, zbx_vector_uint64_t *skip)
{
	zbx_hashset_iter_t	iter;
	zbx_um_host_update_t	*update;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_iter_reset(updates, &iter);
	while (NULL != (update = (zbx_um_host_update_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_uint64_t	*phostid = &update->hostid;
		zbx_um_host_t	*host, **phost;

		if (NULL != (phost = (zbx_um_host_t **)zbx_hashset_search(&manager->hosts, &phostid)))
		{
			int	i;

			/* copy over macros to the new host excluding updated/deleted macros */
			host = *phost;
			for (i = 0; i < host->macros.values_num; i++)
			{
				zbx_um_macro_t *macro = host->macros.values[i];
				if (FAIL != zbx_vector_uint64_bsearch(skip, macro->macroid,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC))
				{
					continue;
				}
				zbx_vector_um_macro_append(&update->host->macros, macro);
				macro->refcount++;
			}

			/* copy over parent references */
			zbx_vector_uint64_append_array(&update->host->parentids, host->parentids.values,
					host->parentids.values_num);

			/* release the old host */
			if (0 == update->host->macros.values_num)
				zbx_hashset_remove_direct(&manager->hosts, phost);
			dc_um_host_release(host);
		}
		if (0 != update->host->macros.values_num)
		{
			zbx_vector_um_macro_sort(&update->host->macros, um_macro_compare);
			if (NULL != phost)
				*phost = update->host;
			else
				zbx_hashset_insert(&manager->hosts, &update->host, sizeof(update->host));
		}
		else
			dc_um_host_release(update->host);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

}
static zbx_um_host_t	*dc_um_host_copy(const zbx_um_host_t *src)
{
	zbx_um_host_t	*dst;
	int		i;

	dst = dc_um_host_create(src->hostid);
	dst->refcount = 1;
	zbx_vector_um_macro_append_array(&dst->macros, src->macros.values, src->macros.values_num);
	for (i = 0; i < dst->macros.values_num; i++)
		dst->macros.values[i]->refcount++;
	zbx_vector_uint64_append_array(&dst->parentids, src->parentids.values, src->parentids.values_num);

	return dst;
}

static void	dc_um_sync_update_parents(zbx_um_manager_t *manager, zbx_dbsync_t *htmpls)
{
	char			**row;
	zbx_uint64_t		rowid, hostid, parentid, *phostid = &hostid;
	unsigned char		tag;
	int			ret, i;
	zbx_um_host_t		**phost, *host;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (SUCCEED == (ret = zbx_dbsync_next(htmpls, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(hostid, row[0]);
		if (NULL != (phost = (zbx_um_host_t **)zbx_hashset_search(&manager->hosts, &phostid)))
		{
			if (1 < (*phost)->refcount)
			{
				host = dc_um_host_copy(*phost);
				*phost = host;
			}
			else
				host = *phost;
		}
		else
		{
			host = dc_um_host_create(hostid);
			zbx_hashset_insert(&manager->hosts, &host, sizeof(host));
		}

		ZBX_STR2UINT64(parentid, row[1]);
		zbx_vector_uint64_append(&host->parentids, parentid);
	}

	for (; SUCCEED == ret; ret = zbx_dbsync_next(htmpls, &rowid, &row, &tag))
	{
		ZBX_STR2UINT64(hostid, row[0]);
		if (NULL == (phost = (zbx_um_host_t **)zbx_hashset_search(&manager->hosts, &phostid)))
			continue;

		host = *phost;
		ZBX_STR2UINT64(parentid, row[1]);
		if (FAIL != (i = zbx_vector_uint64_search(&host->parentids, parentid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			zbx_vector_uint64_remove_noorder(&host->parentids, i);

		if (0 == host->parentids.values_num && 0 == host->macros.values_num)
		{
			zbx_hashset_remove_direct(&manager->hosts, phost);
			dc_um_host_release(host);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

zbx_um_manager_t	*zbx_dc_um_sync(zbx_um_manager_t *manager, zbx_dbsync_t *gmacros, zbx_dbsync_t *hmacros,
		zbx_dbsync_t *htmpls)
{
	zbx_hashset_t		updates;
	zbx_vector_uint64_t	skip;
	int			updates_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&skip);

	if (ZBX_DBSYNC_UPDATE == gmacros->mode)
		updates_num = gmacros->rows.values_num;
	if (ZBX_DBSYNC_UPDATE == hmacros->mode)
		updates_num = hmacros->rows.values_num;

	if (0 == updates_num)
		updates_num = 100;
	else
		zbx_vector_uint64_reserve(&skip, updates_num);

	zbx_hashset_create(&updates, updates_num, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (NULL == manager)
		manager = dc_um_manager_create(100);

	if (1 != manager->refcount)
		manager = dc_um_manager_copy(manager);

	dc_um_sync_prepare(hmacros, &config->host_macros, &updates, &skip);
	dc_um_sync_prepare(gmacros, &config->global_macros, &updates, &skip);

	zbx_vector_uint64_sort(&skip, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* go through updates, copy macros from old hosts and replace old hosts */
	dc_um_sync_update(manager, &updates, &skip);

	/* update host parent links */
	dc_um_sync_update_parents(manager, htmpls);

	zbx_vector_uint64_destroy(&skip);
	zbx_hashset_destroy(&updates);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return manager;
}

static void	dc_um_get_macro(zbx_um_manager_t *manager, const zbx_uint64_t *hostids, int hostids_num,
		const char *name, const char *context, zbx_um_macro_t **macro, zbx_um_macro_t **default_macro)
{
	int			i, j;
	zbx_vector_uint64_t	parentids;
	zbx_um_host_t		*host, **phost;

	zbx_vector_uint64_create(&parentids);
	zbx_vector_uint64_reserve(&parentids, 32);

	for (i = 0; i < hostids_num; i++)
	{
		const zbx_uint64_t	*phostid = &hostids[i];
		if (NULL == (phost = (zbx_um_host_t **)zbx_hashset_search(&manager->hosts, &phostid)))
			continue;
		//host = *phost;
		host = NULL;

		for (j = 0; j < host->macros.values_num; j++)
		{
			zbx_um_macro_t	*m = host->macros.values[j];

			if (0 == strcmp(m->name, name))
			{
				if (NULL == m->context && NULL == *default_macro)
					*default_macro = m;

				if (0 == zbx_strcmp_null(m->context, context))
				{
					*macro = m;
					return ;
				}
			}
		}
		zbx_vector_uint64_append_array(&parentids, host->parentids.values, host->parentids.values_num);
	}

	if (0 != parentids.values_num)
	{
		zbx_vector_uint64_sort(&parentids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&parentids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		dc_um_get_macro(manager, parentids.values, parentids.values_num, name, context, macro, default_macro);
	}

	zbx_vector_uint64_destroy(&parentids);
}

int	zbx_dc_um_get_macro_value(zbx_um_manager_t *manager, const zbx_uint64_t *hostids, int hostids_num,
		const char *macro, char **value)
{
	char		*name = NULL, *context = NULL;
	zbx_um_macro_t	*um_macro = NULL, *um_default = NULL;

	if (SUCCEED != zbx_user_macro_parse_dyn(macro, &name, &context, NULL))
		return FAIL;

	dc_um_get_macro(manager, hostids, hostids_num, name, context, &um_macro, &um_default);
	if (NULL == um_macro)
	{
		zbx_uint64_t	globalid = 0;
		dc_um_get_macro(manager, &globalid, 1, name, context, &um_macro, &um_default);
	}

	zbx_free(name);
	zbx_free(context);

	if (NULL == um_macro)
		um_macro = um_default;

	if (NULL != um_macro)
		*value = zbx_strdup(*value, um_macro->value);
}

// WDN: debug
static void	dc_um_dump_manager(zbx_um_manager_t *manager)
{
	zbx_hashset_iter_t	iter;
	zbx_um_host_t		*host, **phost;
	zbx_um_macro_t		*macro;

	printf("refcount:%d, hosts:%d\n", manager->refcount, manager->hosts.num_data);

	zbx_hashset_iter_reset(&manager->hosts, &iter);
	while (NULL != (phost = (zbx_um_host_t **)zbx_hashset_iter_next(&iter)))
	{
		int	i;

		host = *phost;
		printf("    host:" ZBX_FS_UI64 ", refcount:%d macros:%d\n", host->hostid,
				host->refcount, host->macros.values_num);

		for (i = 0; i < host->macros.values_num; i++)
		{
			macro = host->macros.values[i];
			printf("        macro:" ZBX_FS_UI64 ", %s(%s):%s refcount:%d type:%d\n",
					macro->macroid, macro->name, ZBX_NULL2EMPTY_STR(macro->context), macro->value,
					macro->refcount, macro->type);
		}
	}
}

static void	dc_um_dump_config()
{
	zbx_hashset_iter_t	iter;
	zbx_um_macro_t		*macro, **pmacro;

	printf("globalmacro\n");
	zbx_hashset_iter_reset(&config->global_macros, &iter);
	while (NULL != (pmacro = (zbx_um_macro_t **)zbx_hashset_iter_next(&iter)))
	{
		macro = *pmacro;
		printf("    " ZBX_FS_UI64 " %s(%s):%s refcount:%d type:%d\n",
					macro->macroid, macro->name, ZBX_NULL2EMPTY_STR(macro->context),
					macro->value, macro->refcount, macro->type);
	}

	printf("hostmacro\n");
	zbx_hashset_iter_reset(&config->host_macros, &iter);
	while (NULL != (pmacro = (zbx_um_macro_t **)zbx_hashset_iter_next(&iter)))
	{
		macro = *pmacro;
		printf("    " ZBX_FS_UI64 " host:" ZBX_FS_UI64 " %s(%s):%s refcount:%d type:%d\n",
					macro->macroid, macro->hostid, macro->name, ZBX_NULL2EMPTY_STR(macro->context),
					macro->value, macro->refcount, macro->type);
	}
}

static void	dc_um_dump_host(zbx_um_manager_t *manager, zbx_um_host_t *host, int level, zbx_hashset_t *hostids)
{
	char		indent[128];
	int		i;
	zbx_um_host_t	**phost;

	memset(indent, ' ', level * 2);
	indent[level * 2] = '\0';
	printf("%s[" ZBX_FS_UI64 "]\n", indent, host->hostid);
	zbx_hashset_insert(hostids, &host->hostid, sizeof(host->hostid));

	for (i = 0; i < host->parentids.values_num; i++)
	{
		zbx_uint64_t	*phostid = &host->parentids.values[i];
		if (NULL != (phost = (zbx_um_host_t **)zbx_hashset_search(&manager->hosts, &phostid)))
			dc_um_dump_host(manager, *phost, level + 1, hostids);
	}
}

static void	dc_um_dump_tree(zbx_um_manager_t *manager)
{
	zbx_hashset_iter_t	iter;
	zbx_um_host_t		**phost;
	zbx_hashset_t		hostids;

	zbx_hashset_create(&hostids, manager->hosts.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_iter_reset(&manager->hosts, &iter);
	while (NULL != (phost = (zbx_um_host_t **)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&hostids, &(*phost)->hostid))
			dc_um_dump_host(manager, *phost, 0, &hostids);
	}

	zbx_hashset_destroy(&hostids);
}

void	um_dbsync_clear(zbx_dbsync_t *sync)
{
	/* free the resources allocated by row pre-processing */
	zbx_vector_ptr_clear_ext(&sync->columns, zbx_ptr_free);
	zbx_vector_ptr_destroy(&sync->columns);

	zbx_free(sync->row);

	if (ZBX_DBSYNC_UPDATE == sync->mode)
	{
		int			i, j;
		zbx_dbsync_row_t	*row;

		for (i = 0; i < sync->rows.values_num; i++)
		{
			row = (zbx_dbsync_row_t *)sync->rows.values[i];

			if (NULL != row->row)
			{
				for (j = 0; j < sync->columns_num; j++)
					zbx_free(row->row[j]);

				zbx_free(row->row);
			}

			zbx_free(row);
		}

		zbx_vector_ptr_destroy(&sync->rows);
	}
	else
	{
		DBfree_result(sync->dbresult);
		sync->dbresult = NULL;
	}
}


static void	um_dbsync_add_host_row(zbx_dbsync_t *sync, unsigned char tag, const char *macroid, const char *hostid,
		const char *macro, const char *value, const char *type)
{
	zbx_dbsync_row_t	*row;
	zbx_uint64_t		rowid;

	sync->columns_num = 5;

	ZBX_DBROW2UINT64(rowid, macroid);
	row = (zbx_dbsync_row_t *)zbx_malloc(NULL, sizeof(zbx_dbsync_row_t));
	row->rowid = rowid;
	row->tag = tag;

	row->row = (char **)zbx_malloc(NULL, sizeof(char *) * sync->columns_num);
	row->row[0] = zbx_strdup(NULL, macroid);
	row->row[1] = zbx_strdup(NULL, hostid);
	row->row[2] = zbx_strdup(NULL, macro);
	row->row[3] = zbx_strdup(NULL, value);
	row->row[4] = zbx_strdup(NULL, type);

	zbx_vector_ptr_append(&sync->rows, row);

	switch (tag)
	{
		case ZBX_DBSYNC_ROW_ADD:
			sync->add_num++;
			break;
		case ZBX_DBSYNC_ROW_UPDATE:
			sync->update_num++;
			break;
		case ZBX_DBSYNC_ROW_REMOVE:
			sync->remove_num++;
			break;
	}
}

static void	um_dbsync_add_htmpl_row(zbx_dbsync_t *sync, unsigned char tag, const char *hostid, const char *parentid)
{
	zbx_dbsync_row_t	*row;

	sync->columns_num = 2;

	row = (zbx_dbsync_row_t *)zbx_malloc(NULL, sizeof(zbx_dbsync_row_t));
	row->rowid = 0;
	row->tag = tag;

	row->row = (char **)zbx_malloc(NULL, sizeof(char *) * sync->columns_num);
	row->row[0] = zbx_strdup(NULL, hostid);
	row->row[1] = zbx_strdup(NULL, parentid);

	zbx_vector_ptr_append(&sync->rows, row);

	switch (tag)
	{
		case ZBX_DBSYNC_ROW_ADD:
			sync->add_num++;
			break;
		case ZBX_DBSYNC_ROW_UPDATE:
			sync->update_num++;
			break;
		case ZBX_DBSYNC_ROW_REMOVE:
			sync->remove_num++;
			break;
	}
}

static void	um_dbsync_add_global_row(zbx_dbsync_t *sync, unsigned char tag, const char *macroid,
		const char *macro, const char *value, const char *type)
{
	zbx_dbsync_row_t	*row;
	zbx_uint64_t		rowid;

	sync->columns_num = 4;

	ZBX_DBROW2UINT64(rowid, macroid);
	row = (zbx_dbsync_row_t *)zbx_malloc(NULL, sizeof(zbx_dbsync_row_t));
	row->rowid = rowid;
	row->tag = tag;

	row->row = (char **)zbx_malloc(NULL, sizeof(char *) * sync->columns_num);
	row->row[0] = zbx_strdup(NULL, macroid);
	row->row[1] = zbx_strdup(NULL, macro);
	row->row[2] = zbx_strdup(NULL, value);
	row->row[3] = zbx_strdup(NULL, type);

	zbx_vector_ptr_append(&sync->rows, row);

	switch (tag)
	{
		case ZBX_DBSYNC_ROW_ADD:
			sync->add_num++;
			break;
		case ZBX_DBSYNC_ROW_UPDATE:
			sync->update_num++;
			break;
		case ZBX_DBSYNC_ROW_REMOVE:
			sync->remove_num++;
			break;
	}
}

#define HOSTS_MAX	1000000
#define MACROS_MAX	10

static void	prepare_hmacro_sync(zbx_dbsync_t *sync, int hosts, int tag, const char *value_prefix)
{
	int	i, j, id;
	char	hostid[64], macroid[64], macro[64], value[64];

	for (i = 0; i < hosts; i++)
	{
		zbx_snprintf(hostid, sizeof(hostid), "%d", 1000000 + i);

		for (j = 0; j < MACROS_MAX; j++)
		{
			id = (i * MACROS_MAX) + j + 1;
			zbx_snprintf(macroid, sizeof(macroid), "%d", id);
			zbx_snprintf(macro, sizeof(macro), "{$M%d}", id);
			zbx_snprintf(value, sizeof(value), "%s%d", value_prefix, id);

			um_dbsync_add_host_row(sync, tag, macroid, hostid, macro, value, "0");
		}
	}
}

static void	check_macro(zbx_um_manager_t *manager, zbx_uint64_t hostid, const char *macro)
{
	char	*value = NULL;

	zbx_dc_um_get_macro_value(manager, &hostid, 1, macro, &value);

	if (NULL != value)
		zabbix_log(LOG_LEVEL_INFORMATION, ZBX_FS_UI64 ":%s = %s", hostid, macro, value);
	else
		zabbix_log(LOG_LEVEL_INFORMATION, ZBX_FS_UI64 ":%s not found!", hostid, macro);

	zbx_free(value);
}

#include <valgrind/callgrind.h>

void	sync_macros(zbx_dbsync_t *gmacros, zbx_dbsync_t *hmacros, zbx_dbsync_t *htmpls);

//#define ORIGINAL
//#define BENCHMARK

void	test_macros()
{
	zbx_dbsync_t		hmacros, gmacros, htmpls;
	zbx_um_manager_t	*manager1, *manager2, *manager3;
	struct timeval		ss;
	zbx_uint64_t		mem, mem1, memdiff;
	int			msec;

	zbx_dbsync_init(&hmacros, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&gmacros, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&htmpls, ZBX_DBSYNC_UPDATE);

#ifdef BENCHMARK
	prepare_hmacro_sync(&hmacros, HOSTS_MAX, ZBX_DBSYNC_ROW_ADD, "#");

	//manager1 = zbx_dc_um_sync(NULL, &gmacros, &hmacros, &htmpls);
#ifdef ORIGINAL
	zabbix_log(LOG_LEVEL_INFORMATION, "Original:");

	mem1 = mem = config_mem->free_size;
	snapshot_start(&ss);
	sync_macros(&gmacros, &hmacros, &htmpls);
	msec = snapshot_end(&ss);
	zabbix_log(LOG_LEVEL_INFORMATION, "\thmacros:%d gmacros:%d htmpls:%d",
			config->hmacros.num_data, config->gmacros.num_data, config->htmpls.num_data);

	memdiff = (mem > config_mem->free_size ? mem - config_mem->free_size : 0) / 1024;
	zabbix_log(LOG_LEVEL_INFORMATION, "\tinitial sync: %.3f (" ZBX_FS_UI64 " kb)",
			(double)msec / 1000, memdiff);



	um_dbsync_clear(&hmacros);
	um_dbsync_clear(&gmacros);
	um_dbsync_clear(&htmpls);

	zbx_dbsync_init(&hmacros, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&gmacros, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&htmpls, ZBX_DBSYNC_UPDATE);

	prepare_hmacro_sync(&hmacros, 100, ZBX_DBSYNC_ROW_UPDATE, "@");
	mem = config_mem->free_size;

	snapshot_start(&ss);
	sync_macros(&gmacros, &hmacros, &htmpls);
	memdiff = (mem > config_mem->free_size ? mem - config_mem->free_size : 0) / 1024;
	zabbix_log(LOG_LEVEL_INFORMATION, "\tupdate (100): %.3f (" ZBX_FS_UI64 " kb)",
			(double)snapshot_end(&ss) / 1000, memdiff);

	zabbix_log(LOG_LEVEL_INFORMATION, "\t(" ZBX_FS_UI64 " kb)",(mem1 - config_mem->free_size) / 1024);


#else
	zabbix_log(LOG_LEVEL_INFORMATION, "Macro manager:");

	mem = config_mem->free_size;
	snapshot_start(&ss);
	manager1 = zbx_dc_um_sync(NULL, &gmacros, &hmacros, &htmpls);
	msec = snapshot_end(&ss);
	zabbix_log(LOG_LEVEL_INFORMATION, "\thmacros:%d gmacros:%d",
			config->host_macros.num_data, config->global_macros.num_data);

	memdiff = (mem > config_mem->free_size ? mem - config_mem->free_size : 0) / 1024;

	zabbix_log(LOG_LEVEL_INFORMATION, "\tinitial sync: %.3f (" ZBX_FS_UI64 " kb)",
			(double)msec / 1000, memdiff);

	um_dbsync_clear(&hmacros);
	um_dbsync_clear(&gmacros);
	um_dbsync_clear(&htmpls);

	zbx_dbsync_init(&hmacros, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&gmacros, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&htmpls, ZBX_DBSYNC_UPDATE);

	prepare_hmacro_sync(&hmacros, 100, ZBX_DBSYNC_ROW_UPDATE, "@");
	mem = config_mem->free_size;

	snapshot_start(&ss);
	manager2 = zbx_dc_um_sync(manager1, &gmacros, &hmacros, &htmpls);
	memdiff = (mem > config_mem->free_size ? mem - config_mem->free_size : 0) / 1024;
	zabbix_log(LOG_LEVEL_INFORMATION, "\tupdate (100): %.3f (" ZBX_FS_UI64 " kb)",
			(double)snapshot_end(&ss) / 1000, memdiff);

	um_dbsync_clear(&hmacros);
	um_dbsync_clear(&gmacros);
	um_dbsync_clear(&htmpls);

	zbx_dbsync_init(&hmacros, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&gmacros, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&htmpls, ZBX_DBSYNC_UPDATE);

	prepare_hmacro_sync(&hmacros, 100, ZBX_DBSYNC_ROW_UPDATE, "$");
	mem = config_mem->free_size;
	manager2->refcount++;

	snapshot_start(&ss);
	manager3 = zbx_dc_um_sync(manager2, &gmacros, &hmacros, &htmpls);
	memdiff = (mem > config_mem->free_size ? mem - config_mem->free_size : 0) / 1024;
	zabbix_log(LOG_LEVEL_INFORMATION, "\tupdate (100): %.3f (" ZBX_FS_UI64 " kb)",
			(double)snapshot_end(&ss) / 1000, memdiff);

	um_dbsync_clear(&hmacros);
	um_dbsync_clear(&gmacros);
	um_dbsync_clear(&htmpls);

#endif
#endif

	um_dbsync_add_global_row(&gmacros, ZBX_DBSYNC_ROW_ADD, "1", "{$BASE}", "base", "0");

	um_dbsync_add_host_row(&hmacros, ZBX_DBSYNC_ROW_ADD, "2", "1000", "{$BASE:context}", "context", "0");
	um_dbsync_add_htmpl_row(&htmpls, ZBX_DBSYNC_ROW_ADD, "1001", "1000");

	manager1 = zbx_dc_um_sync(NULL, &gmacros, &hmacros, &htmpls);

	check_macro(manager1, 1000, "{$BASE:context}");
	check_macro(manager1, 1000, "{$BASE:unknown}");

	check_macro(manager1, 1001, "{$BASE:context}");
	check_macro(manager1, 1001, "{$BASE:unknown}");


	/*
	manager2 = zbx_dc_um_sync(manager1, &gmacros, &hmacros, &htmpls);

	um_dbsync_clear(&hmacros);
	um_dbsync_clear(&gmacros);
	um_dbsync_clear(&htmpls);

	zbx_dbsync_init(&hmacros, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&gmacros, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&htmpls, ZBX_DBSYNC_UPDATE);

	prepare_hmacro_sync(&hmacros, 4, ZBX_DBSYNC_ROW_UPDATE, "$");

	manager2->refcount++;
	manager3 = zbx_dc_um_sync(manager2, &gmacros, &hmacros, &htmpls);

	dc_um_dump_manager(manager3);
	dc_um_dump_config();
	dc_um_dump_tree(manager3);

	dc_um_manager_release(manager3);
	dc_um_manager_release(manager2);

	manager1 = NULL;
	manager2 = NULL;
	manager3 = NULL;

	*/

	exit(0);
}


