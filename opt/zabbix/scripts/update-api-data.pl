#!/usr/bin/perl -w

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use RSM;
use RSMSLV;
use ApiHelper;
use Parallel;
use JSON::XS;
use Data::Dumper;
use Time::HiRes qw(time);

use constant MAX_PIDS => 64;

use constant JSON_RDDS_SUBSERVICE => 'subService';
use constant JSON_RDDS_43 => 'RDDS43';
use constant JSON_RDDS_80 => 'RDDS80';

use constant PROBE_OFFLINE_STR => 'Offline';
use constant PROBE_NORESULT_STR => 'No result';

use constant AUDIT_RESOURCE_INCIDENT => 32;

parse_opts('tld=s', 'service=s', 'period=n', 'from=n', 'continue!', 'ignore-file=s', 'probe=s', 'limit=n');

exit_if_running();

if (opt('debug'))
{
	dbg("command-line parameters:");
	dbg("$_ => ", getopt($_)) foreach (optkeys());
}

my $sec;
if (opt('stats'))
{
	$sec = time();
}

set_slv_config(get_rsm_config());

db_connect();

__validate_input();

my $opt_from = getopt('from');

if (defined($opt_from))
{
	$opt_from = truncate_from($opt_from);	# use the whole minute
	dbg("option \"from\" truncated to the start of a minute: $opt_from") if ($opt_from != getopt('from'));
}

my @services;
if (opt('service'))
{
	push(@services, lc(getopt('service')));
}
else
{
	push(@services, 'dns', 'dnssec', 'rdds', 'epp');
}

my %ignore_hash;

if (opt('ignore-file'))
{
	my $ignore_file = getopt('ignore-file');

	my $handle;
	fail("cannot open ignore file \"$ignore_file\": $!") unless open($handle, '<', $ignore_file);

	chomp(my @lines = <$handle>);

	close($handle);

	%ignore_hash = map { $_ => 1 } @lines;
}

my $cfg_dns_delay;
my $cfg_dns_valuemaps;
my $cfg_dns_minns;
my $cfg_dns_key_status;
my $cfg_dns_key_rtt;

my $cfg_rdds_delay;
my $cfg_rdds_valuemaps;
my $cfg_rdds_key_status;
my $cfg_rdds_key_43_rtt;
my $cfg_rdds_key_43_ip;
my $cfg_rdds_key_43_upd;
my $cfg_rdds_key_80_rtt;
my $cfg_rdds_key_80_ip;

my $cfg_epp_delay;
my $cfg_epp_valuemaps;
my $cfg_epp_key_status;
my $cfg_epp_key_ip;
my $cfg_epp_key_rtt;

my $cfg_dns_statusmaps = get_statusmaps('dns');

my %services_hash = map { $_ => 1 } @services;

if (exists($services_hash{'dns'}) or exists($services_hash{'dnssec'}))
{
	$cfg_dns_delay = get_macro_dns_udp_delay();
	$cfg_dns_minns = get_macro_minns();
	$cfg_dns_valuemaps = get_valuemaps('dns');
	$cfg_dns_key_status = 'rsm.dns.udp[{$RSM.TLD}]'; # 0 - down, 1 - up
	$cfg_dns_key_rtt = 'rsm.dns.udp.rtt[{$RSM.TLD},';
}
if (exists($services_hash{'rdds'}))
{
	$cfg_rdds_delay = get_macro_rdds_delay();
	$cfg_rdds_valuemaps = get_valuemaps('rdds');
	$cfg_rdds_key_status = 'rsm.rdds[{$RSM.TLD}'; # 0 - down, 1 - up, 2 - only 43, 3 - only 80
	$cfg_rdds_key_43_rtt = 'rsm.rdds.43.rtt[{$RSM.TLD}]';
	$cfg_rdds_key_43_ip = 'rsm.rdds.43.ip[{$RSM.TLD}]';
	$cfg_rdds_key_43_upd = 'rsm.rdds.43.upd[{$RSM.TLD}]';
	$cfg_rdds_key_80_rtt = 'rsm.rdds.80.rtt[{$RSM.TLD}]';
	$cfg_rdds_key_80_ip = 'rsm.rdds.80.ip[{$RSM.TLD}]';
}
if (exists($services_hash{'epp'}))
{
	$cfg_epp_delay = get_macro_epp_delay();
	$cfg_epp_valuemaps = get_valuemaps('epp');
	$cfg_epp_key_status = 'rsm.epp[{$RSM.TLD},'; # 0 - down, 1 - up
	$cfg_epp_key_ip = 'rsm.epp.ip[{$RSM.TLD}]';
	$cfg_epp_key_rtt = 'rsm.epp.rtt[{$RSM.TLD},';
}

my $now = time();

if (opt('stats'))
{
	printf("stats: config prepare took %s\n", format_stats_time($now - $sec));
	$sec = $now;
}

my $tlds_ref;
if (opt('tld'))
{
	fail("TLD ", getopt('tld'), " does not exist.") if (tld_exists(getopt('tld')) == 0);

	my $tld = getopt('tld');
	my $tld_hostid = get_hostid($tld);

	$tlds_ref->{$tld} = $tld_hostid;
}
else
{
	$tlds_ref = get_tlds();
}

my $servicedata;	# hash with various data of TLD service

my $probe_avail_limit = get_macro_probe_avail_limit();

# Get the minimum clock from the item that is collected once a day, this way
# "min(clock)" won't take too much time (see function __get_min_clock() for details)
my $rows_ref = db_select("select itemid from items where key_='rsm.configvalue[RSM.SLV.DNS.TCP.RTT]'");
my $config_itemid = $rows_ref->[0]->[0];
$rows_ref = db_select("select min(clock) from history_uint where itemid=$config_itemid");
my $config_minclock = $rows_ref->[0]->[0];

dbg("config_minclock:$config_minclock");

my $probes_from;
my $probes_till;

