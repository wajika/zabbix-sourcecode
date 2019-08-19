<script type="text/javascript">
	jQuery(function($) {
		$('#copy_type').on('change', function (data) {
			switch ($('#copy_type').find('input[name=copy_type]:checked').val()) {
				case '<?= COPY_TYPE_TO_HOST_GROUP ?>':
					$('#host_groups_row').removeClass('hidden');
					$('#hosts_row, #templates_row').addClass('hidden');
					break;
				case '<?= COPY_TYPE_TO_HOST ?>':
					$('#hosts_row').removeClass('hidden');
					$('#host_groups_row, #templates_row').addClass('hidden');
					break;
				case '<?= COPY_TYPE_TO_TEMPLATE ?>':
					$('#templates_row').removeClass('hidden');
					$('#host_groups_row, #hosts_row').addClass('hidden');
					break;
			}
		});
	});
</script>
