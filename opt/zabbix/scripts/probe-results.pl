#!/usr/bin/perl

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use RSM;
use RSMSLV;

unless ($ARGV[0] && $ARGV[1] && $ARGV[2] && $ARGV[3])
{
	print("usage: $0 <tld> <probe> <from> <till>\n");
	exit(1);
}

parse_opts();

setopt('nolog');
setopt('dry-run');

set_slv_config(get_rsm_config());

db_connect();

my $tld = $ARGV[0];
my $probe = $ARGV[1];
my $from = $ARGV[2];
my $till = $ARGV[3];

my $host = "$tld $probe";

print("Test results:\n");

my $rows_ref = db_select(
    "select h.itemid,h.clock,h.value,i2.key_".
    " from history_uint h, items i2".
    " where i2.itemid=h.itemid".
        " and i2.itemid in".
            " (select i3.itemid".
            " from items i3,hosts ho".
            " where i3.hostid=ho.hostid".
                " and i3.key_ not like 'probe.configvalue%'".
                " and ho.host='$host')".
        " and h.clock between $from and $till".
        " order by h.clock,i2.key_");

foreach my $row_ref (@$rows_ref)
{
    my $itemid = $row_ref->[0];
    my $clock = $row_ref->[1];
    my $value = $row_ref->[2];
    my $key = $row_ref->[3];

    print(ts_full($clock), "\t$key\t$value\n");
}

print("Tests:\n");
$rows_ref = db_select(
    "select h.itemid,h.clock,h.value,i2.key_".
    " from history h, items i2".
    " where i2.itemid=h.itemid".
        " and i2.itemid in".
            " (select i3.itemid".
            " from items i3,hosts ho".
            " where i3.hostid=ho.hostid".
                " and i3.key_ not like 'probe.configvalue%'".
                " and ho.host='$host')".
        " and h.clock between $from and $till".
        " order by h.clock,i2.key_");

foreach my $row_ref (@$rows_ref)
{   
    my $itemid = $row_ref->[0];
    my $clock = $row_ref->[1];
    my $value = $row_ref->[2];
    my $key = $row_ref->[3];

    print(ts_full($clock), "\t$key\t$value\n");
}