my $tlds_processed = 0;
foreach (keys(%$tlds_ref))
{
	last if (opt('limit') && $tlds_processed == getopt('limit'));

	$tlds_processed++;

	# NB! This is needed in order to set the value globally.
	$tld = $_;

	if (__tld_ignored($tld) == SUCCESS)
	{
		dbg("tld \"$tld\" found in IGNORE list");
		next;
	}

	my $ah_tld = ah_get_api_tld($tld);

	foreach my $service (@services)
	{
		if (tld_service_enabled($tld, $service) != SUCCESS)
		{
			if (opt('dry-run'))
			{
				__prnt(uc($service), " DISABLED");
			}
			else
			{
				if (ah_save_alarmed($ah_tld, $service, AH_ALARMED_DISABLED) != AH_SUCCESS)
				{
					fail("cannot save alarmed: ", ah_get_error());
				}
			}

			next;
		}

		my $lastclock_key = "rsm.slv.$service.rollweek";

		my $lastclock = get_lastclock($tld, $lastclock_key);

		if ($lastclock == E_FAIL)
		{
			wrn(uc($service), ": configuration error, item $lastclock_key not found");
			next;
		}

		if ($lastclock == 0)
		{
			wrn(uc($service), ": no rolling week data in the database yet");
			next;
		}

		dbg("lastclock:$lastclock");

		my ($from, $till, $continue_file);

		if (opt('continue'))
		{
			$continue_file = ah_get_continue_file($ah_tld, $service);
			my $handle;

			if (! -e $continue_file)
			{
				$from = truncate_from(__get_min_clock($tld, $service, $config_minclock));
			}
			else
			{
				fail("cannot open continue file $continue_file\": $!") unless (open($handle, '<', $continue_file));

				chomp(my @lines = <$handle>);

				close($handle);

				$from = $lines[0];
			}

			if ($from == 0)
			{
				wrn(uc($service), ": no data from probes in the database yet");
				next;
			}

			if (opt('period'))
			{
				$till = $from + getopt('period') * 60 - 1;
			}
			else
			{
				$till = $lastclock + RESULT_TIMESTAMP_SHIFT;	# include the whole minute

			}
		}
		elsif (opt('from'))
		{
			$from = $opt_from;

			if (opt('period'))
			{
				$till = $from + getopt('period') * 60 - 1;
			}
			else
			{
				$till = $lastclock + RESULT_TIMESTAMP_SHIFT;	# include the whole minute
			}
		}
		elsif (opt('period'))
		{
			# only period specified
			$till = $lastclock + RESULT_TIMESTAMP_SHIFT;	# include the whole minute
			$from = $till - getopt('period') * 60 + 1;
		}

		if ($from and ((!$probes_from) or ($from < $probes_from)))
		{
			$probes_from = $from;
		}

		if ($till and ((!$probes_till) or ($till > $probes_till)))
		{
			$probes_till = $till;
		}

		# NB! This check must be done after setting $probes_from and $probes_till, otherwise
		# calculation of probe status information may be done for the period up till now.
		if ((defined($from) and $from > $lastclock))
		{
			wrn(uc($service), ": time period (" . __selected_period($from, $till) . ")". " is in the future from the latest data available (" . ts_str($lastclock) . ")");
			next;
		}

		$servicedata->{$tld}->{'hostid'} = $tlds_ref->{$tld};
		$servicedata->{$tld}->{$service}->{'from'} = $from;
		$servicedata->{$tld}->{$service}->{'till'} = $till;
		$servicedata->{$tld}->{$service}->{'lastclock'} = $lastclock;
		$servicedata->{$tld}->{$service}->{'continue_file'} = $continue_file;
	}
}

fail("cannot calculate beginning of the period") unless(defined($probes_from));
fail("cannot calculate end of the period") unless(defined($probes_till));

my $sec_avail_keys;
if (opt('stats'))
{
	$sec_avail_keys = time();
}

my @a = sort(values(%$servicedata));
my @h = map {$_->{'hostid'}} @a;
my $tld_hostids_str = join(',', @h);

$rows_ref = db_select("select hostid,key_,itemid from items where key_ like 'rsm.slv.%.avail' and hostid in ($tld_hostids_str)");
my $avail_keys;

foreach my $row_ref (@$rows_ref)
{
	my $service = (split(/\./, $row_ref->[1]))[2];

	# TLD hostid and service availability itemid mapping, e. g. hostid->dns->itemid
	$avail_keys->{$row_ref->[0]}->{$service} = $row_ref->[2];
}

if (opt('stats'))
{
	my $t = time();
	printf("stats: avail_keys generation took %s\n", format_stats_time($t - $sec_avail_keys));
	printf("stats: TLD data prepare took %s\n", format_stats_time($t - $sec));
	$sec = $t;
}

my $all_probes_ref = get_probes();

if (opt('probe'))
{
	my $temp = $all_probes_ref;

	undef($all_probes_ref);

	$all_probes_ref->{getopt('probe')} = $temp->{getopt('probe')};
}

my $probe_times_ref = get_probe_times($probes_from, $probes_till, $probe_avail_limit, $all_probes_ref);

if (opt('stats'))
{
	my $t = time();
	printf("stats: probes prepare took %s\n", format_stats_time($t - $sec));
	$sec = $t;
}

my @tlds = sort(keys(%$servicedata));

my $pid_count = MAX_PIDS < scalar(@tlds) ? MAX_PIDS : scalar(@tlds);

start_children($pid_count, \&__parent_exit, SUCCESS);

db_connect();

for (my $i = 0; $i < scalar(@tlds); $i++)
{
	# NB! This is needed in order to set the value globally.
	$tld = $tlds[$i];

	next unless ($i % $pid_count == get_pidnum());

	dbg("child#", get_pidnum(), " started to handle $tld");

	my $ah_tld = ah_get_api_tld($tld);

	my $tld_hostid = $servicedata->{$tld}->{'hostid'};

	foreach my $service (@services)
	{
		my $from = $servicedata->{$tld}->{$service}->{'from'};
		my $till = $servicedata->{$tld}->{$service}->{'till'};
		my $lastclock = $servicedata->{$tld}->{$service}->{'lastclock'};
		my $continue_file = $servicedata->{$tld}->{$service}->{'continue_file'};

		my $avail_key = "rsm.slv.$service.avail";
		#my $avail_itemid = get_itemid_by_hostid($tld_hostid, $avail_key);
		my $avail_itemid = $avail_keys->{$tld_hostid}->{$service};

		if (!$avail_itemid)
		{
			wrn("configuration error: service $service enabled but item \"$avail_key\" not found");
			next;
		}

		# we need down time in minutes, not percent, that's why we can't use "rsm.slv.$service.rollweek" value
		my ($rollweek_from, $rollweek_till) = get_rollweek_bounds();
		my $downtime = get_downtime($avail_itemid, $rollweek_from, $rollweek_till);

		my $service_delay;
		if ($service eq 'dns' or $service eq 'dnssec')
		{
			$service_delay = $cfg_dns_delay;
		}
		elsif ($service eq 'rdds')
		{
			$service_delay = $cfg_rdds_delay;
		}
		elsif ($service eq 'epp')
		{
			$service_delay = $cfg_epp_delay;
		}

		__prnt("period: ", __selected_period($from, $till), " (", uc($service), ")") if (opt('dry-run') or opt('debug'));

		if (opt('dry-run'))
		{
			__prnt(uc($service), " service availability $downtime (", ts_str($lastclock), ")");
		}
		else
		{
			if (ah_save_service_availability($ah_tld, $service, $downtime, $lastclock) != AH_SUCCESS)
			{
				fail("cannot save service availability: ", ah_get_error());
			}
		}

		dbg("getting current $service availability");

		# get availability
		my $incidents = get_incidents($avail_itemid, $now);

		my $alarmed_status = AH_ALARMED_NO;
		if (scalar(@$incidents) != 0)
		{
			if ($incidents->[0]->{'false_positive'} == 0 and not defined($incidents->[0]->{'end'}))
			{
				$alarmed_status = AH_ALARMED_YES;
			}
		}

		if (opt('dry-run'))
		{
			__prnt(uc($service), " alarmed:$alarmed_status");
		}
		else
		{
			if (ah_save_alarmed($ah_tld, $service, $alarmed_status, $lastclock) != AH_SUCCESS)
			{
				fail("cannot save alarmed: ", ah_get_error());
			}
		}

		my ($nsips_ref, $dns_items_ref, $rdds_dbl_items_ref, $rdds_str_items_ref, $epp_dbl_items_ref, $epp_str_items_ref);

		if ($service eq 'dns' or $service eq 'dnssec')
		{
			$nsips_ref = get_nsips($tld, $cfg_dns_key_rtt, 1); # templated
			$dns_items_ref = __get_dns_itemids($nsips_ref, $cfg_dns_key_rtt, $tld, getopt('probe'));
		}
		elsif ($service eq 'rdds')
		{
			$rdds_dbl_items_ref = __get_rdds_dbl_itemids($tld, getopt('probe'));
			$rdds_str_items_ref = __get_rdds_str_itemids($tld, getopt('probe'));
		}
		elsif ($service eq 'epp')
		{
			$epp_dbl_items_ref = __get_epp_dbl_itemids($tld, getopt('probe'));
			$epp_str_items_ref = __get_epp_str_itemids($tld, getopt('probe'));
		}

		$incidents = get_incidents($avail_itemid, $from, $till);

		foreach (@$incidents)
		{
			my $eventid = $_->{'eventid'};
			my $event_start = $_->{'start'};
			my $event_end = $_->{'end'};
			my $false_positive = $_->{'false_positive'};

			my $start = $event_start;
			my $end = $event_end;

			if (defined($from) and $from > $event_start)
			{
				$start = $from;
			}

			if (defined($till))
			{
				if (not defined($event_end) or (defined($event_end) and $till < $event_end))
				{
					$end = $till;
				}
			}

			# get results within incidents
			my $rows_ref = db_select(
				"select value,clock".
				" from history_uint".
				" where itemid=$avail_itemid".
					" and ".sql_time_condition($start, $end).
				" order by clock");

			my @test_results;

			my $status_up = 0;
			my $status_down = 0;

			foreach my $row_ref (@$rows_ref)
			{
				my $value = $row_ref->[0];
				my $clock = $row_ref->[1];

				my $result;

				$result->{'tld'} = $tld;
				$result->{'status'} = get_result_string($cfg_dns_statusmaps, $value);
				$result->{'clock'} = $clock;

				# We have the test resulting value (Up or Down) at "clock". Now we need to select the
				# time bounds (start/end) of all data points from all proxies.
				#
				#   +........................period (service delay)...........................+
				#   |                                                                         |
				# start                                 clock                                end
				#   |.....................................|...................................|
				#   0 seconds <--zero or more minutes--> 30                                  59
				#
				$result->{'start'} = $clock - $service_delay + RESULT_TIMESTAMP_SHIFT + 1; # we need to start at 0
				$result->{'end'} = $clock + RESULT_TIMESTAMP_SHIFT;

				if (opt('dry-run'))
				{
					if ($value == UP)
					{
						$status_up++;
					}
					elsif ($value == DOWN)
					{
						$status_down++;
					}
					else
					{
						wrn("unknown status: $value (expected UP (0) or DOWN (1))");
					}
				}

				push(@test_results, $result);
			}

			my $test_results_count = scalar(@test_results);

			if ($test_results_count == 0)
			{
				wrn("$service: no results within incident (id:$eventid clock:$event_start)");
				last;
			}

			if (opt('dry-run'))
			{
				__prnt(uc($service), " incident id:$eventid start:", ts_str($event_start), " end:" . ($event_end ? ts_str($event_end) : "ACTIVE") . " fp:$false_positive");
				__prnt(uc($service), " tests successful:$status_up failed:$status_down");
			}
			else
			{
				if (ah_save_incident($ah_tld, $service, $eventid, $event_start, $event_end, $false_positive, $lastclock) != AH_SUCCESS)
				{
					fail("cannot save incident: ", ah_get_error());
				}
			}

			my $values_from = $test_results[0]->{'start'};
			my $values_till = $test_results[$test_results_count - 1]->{'end'};

			if ($service eq 'dns' or $service eq 'dnssec')
			{
				my $values_ref = __get_dns_test_values($dns_items_ref, $values_from, $values_till);

				# run through values from probes (ordered by clock)
				foreach my $probe (keys(%$values_ref))
				{
					my $nsips_ref = $values_ref->{$probe};

					dbg("probe:$probe");

					foreach my $nsip (keys(%$nsips_ref))
					{
						my $endvalues_ref = $nsips_ref->{$nsip};

						my ($ns, $ip) = split(',', $nsip);

						dbg("  ", scalar(keys(%$endvalues_ref)), " values for $nsip:") if (opt('debug'));

						my $test_result_index = 0;

						foreach my $clock (sort(keys(%$endvalues_ref))) # must be sorted by clock
						{
							if ($clock < $test_results[$test_result_index]->{'start'})
							{
								__no_status_result($service, $avail_key, $probe, $clock, $nsip);
								next;
							}

							# move to corresponding test result
							$test_result_index++ while ($test_result_index < $test_results_count and $clock > $test_results[$test_result_index]->{'end'});

							if ($test_result_index == $test_results_count)
							{
								__no_status_result($service, $avail_key, $probe, $clock, $nsip);
								next;
							}

							my $tr_ref = $test_results[$test_result_index];
							$tr_ref->{'probes'}->{$probe}->{'status'} = undef;	# the status is set later

							if (__probe_offline_at($probe, $clock) != 0)
							{
								$tr_ref->{'probes'}->{$probe}->{'status'} = PROBE_OFFLINE_STR;
							}
							else
							{
								push(@{$tr_ref->{'probes'}->{$probe}->{'details'}->{$ns}}, {'clock' => $clock, 'rtt' => $endvalues_ref->{$clock}, 'ip' => $ip});
							}
						}
					}
				}

				# add probes that are missing results
				foreach my $probe (keys(%$all_probes_ref))
				{
					foreach my $tr_ref (@test_results)
					{
						my $found = 0;

						my $probes_ref = $tr_ref->{'probes'};
						foreach my $tr_ref_probe (keys(%$probes_ref))
						{
							if ($tr_ref_probe eq $probe)
							{
								dbg("\"$tr_ref_probe\" found!");

								$found = 1;
								last;
							}
						}

						$probes_ref->{$probe}->{'status'} = PROBE_NORESULT_STR if ($found == 0);
					}
				}

				# get results from probes: number of working Name Servers
				my $itemids_ref = __get_status_itemids($tld, $cfg_dns_key_status);
				my $statuses_ref = __get_probe_statuses($itemids_ref, $values_from, $values_till);

				foreach my $tr_ref (@test_results)
				{
					# set status
					my $tr_start = $tr_ref->{'start'};
					my $tr_end = $tr_ref->{'end'};

					delete($tr_ref->{'start'});
					delete($tr_ref->{'end'});

					my $probes_ref = $tr_ref->{'probes'};
					foreach my $probe (keys(%$probes_ref))
					{
						foreach my $status_ref (@{$statuses_ref->{$probe}})
						{
							next if ($status_ref->{'clock'} < $tr_start);
							last if ($status_ref->{'clock'} > $tr_end);

							if (not defined($probes_ref->{$probe}->{'status'}))
							{
								$probes_ref->{$probe}->{'status'} = ($status_ref->{'value'} >= $cfg_dns_minns ? "Up" : "Down");
							}
						}
					}

					if (opt('dry-run'))
					{
						__prnt_json($tr_ref);
					}
					else
					{
						if (ah_save_incident_json($ah_tld, $service, $eventid, $event_start, encode_json($tr_ref), $tr_ref->{'clock'}) != AH_SUCCESS)
						{
							fail("cannot save incident: ", ah_get_error());
						}
					}
				}
			}
			elsif ($service eq 'rdds')
			{
				my $values_ref = __get_rdds_test_values($rdds_dbl_items_ref, $rdds_str_items_ref, $values_from, $values_till);

				# run through values from probes (ordered by clock)
				foreach my $probe (keys(%$values_ref))
				{
					my $subservices_ref = $values_ref->{$probe};

					dbg("probe:$probe");

					foreach my $subservice (keys(%$subservices_ref))
					{
						my $test_result_index = 0;

						foreach my $endvalues_ref (@{$subservices_ref->{$subservice}})
						{
							my $clock = $endvalues_ref->{'clock'};

							if ($clock < $test_results[$test_result_index]->{'start'})
							{
								__no_status_result($subservice, $avail_key, $probe, $clock);
								next;
							}

							# move to corresponding test result
							$test_result_index++ while ($test_result_index < $test_results_count and $clock > $test_results[$test_result_index]->{'end'});

							if ($test_result_index == $test_results_count)
							{
								__no_status_result($subservice, $avail_key, $probe, $clock);
								next;
							}

							my $tr_ref = $test_results[$test_result_index];
							$tr_ref->{+JSON_RDDS_SUBSERVICE}->{$subservice}->{$probe}->{'status'} = undef;	# the status is set later

							if (__probe_offline_at($probe, $clock) != 0)
							{
								$tr_ref->{+JSON_RDDS_SUBSERVICE}->{$subservice}->{$probe}->{'status'} = PROBE_OFFLINE_STR;
							}
							else
							{
								push(@{$tr_ref->{+JSON_RDDS_SUBSERVICE}->{$subservice}->{$probe}->{'details'}}, $endvalues_ref);
							}
						}
					}
				}

				# add probes that are missing results
				foreach my $probe (keys(%$all_probes_ref))
				{
					foreach my $tr_ref (@test_results)
					{
						my $subservices_ref = $tr_ref->{+JSON_RDDS_SUBSERVICE};

						foreach my $subservice (keys(%$subservices_ref))
						{
							my $probes_ref = $subservices_ref->{$subservice};

							my $found = 0;

							foreach my $tr_ref_probe (keys(%$probes_ref))
							{
								if ($tr_ref_probe eq $probe)
								{
									$found = 1;
									last;
								}
							}

							$probes_ref->{$probe}->{'status'} = PROBE_NORESULT_STR if ($found == 0);
						}
					}
				}

				# get results from probes: working services (rdds43, rdds80)
				my $itemids_ref = __get_status_itemids($tld, $cfg_rdds_key_status);
				my $statuses_ref = __get_probe_statuses($itemids_ref, $values_from, $values_till);

				foreach my $tr_ref (@test_results)
				{
					# set status
					my $tr_start = $tr_ref->{'start'};
					my $tr_end = $tr_ref->{'end'};

					delete($tr_ref->{'start'});
					delete($tr_ref->{'end'});

					my $subservices_ref = $tr_ref->{+JSON_RDDS_SUBSERVICE};

					foreach my $subservice (keys(%$subservices_ref))
					{
						my $probes_ref = $subservices_ref->{$subservice};

						foreach my $probe (keys(%$probes_ref))
						{
							foreach my $status_ref (@{$statuses_ref->{$probe}})
							{
								next if ($status_ref->{'clock'} < $tr_start);
								last if ($status_ref->{'clock'} > $tr_end);

								if (not defined($probes_ref->{$probe}->{'status'}))
								{
									my $service_only = ($subservice eq JSON_RDDS_43 ? 2 : 3); # 0 - down, 1 - up, 2 - only 43, 3 - only 80

									$probes_ref->{$probe}->{'status'} = (($status_ref->{'value'} == 1 or $status_ref->{'value'} == $service_only) ? "Up" : "Down");
								}
							}
						}
					}

					if (opt('dry-run'))
					{
						__prnt_json($tr_ref);
					}
					else
					{
						if (ah_save_incident_json($ah_tld, $service, $eventid, $event_start, encode_json($tr_ref), $tr_ref->{'clock'}) != AH_SUCCESS)
						{
							fail("cannot save incident: ", ah_get_error());
						}
					}
				}
			}
			elsif ($service eq 'epp')
			{
				dbg("EPP results calculation is not implemented yet");

				my $values_ref = __get_epp_test_values($epp_dbl_items_ref, $epp_str_items_ref, $values_from, $values_till);

				foreach my $probe (keys(%$values_ref))
				{
					my $endvalues_ref = $values_ref->{$probe};

					my $test_result_index = 0;

					foreach my $clock (sort(keys(%$endvalues_ref))) # must be sorted by clock
					{
						if ($clock < $test_results[$test_result_index]->{'start'})
						{
							__no_status_result($service, $avail_key, $probe, $clock);
							next;
						}

						# move to corresponding test result
						$test_result_index++ while ($test_result_index < $test_results_count and $clock > $test_results[$test_result_index]->{'end'});

						if ($test_result_index == $test_results_count)
						{
							__no_status_result($service, $avail_key, $probe, $clock);
							next;
						}

						my $tr_ref = $test_results[$test_result_index];
						$tr_ref->{'probes'}->{$probe}->{'status'} = undef;	# the status is set later

						if (__probe_offline_at($probe, $clock) != 0)
						{
							$tr_ref->{'probes'}->{$probe}->{'status'} = PROBE_OFFLINE_STR;
						}
						else
						{
							$tr_ref->{'probes'}->{$probe}->{'details'}->{$clock} = $endvalues_ref->{$clock};
						}
					}
				}

				# add probes that are missing results
				foreach my $probe (keys(%$all_probes_ref))
				{
					foreach my $tr_ref (@test_results)
					{
						my $found = 0;

						my $probes_ref = $tr_ref->{'probes'};
						foreach my $tr_ref_probe (keys(%$probes_ref))
						{
							if ($tr_ref_probe eq $probe)
							{
								dbg("\"$tr_ref_probe\" found!");

								$found = 1;
								last;
							}
						}

						$probes_ref->{$probe}->{'status'} = PROBE_NORESULT_STR if ($found == 0);
					}
				}

				# get results from probes: EPP down (0) or up (1)
				my $itemids_ref = __get_status_itemids($tld, $cfg_epp_key_status);
                                my $statuses_ref = __get_probe_statuses($itemids_ref, $values_from, $values_till);

				foreach my $tr_ref (@test_results)
                                {
                                        # set status
                                        my $tr_start = $tr_ref->{'start'};
                                        my $tr_end = $tr_ref->{'end'};

                                        delete($tr_ref->{'start'});
                                        delete($tr_ref->{'end'});

                                        my $probes_ref = $tr_ref->{'probes'};

					foreach my $probe (keys(%$probes_ref))
					{
						foreach my $status_ref (@{$statuses_ref->{$probe}})
						{
							next if ($status_ref->{'clock'} < $tr_start);
							last if ($status_ref->{'clock'} > $tr_end);

							if (not defined($probes_ref->{$probe}->{'status'}))
							{
								$probes_ref->{$probe}->{'status'} = ($status_ref->{'value'} == 1 ? "Up" : "Down");
							}
						}
					}

					if (opt('dry-run'))
					{
						__prnt_json($tr_ref);
					}
					else
					{
						if (ah_save_incident_json($ah_tld, $service, $eventid, $event_start, encode_json($tr_ref), $tr_ref->{'clock'}) != AH_SUCCESS)
						{
							fail("cannot save incident: ", ah_get_error());
						}
					}
				}
			}
			else
			{
				fail("THIS SHOULD NEVER HAPPEN (unknown service \"$service\")");
			}
		}

		if (defined($continue_file) and not opt('dry-run'))
		{
			my $updated = (defined($till) ? $till : $lastclock + RESULT_TIMESTAMP_SHIFT) + 1;	# include the whole minute

			unless (write_file($continue_file, $updated) == SUCCESS)
			{
				wrn("cannot update continue file \"$continue_file\": $!");
				next;
			}

			dbg("$service: updated till ", ts_str($updated));
		}
	}
}

# unset TLD (for the logs)
$tld = undef;

sub __parent_exit
{
	unless (opt('dry-run'))
	{
		db_connect();

		__update_false_positives();
	}

	if (opt('stats'))
	{
		printf("stats: TLD data processing took %s (TLDs: %d)\n", format_stats_time(time() - $sec), $tlds_processed);
	}

	slv_exit(SUCCESS);
}

# values are organized like this:
# {
#           'WashingtonDC' => {
#                               'ns1,192.0.34.201' => {
#                                                       '1418994681' => '-204.0000',
#                                                       '1418994621' => '-204.0000'
#                                                     },
#                               'ns2,2620:0:2d0:270::1:201' => {
#                                                                '1418994681' => '-204.0000',
#                                                                '1418994621' => '-204.0000'
#                                                              }
#                             },
# ...
sub __get_dns_test_values
{
	my $dns_items_ref = shift;
	my $start = shift;
	my $end = shift;

	my %result;

	# generate list if itemids
	my $itemids_str = '';
	foreach my $probe (keys(%$dns_items_ref))
	{
		my $itemids_ref = $dns_items_ref->{$probe};

		foreach my $itemid (keys(%$itemids_ref))
		{
			$itemids_str .= ',' unless ($itemids_str eq '');
			$itemids_str .= $itemid;
		}
	}

	if ($itemids_str ne '')
	{
		my $rows_ref = db_select("select itemid,value,clock from history where itemid in ($itemids_str) and " . sql_time_condition($start, $end). " order by clock");

		foreach my $row_ref (@$rows_ref)
		{
			my $itemid = $row_ref->[0];
			my $value = $row_ref->[1];
			my $clock = $row_ref->[2];

			my ($nsip, $probe);
			my $last = 0;

			foreach my $pr (keys(%$dns_items_ref))
			{
				my $itemids_ref = $dns_items_ref->{$pr};

				foreach my $i (keys(%$itemids_ref))
				{
					if ($i == $itemid)
					{
						$nsip = $dns_items_ref->{$pr}->{$i};
						$probe = $pr;
						$last = 1;
						last;
					}
				}
				last if ($last == 1);
			}

			unless (defined($nsip))
			{
				wrn("internal error: Name Server,IP pair of item $itemid not found");
				next;
			}

			$result{$probe}->{$nsip}->{$clock} = get_detailed_result($cfg_dns_valuemaps, $value);
		}
	}

	return \%result;
}

sub __find_probe_key_by_itemid
{
	my $itemid = shift;
	my $items_ref = shift;

	my ($probe, $key);
	my $last = 0;

	foreach my $pr (keys(%$items_ref))
	{
		my $itemids_ref = $items_ref->{$pr};

		foreach my $i (keys(%$itemids_ref))
		{
			if ($i == $itemid)
			{
				$probe = $pr;
				$key = $items_ref->{$pr}->{$i};
				$last = 1;
				last;
			}
		}
		last if ($last == 1);
	}

	return ($probe, $key);
}

# values are organized like this:
# {
#           'WashingtonDC' => {
#                               '80' => {
#                                         '1418994206' => {
#                                                           'ip' => '192.0.34.201',
#                                                           'rtt' => '127.0000'
#                                                         },
#                                         '1418994086' => {
#                                                           'ip' => '192.0.34.201',
#                                                           'rtt' => '127.0000'
#                                                         },
#                               '43' => {
#                                         '1418994206' => {
#                                                           'ip' => '192.0.34.201',
#                                                           'rtt' => '127.0000'
#                                                         },
#                                         '1418994086' => {
#                                                           'ip' => '192.0.34.201',
#                                                           'rtt' => '127.0000'
#                                                         },
# ...
sub __get_rdds_test_values
{
	my $rdds_dbl_items_ref = shift;
	my $rdds_str_items_ref = shift;
	my $start = shift;
	my $end = shift;

	# generate list if itemids
	my $dbl_itemids_str = '';
	foreach my $probe (keys(%$rdds_dbl_items_ref))
	{
		my $itemids_ref = $rdds_dbl_items_ref->{$probe};

		foreach my $itemid (keys(%$itemids_ref))
		{
			$dbl_itemids_str .= ',' unless ($dbl_itemids_str eq '');
			$dbl_itemids_str .= $itemid;
		}
	}

	my $str_itemids_str = '';
	foreach my $probe (keys(%$rdds_str_items_ref))
	{
		my $itemids_ref = $rdds_str_items_ref->{$probe};

		foreach my $itemid (keys(%$itemids_ref))
		{
			$str_itemids_str .= ',' unless ($str_itemids_str eq '');
			$str_itemids_str .= $itemid;
		}
	}

	my %result;

	return \%result if ($dbl_itemids_str eq '' or $str_itemids_str eq '');

	# we need pre_result to combine IP and RTT to single test result
	my %pre_result;

	my $dbl_rows_ref = db_select("select itemid,value,clock from history where itemid in ($dbl_itemids_str) and " . sql_time_condition($start, $end). " order by clock");

	foreach my $row_ref (@$dbl_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $rdds_dbl_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid") unless (defined($probe) and defined($key));

		my $port = __get_rdds_port($key);
		my $type = __get_rdds_dbl_type($key);

		my $subservice;
		if ($port eq '43')
		{
			$subservice = JSON_RDDS_43;
		}
		elsif ($port eq '80')
		{
			$subservice = JSON_RDDS_80;
		}
		else
		{
			fail("unknown RDDS port in item (id:$itemid)");
		}

		$pre_result{$probe}->{$subservice}->{$clock}->{$type} = ($type eq 'rtt') ? get_detailed_result($cfg_rdds_valuemaps, $value) : int($value);
	}

	my $str_rows_ref = db_select("select itemid,value,clock from history_str where itemid in ($str_itemids_str) and " . sql_time_condition($start, $end). " order by clock");

	foreach my $row_ref (@$str_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $rdds_str_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid") unless (defined($probe) and defined($key));

		my $port = __get_rdds_port($key);
		my $type = __get_rdds_str_type($key);

		my $subservice;
                if ($port eq '43')
                {
                        $subservice = JSON_RDDS_43;
                }
                elsif ($port eq '80')
                {
                        $subservice = JSON_RDDS_80;
                }
                else
                {
                        fail("unknown RDDS port in item (id:$itemid)");
                }

		$pre_result{$probe}->{$subservice}->{$clock}->{$type} = $value;
	}

	foreach my $probe (keys(%pre_result))
	{
		foreach my $subservice (keys(%{$pre_result{$probe}}))
		{
			foreach my $clock (sort(keys(%{$pre_result{$probe}->{$subservice}})))	# must be sorted by clock
			{
				my $h;
				my $clock_ref = $pre_result{$probe}->{$subservice}->{$clock};
				foreach my $key (keys(%{$pre_result{$probe}->{$subservice}->{$clock}}))
				{
					$h->{$key} = $clock_ref->{$key};
				}
				$h->{'clock'} = $clock;

				push(@{$result{$probe}->{$subservice}}, $h);
			}
		}
	}

	return \%result;
}

# values are organized like this:
# {
#         'WashingtonDC' => {
#                 '1418994206' => {
#                               'ip' => '192.0.34.201',
#                               'login' => '127.0000',
#                               'update' => '366.0000'
#                               'info' => '366.0000'
#                 },
#                 '1418994456' => {
#                               'ip' => '192.0.34.202',
#                               'login' => '121.0000',
#                               'update' => '263.0000'
#                               'info' => '321.0000'
#                 },
# ...
sub __get_epp_test_values
{
	my $epp_dbl_items_ref = shift;
	my $epp_str_items_ref = shift;
	my $start = shift;
	my $end = shift;

	my %result;

	# generate list if itemids
	my $dbl_itemids_str = '';
	foreach my $probe (keys(%$epp_dbl_items_ref))
	{
		my $itemids_ref = $epp_dbl_items_ref->{$probe};

		foreach my $itemid (keys(%$itemids_ref))
		{
			$dbl_itemids_str .= ',' unless ($dbl_itemids_str eq '');
			$dbl_itemids_str .= $itemid;
		}
	}

	my $str_itemids_str = '';
	foreach my $probe (keys(%$epp_str_items_ref))
	{
		my $itemids_ref = $epp_str_items_ref->{$probe};

		foreach my $itemid (keys(%$itemids_ref))
		{
			$str_itemids_str .= ',' unless ($str_itemids_str eq '');
			$str_itemids_str .= $itemid;
		}
	}

	return \%result if ($dbl_itemids_str eq '' or $str_itemids_str eq '');

	my $dbl_rows_ref = db_select("select itemid,value,clock from history where itemid in ($dbl_itemids_str) and " . sql_time_condition($start, $end). " order by clock");

	foreach my $row_ref (@$dbl_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $epp_dbl_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid") unless (defined($probe) and defined($key));

		my $type = __get_epp_dbl_type($key);

		$result{$probe}->{$clock}->{$type} = get_detailed_result($cfg_epp_valuemaps, $value);
	}

	my $str_rows_ref = db_select("select itemid,value,clock from history_str where itemid in ($str_itemids_str) and " . sql_time_condition($start, $end). " order by clock");

	foreach my $row_ref (@$str_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $epp_str_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid") unless (defined($probe) and defined($key));

		my $type = __get_epp_str_type($key);

		$result{$probe}->{$clock}->{$type} = $value;
	}

	return \%result;
}

# return itemids grouped by Probes:
#
# {
#    'Amsterdam' => {
#         'itemid1' => 'ns2,2620:0:2d0:270::1:201',
#         'itemid2' => 'ns1,192.0.34.201'
#    },
#    'London' => {
#         'itemid3' => 'ns2,2620:0:2d0:270::1:201',
#         'itemid4' => 'ns1,192.0.34.201'
#    }
# }
sub __get_dns_itemids
{
	my $nsips_ref = shift; # array reference of NS,IP pairs
	my $key = shift;
	my $tld = shift;
	my $probe = shift;

	my $hosts;

	my $host_cond = ($probe ? "host='$tld $probe'" : "host like '$tld %'");

	my $rows_ref = db_select("select hostid,host from hosts where $host_cond");

	foreach my $row_ref (@$rows_ref)
	{
		$hosts->{$row_ref->[0]} = $row_ref->[1];
	}

	my $hostids_str = join(',', keys(%$hosts));

	my @keys;
	push(@keys, "'" . $key . $_ . "]'") foreach (@$nsips_ref);
	my $keys_str = join(',', @keys);

	$rows_ref = db_select("select hostid,itemid,key_ from items where templateid is not null and hostid in ($hostids_str) and key_ in ($keys_str)");

	my %result;

	my $tld_length = length($tld) + 1; # white space
	foreach my $row_ref (@$rows_ref)
	{
		my $hostid = $row_ref->[0];
		my $itemid = $row_ref->[1];
		my $key = $row_ref->[2];

		my $host = $hosts->{$hostid};

		# remove TLD from host name to get just the Probe name
		my $_probe = ($probe ? $probe : substr($host, $tld_length));

		$result{$_probe}->{$itemid} = get_nsip_from_key($key);
	}

	wrn("cannot find items ($keys_str) at host ($tld *)") if (scalar(keys(%result)) == 0);

	return \%result;
}

sub __get_rdds_port
{
	my $key = shift;

	# rsm.rdds.43... <-- returns 43 or 80
	return substr($key, 9, 2);
}

sub __get_rdds_dbl_type
{
	my $key = shift;

	# rsm.rdds.43.rtt... rsm.rdds.43.upd[... <-- returns "rtt" or "upd"
	return substr($key, 12, 3);
}

sub __get_rdds_str_type
{
	# NB! This is done for consistency, perhaps in the future there will be more string items, not just "ip".
	return 'ip';
}

sub __get_epp_dbl_type
{
	my $key = shift;

	chop($key); # remove last char ']'

	# rsm.epp.rtt[{$RSM.TLD},login <-- returns "login" (other options: "update", "info")
        return substr($key, 23);
}

sub __get_epp_str_type
{
	# NB! This is done for consistency, perhaps in the future there will be more string items, not just "ip".
	return 'ip';
}

# return itemids of dbl items grouped by Probes:
#
# {
#    'Amsterdam' => {
#         'itemid1' => 'rsm.rdds.43.rtt...',
#         'itemid2' => 'rsm.rdds.43.upd...',
#         'itemid3' => 'rsm.rdds.80.rtt...'
#    },
#    'London' => {
#         'itemid4' => 'rsm.rdds.43.rtt...',
#         'itemid5' => 'rsm.rdds.43.upd...',
#         'itemid6' => 'rsm.rdds.80.rtt...'
#    }
# }
sub __get_rdds_dbl_itemids
{
	my $tld = shift;
	my $probe = shift;

	return __get_itemids_by_complete_key($tld, $probe, $cfg_rdds_key_43_rtt, $cfg_rdds_key_80_rtt, $cfg_rdds_key_43_upd);
}

# return itemids of string items grouped by Probes:
#
# {
#    'Amsterdam' => {
#         'itemid1' => 'rsm.rdds.43.ip...',
#         'itemid2' => 'rsm.rdds.80.ip...'
#    },
#    'London' => {
#         'itemid3' => 'rsm.rdds.43.ip...',
#         'itemid4' => 'rsm.rdds.80.ip...'
#    }
# }
sub __get_rdds_str_itemids
{
	my $tld = shift;
	my $probe = shift;

	return __get_itemids_by_complete_key($tld, $probe, $cfg_rdds_key_43_ip, $cfg_rdds_key_80_ip);
}

sub __get_epp_dbl_itemids
{
	my $tld = shift;
	my $probe = shift;

	return __get_itemids_by_incomplete_key($tld, $probe, $cfg_epp_key_rtt);
}

sub __get_epp_str_itemids
{
	my $tld = shift;
	my $probe = shift;

	return __get_itemids_by_complete_key($tld, $probe, $cfg_epp_key_ip);
}

# $keys_str - list of complete keys
sub __get_itemids_by_complete_key
{
	my $tld = shift;
	my $probe = shift;

	my $keys_str = "'" . join("','", @_) . "'";

	my $host_value = ($probe ? "$tld $probe" : "$tld %");

	my $rows_ref = db_select(
		"select h.host,i.itemid,i.key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and h.host like '$host_value'".
			" and i.key_ in ($keys_str)".
			" and i.templateid is not null");

	my %result;

	my $tld_length = length($tld) + 1; # white space
	foreach my $row_ref (@$rows_ref)
	{
		my $host = $row_ref->[0];
		my $itemid = $row_ref->[1];
		my $key = $row_ref->[2];

		# remove TLD from host name to get just the Probe name
		my $_probe = ($probe ? $probe : substr($host, $tld_length));

		$result{$_probe}->{$itemid} = $key;
	}

	wrn("cannot find items ($keys_str) at host ($tld *)") if (scalar(keys(%result)) == 0);

	return \%result;
}

# call this function with list of incomplete keys after $tld, e. g.:
# __get_itemids_by_incomplete_key("example", "aaa[", "bbb[", ...)
sub __get_itemids_by_incomplete_key
{
	my $tld = shift;
	my $probe = shift;

	my $keys_cond = "(key_ like '" . join("%' or key_ like '", @_) . "%')";

	my $host_value = ($probe ? "$tld $probe" : "$tld %");

	my $rows_ref = db_select(
		"select h.host,i.itemid,i.key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and h.host like '$host_value'".
			" and i.templateid is not null".
			" and $keys_cond");

	my %result;

	my $tld_length = length($tld) + 1; # white space
	foreach my $row_ref (@$rows_ref)
	{
		my $host = $row_ref->[0];
		my $itemid = $row_ref->[1];
		my $key = $row_ref->[2];

		# remove TLD from host name to get just the Probe name
		my $_probe = ($probe ? $probe : substr($host, $tld_length));

		$result{$_probe}->{$itemid} = $key;
	}

	wrn("cannot find items ('", join("','", @_), "') at host ($tld *)") if (scalar(keys(%result)) == 0);

	return \%result;
}

# returns hash reference of Probe=>itemid of specified key
#
# {
#    'Amsterdam' => 'itemid1',
#    'London' => 'itemid2',
#    ...
# }
sub __get_status_itemids
{
	my $tld = shift;
	my $key = shift;

	my $key_condition = (substr($key, -1) eq ']' ? "i.key_='$key'" : "i.key_ like '$key%'");

	my $sql =
		"select h.host,i.itemid".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and i.templateid is not null".
			" and $key_condition".
			" and h.host like '$tld %'".
		" group by h.host,i.itemid";

	my $rows_ref = db_select($sql);

	my %result;

	if (scalar(@$rows_ref) == 0)
	{
		wrn("no items matching '$key' found at host '$tld %'");
	}
	else
	{
		my $tld_length = length($tld) + 1; # white space
		foreach my $row_ref (@$rows_ref)
		{
			my $host = $row_ref->[0];
			my $itemid = $row_ref->[1];

			# remove TLD from host name to get just the Probe name
			my $probe = substr($host, $tld_length);

			$result{$probe} = $itemid;
		}
	}

	return \%result;
}

#
# {
#     'Probe1' =>
#     [
#         {
#             'clock' => 1234234234,
#             'value' => 'Up'
#         },
#         {
#             'clock' => 1234234294,
#             'value' => 'Up'
#         }
#     ],
#     'Probe2' =>
#     [
#         {
#             'clock' => 1234234234,
#             'value' => 'Down'
#         },
#         {
#             'clock' => 1234234294,
#             'value' => 'Up'
#         }
#     ]
# }
#
sub __get_probe_statuses
{
	my $itemids_ref = shift;
	my $from = shift;
	my $till = shift;

	my %result;

	# generate list if itemids
	my $itemids_str = '';
	foreach my $probe (keys(%$itemids_ref))
	{
		$itemids_str .= ',' unless ($itemids_str eq '');
		$itemids_str .= $itemids_ref->{$probe};
	}

	if ($itemids_str ne '')
	{
		my $rows_ref = db_select("select itemid,value,clock from history_uint where itemid in ($itemids_str) and " . sql_time_condition($from, $till). " order by clock");

		foreach my $row_ref (@$rows_ref)
		{
			my $itemid = $row_ref->[0];
			my $value = $row_ref->[1];
			my $clock = $row_ref->[2];

			my $probe;
			foreach my $pr (keys(%$itemids_ref))
			{
				my $i = $itemids_ref->{$pr};

				if ($i == $itemid)
				{
					$probe = $pr;

					last;
				}
			}

			unless (defined($probe))
			{
				wrn("internal error: Probe of item (itemid:$itemid) not found");
				return \%result;
			}

			push(@{$result{$probe}}, {'value' => $value, 'clock' => $clock});
		}
	}

	return \%result;
}

sub __prnt
{
	print((defined($tld) ? "$tld: " : ''), join('', @_), "\n");
}

sub __prnt_json
{
	my $tr_ref = shift;

	if (opt('debug'))
	{
		dbg(JSON->new->utf8(1)->pretty(1)->encode($tr_ref), "-----------------------------------------------------------");
	}
	else
	{
		__prnt(ts_str($tr_ref->{'clock'}), " ", $tr_ref->{'status'});
	}
}

sub __selected_period
{
	my $from = shift;
	my $till = shift;

	return "till " . ts_str($till) if (!$from and $till);
	return "from " . ts_str($from) if ($from and !$till);
	return "from " . ts_str($from) . " till " . ts_str($till) if ($from and $till);

	return "any time";
}

sub __tld_ignored
{
	my $tld = shift;

	return SUCCESS if (exists($ignore_hash{$tld}));

	return E_FAIL;
}

sub __update_false_positives
{
	# now check for possible false_positive change in front-end
	my $last_audit = ah_get_last_audit();
	my $maxclock = 0;

	my $rows_ref = db_select(
		"select details,max(clock)".
		" from auditlog".
		" where resourcetype=".AUDIT_RESOURCE_INCIDENT.
			" and clock>$last_audit".
		" group by details");

	foreach my $row_ref (@$rows_ref)
	{
		my $details = $row_ref->[0];
		my $clock = $row_ref->[1];

		# ignore old "details" format (dropped in December 2014)
		next if ($details =~ '.*Incident \[.*\]');

		my $eventid = $details;
		$eventid =~ s/^([0-9]+): .*/$1/;

		$maxclock = $clock if ($clock > $maxclock);

		my $rows_ref2 = db_select("select objectid,clock,false_positive from events where eventid=$eventid");

		fail("cannot get event with ID $eventid") unless (scalar(@$rows_ref2) == 1);

		my $triggerid = $rows_ref2->[0]->[0];
		my $event_clock = $rows_ref2->[0]->[1];
		my $false_positive = $rows_ref2->[0]->[2];

		my ($tld, $service) = get_tld_by_trigger($triggerid);

		dbg("auditlog message: $eventid\t$service\t".ts_str($event_clock)."\t".ts_str($clock)."\tfp:$false_positive\t$tld\n");

		fail("cannot update false_positive status of event with ID $eventid") unless (ah_save_false_positive($tld, $service, $eventid, $event_clock, $false_positive, $clock) == AH_SUCCESS);
	}

	ah_save_audit($maxclock) unless ($maxclock == 0);
}

sub __validate_input
{
	if (opt('service'))
	{
		if (getopt('service') ne 'dns' and getopt('service') ne 'dnssec' and getopt('service') ne 'rdds' and getopt('service') ne 'epp')
		{
			print("Error: \"", getopt('service'), "\" - unknown service\n");
			usage();
		}
	}

	if (opt('tld') and opt('ignore-file'))
	{
		print("Error: options --tld and --ignore-file cannot be used together\n");
		usage();
	}

	if (opt('continue') and opt('from'))
        {
                print("Error: options --continue and --from cannot be used together\n");
                usage();
        }

	if (opt('probe'))
	{
		if (not opt('dry-run'))
		{
			print("Error: option --probe can only be used together with --dry-run\n");
			usage();
		}

		my $probe = getopt('probe');

		my $probes_ref = get_probes();
		my $valid = 0;

		foreach my $name (keys(%$probes_ref))
		{
			if ($name eq $probe)
			{
				$valid = 1;
				last;
			}
		}

		if ($valid == 0)
		{
			print("Error: unknown probe \"$probe\"\n");
			print("\nAvailable probes:\n");
			foreach my $name (keys(%$probes_ref))
			{
				print("  $name\n");
			}
			exit(E_FAIL);
		}
        }
}

sub __sql_arr_to_str
{
	my $rows_ref = shift;

	my @arr;
	foreach my $row_ref (@$rows_ref)
        {
                push(@arr, $row_ref->[0]);
	}

	return join(',', @arr);
}

sub __get_min_clock
{
	my $tld = shift;
	my $service = shift;
	my $minclock = shift;

	my $key_condition;
	if ($service eq 'dns' or $service eq 'dnssec')
	{
		$key_condition = "key_='$cfg_dns_key_status'";
	}
	elsif ($service eq 'rdds')
	{
		$key_condition = "key_ like '$cfg_rdds_key_status%'";
	}
	elsif ($service eq 'epp')
	{
		$key_condition = "key_='$cfg_epp_key_status'";
	}

	my $rows_ref = db_select("select hostid from hosts where host like '$tld %'");

	return 0 if (scalar(@$rows_ref) == 0);

	my $hostids_str = __sql_arr_to_str($rows_ref);

	$rows_ref = db_select("select itemid from items where $key_condition and templateid is not NULL and hostid in ($hostids_str)");

	return 0 if (scalar(@$rows_ref) == 0);

	my $itemids_str = __sql_arr_to_str($rows_ref);

	my $ret = 0;

	while ($ret == 0 && $minclock < $now)
	{
		$rows_ref = db_select("select min(clock) from history_uint where itemid in ($itemids_str) and clock<$minclock");

		$ret = $rows_ref->[0]->[0] if ($rows_ref->[0]->[0]);

		# move half of a day forward
		$minclock += 43200;
	}

	return $ret;
}

sub __probe_offline_at
{
	my $probe = shift;
	my $clock = shift;

	# if a probe was down for the whole period it won't be in a hash
	return 1 unless exists($probe_times_ref->{$probe});	# offline

	my $times_ref = $probe_times_ref->{$probe};

	my $clocks_count = scalar(@$times_ref);

	my $clock_index = 0;
	while ($clock_index < $clocks_count)
	{
		my $from = $times_ref->[$clock_index++];
		my $till = $times_ref->[$clock_index++];

		return 0 if (($from < $clock) and ($clock < $till));	# online
	}

	return 1;	# offline
}

sub __no_status_result
{
	my $service = shift;
	my $avail_key = shift;
	my $probe = shift;
	my $clock = shift;
	my $details = shift;

	wrn("Service availability result is missing for ", uc($service), " test ", ($details ? "($details) " : ''),
		"performed at ", ts_str($clock), " ($clock) on probe $probe. This means the test period was not" .
		" handled by SLV availability cron job ($avail_key). This may happen e. g. if cron was not running" .
		" at some point. In order to fix this problem please run".
		"\n  $avail_key.pl --from $clock".
		"\nmanually to add missing service availability result.");
}

__END__

=head1 NAME

update-api-data.pl - save information about the incidents to a filesystem

=head1 SYNOPSIS

update-api-data.pl [--service <dns|dnssec|rdds|epp>] [--tld <tld>|--ignore-file <file>] [--from <timestamp>|--continue] [--period minutes] [--dry-run [--probe name]] [--warnslow <seconds>] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--service> service

Process only specified service. Service must be one of: dns, dnssec, rdds or epp.

=item B<--tld> tld

Process only specified TLD. If not specified all TLDs will be processed.

This option cannot be used together with option --ignore-file.

=item B<--ignore-file> file

Specify file containing the list of TLDs that should be ignored. TLDs are specified one per line.

This option cannot be used together with option --tld.

=item B<--period> minutes

Specify number minutes of the period to handle during this run. The first cycle to handle can be specified
using options --from or --continue (continue from the last time when --continue was used) (see below).

=item B<--from> timestamp

Specify Unix timestamp within the oldest test cycle to handle in this run. You don't need to specify the
first second of the test cycle, any timestamp within it will work. Number of test cycles to handle within
this run can be specified using option --period otherwise all completed test cycles available in the
database up till now will be handled.

This option cannot be used together with option --continue.

=item B<--continue>

Continue calculation from the timestamp of the last run with --continue. In case of first run with
--continue the oldest available data will be used as starting point. You may specify the end point
of the period with --period option (see above). If you don't specify the end point the timestamp
of the newest available data in the database will be used.

The continue token is saved per each TLD-service pair separately.

Note, that continue token is not be updated if this option was specified together with --dry-run
or when you use --from option.

=item B<--probe> name

Only calculate data from specified probe.

This option can only be used for debugging purposes and must be used together with option --dry-run .

=item B<--dry-run>

Print data to the screen, do not write anything to the filesystem.

=item B<--warnslow> seconds

Issue a warning in case an SQL query takes more than specified number of seconds. A floating-point number
is supported as seconds (i. e. 0.5, 1, 1.5 are valid).

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will run through all the incidents found at optionally specified time bounds
and store details about each on the filesystem. This information will be used by external
program to provide it for users in convenient way.

=head1 EXAMPLES

./update-api-data.pl --tld example --period 10

This will update API data of the last 10 minutes of DNS, DNSSEC, RDDS and EPP services of TLD example.

=cut
